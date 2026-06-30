<?php

namespace Tests\Feature;

use App\Enums\ApprovalModuleType;
use App\Models\ApprovalWorkflow;
use App\Models\AttendanceCorrection;
use App\Models\AttendancePolicy;
use App\Models\AttendanceSummary;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\OvertimeCalculation;
use App\Models\OvertimeRequest;
use App\Models\Payroll;
use App\Services\Attendance\AttendanceCalculationService;
use App\Services\OvertimeCalculationService;
use App\Services\OvertimeRequestService;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OvertimeAttendanceReadinessV149Test extends TestCase
{
    use RefreshDatabase;

    private OvertimeRequestService $overtimeRequestService;

    private OvertimeCalculationService $overtimeCalculationService;

    private AttendanceCalculationService $attendanceCalculationService;

    private int $employeeSequence = 1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->overtimeRequestService = app(OvertimeRequestService::class);
        $this->overtimeCalculationService = app(OvertimeCalculationService::class);
        $this->attendanceCalculationService = app(AttendanceCalculationService::class);
    }

    public function test_overtime_tables_exist(): void
    {
        $this->assertTrue(Schema::hasTable('overtime_requests'));
        $this->assertTrue(Schema::hasTable('overtime_calculations'));
    }

    public function test_overtime_request_can_be_created_for_employee_and_date(): void
    {
        $employee = $this->newEmployeeWithPolicy();
        $date = $this->nextWeekday(Carbon::MONDAY);
        $summary = $this->createAttendanceSummary($employee, $date, [
            'scheduled_end_at' => $date->copy()->setTime(17, 0),
            'actual_in_at' => $date->copy()->setTime(8, 0),
            'actual_out_at' => $date->copy()->setTime(18, 0),
        ]);

        $request = $this->overtimeRequestService->createDraft($employee, [
            'overtime_date' => $date->toDateString(),
            'requested_minutes' => 60,
            'reason' => 'Project launch support.',
        ]);

        $this->assertSame($employee->id, $request->employee_id);
        $this->assertSame($summary->id, $request->attendance_summary_id);
        $this->assertSame(OvertimeRequest::STATUS_DRAFT, $request->status);
    }

    public function test_overtime_calculation_requires_attendance_summary(): void
    {
        $employee = $this->newEmployeeWithPolicy();
        $date = $this->nextWeekday(Carbon::TUESDAY);

        $request = OvertimeRequest::query()->create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'attendance_summary_id' => null,
            'overtime_date' => $date->toDateString(),
            'requested_minutes' => 60,
            'reason' => 'No summary yet.',
            'status' => OvertimeRequest::STATUS_APPROVED,
            'approved_minutes' => 60,
            'approved_at' => now(config('app.timezone')),
            'approved_by' => $employee->id,
            'created_by' => $employee->id,
            'updated_by' => $employee->id,
        ]);

        $this->expectException(ValidationException::class);

        $this->overtimeCalculationService->calculateForRequest($request);
    }

    public function test_approved_overtime_request_can_calculate_overtime_from_attendance_summary(): void
    {
        [$employee, $supervisor, $hrApprover] = $this->prepareApprovalScenario();
        $date = $this->nextWeekday(Carbon::WEDNESDAY);
        $this->createAttendanceSummary($employee, $date, [
            'scheduled_end_at' => $date->copy()->setTime(17, 0),
            'actual_in_at' => $date->copy()->setTime(8, 0),
            'actual_out_at' => $date->copy()->setTime(19, 0),
        ]);

        $submitted = $this->submitOvertimeDraft($employee, [
            'overtime_date' => $date->toDateString(),
            'requested_minutes' => 180,
            'reason' => 'Release deployment.',
        ]);

        $this->overtimeRequestService->processApproval($submitted->approvalRequest, $supervisor, 'approved', 'Supervisor approved.');
        $this->overtimeRequestService->processApproval($submitted->fresh('approvalRequest')->approvalRequest, $supervisor, 'approved', 'Department head approved.');
        $this->overtimeRequestService->processApproval($submitted->fresh('approvalRequest')->approvalRequest, $hrApprover, 'approved', 'HR approved.');

        $approved = $submitted->fresh('calculation');

        $this->assertSame(OvertimeRequest::STATUS_APPROVED, $approved->status);
        $this->assertNotNull($approved->calculation);
        $this->assertSame(120, $approved->calculation->actual_overtime_minutes);
        $this->assertSame(120, $approved->calculation->calculated_minutes);
    }

    public function test_submitted_overtime_request_is_not_treated_as_approved_or_calculated(): void
    {
        [$employee] = $this->prepareApprovalScenario();
        $date = $this->nextWeekday(Carbon::THURSDAY);
        $this->createAttendanceSummary($employee, $date, [
            'scheduled_end_at' => $date->copy()->setTime(17, 0),
            'actual_in_at' => $date->copy()->setTime(8, 0),
            'actual_out_at' => $date->copy()->setTime(18, 30),
        ]);

        $submitted = $this->submitOvertimeDraft($employee, [
            'overtime_date' => $date->toDateString(),
            'requested_minutes' => 90,
        ]);

        $this->assertSame(OvertimeRequest::STATUS_SUBMITTED, $submitted->status);
        $this->assertDatabaseCount('overtime_calculations', 0);
        $this->expectException(ValidationException::class);

        $this->overtimeCalculationService->calculateForRequest($submitted);
    }

    public function test_rejected_overtime_request_is_not_treated_as_approved_or_calculated(): void
    {
        [$employee, $supervisor] = $this->prepareApprovalScenario();
        $date = $this->nextWeekday(Carbon::FRIDAY);
        $this->createAttendanceSummary($employee, $date, [
            'scheduled_end_at' => $date->copy()->setTime(17, 0),
            'actual_in_at' => $date->copy()->setTime(8, 0),
            'actual_out_at' => $date->copy()->setTime(18, 30),
        ]);

        $submitted = $this->submitOvertimeDraft($employee, [
            'overtime_date' => $date->toDateString(),
            'requested_minutes' => 90,
        ]);

        $rejected = $this->overtimeRequestService->reject($submitted, $supervisor, 'Insufficient justification.');

        $this->assertSame(OvertimeRequest::STATUS_REJECTED, $rejected->status);
        $this->assertDatabaseCount('overtime_calculations', 0);
        $this->expectException(ValidationException::class);

        $this->overtimeCalculationService->calculateForRequest($rejected);
    }

    public function test_after_hours_overtime_is_calculated_from_scheduled_end_to_actual_clock_out(): void
    {
        $employee = $this->newEmployeeWithPolicy();
        $date = $this->nextWeekday(Carbon::MONDAY);
        $summary = $this->createAttendanceSummary($employee, $date, [
            'scheduled_end_at' => $date->copy()->setTime(17, 0),
            'actual_in_at' => $date->copy()->setTime(8, 0),
            'actual_out_at' => $date->copy()->setTime(18, 45),
        ]);
        $request = $this->approvedRequest($employee, $summary, 200);

        $calculation = $this->overtimeCalculationService->calculateForRequest($request);

        $this->assertSame(105, $calculation->actual_overtime_minutes);
        $this->assertSame(105, $calculation->calculated_minutes);
    }

    public function test_overtime_threshold_from_attendance_policy_is_respected(): void
    {
        $employee = $this->newEmployeeWithPolicy([
            'overtime_threshold_minutes' => 30,
        ]);
        $date = $this->nextWeekday(Carbon::TUESDAY);
        $summary = $this->createAttendanceSummary($employee, $date, [
            'attendance_policy_id' => $employee->attendance_policy_id,
            'scheduled_end_at' => $date->copy()->setTime(17, 0),
            'actual_in_at' => $date->copy()->setTime(8, 0),
            'actual_out_at' => $date->copy()->setTime(17, 20),
        ]);
        $request = $this->approvedRequest($employee, $summary, 60);

        $calculation = $this->overtimeCalculationService->calculateForRequest($request);

        $this->assertSame(20, $calculation->actual_overtime_minutes);
        $this->assertSame(0, $calculation->calculated_minutes);
    }

    public function test_calculated_overtime_does_not_exceed_actual_attendance_supported_overtime(): void
    {
        $employee = $this->newEmployeeWithPolicy();
        $date = $this->nextWeekday(Carbon::WEDNESDAY);
        $summary = $this->createAttendanceSummary($employee, $date, [
            'scheduled_end_at' => $date->copy()->setTime(17, 0),
            'actual_in_at' => $date->copy()->setTime(8, 0),
            'actual_out_at' => $date->copy()->setTime(18, 0),
        ]);
        $request = $this->approvedRequest($employee, $summary, 240, 240);

        $calculation = $this->overtimeCalculationService->calculateForRequest($request);

        $this->assertSame(60, $calculation->actual_overtime_minutes);
        $this->assertSame(60, $calculation->calculated_minutes);
    }

    public function test_calculated_overtime_does_not_blindly_use_requested_or_approved_minutes_when_actual_attendance_is_shorter(): void
    {
        $employee = $this->newEmployeeWithPolicy();
        $date = $this->nextWeekday(Carbon::THURSDAY);
        $summary = $this->createAttendanceSummary($employee, $date, [
            'scheduled_end_at' => $date->copy()->setTime(17, 0),
            'actual_in_at' => $date->copy()->setTime(8, 0),
            'actual_out_at' => $date->copy()->setTime(18, 15),
        ]);
        $request = $this->approvedRequest($employee, $summary, 180, 150);

        $calculation = $this->overtimeCalculationService->calculateForRequest($request);

        $this->assertSame(75, $calculation->actual_overtime_minutes);
        $this->assertSame(75, $calculation->calculated_minutes);
    }

    public function test_repeated_calculation_does_not_create_duplicate_overtime_calculation_records(): void
    {
        $employee = $this->newEmployeeWithPolicy();
        $date = $this->nextWeekday(Carbon::FRIDAY);
        $summary = $this->createAttendanceSummary($employee, $date, [
            'scheduled_end_at' => $date->copy()->setTime(17, 0),
            'actual_in_at' => $date->copy()->setTime(8, 0),
            'actual_out_at' => $date->copy()->setTime(19, 0),
        ]);
        $request = $this->approvedRequest($employee, $summary, 180, 120);

        $first = $this->overtimeCalculationService->calculateForRequest($request);
        $second = $this->overtimeCalculationService->calculateForRequest($request->fresh());

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, OvertimeCalculation::query()->count());
    }

    public function test_attendance_correction_can_support_overtime_recalculation_without_auto_approval(): void
    {
        $employee = $this->newEmployeeWithPolicy();
        $date = $this->nextWeekday(Carbon::MONDAY);
        $summary = $this->createAttendanceSummary($employee, $date, [
            'scheduled_start_at' => $date->copy()->setTime(8, 0),
            'scheduled_end_at' => $date->copy()->setTime(17, 0),
            'actual_in_at' => $date->copy()->setTime(8, 0),
            'actual_out_at' => $date->copy()->setTime(18, 0),
        ]);
        $request = $this->approvedRequest($employee, $summary, 120, 120);

        $initial = $this->overtimeCalculationService->calculateForRequest($request);
        $this->assertSame(60, $initial->calculated_minutes);

        AttendanceCorrection::query()->create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'attendance_summary_id' => $summary->id,
            'attendance_date' => $date->toDateString(),
            'correction_type' => AttendanceCorrection::TYPE_MANUAL_ADJUSTMENT,
            'reason' => 'Updated clock out after review.',
            'approved_clock_in_at' => $date->copy()->setTime(8, 0),
            'approved_clock_out_at' => $date->copy()->setTime(19, 30),
            'status' => AttendanceCorrection::STATUS_APPROVED,
            'approved_at' => now(config('app.timezone')),
            'approved_by' => $employee->id,
            'created_by' => $employee->id,
            'updated_by' => $employee->id,
        ]);

        $this->attendanceCalculationService->calculateForEmployeeDate($employee, $date);
        $recalculated = $this->overtimeCalculationService->recalculateForRequest($request->fresh());

        $this->assertSame(OvertimeRequest::STATUS_APPROVED, $request->fresh()->status);
        $this->assertSame(120, $recalculated->calculated_minutes);
    }

    public function test_absent_day_does_not_generate_overtime(): void
    {
        $employee = $this->newEmployeeWithPolicy();
        $date = $this->nextWeekday(Carbon::TUESDAY);
        $summary = $this->createAttendanceSummary($employee, $date, [
            'status' => AttendanceSummary::STATUS_ABSENT,
            'is_complete' => false,
            'scheduled_end_at' => $date->copy()->setTime(17, 0),
            'actual_in_at' => null,
            'actual_out_at' => null,
            'work_minutes' => 0,
        ]);
        $request = $this->approvedRequest($employee, $summary, 60, 60);

        $calculation = $this->overtimeCalculationService->calculateForRequest($request);

        $this->assertSame(0, $calculation->calculated_minutes);
    }

    public function test_cross_midnight_shift_handling_uses_datetime_boundaries_safely(): void
    {
        $employee = $this->newEmployeeWithPolicy();
        $date = $this->nextWeekday(Carbon::WEDNESDAY);
        $summary = $this->createAttendanceSummary($employee, $date, [
            'scheduled_start_at' => $date->copy()->setTime(22, 0),
            'scheduled_end_at' => $date->copy()->addDay()->setTime(6, 0),
            'actual_in_at' => $date->copy()->setTime(22, 0),
            'actual_out_at' => $date->copy()->addDay()->setTime(8, 0),
            'work_minutes' => 600,
        ]);
        $request = $this->approvedRequest($employee, $summary, 180, 180);

        $calculation = $this->overtimeCalculationService->calculateForRequest($request);

        $this->assertSame(120, $calculation->actual_overtime_minutes);
        $this->assertSame(120, $calculation->calculated_minutes);
    }

    public function test_payroll_behavior_remains_unchanged(): void
    {
        $employee = $this->newEmployeeWithPolicy();
        $date = $this->nextWeekday(Carbon::THURSDAY);
        $summary = $this->createAttendanceSummary($employee, $date, [
            'scheduled_end_at' => $date->copy()->setTime(17, 0),
            'actual_in_at' => $date->copy()->setTime(8, 0),
            'actual_out_at' => $date->copy()->setTime(18, 0),
        ]);
        $request = $this->approvedRequest($employee, $summary, 60, 60);
        $beforePayrollCount = Payroll::query()->count();

        $this->overtimeCalculationService->calculateForRequest($request);

        $this->assertSame($beforePayrollCount, Payroll::query()->count());
    }

    private function submitOvertimeDraft(Employee $employee, array $payload): OvertimeRequest
    {
        $draft = $this->overtimeRequestService->createDraft($employee, $payload);

        return $this->overtimeRequestService->submit($draft, $employee);
    }

    private function approvedRequest(
        Employee $employee,
        AttendanceSummary $summary,
        ?int $requestedMinutes,
        ?int $approvedMinutes = null,
    ): OvertimeRequest {
        return OvertimeRequest::query()->create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'attendance_summary_id' => $summary->id,
            'overtime_date' => $summary->attendance_date->toDateString(),
            'requested_minutes' => $requestedMinutes,
            'reason' => 'Approved test request.',
            'status' => OvertimeRequest::STATUS_APPROVED,
            'submitted_at' => now(config('app.timezone'))->subHour(),
            'submitted_by' => $employee->id,
            'approved_minutes' => $approvedMinutes ?? $requestedMinutes,
            'approved_by' => $employee->id,
            'approved_at' => now(config('app.timezone')),
            'created_by' => $employee->id,
            'updated_by' => $employee->id,
        ]);
    }

    /**
     * @return array{0: Employee, 1: Employee, 2: Employee}
     */
    private function prepareApprovalScenario(): array
    {
        $employee = $this->newEmployeeWithPolicy();
        $supervisor = $this->makeEmployeeFrom($employee, [
            'email' => sprintf('overtime-supervisor-%03d@example.test', $this->employeeSequence),
            'employee_code' => sprintf('EMP-OT-SPV-%03d', $this->employeeSequence),
            'full_name' => 'Overtime Supervisor',
            'first_name' => 'Overtime',
            'last_name' => 'Supervisor',
        ]);
        $hrApprover = $this->makeEmployeeFrom($employee, [
            'email' => sprintf('overtime-hr-%03d@example.test', $this->employeeSequence),
            'employee_code' => sprintf('EMP-OT-HR-%03d', $this->employeeSequence),
            'full_name' => 'Overtime HR',
            'first_name' => 'Overtime',
            'last_name' => 'HR',
        ]);
        $department = Department::query()->create([
            'company_id' => $employee->company_id,
            'company_group_id' => $employee->company_group_id,
            'code' => sprintf('OT-DEP-%03d', $this->employeeSequence),
            'name' => 'Overtime Department',
            'manager_id' => $supervisor->id,
        ]);

        $employee->forceFill([
            'direct_supervisor_id' => $supervisor->id,
            'department_id' => $department->id,
        ])->save();

        $supervisor->forceFill([
            'department_id' => $department->id,
        ])->save();

        $this->createOvertimeWorkflow($employee, $hrApprover);

        return [$employee->fresh(), $supervisor->fresh(), $hrApprover->fresh()];
    }

    private function createOvertimeWorkflow(Employee $employee, Employee $hrApprover): ApprovalWorkflow
    {
        ApprovalWorkflow::query()
            ->where('company_id', $employee->company_id)
            ->where('module_type', ApprovalModuleType::OVERTIME->value)
            ->delete();

        $workflow = ApprovalWorkflow::query()->create([
            'company_id' => $employee->company_id,
            'company_group_id' => $employee->company_group_id,
            'code' => 'OVERTIME-'.$this->employeeSequence,
            'name' => 'Overtime Workflow',
            'module_type' => ApprovalModuleType::OVERTIME->value,
            'is_active' => true,
        ]);

        $workflow->steps()->createMany([
            [
                'step_order' => 1,
                'name' => 'Supervisor Review',
                'approver_type' => 'direct_supervisor',
                'is_required' => true,
                'can_reject' => true,
                'can_return' => false,
                'is_final_step' => false,
            ],
            [
                'step_order' => 2,
                'name' => 'Department Review',
                'approver_type' => 'department_head',
                'is_required' => true,
                'can_reject' => true,
                'can_return' => false,
                'is_final_step' => false,
            ],
            [
                'step_order' => 3,
                'name' => 'HR Review',
                'approver_type' => 'specific_employee',
                'approver_employee_id' => $hrApprover->id,
                'is_required' => true,
                'can_reject' => true,
                'can_return' => false,
                'is_final_step' => true,
            ],
        ]);

        return $workflow->load('steps');
    }

    private function createAttendanceSummary(Employee $employee, Carbon $date, array $overrides = []): AttendanceSummary
    {
        $scheduledStartAt = $overrides['scheduled_start_at'] ?? $date->copy()->setTime(8, 0);
        $scheduledEndAt = $overrides['scheduled_end_at'] ?? $date->copy()->setTime(17, 0);
        $actualInAt = $overrides['actual_in_at'] ?? $date->copy()->setTime(8, 0);
        $actualOutAt = $overrides['actual_out_at'] ?? $date->copy()->setTime(17, 0);
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
            'code' => sprintf('OTP-%03d', $this->employeeSequence),
            'name' => 'Overtime Test Policy',
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
            'employee_code' => sprintf('EMP-OT-%03d', $sequence),
            'full_name' => "Overtime User {$sequence}",
            'first_name' => 'Overtime',
            'last_name' => "User {$sequence}",
            'email' => sprintf('overtime-%03d@example.test', $sequence),
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

    private function nextWeekday(int $dayConstant): Carbon
    {
        return now(config('app.timezone'))->next($dayConstant)->startOfDay();
    }
}
