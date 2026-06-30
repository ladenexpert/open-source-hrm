<?php

namespace App\Services;

use App\Models\AttendancePolicy;
use App\Models\AttendanceSummary;
use App\Models\OvertimeCalculation;
use App\Models\OvertimeRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OvertimeCalculationService
{
    public function calculateForRequest(OvertimeRequest $overtimeRequest): OvertimeCalculation
    {
        return DB::transaction(function () use ($overtimeRequest): OvertimeCalculation {
            $overtimeRequest = $this->lockRequest($overtimeRequest);

            if (! $overtimeRequest->isApproved()) {
                throw ValidationException::withMessages([
                    'status' => 'Only approved overtime requests can be calculated.',
                ]);
            }

            $attendanceSummary = $this->resolveAttendanceSummary($overtimeRequest);

            if (! $attendanceSummary instanceof AttendanceSummary) {
                throw ValidationException::withMessages([
                    'attendance_summary_id' => 'Overtime calculation requires an attendance summary.',
                ]);
            }

            $actualOvertimeMinutes = $this->calculateActualOvertimeMinutes($attendanceSummary);
            $thresholdMinutes = (int) ($attendanceSummary->attendancePolicy?->overtime_threshold_minutes ?? 0);
            $thresholdSatisfied = $actualOvertimeMinutes >= $thresholdMinutes;
            $eligibleActualMinutes = $this->isEligibleForOvertime($attendanceSummary) && $thresholdSatisfied
                ? $actualOvertimeMinutes
                : 0;
            $limitMinutes = $overtimeRequest->approved_minutes ?? $overtimeRequest->requested_minutes;
            $calculatedMinutes = $limitMinutes !== null
                ? min($eligibleActualMinutes, max(0, (int) $limitMinutes))
                : $eligibleActualMinutes;

            $calculation = OvertimeCalculation::query()
                ->where('company_id', $overtimeRequest->company_id)
                ->where('employee_id', $overtimeRequest->employee_id)
                ->whereDate('calculation_date', $overtimeRequest->overtime_date->toDateString())
                ->lockForUpdate()
                ->first();

            $calculation ??= new OvertimeCalculation();
            $calculation->fill([
                'company_id' => $overtimeRequest->company_id,
                'employee_id' => $overtimeRequest->employee_id,
                'overtime_request_id' => $overtimeRequest->getKey(),
                'attendance_summary_id' => $attendanceSummary->getKey(),
                'calculation_date' => $overtimeRequest->overtime_date->toDateString(),
                'scheduled_end_at' => $attendanceSummary->scheduled_end_at,
                'actual_clock_out_at' => $attendanceSummary->actual_out_at,
                'actual_overtime_minutes' => $actualOvertimeMinutes,
                'requested_minutes' => $overtimeRequest->requested_minutes,
                'approved_minutes' => $overtimeRequest->approved_minutes,
                'calculated_minutes' => $calculatedMinutes,
                'calculation_status' => OvertimeCalculation::STATUS_CALCULATED,
                'calculated_at' => now(config('app.timezone')),
                'metadata' => [
                    'attendance_status' => $attendanceSummary->status,
                    'attendance_policy_id' => $attendanceSummary->attendance_policy_id,
                    'threshold_minutes' => $thresholdMinutes,
                    'threshold_satisfied' => $thresholdSatisfied,
                    'used_limit_minutes' => $limitMinutes,
                ],
            ]);
            $calculation->save();

            if ((int) $overtimeRequest->attendance_summary_id !== (int) $attendanceSummary->getKey()) {
                $overtimeRequest->forceFill([
                    'attendance_summary_id' => $attendanceSummary->getKey(),
                ])->save();
            }

            return $calculation->fresh([
                'company',
                'employee',
                'overtimeRequest.approvalRequest',
                'attendanceSummary.attendancePolicy',
            ]);
        });
    }

    public function recalculateForRequest(OvertimeRequest $overtimeRequest): OvertimeCalculation
    {
        return $this->calculateForRequest($overtimeRequest);
    }

    private function resolveAttendanceSummary(OvertimeRequest $overtimeRequest): ?AttendanceSummary
    {
        if ($overtimeRequest->attendanceSummary instanceof AttendanceSummary) {
            return $overtimeRequest->attendanceSummary->loadMissing('attendancePolicy');
        }

        return AttendanceSummary::query()
            ->forCompany($overtimeRequest->company_id)
            ->forEmployee($overtimeRequest->employee_id)
            ->forDate($overtimeRequest->overtime_date)
            ->with('attendancePolicy')
            ->first();
    }

    private function calculateActualOvertimeMinutes(AttendanceSummary $attendanceSummary): int
    {
        if (! $attendanceSummary->scheduled_end_at instanceof Carbon || ! $attendanceSummary->actual_out_at instanceof Carbon) {
            return 0;
        }

        if (! $attendanceSummary->actual_out_at->greaterThan($attendanceSummary->scheduled_end_at)) {
            return 0;
        }

        return $attendanceSummary->scheduled_end_at->diffInMinutes($attendanceSummary->actual_out_at);
    }

    private function isEligibleForOvertime(AttendanceSummary $attendanceSummary): bool
    {
        return ! in_array($attendanceSummary->status, [
            AttendanceSummary::STATUS_ABSENT,
            AttendanceSummary::STATUS_LEAVE,
            AttendanceSummary::STATUS_INCOMPLETE,
            AttendanceSummary::STATUS_NO_SCHEDULE,
        ], true);
    }

    private function lockRequest(OvertimeRequest $overtimeRequest): OvertimeRequest
    {
        return OvertimeRequest::query()
            ->with([
                'company',
                'employee',
                'attendanceSummary.attendancePolicy',
                'approvalRequest.logs.actor',
                'approvalRequest.steps.approver',
                'approvalRequest.steps.workflowStep',
                'approvalRequest.workflow.steps',
                'calculation',
            ])
            ->whereKey($overtimeRequest->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }
}
