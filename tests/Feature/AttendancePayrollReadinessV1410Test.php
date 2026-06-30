<?php

namespace Tests\Feature;

use App\Models\AttendanceCorrection;
use App\Models\AttendancePayrollSnapshot;
use App\Models\AttendancePolicy;
use App\Models\AttendanceSummary;
use App\Models\Company;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\OvertimeCalculation;
use App\Models\OvertimeRequest;
use App\Models\Payroll;
use App\Services\AttendancePayrollReadinessService;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AttendancePayrollReadinessV1410Test extends TestCase
{
    use RefreshDatabase;

    private AttendancePayrollReadinessService $service;

    private int $employeeSequence = 1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->service = app(AttendancePayrollReadinessService::class);
    }

    public function test_snapshot_table_exists_and_snapshot_can_be_generated_for_employee_and_period(): void
    {
        $this->assertTrue(Schema::hasTable('attendance_payroll_snapshots'));

        $employee = $this->newEmployeeWithPolicy();
        $periodStart = now(config('app.timezone'))->startOfMonth()->addWeek()->startOfWeek(Carbon::MONDAY);
        $periodEnd = $periodStart->copy()->addDays(5);
        $summaries = $this->createSummaryFixture($employee, $periodStart);
        $approvedLeave = $this->createApprovedLeaveRequest($employee, $periodStart->copy()->addDays(4));
        $this->createApprovedCorrection($employee, $summaries['late'], 15);
        $approvedOvertime = $this->createOvertimeRequest($employee, $summaries['present'], OvertimeRequest::STATUS_APPROVED, 60, 60);
        $approvedCalculation = $this->createOvertimeCalculation($employee, $approvedOvertime, $summaries['present'], OvertimeCalculation::STATUS_CALCULATED, 60);
        $staleOvertime = $this->createOvertimeRequest($employee, $summaries['late'], OvertimeRequest::STATUS_APPROVED, 45, 45);
        $this->createOvertimeCalculation($employee, $staleOvertime, $summaries['late'], OvertimeCalculation::STATUS_STALE, 45);
        $cancelledOvertime = $this->createOvertimeRequest($employee, $summaries['early'], OvertimeRequest::STATUS_CANCELLED, 30, null);
        $this->createOvertimeCalculation($employee, $cancelledOvertime, $summaries['early'], OvertimeCalculation::STATUS_CALCULATED, 30);

        $snapshot = $this->service->generateSnapshot($employee, $periodStart, $periodEnd, $employee);

        $this->assertSame($employee->company_id, $snapshot->company_id);
        $this->assertSame($employee->id, $snapshot->employee_id);
        $this->assertSame($periodStart->toDateString(), $snapshot->period_start->toDateString());
        $this->assertSame($periodEnd->toDateString(), $snapshot->period_end->toDateString());
        $this->assertSame(5, $snapshot->total_work_days);
        $this->assertSame(3, $snapshot->total_present_days);
        $this->assertSame(1, $snapshot->total_absent_days);
        $this->assertSame(15, $snapshot->total_late_minutes);
        $this->assertSame(30, $snapshot->total_early_leave_minutes);
        $this->assertSame(1515, $snapshot->total_work_minutes);
        $this->assertSame(60, $snapshot->total_overtime_minutes);
        $this->assertSame('1.00', $snapshot->total_leave_days);
        $this->assertSame(1, $snapshot->total_correction_count);
        $this->assertSame(AttendancePayrollSnapshot::STATUS_CALCULATED, $snapshot->snapshot_status);
        $this->assertNotNull($snapshot->calculated_at);
        $this->assertSame(
            [$summaries['present']->id, $summaries['late']->id, $summaries['early']->id, $summaries['absent']->id, $summaries['leave']->id, $summaries['holiday']->id],
            $snapshot->metadata['attendance_summary_ids'] ?? []
        );
        $this->assertSame([$approvedCalculation->id], $snapshot->metadata['overtime_calculation_ids'] ?? []);
        $this->assertSame([$approvedLeave->id], $snapshot->metadata['leave_request_ids'] ?? []);
        $this->assertSame('v1.4.10', $snapshot->metadata['calculation_version'] ?? null);
        $this->assertSame(AttendancePayrollReadinessService::class, $snapshot->metadata['generated_by'] ?? null);
    }

    public function test_snapshot_uses_only_approved_and_calculated_overtime_records(): void
    {
        $employee = $this->newEmployeeWithPolicy();
        $date = now(config('app.timezone'))->startOfMonth()->addDays(10)->startOfDay();
        $approvedSummary = $this->createAttendanceSummary($employee, $date, [
            'status' => AttendanceSummary::STATUS_PRESENT,
            'work_minutes' => 540,
        ]);
        $approved = $this->createOvertimeRequest($employee, $approvedSummary, OvertimeRequest::STATUS_APPROVED, 90, 90);
        $this->createOvertimeCalculation($employee, $approved, $approvedSummary, OvertimeCalculation::STATUS_CALCULATED, 90);

        $statusScenarios = [
            [$date->copy()->addDay(), OvertimeRequest::STATUS_DRAFT, OvertimeCalculation::STATUS_CALCULATED],
            [$date->copy()->addDays(2), OvertimeRequest::STATUS_SUBMITTED, OvertimeCalculation::STATUS_CALCULATED],
            [$date->copy()->addDays(3), OvertimeRequest::STATUS_REJECTED, OvertimeCalculation::STATUS_CALCULATED],
            [$date->copy()->addDays(4), OvertimeRequest::STATUS_CANCELLED, OvertimeCalculation::STATUS_CALCULATED],
            [$date->copy()->addDays(5), OvertimeRequest::STATUS_APPROVED, OvertimeCalculation::STATUS_STALE],
        ];

        foreach ($statusScenarios as [$scenarioDate, $requestStatus, $calculationStatus]) {
            $summary = $this->createAttendanceSummary($employee, $scenarioDate, [
                'status' => AttendanceSummary::STATUS_PRESENT,
                'work_minutes' => 510,
            ]);
            $request = $this->createOvertimeRequest($employee, $summary, $requestStatus, 25, $requestStatus === OvertimeRequest::STATUS_APPROVED ? 25 : null);
            $this->createOvertimeCalculation($employee, $request, $summary, $calculationStatus, 25);
        }

        $snapshot = $this->service->generateSnapshot($employee, $date, $date->copy()->addDays(5), $employee);

        $this->assertSame(90, $snapshot->total_overtime_minutes);
        $this->assertCount(1, $snapshot->metadata['overtime_calculation_ids'] ?? []);
    }

    public function test_snapshot_calculation_is_idempotent_and_does_not_create_duplicates(): void
    {
        $employee = $this->newEmployeeWithPolicy();
        $date = now(config('app.timezone'))->startOfMonth()->addDays(14)->startOfDay();
        $this->createAttendanceSummary($employee, $date, [
            'status' => AttendanceSummary::STATUS_PRESENT,
            'work_minutes' => 480,
        ]);

        $first = $this->service->generateSnapshot($employee, $date, $date, $employee);
        $second = $this->service->generateSnapshot($employee, $date, $date, $employee);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, AttendancePayrollSnapshot::query()->count());
    }

    public function test_recalculation_updates_unlocked_snapshot(): void
    {
        $employee = $this->newEmployeeWithPolicy();
        $date = now(config('app.timezone'))->startOfMonth()->addDays(16)->startOfDay();
        $summary = $this->createAttendanceSummary($employee, $date, [
            'status' => AttendanceSummary::STATUS_LATE,
            'late_minutes' => 20,
            'work_minutes' => 450,
        ]);

        $snapshot = $this->service->generateSnapshot($employee, $date, $date, $employee);
        $this->assertSame(20, $snapshot->total_late_minutes);
        $this->assertSame(450, $snapshot->total_work_minutes);

        $summary->forceFill([
            'status' => AttendanceSummary::STATUS_PRESENT,
            'late_minutes' => 0,
            'work_minutes' => 480,
        ])->save();

        $recalculated = $this->service->recalculateSnapshot($snapshot, $employee);

        $this->assertSame($snapshot->id, $recalculated->id);
        $this->assertSame(0, $recalculated->total_late_minutes);
        $this->assertSame(480, $recalculated->total_work_minutes);
        $this->assertSame(AttendancePayrollSnapshot::STATUS_CALCULATED, $recalculated->snapshot_status);
    }

    public function test_locked_snapshot_cannot_be_recalculated_directly(): void
    {
        $employee = $this->newEmployeeWithPolicy();
        $date = now(config('app.timezone'))->startOfMonth()->addDays(18)->startOfDay();
        $this->createAttendanceSummary($employee, $date, [
            'status' => AttendanceSummary::STATUS_PRESENT,
            'work_minutes' => 480,
        ]);

        $snapshot = $this->service->generateSnapshot($employee, $date, $date, $employee);
        $locked = $this->service->lockSnapshot($snapshot, $employee);

        $this->assertSame(AttendancePayrollSnapshot::STATUS_LOCKED, $locked->snapshot_status);
        $this->assertNotNull($locked->locked_at);
        $this->assertSame($employee->id, $locked->locked_by);

        $this->expectException(ValidationException::class);

        $this->service->recalculateSnapshot($locked, $employee);
    }

    public function test_source_changes_can_mark_snapshot_stale(): void
    {
        $employee = $this->newEmployeeWithPolicy();
        $date = now(config('app.timezone'))->startOfMonth()->addDays(20)->startOfDay();
        $summary = $this->createAttendanceSummary($employee, $date, [
            'status' => AttendanceSummary::STATUS_PRESENT,
            'work_minutes' => 480,
        ]);

        $snapshot = $this->service->generateSnapshot($employee, $date, $date, $employee);
        $this->assertFalse($this->service->hasSourceDataChangedSinceCalculation($snapshot));

        Carbon::setTestNow($snapshot->calculated_at->copy()->addMinute());

        $summary->forceFill([
            'work_minutes' => 510,
        ])->save();

        $stale = $this->service->markSnapshotStaleIfSourceDataChanged($snapshot->fresh(), 'Attendance summary updated after snapshot calculation.');

        Carbon::setTestNow();

        $this->assertTrue($this->service->hasSourceDataChangedSinceCalculation($stale));
        $this->assertSame(AttendancePayrollSnapshot::STATUS_STALE, $stale->snapshot_status);
        $this->assertSame(
            'Attendance summary updated after snapshot calculation.',
            $stale->metadata['stale_context']['reason'] ?? null
        );
    }

    public function test_snapshot_generation_does_not_create_payroll_money_or_modify_payroll_behavior(): void
    {
        $employee = $this->newEmployeeWithPolicy();
        $date = now(config('app.timezone'))->startOfMonth()->addDays(22)->startOfDay();
        $this->createAttendanceSummary($employee, $date, [
            'status' => AttendanceSummary::STATUS_PRESENT,
            'work_minutes' => 480,
        ]);
        $beforePayrollCount = Payroll::query()->count();

        $snapshot = $this->service->generateSnapshot($employee, $date, $date, $employee);

        $this->assertSame($beforePayrollCount, Payroll::query()->count());
        $this->assertFalse(Schema::hasColumn('attendance_payroll_snapshots', 'gross_pay'));
        $this->assertFalse(Schema::hasColumn('attendance_payroll_snapshots', 'net_pay'));
        $this->assertFalse(Schema::hasColumn('attendance_payroll_snapshots', 'allowances'));
        $this->assertSame(AttendancePayrollSnapshot::STATUS_CALCULATED, $snapshot->snapshot_status);
    }

    /**
     * @return array{present: AttendanceSummary, late: AttendanceSummary, early: AttendanceSummary, absent: AttendanceSummary, leave: AttendanceSummary, holiday: AttendanceSummary}
     */
    private function createSummaryFixture(Employee $employee, Carbon $periodStart): array
    {
        return [
            'present' => $this->createAttendanceSummary($employee, $periodStart->copy(), [
                'status' => AttendanceSummary::STATUS_PRESENT,
                'work_minutes' => 540,
            ]),
            'late' => $this->createAttendanceSummary($employee, $periodStart->copy()->addDay(), [
                'status' => AttendanceSummary::STATUS_LATE,
                'late_minutes' => 15,
                'work_minutes' => 525,
            ]),
            'early' => $this->createAttendanceSummary($employee, $periodStart->copy()->addDays(2), [
                'status' => AttendanceSummary::STATUS_EARLY_OUT,
                'early_out_minutes' => 30,
                'work_minutes' => 450,
            ]),
            'absent' => $this->createAttendanceSummary($employee, $periodStart->copy()->addDays(3), [
                'status' => AttendanceSummary::STATUS_ABSENT,
                'actual_in_at' => null,
                'actual_out_at' => null,
                'is_complete' => false,
                'work_minutes' => 0,
            ]),
            'leave' => $this->createAttendanceSummary($employee, $periodStart->copy()->addDays(4), [
                'status' => AttendanceSummary::STATUS_LEAVE,
                'actual_in_at' => null,
                'actual_out_at' => null,
                'is_complete' => false,
                'work_minutes' => 0,
            ]),
            'holiday' => $this->createAttendanceSummary($employee, $periodStart->copy()->addDays(5), [
                'status' => AttendanceSummary::STATUS_HOLIDAY,
                'actual_in_at' => null,
                'actual_out_at' => null,
                'is_complete' => false,
                'work_minutes' => 0,
            ]),
        ];
    }

    private function createAttendanceSummary(Employee $employee, Carbon $date, array $overrides = []): AttendanceSummary
    {
        $scheduledStartAt = $overrides['scheduled_start_at'] ?? $date->copy()->setTime(8, 0);
        $scheduledEndAt = $overrides['scheduled_end_at'] ?? $date->copy()->setTime(17, 0);
        $actualInAt = array_key_exists('actual_in_at', $overrides) ? $overrides['actual_in_at'] : $date->copy()->setTime(8, 0);
        $actualOutAt = array_key_exists('actual_out_at', $overrides) ? $overrides['actual_out_at'] : $date->copy()->setTime(17, 0);
        $status = $overrides['status'] ?? AttendanceSummary::STATUS_PRESENT;
        $isComplete = $overrides['is_complete'] ?? ($actualInAt instanceof Carbon && $actualOutAt instanceof Carbon);
        $workMinutes = $overrides['work_minutes'] ?? (
            $actualInAt instanceof Carbon && $actualOutAt instanceof Carbon && $actualOutAt->greaterThan($actualInAt)
                ? $actualInAt->diffInMinutes($actualOutAt)
                : 0
        );

        return AttendanceSummary::query()->create(array_merge([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'attendance_date' => $date->toDateString(),
            'attendance_policy_id' => $employee->attendance_policy_id,
            'scheduled_start_at' => $scheduledStartAt,
            'scheduled_end_at' => $scheduledEndAt,
            'break_duration_minutes' => 0,
            'actual_in_at' => $actualInAt,
            'actual_out_at' => $actualOutAt,
            'work_minutes' => $workMinutes,
            'late_minutes' => 0,
            'early_out_minutes' => 0,
            'status' => $status,
            'is_complete' => $isComplete,
            'is_recalculated' => false,
            'calculated_at' => now(config('app.timezone')),
            'created_by' => $employee->id,
            'updated_by' => $employee->id,
        ], $overrides));
    }

    private function createApprovedCorrection(Employee $employee, AttendanceSummary $summary, int $lateMinutes): AttendanceCorrection
    {
        return AttendanceCorrection::query()->create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'attendance_summary_id' => $summary->id,
            'attendance_date' => $summary->attendance_date->toDateString(),
            'correction_type' => AttendanceCorrection::TYPE_MANUAL_ADJUSTMENT,
            'reason' => 'Approved attendance correction.',
            'approved_clock_in_at' => $summary->actual_in_at?->copy()?->subMinutes($lateMinutes),
            'approved_clock_out_at' => $summary->actual_out_at,
            'status' => AttendanceCorrection::STATUS_APPROVED,
            'approved_at' => now(config('app.timezone')),
            'approved_by' => $employee->id,
            'created_by' => $employee->id,
            'updated_by' => $employee->id,
        ]);
    }

    private function createApprovedLeaveRequest(Employee $employee, Carbon $date): LeaveRequest
    {
        return LeaveRequest::query()->create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'leave_type_id' => $this->leaveTypeFor($employee)->id,
            'start_date' => $date->toDateString(),
            'end_date' => $date->toDateString(),
            'is_half_day' => false,
            'requested_days' => 1,
            'reason' => 'Approved leave for readiness snapshot test.',
            'status' => LeaveRequest::STATUS_APPROVED,
            'submitted_at' => now(config('app.timezone'))->subDay(),
        ]);
    }

    private function createOvertimeRequest(
        Employee $employee,
        AttendanceSummary $summary,
        string $status,
        ?int $requestedMinutes,
        ?int $approvedMinutes,
    ): OvertimeRequest {
        return OvertimeRequest::query()->create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'attendance_summary_id' => $summary->id,
            'overtime_date' => $summary->attendance_date->toDateString(),
            'requested_minutes' => $requestedMinutes,
            'reason' => 'Overtime readiness test request.',
            'status' => $status,
            'submitted_at' => now(config('app.timezone'))->subHour(),
            'submitted_by' => $employee->id,
            'approved_minutes' => $approvedMinutes,
            'approved_by' => $status === OvertimeRequest::STATUS_APPROVED ? $employee->id : null,
            'approved_at' => $status === OvertimeRequest::STATUS_APPROVED ? now(config('app.timezone')) : null,
            'cancelled_by' => $status === OvertimeRequest::STATUS_CANCELLED ? $employee->id : null,
            'cancelled_at' => $status === OvertimeRequest::STATUS_CANCELLED ? now(config('app.timezone')) : null,
            'rejected_by' => $status === OvertimeRequest::STATUS_REJECTED ? $employee->id : null,
            'rejected_at' => $status === OvertimeRequest::STATUS_REJECTED ? now(config('app.timezone')) : null,
            'created_by' => $employee->id,
            'updated_by' => $employee->id,
        ]);
    }

    private function createOvertimeCalculation(
        Employee $employee,
        OvertimeRequest $request,
        AttendanceSummary $summary,
        string $status,
        int $calculatedMinutes,
    ): OvertimeCalculation {
        return OvertimeCalculation::query()->create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'overtime_request_id' => $request->id,
            'attendance_summary_id' => $summary->id,
            'calculation_date' => $summary->attendance_date->toDateString(),
            'scheduled_end_at' => $summary->scheduled_end_at,
            'actual_clock_out_at' => $summary->actual_out_at,
            'actual_overtime_minutes' => $calculatedMinutes,
            'requested_minutes' => $request->requested_minutes,
            'approved_minutes' => $request->approved_minutes,
            'calculated_minutes' => $calculatedMinutes,
            'calculation_status' => $status,
            'calculated_at' => now(config('app.timezone')),
        ]);
    }

    private function leaveTypeFor(Employee $employee): LeaveType
    {
        return LeaveType::query()
            ->forCompany($employee->company_id)
            ->orderBy('id')
            ->firstOrFail();
    }

    private function newEmployeeWithPolicy(array $policyOverrides = [], ?Employee $template = null): Employee
    {
        $template ??= $this->employee('andi.permanent@example.test');
        $employee = $this->makeEmployeeFrom($template, [
            'attendance_policy_id' => null,
            'attendance_location_mode_override' => null,
            'branch_id' => null,
            'department_id' => null,
            'division_id' => null,
            'position_id' => null,
            'direct_supervisor_id' => null,
        ]);
        $policy = $this->createAttendancePolicy($template, $policyOverrides);

        $employee->forceFill([
            'attendance_policy_id' => $policy->id,
        ])->save();

        return $employee->fresh();
    }

    private function createAttendancePolicy(Employee $template, array $overrides = []): AttendancePolicy
    {
        return AttendancePolicy::query()->create(array_merge([
            'company_id' => $template->company_id,
            'code' => sprintf('APR-%03d', $this->employeeSequence),
            'name' => 'Attendance Payroll Readiness Policy',
            'location_mode' => AttendancePolicy::LOCATION_MODE_FIXED,
            'gps_required' => false,
            'require_selfie' => false,
            'radius_validation_enabled' => false,
            'late_tolerance_minutes' => 0,
            'early_out_tolerance_minutes' => 0,
            'minimum_work_minutes' => 0,
            'auto_absent_after_minutes' => 120,
            'overtime_threshold_minutes' => null,
            'trusted_device_mode' => AttendancePolicy::TRUSTED_DEVICE_MODE_NONE,
            'auto_trust_first_device' => false,
            'max_trusted_devices' => null,
            'is_active' => true,
        ], $overrides));
    }

    private function employee(string $email): Employee
    {
        return Employee::query()->where('email', $email)->firstOrFail();
    }

    private function makeEmployeeFrom(Employee $template, array $overrides = []): Employee
    {
        $sequence = $this->employeeSequence++;

        return Employee::query()->create(array_merge([
            'employee_code' => sprintf('EMP-APR-%03d', $sequence),
            'full_name' => "Attendance Payroll User {$sequence}",
            'first_name' => 'Attendance',
            'last_name' => "Payroll {$sequence}",
            'email' => sprintf('attendance-payroll-%03d@example.test', $sequence),
            'company_id' => $template->company_id,
            'company_group_id' => $template->company_group_id,
            'branch_id' => $template->branch_id,
            'work_location_id' => $template->work_location_id,
            'department_id' => $template->department_id,
            'division_id' => $template->division_id,
            'position_id' => $template->position_id,
            'job_level_id' => $template->job_level_id,
            'job_grade_id' => $template->job_grade_id,
            'employment_status_id' => $template->employment_status_id,
            'employment_type_id' => $template->employment_type_id,
            'contract_type_id' => $template->contract_type_id,
            'identity_type_id' => $template->identity_type_id,
            'religion_id' => $template->religion_id,
            'marital_status_id' => $template->marital_status_id,
            'employment_type' => $template->employment_type,
            'hire_date' => now(config('app.timezone'))->toDateString(),
            'join_date' => now(config('app.timezone'))->toDateString(),
            'is_active' => true,
            'password' => 'password123',
        ], $overrides));
    }
}
