<?php

namespace App\Services\Attendance;

use App\Models\AttendanceLog;
use App\Models\AttendancePolicy;
use App\Models\AttendanceSummary;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\ShiftPatternDetail;
use App\Models\WorkLocation;
use App\Models\WorkdayPattern;
use App\Models\WorkdayPatternDay;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class AttendanceCalculationService
{
    private const OVERNIGHT_BUFFER_HOURS = 6;

    public function __construct(
        private readonly AttendancePolicyResolverService $attendancePolicyResolverService,
        private readonly ShiftResolverService $shiftResolverService,
    ) {
    }

    public function calculateForEmployeeDate(Employee $employee, Carbon $date): AttendanceSummary
    {
        $date = $date->copy()->setTimezone(config('app.timezone'))->startOfDay();
        $companyId = (int) $employee->getEffectiveCompanyId();
        $attendancePolicy = $this->attendancePolicyResolverService->resolvePolicy($employee);
        $resolution = $this->shiftResolverService->resolve($employee, $date->copy());
        $shiftPattern = $resolution->shiftPattern;
        $shiftDetail = $this->resolveShiftDetail($shiftPattern?->details, $date);
        $workdayPattern = $this->resolveWorkdayPattern($companyId);
        $workdayPatternDay = $workdayPattern?->days->firstWhere('day_of_week', $this->normalizeDayOfWeek($date));
        $schedule = $this->buildSchedule($date, $shiftDetail);
        $leaveRequest = $this->resolveApprovedFullDayLeave($employee, $date);
        $holiday = $this->resolveHoliday($companyId, $date);
        $logWindow = $this->resolveLogWindow($date, $schedule['scheduled_start_at'], $schedule['scheduled_end_at']);
        $logs = $this->resolveLogs($employee, $logWindow['window_start'], $logWindow['window_end']);
        $actuals = $this->resolveActuals($logs['valid_logs']);
        $workMinutes = $this->calculateWorkMinutes(
            $actuals['actual_in_at'],
            $actuals['actual_out_at'],
            $schedule['break_duration_minutes'],
        );
        $lateMinutes = $this->calculateLateMinutes(
            $actuals['actual_in_at'],
            $schedule['scheduled_start_at'],
            $attendancePolicy,
        );
        $earlyOutMinutes = $this->calculateEarlyOutMinutes(
            $actuals['actual_out_at'],
            $schedule['scheduled_end_at'],
            $attendancePolicy,
        );
        $isComplete = $actuals['actual_in_at'] !== null && $actuals['actual_out_at'] !== null;
        $notes = $this->buildCalculationNotes($logs['invalid_logs'], $leaveRequest);
        $status = $this->resolveStatus(
            leaveRequest: $leaveRequest,
            holiday: $holiday,
            shiftDetail: $shiftDetail,
            workdayPatternDay: $workdayPatternDay,
            resolution: $resolution,
            actualInAt: $actuals['actual_in_at'],
            actualOutAt: $actuals['actual_out_at'],
            lateMinutes: $lateMinutes,
            earlyOutMinutes: $earlyOutMinutes,
        );

        $existingSummary = AttendanceSummary::query()
            ->forCompany($companyId)
            ->forEmployee($employee)
            ->forDate($date)
            ->first();
        $auditActorId = $this->resolveAuditActorId($companyId);

        $summary = $existingSummary ?? new AttendanceSummary();
        $summary->fill([
            'company_id' => $companyId,
            'employee_id' => $employee->getKey(),
            'attendance_date' => $date->toDateString(),
            'shift_pattern_id' => $shiftPattern?->getKey(),
            'shift_pattern_detail_id' => $shiftDetail?->getKey(),
            'shift_assignment_id' => $resolution->shiftAssignment?->getKey(),
            'employee_schedule_id' => $resolution->employeeSchedule?->getKey(),
            'attendance_policy_id' => $attendancePolicy?->getKey(),
            'work_location_id' => $this->resolveWorkLocation($employee, $resolution)?->getKey(),
            'scheduled_start_at' => $schedule['scheduled_start_at'],
            'scheduled_end_at' => $schedule['scheduled_end_at'],
            'break_duration_minutes' => $schedule['break_duration_minutes'],
            'actual_in_at' => $actuals['actual_in_at'],
            'actual_out_at' => $actuals['actual_out_at'],
            'first_log_id' => $actuals['first_log_id'],
            'last_log_id' => $actuals['last_log_id'],
            'work_minutes' => $workMinutes,
            'late_minutes' => $lateMinutes,
            'early_out_minutes' => $earlyOutMinutes,
            'status' => $status,
            'is_complete' => $isComplete,
            'is_recalculated' => $existingSummary instanceof AttendanceSummary,
            'calculated_at' => now(config('app.timezone')),
            'calculation_notes' => $notes,
            'created_by' => $existingSummary?->created_by ?? $auditActorId,
            'updated_by' => $auditActorId,
        ]);
        $summary->save();

        return $summary->fresh([
            'company',
            'employee',
            'shiftPattern',
            'shiftPatternDetail',
            'shiftAssignment',
            'employeeSchedule',
            'attendancePolicy',
            'workLocation',
            'firstLog',
            'lastLog',
        ]);
    }

    public function calculateForDateRange(Employee $employee, Carbon $startDate, Carbon $endDate): Collection
    {
        $startDate = $startDate->copy()->setTimezone(config('app.timezone'))->startOfDay();
        $endDate = $endDate->copy()->setTimezone(config('app.timezone'))->startOfDay();
        $summaries = collect();
        $cursor = $startDate->copy();

        while ($cursor->lte($endDate)) {
            $summaries->push($this->calculateForEmployeeDate($employee, $cursor));
            $cursor->addDay();
        }

        return $summaries;
    }

    public function recalculateSummary(AttendanceSummary $summary): AttendanceSummary
    {
        return $this->calculateForEmployeeDate(
            $summary->employee()->firstOrFail(),
            $summary->attendance_date->copy(),
        );
    }

    private function resolveShiftDetail(?Collection $details, Carbon $date): ?ShiftPatternDetail
    {
        if (! $details instanceof Collection) {
            return null;
        }

        $detail = $details->firstWhere('day_of_week', $this->normalizeDayOfWeek($date));

        return $detail instanceof ShiftPatternDetail ? $detail : null;
    }

    private function resolveWorkdayPattern(int $companyId): ?WorkdayPattern
    {
        return WorkdayPattern::query()
            ->forCompany($companyId)
            ->active()
            ->with('days')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->first();
    }

    /**
     * @return array{scheduled_start_at: ?Carbon, scheduled_end_at: ?Carbon, break_duration_minutes: int}
     */
    private function buildSchedule(Carbon $date, ?ShiftPatternDetail $shiftDetail): array
    {
        if (! $shiftDetail instanceof ShiftPatternDetail || ! $shiftDetail->is_working_day) {
            return [
                'scheduled_start_at' => null,
                'scheduled_end_at' => null,
                'break_duration_minutes' => 0,
            ];
        }

        $scheduledStartAt = $date->copy()->setTimeFromTimeString((string) $shiftDetail->start_time);
        $scheduledEndAt = $date->copy()->setTimeFromTimeString((string) $shiftDetail->end_time);

        if ($shiftDetail->isOvernight()) {
            $scheduledEndAt->addDay();
        }

        return [
            'scheduled_start_at' => $scheduledStartAt,
            'scheduled_end_at' => $scheduledEndAt,
            'break_duration_minutes' => (int) $shiftDetail->break_duration_minutes,
        ];
    }

    private function resolveApprovedFullDayLeave(Employee $employee, Carbon $date): ?LeaveRequest
    {
        return LeaveRequest::query()
            ->forCompany($employee->getEffectiveCompanyId())
            ->where('employee_id', $employee->getKey())
            ->where('status', LeaveRequest::STATUS_APPROVED)
            ->where('is_half_day', false)
            ->whereDate('start_date', '<=', $date->toDateString())
            ->whereDate('end_date', '>=', $date->toDateString())
            ->orderBy('id')
            ->first();
    }

    private function resolveHoliday(int $companyId, Carbon $date): ?Holiday
    {
        return Holiday::query()
            ->forCompany($companyId)
            ->whereDate('date', $date->toDateString())
            ->whereHas('holidayCalendar', function ($query) use ($date): void {
                $query->where('is_active', true)
                    ->where('year', $date->year);
            })
            ->orderBy('id')
            ->first();
    }

    /**
     * @return array{window_start: Carbon, window_end: Carbon}
     */
    private function resolveLogWindow(Carbon $date, ?Carbon $scheduledStartAt, ?Carbon $scheduledEndAt): array
    {
        if ($scheduledStartAt instanceof Carbon && $scheduledEndAt instanceof Carbon && $scheduledEndAt->isAfter($scheduledStartAt->copy()->endOfDay())) {
            return [
                'window_start' => $scheduledStartAt->copy()->subHours(self::OVERNIGHT_BUFFER_HOURS),
                'window_end' => $scheduledEndAt->copy()->addHours(self::OVERNIGHT_BUFFER_HOURS),
            ];
        }

        return [
            'window_start' => $date->copy()->startOfDay(),
            'window_end' => $date->copy()->endOfDay(),
        ];
    }

    /**
     * @return array{valid_logs: Collection<int, AttendanceLog>, invalid_logs: Collection<int, AttendanceLog>}
     */
    private function resolveLogs(Employee $employee, Carbon $windowStart, Carbon $windowEnd): array
    {
        $baseQuery = AttendanceLog::query()
            ->forCompany($employee->getEffectiveCompanyId())
            ->forEmployee($employee)
            ->whereBetween('clocked_at', [$windowStart, $windowEnd])
            ->orderBy('clocked_at')
            ->orderBy('id');

        return [
            'valid_logs' => (clone $baseQuery)->valid()->get(),
            'invalid_logs' => (clone $baseQuery)->invalid()->get(),
        ];
    }

    /**
     * @param Collection<int, AttendanceLog> $logs
     * @return array{actual_in_at: ?Carbon, actual_out_at: ?Carbon, first_log_id: ?int, last_log_id: ?int}
     */
    private function resolveActuals(Collection $logs): array
    {
        /** @var AttendanceLog|null $firstLog */
        $firstLog = $logs->first(fn (AttendanceLog $log): bool => $log->isClockIn());
        /** @var AttendanceLog|null $lastLog */
        $lastLog = $logs->filter(fn (AttendanceLog $log): bool => $log->isClockOut())->last();

        return [
            'actual_in_at' => $firstLog?->clocked_at?->copy(),
            'actual_out_at' => $lastLog?->clocked_at?->copy(),
            'first_log_id' => $firstLog?->getKey(),
            'last_log_id' => $lastLog?->getKey(),
        ];
    }

    private function calculateWorkMinutes(?Carbon $actualInAt, ?Carbon $actualOutAt, int $breakDurationMinutes): int
    {
        if (! $actualInAt instanceof Carbon || ! $actualOutAt instanceof Carbon || ! $actualOutAt->greaterThan($actualInAt)) {
            return 0;
        }

        return max(0, $actualInAt->diffInMinutes($actualOutAt) - $breakDurationMinutes);
    }

    private function calculateLateMinutes(?Carbon $actualInAt, ?Carbon $scheduledStartAt, ?AttendancePolicy $attendancePolicy): int
    {
        if (! $actualInAt instanceof Carbon || ! $scheduledStartAt instanceof Carbon) {
            return 0;
        }

        $threshold = $scheduledStartAt->copy()->addMinutes((int) ($attendancePolicy?->late_tolerance_minutes ?? 0));

        return $actualInAt->greaterThan($threshold)
            ? $threshold->diffInMinutes($actualInAt)
            : 0;
    }

    private function calculateEarlyOutMinutes(?Carbon $actualOutAt, ?Carbon $scheduledEndAt, ?AttendancePolicy $attendancePolicy): int
    {
        if (! $actualOutAt instanceof Carbon || ! $scheduledEndAt instanceof Carbon) {
            return 0;
        }

        $threshold = $scheduledEndAt->copy()->subMinutes((int) ($attendancePolicy?->early_out_tolerance_minutes ?? 0));

        return $actualOutAt->lessThan($threshold)
            ? $actualOutAt->diffInMinutes($threshold)
            : 0;
    }

    private function buildCalculationNotes(Collection $invalidLogs, ?LeaveRequest $leaveRequest): ?string
    {
        $notes = [];

        if ($invalidLogs->isNotEmpty()) {
            $notes[] = sprintf('%d invalid logs ignored for calculation.', $invalidLogs->count());
        }

        if ($leaveRequest instanceof LeaveRequest && $leaveRequest->is_half_day) {
            $notes[] = 'Half-day leave handling is deferred in v1.4.2.';
        }

        return $notes === [] ? null : implode(' ', $notes);
    }

    private function resolveStatus(
        ?LeaveRequest $leaveRequest,
        ?Holiday $holiday,
        ?ShiftPatternDetail $shiftDetail,
        mixed $workdayPatternDay,
        ShiftResolutionResult $resolution,
        ?Carbon $actualInAt,
        ?Carbon $actualOutAt,
        int $lateMinutes,
        int $earlyOutMinutes,
    ): string {
        if ($leaveRequest instanceof LeaveRequest) {
            return AttendanceSummary::STATUS_LEAVE;
        }

        if ($holiday instanceof Holiday) {
            return AttendanceSummary::STATUS_HOLIDAY;
        }

        if (
            ($shiftDetail instanceof ShiftPatternDetail && ! $shiftDetail->is_working_day)
            || ($workdayPatternDay instanceof WorkdayPatternDay && ! $workdayPatternDay->is_working_day)
        ) {
            return AttendanceSummary::STATUS_WEEKEND;
        }

        if ($resolution->isDayOffOverride() || $resolution->shiftPattern === null || ! ($shiftDetail instanceof ShiftPatternDetail)) {
            return AttendanceSummary::STATUS_NO_SCHEDULE;
        }

        if ($actualInAt === null && $actualOutAt === null) {
            return AttendanceSummary::STATUS_ABSENT;
        }

        if ($actualInAt === null || $actualOutAt === null) {
            return AttendanceSummary::STATUS_INCOMPLETE;
        }

        if ($lateMinutes > 0) {
            return AttendanceSummary::STATUS_LATE;
        }

        if ($earlyOutMinutes > 0) {
            return AttendanceSummary::STATUS_EARLY_OUT;
        }

        return AttendanceSummary::STATUS_PRESENT;
    }

    private function resolveWorkLocation(Employee $employee, ShiftResolutionResult $resolution): ?WorkLocation
    {
        if ($resolution->employeeSchedule?->workLocation instanceof WorkLocation) {
            return $resolution->employeeSchedule->workLocation;
        }

        if ($resolution->shiftAssignment?->workLocation instanceof WorkLocation) {
            return $resolution->shiftAssignment->workLocation;
        }

        $workLocation = $employee->relationLoaded('workLocation')
            ? $employee->workLocation
            : $employee->workLocation()->first();

        return $workLocation instanceof WorkLocation ? $workLocation : null;
    }

    private function resolveAuditActorId(int $companyId): ?int
    {
        $user = Auth::user();

        if ($user instanceof Employee && (int) $user->getEffectiveCompanyId() === $companyId) {
            return (int) $user->getKey();
        }

        return null;
    }

    private function normalizeDayOfWeek(Carbon $date): int
    {
        return $date->dayOfWeek === 0 ? 7 : $date->dayOfWeek;
    }
}
