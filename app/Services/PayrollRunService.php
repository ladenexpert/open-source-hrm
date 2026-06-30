<?php

namespace App\Services;

use App\Models\AttendancePayrollSnapshot;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\PayrollRunEmployee;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayrollRunService
{
    public function __construct(
        private readonly AttendancePayrollReadinessService $attendancePayrollReadinessService,
    ) {}

    public function createRun(
        PayrollPeriod|int $payrollPeriod,
        string $runType = PayrollRun::RUN_TYPE_REGULAR,
        ?string $runCode = null,
        array $metadata = [],
        ?Employee $actor = null,
    ): PayrollRun {
        return DB::transaction(function () use ($payrollPeriod, $runType, $runCode, $metadata, $actor): PayrollRun {
            $period = $this->resolvePayrollPeriod($payrollPeriod);

            if ($period->isCancelled()) {
                throw ValidationException::withMessages([
                    'payroll_period_id' => 'Cancelled payroll periods cannot be used to create payroll runs.',
                ]);
            }

            $run = new PayrollRun();
            $run->fill([
                'company_id' => $period->company_id,
                'payroll_period_id' => $period->id,
                'run_code' => $runCode,
                'run_type' => $runType,
                'status' => PayrollRun::STATUS_DRAFT,
                'period_start' => $period->period_start?->toDateString(),
                'period_end' => $period->period_end?->toDateString(),
                'metadata' => array_merge($metadata, [
                    'creation_context' => array_filter([
                        'service' => self::class,
                        'created_at' => now(config('app.timezone'))->toDateTimeString(),
                        'created_by' => $this->resolveActorId($actor, (int) $period->company_id),
                        'payroll_period_id' => $period->id,
                    ], static fn (mixed $value): bool => $value !== null),
                ]),
            ]);
            $run->save();

            return $run->fresh($this->defaultRelations());
        });
    }

    /**
     * @param array<int, int|string> $employeeIds
     */
    public function prepareRun(
        PayrollRun $payrollRun,
        array $employeeIds = [],
        bool $includeAllActiveEmployees = false,
        ?Employee $actor = null,
    ): PayrollRun {
        $employeeIds = $this->normalizeEmployeeIds($employeeIds);

        if ($employeeIds === [] && ! $includeAllActiveEmployees) {
            throw ValidationException::withMessages([
                'employee_ids' => 'Select at least one employee or include all active employees.',
            ]);
        }

        return DB::transaction(function () use ($payrollRun, $employeeIds, $includeAllActiveEmployees, $actor): PayrollRun {
            $payrollRun = $this->lockPayrollRunRecord($payrollRun);
            $this->assertRunCanBePrepared($payrollRun);

            $employees = $this->resolveEmployeesForPreparation($payrollRun, $employeeIds, $includeAllActiveEmployees);

            if ($employees->isEmpty()) {
                throw ValidationException::withMessages([
                    'employee_ids' => 'No eligible employees were found for payroll preparation.',
                ]);
            }

            $preparedAt = now(config('app.timezone'));

            foreach ($employees as $employee) {
                $this->prepareEmployee(
                    payrollRun: $payrollRun,
                    employee: $employee,
                    preparedAt: $preparedAt,
                    explicitSelection: in_array((int) $employee->id, $employeeIds, true),
                );
            }

            $payrollRun->forceFill([
                'status' => PayrollRun::STATUS_PREPARED,
                'prepared_at' => $preparedAt,
                'metadata' => $this->mergeMetadata(
                    $payrollRun->metadata,
                    'preparation_context',
                    [
                        'service' => self::class,
                        'prepared_at' => $preparedAt->toDateTimeString(),
                        'prepared_by' => $this->resolveActorId($actor, (int) $payrollRun->company_id),
                        'selected_employee_ids' => $employeeIds,
                        'include_all_active_employees' => $includeAllActiveEmployees,
                    ],
                ),
            ])->save();

            $this->refreshCountsOnModel($payrollRun);

            return $payrollRun->fresh($this->defaultRelations());
        });
    }

    public function refreshCounts(PayrollRun $payrollRun): PayrollRun
    {
        return DB::transaction(function () use ($payrollRun): PayrollRun {
            $payrollRun = $this->lockPayrollRunRecord($payrollRun);
            $this->refreshCountsOnModel($payrollRun);

            return $payrollRun->fresh($this->defaultRelations());
        });
    }

    public function lockRun(PayrollRun $payrollRun, ?Employee $actor = null): PayrollRun
    {
        return DB::transaction(function () use ($payrollRun, $actor): PayrollRun {
            $payrollRun = $this->lockPayrollRunRecord($payrollRun);

            if ($payrollRun->isApproved()) {
                throw ValidationException::withMessages([
                    'status' => 'Approved payroll runs cannot be locked again.',
                ]);
            }

            if ($payrollRun->isCancelled()) {
                throw ValidationException::withMessages([
                    'status' => 'Cancelled payroll runs cannot be locked.',
                ]);
            }

            if ($payrollRun->isLocked()) {
                return $payrollRun->fresh($this->defaultRelations());
            }

            $this->refreshCountsOnModel($payrollRun);

            if ((int) $payrollRun->blocked_employees > 0) {
                throw ValidationException::withMessages([
                    'blocked_employees' => 'Payroll runs with blocked employees cannot be locked.',
                ]);
            }

            $lockedAt = now(config('app.timezone'));

            $payrollRun->forceFill([
                'status' => PayrollRun::STATUS_LOCKED,
                'locked_at' => $lockedAt,
                'locked_by' => $this->resolveActorId($actor, (int) $payrollRun->company_id),
                'metadata' => $this->mergeMetadata(
                    $payrollRun->metadata,
                    'lock_context',
                    [
                        'service' => self::class,
                        'locked_at' => $lockedAt->toDateTimeString(),
                    ],
                ),
            ])->save();

            return $payrollRun->fresh($this->defaultRelations());
        });
    }

    public function approveRun(PayrollRun $payrollRun, ?Employee $actor = null): PayrollRun
    {
        return DB::transaction(function () use ($payrollRun, $actor): PayrollRun {
            $payrollRun = $this->lockPayrollRunRecord($payrollRun);

            if ($payrollRun->isCancelled()) {
                throw ValidationException::withMessages([
                    'status' => 'Cancelled payroll runs cannot be approved.',
                ]);
            }

            if ($payrollRun->isApproved()) {
                return $payrollRun->fresh($this->defaultRelations());
            }

            if (! $payrollRun->isLocked()) {
                throw ValidationException::withMessages([
                    'status' => 'Only locked payroll runs can be approved.',
                ]);
            }

            $approvedAt = now(config('app.timezone'));

            $payrollRun->forceFill([
                'status' => PayrollRun::STATUS_APPROVED,
                'approved_at' => $approvedAt,
                'approved_by' => $this->resolveActorId($actor, (int) $payrollRun->company_id),
                'metadata' => $this->mergeMetadata(
                    $payrollRun->metadata,
                    'approval_context',
                    [
                        'service' => self::class,
                        'approved_at' => $approvedAt->toDateTimeString(),
                    ],
                ),
            ])->save();

            return $payrollRun->fresh($this->defaultRelations());
        });
    }

    public function cancelRun(PayrollRun $payrollRun, ?Employee $actor = null, ?string $reason = null): PayrollRun
    {
        $reason = trim((string) $reason);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'cancellation_reason' => 'A cancellation reason is required.',
            ]);
        }

        return DB::transaction(function () use ($payrollRun, $actor, $reason): PayrollRun {
            $payrollRun = $this->lockPayrollRunRecord($payrollRun);

            if ($payrollRun->isApproved()) {
                throw ValidationException::withMessages([
                    'status' => 'Approved payroll runs cannot be cancelled.',
                ]);
            }

            if ($payrollRun->isCancelled()) {
                return $payrollRun->fresh($this->defaultRelations());
            }

            $cancelledAt = now(config('app.timezone'));

            $payrollRun->forceFill([
                'status' => PayrollRun::STATUS_CANCELLED,
                'cancelled_at' => $cancelledAt,
                'cancelled_by' => $this->resolveActorId($actor, (int) $payrollRun->company_id),
                'cancellation_reason' => $reason,
                'metadata' => $this->mergeMetadata(
                    $payrollRun->metadata,
                    'cancellation_context',
                    [
                        'service' => self::class,
                        'cancelled_at' => $cancelledAt->toDateTimeString(),
                        'reason' => $reason,
                    ],
                ),
            ])->save();

            return $payrollRun->fresh($this->defaultRelations());
        });
    }

    private function resolvePayrollPeriod(PayrollPeriod|int $payrollPeriod): PayrollPeriod
    {
        if ($payrollPeriod instanceof PayrollPeriod) {
            return $payrollPeriod;
        }

        return PayrollPeriod::query()->findOrFail($payrollPeriod);
    }

    private function lockPayrollRunRecord(PayrollRun $payrollRun): PayrollRun
    {
        return PayrollRun::query()
            ->with($this->defaultRelations())
            ->whereKey($payrollRun->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function assertRunCanBePrepared(PayrollRun $payrollRun): void
    {
        if ($payrollRun->isLocked()) {
            throw ValidationException::withMessages([
                'status' => 'Locked payroll runs cannot be prepared directly.',
            ]);
        }

        if ($payrollRun->isApproved()) {
            throw ValidationException::withMessages([
                'status' => 'Approved payroll runs cannot be modified.',
            ]);
        }

        if ($payrollRun->isCancelled()) {
            throw ValidationException::withMessages([
                'status' => 'Cancelled payroll runs cannot be modified.',
            ]);
        }
    }

    /**
     * @param array<int, int> $employeeIds
     * @return EloquentCollection<int, Employee>
     */
    private function resolveEmployeesForPreparation(
        PayrollRun $payrollRun,
        array $employeeIds,
        bool $includeAllActiveEmployees,
    ): EloquentCollection {
        $employees = new EloquentCollection();

        if ($employeeIds !== []) {
            $selectedEmployees = Employee::query()
                ->where('company_id', $payrollRun->company_id)
                ->whereIn('id', $employeeIds)
                ->get();

            if ($selectedEmployees->count() !== count($employeeIds)) {
                throw ValidationException::withMessages([
                    'employee_ids' => 'One or more selected employees are outside the payroll run company scope.',
                ]);
            }

            $employees = $employees->merge($selectedEmployees);
        }

        if ($includeAllActiveEmployees) {
            $employees = $employees->merge(
                Employee::query()
                    ->where('company_id', $payrollRun->company_id)
                    ->where('is_active', true)
                    ->get()
            );
        }

        return $employees
            ->unique(fn (Employee $employee): int => (int) $employee->getKey())
            ->values();
    }

    private function prepareEmployee(
        PayrollRun $payrollRun,
        Employee $employee,
        Carbon $preparedAt,
        bool $explicitSelection,
    ): void {
        $payrollRunEmployee = PayrollRunEmployee::query()
            ->where('payroll_run_id', $payrollRun->id)
            ->where('employee_id', $employee->id)
            ->lockForUpdate()
            ->first();

        if (
            $payrollRunEmployee instanceof PayrollRunEmployee
            && in_array($payrollRunEmployee->status, [PayrollRunEmployee::STATUS_EXCLUDED, PayrollRunEmployee::STATUS_CANCELLED], true)
            && ! $explicitSelection
        ) {
            return;
        }

        $snapshot = $this->resolveAttendancePayrollSnapshot($payrollRun, $employee);
        [$status, $message, $reasonCode] = $this->evaluateSnapshotReadiness($snapshot);

        $payrollRunEmployee ??= new PayrollRunEmployee();
        $payrollRunEmployee->fill(array_merge(
            [
                'company_id' => $payrollRun->company_id,
                'payroll_run_id' => $payrollRun->id,
                'employee_id' => $employee->id,
                'attendance_payroll_snapshot_id' => $snapshot?->id,
                'status' => $status,
                'readiness_message' => $message,
                'snapshot_status' => $snapshot?->snapshot_status,
                'metadata' => $this->buildPayrollRunEmployeeMetadata(
                    existingMetadata: $payrollRunEmployee->metadata,
                    snapshot: $snapshot,
                    preparedAt: $preparedAt,
                    reasonCode: $reasonCode,
                ),
            ],
            $this->snapshotTotals($snapshot),
        ));
        $payrollRunEmployee->save();
    }

    private function refreshCountsOnModel(PayrollRun $payrollRun): void
    {
        $baseQuery = PayrollRunEmployee::query()
            ->where('payroll_run_id', $payrollRun->id)
            ->where('status', '!=', PayrollRunEmployee::STATUS_CANCELLED);

        $payrollRun->forceFill([
            'total_employees' => (clone $baseQuery)->count(),
            'ready_employees' => (clone $baseQuery)->where('status', PayrollRunEmployee::STATUS_READY)->count(),
            'blocked_employees' => (clone $baseQuery)->where('status', PayrollRunEmployee::STATUS_BLOCKED)->count(),
        ])->save();
    }

    private function resolveAttendancePayrollSnapshot(
        PayrollRun $payrollRun,
        Employee $employee,
    ): ?AttendancePayrollSnapshot {
        return AttendancePayrollSnapshot::query()
            ->where('company_id', $payrollRun->company_id)
            ->where('employee_id', $employee->id)
            ->whereDate('period_start', $payrollRun->period_start->toDateString())
            ->whereDate('period_end', $payrollRun->period_end->toDateString())
            ->first();
    }

    /**
     * @return array{0: string, 1: string, 2: string|null}
     */
    private function evaluateSnapshotReadiness(?AttendancePayrollSnapshot $snapshot): array
    {
        if (! $snapshot instanceof AttendancePayrollSnapshot) {
            return [
                PayrollRunEmployee::STATUS_BLOCKED,
                'Attendance payroll snapshot is missing for this employee and period.',
                'missing_snapshot',
            ];
        }

        if ($snapshot->isCancelled()) {
            return [
                PayrollRunEmployee::STATUS_BLOCKED,
                'Attendance payroll snapshot is cancelled and must be regenerated.',
                'cancelled_snapshot',
            ];
        }

        if ($snapshot->isStale()) {
            return [
                PayrollRunEmployee::STATUS_BLOCKED,
                'Attendance payroll snapshot is stale and must be recalculated.',
                'stale_snapshot',
            ];
        }

        if ($snapshot->isLocked()) {
            return [
                PayrollRunEmployee::STATUS_READY,
                'Ready from locked attendance payroll snapshot.',
                null,
            ];
        }

        if ($snapshot->isCalculated()) {
            if ($this->attendancePayrollReadinessService->hasSourceDataChangedSinceCalculation($snapshot)) {
                return [
                    PayrollRunEmployee::STATUS_BLOCKED,
                    'Attendance payroll snapshot is stale because source data changed after calculation.',
                    'source_data_changed',
                ];
            }

            return [
                PayrollRunEmployee::STATUS_READY,
                'Ready from calculated attendance payroll snapshot.',
                null,
            ];
        }

        return [
            PayrollRunEmployee::STATUS_BLOCKED,
            'Attendance payroll snapshot is not ready for payroll preparation.',
            'snapshot_not_ready',
        ];
    }

    /**
     * @return array<string, int|string>
     */
    private function snapshotTotals(?AttendancePayrollSnapshot $snapshot): array
    {
        if (! $snapshot instanceof AttendancePayrollSnapshot) {
            return [
                'total_work_days' => 0,
                'total_present_days' => 0,
                'total_absent_days' => 0,
                'total_late_minutes' => 0,
                'total_early_leave_minutes' => 0,
                'total_work_minutes' => 0,
                'total_overtime_minutes' => 0,
                'total_leave_days' => '0.00',
                'total_correction_count' => 0,
            ];
        }

        return [
            'total_work_days' => (int) $snapshot->total_work_days,
            'total_present_days' => (int) $snapshot->total_present_days,
            'total_absent_days' => (int) $snapshot->total_absent_days,
            'total_late_minutes' => (int) $snapshot->total_late_minutes,
            'total_early_leave_minutes' => (int) $snapshot->total_early_leave_minutes,
            'total_work_minutes' => (int) $snapshot->total_work_minutes,
            'total_overtime_minutes' => (int) $snapshot->total_overtime_minutes,
            'total_leave_days' => number_format((float) $snapshot->total_leave_days, 2, '.', ''),
            'total_correction_count' => (int) $snapshot->total_correction_count,
        ];
    }

    /**
     * @param array<string, mixed>|null $existingMetadata
     * @return array<string, mixed>
     */
    private function buildPayrollRunEmployeeMetadata(
        ?array $existingMetadata,
        ?AttendancePayrollSnapshot $snapshot,
        Carbon $preparedAt,
        ?string $reasonCode,
    ): array {
        $metadata = $existingMetadata ?? [];
        $metadata['preparation_context'] = array_filter([
            'service' => self::class,
            'prepared_at' => $preparedAt->toDateTimeString(),
            'snapshot_id' => $snapshot?->id,
            'snapshot_status' => $snapshot?->snapshot_status,
            'snapshot_period_start' => $snapshot?->period_start?->toDateString(),
            'snapshot_period_end' => $snapshot?->period_end?->toDateString(),
            'snapshot_calculated_at' => $snapshot?->calculated_at?->toDateTimeString(),
            'snapshot_locked_at' => $snapshot?->locked_at?->toDateTimeString(),
            'readiness_reason' => $reasonCode,
        ], static fn (mixed $value): bool => $value !== null);

        return $metadata;
    }

    /**
     * @param array<string, mixed>|null $metadata
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function mergeMetadata(?array $metadata, string $key, array $context): array
    {
        $metadata ??= [];
        $metadata[$key] = array_merge($metadata[$key] ?? [], $context);

        return $metadata;
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

    /**
     * @param array<int, int|string> $employeeIds
     * @return array<int, int>
     */
    private function normalizeEmployeeIds(array $employeeIds): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn (int|string $employeeId): ?int => filled($employeeId) ? (int) $employeeId : null,
            $employeeIds,
        ))));
    }

    /**
     * @return array<int, string>
     */
    private function defaultRelations(): array
    {
        return [
            'company',
            'payrollPeriod',
            'lockedBy',
            'approvedBy',
            'cancelledBy',
            'payrollRunEmployees.employee',
            'payrollRunEmployees.attendancePayrollSnapshot',
        ];
    }
}
