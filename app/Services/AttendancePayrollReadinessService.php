<?php

namespace App\Services;

use App\Models\AttendanceCorrection;
use App\Models\AttendancePayrollSnapshot;
use App\Models\AttendanceSummary;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\OvertimeCalculation;
use App\Models\OvertimeRequest;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendancePayrollReadinessService
{
    private const CALCULATION_VERSION = 'v1.4.10';

    /**
     * @param Employee|int $employee
     */
    public function generateSnapshot(
        Employee|int $employee,
        Carbon|string $periodStart,
        Carbon|string $periodEnd,
        ?Employee $actor = null,
    ): AttendancePayrollSnapshot {
        return DB::transaction(function () use ($employee, $periodStart, $periodEnd, $actor): AttendancePayrollSnapshot {
            $employee = $this->resolveEmployee($employee);
            [$resolvedStart, $resolvedEnd] = $this->normalizePeriod($periodStart, $periodEnd);
            $snapshot = AttendancePayrollSnapshot::query()
                ->where('company_id', $employee->getEffectiveCompanyId())
                ->where('employee_id', $employee->getKey())
                ->whereDate('period_start', $resolvedStart->toDateString())
                ->whereDate('period_end', $resolvedEnd->toDateString())
                ->lockForUpdate()
                ->first();

            if ($snapshot?->isLocked()) {
                throw ValidationException::withMessages([
                    'snapshot_status' => 'Locked attendance payroll snapshots cannot be recalculated directly.',
                ]);
            }

            $summaryRecords = $this->resolveAttendanceSummaries($employee, $resolvedStart, $resolvedEnd);
            $overtimeCalculations = $this->resolveApprovedOvertimeCalculations($employee, $resolvedStart, $resolvedEnd);
            $approvedCorrections = $this->resolveApprovedCorrections($employee, $resolvedStart, $resolvedEnd);
            $approvedLeaveRequests = $this->resolveApprovedLeaveRequests($employee, $resolvedStart, $resolvedEnd);
            $actorId = $this->resolveActorId($actor, $employee->getEffectiveCompanyId());
            $now = now(config('app.timezone'));

            $snapshot ??= new AttendancePayrollSnapshot();
            $snapshot->fill([
                'company_id' => $employee->getEffectiveCompanyId(),
                'employee_id' => $employee->getKey(),
                'period_start' => $resolvedStart->toDateString(),
                'period_end' => $resolvedEnd->toDateString(),
                'total_work_days' => $this->countWorkDays($summaryRecords),
                'total_present_days' => $this->countPresentDays($summaryRecords),
                'total_absent_days' => $summaryRecords->where('status', AttendanceSummary::STATUS_ABSENT)->count(),
                'total_late_minutes' => (int) $summaryRecords->sum('late_minutes'),
                'total_early_leave_minutes' => (int) $summaryRecords->sum('early_out_minutes'),
                'total_work_minutes' => (int) $summaryRecords->sum('work_minutes'),
                'total_overtime_minutes' => (int) $overtimeCalculations->sum('calculated_minutes'),
                'total_leave_days' => number_format(
                    (float) $summaryRecords->where('status', AttendanceSummary::STATUS_LEAVE)->count(),
                    2,
                    '.',
                    ''
                ),
                'total_correction_count' => $approvedCorrections->count(),
                'snapshot_status' => AttendancePayrollSnapshot::STATUS_CALCULATED,
                'calculated_at' => $now,
                'metadata' => $this->buildMetadata(
                    snapshot: $snapshot,
                    summaryRecords: $summaryRecords,
                    overtimeCalculations: $overtimeCalculations,
                    approvedCorrections: $approvedCorrections,
                    approvedLeaveRequests: $approvedLeaveRequests,
                    actorId: $actorId,
                    calculatedAt: $now,
                ),
            ]);

            if (! $snapshot->exists) {
                $snapshot->locked_at = null;
                $snapshot->locked_by = null;
            }

            $snapshot->save();

            return $snapshot->fresh(['company', 'employee', 'lockedBy']);
        });
    }

    public function recalculateSnapshot(
        AttendancePayrollSnapshot $snapshot,
        ?Employee $actor = null,
    ): AttendancePayrollSnapshot {
        return $this->generateSnapshot(
            employee: (int) $snapshot->employee_id,
            periodStart: $snapshot->period_start->copy(),
            periodEnd: $snapshot->period_end->copy(),
            actor: $actor,
        );
    }

    public function lockSnapshot(
        AttendancePayrollSnapshot $snapshot,
        ?Employee $actor = null,
    ): AttendancePayrollSnapshot {
        return DB::transaction(function () use ($snapshot, $actor): AttendancePayrollSnapshot {
            $snapshot = $this->lockSnapshotRecord($snapshot);

            if ($snapshot->isLocked()) {
                return $snapshot->fresh(['company', 'employee', 'lockedBy']);
            }

            if (! $snapshot->isCalculated()) {
                throw ValidationException::withMessages([
                    'snapshot_status' => 'Only calculated attendance payroll snapshots can be locked.',
                ]);
            }

            $snapshot->forceFill([
                'snapshot_status' => AttendancePayrollSnapshot::STATUS_LOCKED,
                'locked_at' => now(config('app.timezone')),
                'locked_by' => $this->resolveActorId($actor, (int) $snapshot->company_id),
                'metadata' => array_merge($snapshot->metadata ?? [], [
                    'lock_context' => [
                        'service' => self::class,
                        'locked_at' => now(config('app.timezone'))->toDateTimeString(),
                    ],
                ]),
            ])->save();

            return $snapshot->fresh(['company', 'employee', 'lockedBy']);
        });
    }

    public function cancelSnapshot(
        AttendancePayrollSnapshot $snapshot,
        ?Employee $actor = null,
        ?string $reason = null,
    ): AttendancePayrollSnapshot {
        return DB::transaction(function () use ($snapshot, $actor, $reason): AttendancePayrollSnapshot {
            $snapshot = $this->lockSnapshotRecord($snapshot);

            if ($snapshot->isLocked()) {
                throw ValidationException::withMessages([
                    'snapshot_status' => 'Locked attendance payroll snapshots cannot be cancelled directly.',
                ]);
            }

            $snapshot->forceFill([
                'snapshot_status' => AttendancePayrollSnapshot::STATUS_CANCELLED,
                'metadata' => $this->mergeStatusMetadata(
                    $snapshot,
                    'cancel_context',
                    array_filter([
                        'service' => self::class,
                        'reason' => $reason,
                        'actor_id' => $this->resolveActorId($actor, (int) $snapshot->company_id),
                        'cancelled_at' => now(config('app.timezone'))->toDateTimeString(),
                    ], static fn (mixed $value): bool => $value !== null),
                ),
            ])->save();

            return $snapshot->fresh(['company', 'employee', 'lockedBy']);
        });
    }

    public function markSnapshotStale(
        AttendancePayrollSnapshot $snapshot,
        ?string $reason = null,
    ): AttendancePayrollSnapshot {
        return DB::transaction(function () use ($snapshot, $reason): AttendancePayrollSnapshot {
            $snapshot = $this->lockSnapshotRecord($snapshot);

            if ($snapshot->isStale()) {
                return $snapshot->fresh(['company', 'employee', 'lockedBy']);
            }

            $snapshot->forceFill([
                'snapshot_status' => AttendancePayrollSnapshot::STATUS_STALE,
                'metadata' => $this->mergeStatusMetadata(
                    $snapshot,
                    'stale_context',
                    array_filter([
                        'service' => self::class,
                        'reason' => $reason,
                        'marked_at' => now(config('app.timezone'))->toDateTimeString(),
                    ], static fn (mixed $value): bool => $value !== null),
                ),
            ])->save();

            return $snapshot->fresh(['company', 'employee', 'lockedBy']);
        });
    }

    public function markSnapshotStaleIfSourceDataChanged(
        AttendancePayrollSnapshot $snapshot,
        ?string $reason = null,
    ): AttendancePayrollSnapshot {
        if (! $this->hasSourceDataChangedSinceCalculation($snapshot)) {
            return $snapshot->fresh(['company', 'employee', 'lockedBy']);
        }

        return $this->markSnapshotStale(
            $snapshot,
            $reason ?? 'Source attendance, overtime, correction, or leave data changed after snapshot calculation.',
        );
    }

    public function hasSourceDataChangedSinceCalculation(AttendancePayrollSnapshot $snapshot): bool
    {
        if (! $snapshot->calculated_at instanceof Carbon) {
            return false;
        }

        $companyId = (int) $snapshot->company_id;
        $employeeId = (int) $snapshot->employee_id;
        $periodStart = $snapshot->period_start->toDateString();
        $periodEnd = $snapshot->period_end->toDateString();
        $calculatedAt = $snapshot->calculated_at->copy();

        $latestSourceUpdate = collect([
            AttendanceSummary::query()
                ->forCompany($companyId)
                ->where('employee_id', $employeeId)
                ->whereBetween('attendance_date', [$periodStart, $periodEnd])
                ->max('updated_at'),
            AttendanceCorrection::query()
                ->forCompany($companyId)
                ->where('employee_id', $employeeId)
                ->whereBetween('attendance_date', [$periodStart, $periodEnd])
                ->max('updated_at'),
            OvertimeCalculation::query()
                ->forCompany($companyId)
                ->where('employee_id', $employeeId)
                ->whereBetween('calculation_date', [$periodStart, $periodEnd])
                ->max('updated_at'),
            LeaveRequest::query()
                ->forCompany($companyId)
                ->where('employee_id', $employeeId)
                ->where('status', LeaveRequest::STATUS_APPROVED)
                ->whereDate('start_date', '<=', $periodEnd)
                ->whereDate('end_date', '>=', $periodStart)
                ->max('updated_at'),
        ])
            ->filter()
            ->map(static fn (mixed $value): Carbon => Carbon::parse($value, config('app.timezone')))
            ->sortByDesc(static fn (Carbon $value): int => $value->getTimestamp())
            ->first();

        return $latestSourceUpdate instanceof Carbon
            && $latestSourceUpdate->greaterThan($calculatedAt);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function normalizePeriod(Carbon|string $periodStart, Carbon|string $periodEnd): array
    {
        $resolvedStart = $periodStart instanceof Carbon
            ? $periodStart->copy()
            : Carbon::parse($periodStart, config('app.timezone'));
        $resolvedEnd = $periodEnd instanceof Carbon
            ? $periodEnd->copy()
            : Carbon::parse($periodEnd, config('app.timezone'));

        $resolvedStart = $resolvedStart->startOfDay();
        $resolvedEnd = $resolvedEnd->startOfDay();

        if ($resolvedEnd->lt($resolvedStart)) {
            throw ValidationException::withMessages([
                'period_end' => 'The period end date must be on or after the period start date.',
            ]);
        }

        return [$resolvedStart, $resolvedEnd];
    }

    /**
     * @param Employee|int $employee
     */
    private function resolveEmployee(Employee|int $employee): Employee
    {
        if ($employee instanceof Employee) {
            return $employee;
        }

        return Employee::query()->findOrFail($employee);
    }

    /**
     * @return Collection<int, AttendanceSummary>
     */
    private function resolveAttendanceSummaries(Employee $employee, Carbon $periodStart, Carbon $periodEnd): Collection
    {
        return AttendanceSummary::query()
            ->forCompany($employee->getEffectiveCompanyId())
            ->forEmployee($employee)
            ->betweenDates($periodStart, $periodEnd)
            ->orderBy('attendance_date')
            ->get();
    }

    /**
     * @return Collection<int, OvertimeCalculation>
     */
    private function resolveApprovedOvertimeCalculations(Employee $employee, Carbon $periodStart, Carbon $periodEnd): Collection
    {
        return OvertimeCalculation::query()
            ->forCompany($employee->getEffectiveCompanyId())
            ->forEmployee($employee)
            ->whereBetween('calculation_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->where('calculation_status', OvertimeCalculation::STATUS_CALCULATED)
            ->whereHas('overtimeRequest', function ($query): void {
                $query->where('status', OvertimeRequest::STATUS_APPROVED);
            })
            ->orderBy('calculation_date')
            ->get();
    }

    /**
     * @return Collection<int, AttendanceCorrection>
     */
    private function resolveApprovedCorrections(Employee $employee, Carbon $periodStart, Carbon $periodEnd): Collection
    {
        return AttendanceCorrection::query()
            ->forCompany($employee->getEffectiveCompanyId())
            ->forEmployee($employee)
            ->approved()
            ->whereBetween('attendance_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->orderBy('attendance_date')
            ->get();
    }

    /**
     * @return Collection<int, LeaveRequest>
     */
    private function resolveApprovedLeaveRequests(Employee $employee, Carbon $periodStart, Carbon $periodEnd): Collection
    {
        return LeaveRequest::query()
            ->forCompany($employee->getEffectiveCompanyId())
            ->where('employee_id', $employee->getKey())
            ->where('status', LeaveRequest::STATUS_APPROVED)
            ->whereDate('start_date', '<=', $periodEnd->toDateString())
            ->whereDate('end_date', '>=', $periodStart->toDateString())
            ->orderBy('start_date')
            ->get();
    }

    /**
     * @param Collection<int, AttendanceSummary> $summaryRecords
     */
    private function countWorkDays(Collection $summaryRecords): int
    {
        return $summaryRecords->reject(function (AttendanceSummary $summary): bool {
            return in_array($summary->status, [
                AttendanceSummary::STATUS_HOLIDAY,
                AttendanceSummary::STATUS_WEEKEND,
                AttendanceSummary::STATUS_NO_SCHEDULE,
            ], true);
        })->count();
    }

    /**
     * @param Collection<int, AttendanceSummary> $summaryRecords
     */
    private function countPresentDays(Collection $summaryRecords): int
    {
        return $summaryRecords->filter(function (AttendanceSummary $summary): bool {
            return in_array($summary->status, [
                AttendanceSummary::STATUS_PRESENT,
                AttendanceSummary::STATUS_LATE,
                AttendanceSummary::STATUS_EARLY_OUT,
            ], true);
        })->count();
    }

    /**
     * @param Collection<int, AttendanceSummary> $summaryRecords
     * @param Collection<int, OvertimeCalculation> $overtimeCalculations
     * @param Collection<int, AttendanceCorrection> $approvedCorrections
     * @param Collection<int, LeaveRequest> $approvedLeaveRequests
     * @return array<string, mixed>
     */
    private function buildMetadata(
        AttendancePayrollSnapshot $snapshot,
        Collection $summaryRecords,
        Collection $overtimeCalculations,
        Collection $approvedCorrections,
        Collection $approvedLeaveRequests,
        ?int $actorId,
        Carbon $calculatedAt,
    ): array {
        $existingMetadata = $snapshot->metadata ?? [];
        $existingMetadata['attendance_summary_ids'] = $summaryRecords->pluck('id')->map(static fn (mixed $id): int => (int) $id)->values()->all();
        $existingMetadata['overtime_calculation_ids'] = $overtimeCalculations->pluck('id')->map(static fn (mixed $id): int => (int) $id)->values()->all();
        $existingMetadata['attendance_correction_ids'] = $approvedCorrections->pluck('id')->map(static fn (mixed $id): int => (int) $id)->values()->all();
        $existingMetadata['leave_request_ids'] = $approvedLeaveRequests->pluck('id')->map(static fn (mixed $id): int => (int) $id)->values()->all();
        $existingMetadata['calculation_version'] = self::CALCULATION_VERSION;
        $existingMetadata['generated_by'] = self::class;
        $existingMetadata['generated_actor_id'] = $actorId;
        $existingMetadata['calculated_at'] = $calculatedAt->toDateTimeString();

        return $existingMetadata;
    }

    private function resolveActorId(?Employee $actor, int $companyId): ?int
    {
        if ($actor instanceof Employee && (int) $actor->getEffectiveCompanyId() === $companyId) {
            return (int) $actor->getKey();
        }

        $user = Auth::user();

        if ($user instanceof Employee && (int) $user->getEffectiveCompanyId() === $companyId) {
            return (int) $user->getKey();
        }

        return null;
    }

    private function lockSnapshotRecord(AttendancePayrollSnapshot $snapshot): AttendancePayrollSnapshot
    {
        return AttendancePayrollSnapshot::query()
            ->with(['company', 'employee', 'lockedBy'])
            ->whereKey($snapshot->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function mergeStatusMetadata(
        AttendancePayrollSnapshot $snapshot,
        string $key,
        array $context,
    ): array {
        $metadata = $snapshot->metadata ?? [];
        $metadata[$key] = array_merge($metadata[$key] ?? [], $context);

        return $metadata;
    }
}
