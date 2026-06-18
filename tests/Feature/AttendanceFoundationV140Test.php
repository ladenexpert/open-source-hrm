<?php

namespace Tests\Feature;

use App\Filament\Resources\Attendance\AttendancePolicyResource;
use App\Filament\Resources\Attendance\AttendancePolicyResource\Pages\ListAttendancePolicies;
use App\Filament\Resources\Attendance\ShiftAssignmentResource;
use App\Filament\Resources\Attendance\ShiftAssignmentResource\Pages\ListShiftAssignments;
use App\Filament\Resources\Attendance\ShiftPatternResource;
use App\Filament\Resources\Attendance\ShiftPatternResource\Pages\ListShiftPatterns;
use App\Models\AttendancePolicy;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\LeaveEntitlement;
use App\Models\LeaveType;
use App\Models\ShiftAssignment;
use App\Models\ShiftPattern;
use App\Models\ShiftPatternDetail;
use App\Models\WorkLocation;
use App\Models\WorkdayPattern;
use App\Services\Attendance\AttendancePolicyResolverService;
use App\Services\Attendance\ShiftResolverService;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class AttendanceFoundationV140Test extends TestCase
{
    use RefreshDatabase;

    private AttendancePolicyResolverService $attendancePolicyResolverService;

    private ShiftResolverService $shiftResolverService;

    private int $employeeSequence = 1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->attendancePolicyResolverService = app(AttendancePolicyResolverService::class);
        $this->shiftResolverService = app(ShiftResolverService::class);
    }

    public function test_employee_level_shift_assignment_takes_priority(): void
    {
        $template = $this->employee('andi.permanent@example.test');
        $employee = $this->makeEmployeeFrom($template);
        $date = $this->nextWeekday(Carbon::MONDAY);
        $officePattern = $this->shiftPattern($employee->company_id, 'OFFICE-DAY');
        $nightPattern = $this->shiftPattern($employee->company_id, 'NIGHT-OPS');

        ShiftAssignment::query()->create([
            'company_id' => $employee->company_id,
            'assignable_type' => ShiftAssignment::ASSIGNABLE_TYPE_DEPARTMENT,
            'assignable_id' => $employee->department_id,
            'shift_pattern_id' => $officePattern->id,
            'effective_date' => $date->copy()->startOfMonth()->toDateString(),
            'end_date' => null,
        ]);

        ShiftAssignment::query()->create([
            'company_id' => $employee->company_id,
            'assignable_type' => ShiftAssignment::ASSIGNABLE_TYPE_EMPLOYEE,
            'assignable_id' => $employee->id,
            'shift_pattern_id' => $nightPattern->id,
            'effective_date' => $date->copy()->startOfMonth()->toDateString(),
            'end_date' => null,
        ]);

        $resolved = $this->shiftResolverService->resolveShift($employee, $date);

        $this->assertInstanceOf(ShiftPattern::class, $resolved);
        $this->assertSame($nightPattern->id, $resolved->id);
    }

    public function test_department_assignment_used_when_no_employee_assignment(): void
    {
        $template = $this->employee('maya.contract@example.test');
        $employee = $this->makeEmployeeFrom($template);
        $date = $this->nextWeekday(Carbon::TUESDAY);
        $officePattern = $this->shiftPattern($employee->company_id, 'OFFICE-DAY');

        ShiftAssignment::query()->create([
            'company_id' => $employee->company_id,
            'assignable_type' => ShiftAssignment::ASSIGNABLE_TYPE_DEPARTMENT,
            'assignable_id' => $employee->department_id,
            'shift_pattern_id' => $officePattern->id,
            'effective_date' => $date->copy()->startOfMonth()->toDateString(),
            'end_date' => null,
        ]);

        $resolved = $this->shiftResolverService->resolveShift($employee, $date);

        $this->assertInstanceOf(ShiftPattern::class, $resolved);
        $this->assertSame($officePattern->id, $resolved->id);
    }

    public function test_branch_assignment_used_when_no_employee_or_department_assignment(): void
    {
        $template = $this->employee('andi.permanent@example.test');
        $employee = $this->makeEmployeeFrom($template, [
            'department_id' => null,
            'division_id' => null,
            'position_id' => null,
            'direct_supervisor_id' => null,
        ]);
        $date = $this->nextWeekday(Carbon::WEDNESDAY);
        $officePattern = $this->shiftPattern($employee->company_id, 'OFFICE-DAY');

        ShiftAssignment::query()->create([
            'company_id' => $employee->company_id,
            'assignable_type' => ShiftAssignment::ASSIGNABLE_TYPE_BRANCH,
            'assignable_id' => $employee->branch_id,
            'shift_pattern_id' => $officePattern->id,
            'effective_date' => $date->copy()->startOfMonth()->toDateString(),
            'end_date' => null,
        ]);

        $resolved = $this->shiftResolverService->resolveShift($employee, $date);

        $this->assertInstanceOf(ShiftPattern::class, $resolved);
        $this->assertSame($officePattern->id, $resolved->id);
    }

    public function test_company_default_used_as_final_fallback(): void
    {
        $template = $this->employee('andi.permanent@example.test');
        $employee = $this->makeEmployeeFrom($template, [
            'branch_id' => null,
            'department_id' => null,
            'division_id' => null,
            'position_id' => null,
            'direct_supervisor_id' => null,
        ]);
        $date = $this->nextWeekday(Carbon::THURSDAY);

        $resolved = $this->shiftResolverService->resolveShift($employee, $date);

        $this->assertInstanceOf(ShiftPattern::class, $resolved);
        $this->assertSame(
            $employee->company->default_shift_pattern_id,
            $resolved->id,
        );
    }

    public function test_resolve_shift_returns_null_when_no_assignment_at_any_level(): void
    {
        $company = $this->company(Company::DEFAULT_CODE);
        $company->forceFill([
            'default_shift_pattern_id' => null,
        ])->save();

        $template = $this->employee('andi.permanent@example.test');
        $employee = $this->makeEmployeeFrom($template, [
            'branch_id' => null,
            'department_id' => null,
            'division_id' => null,
            'position_id' => null,
            'direct_supervisor_id' => null,
        ]);

        $resolved = $this->shiftResolverService->resolveShift($employee, $this->nextWeekday(Carbon::FRIDAY));

        $this->assertNull($resolved);
    }

    public function test_employee_schedule_exception_overrides_shift_assignment(): void
    {
        $template = $this->employee('andi.permanent@example.test');
        $employee = $this->makeEmployeeFrom($template);
        $date = $this->nextWeekday(Carbon::TUESDAY);
        $officePattern = $this->shiftPattern($employee->company_id, 'OFFICE-DAY');
        $nightPattern = $this->shiftPattern($employee->company_id, 'NIGHT-OPS');

        ShiftAssignment::query()->create([
            'company_id' => $employee->company_id,
            'assignable_type' => ShiftAssignment::ASSIGNABLE_TYPE_EMPLOYEE,
            'assignable_id' => $employee->id,
            'shift_pattern_id' => $nightPattern->id,
            'effective_date' => $date->copy()->startOfMonth()->toDateString(),
            'end_date' => null,
        ]);

        EmployeeSchedule::query()->create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'schedule_date' => $date->toDateString(),
            'shift_pattern_id' => $officePattern->id,
            'override_reason' => EmployeeSchedule::OVERRIDE_REASON_HR_OVERRIDE,
        ]);

        $resolved = $this->shiftResolverService->resolveShift($employee, $date);

        $this->assertInstanceOf(ShiftPattern::class, $resolved);
        $this->assertSame($officePattern->id, $resolved->id);
    }

    public function test_employee_schedule_day_off_override_returns_null(): void
    {
        $template = $this->employee('andi.permanent@example.test');
        $employee = $this->makeEmployeeFrom($template);
        $date = $this->nextWeekday(Carbon::TUESDAY);
        $nightPattern = $this->shiftPattern($employee->company_id, 'NIGHT-OPS');

        ShiftAssignment::query()->create([
            'company_id' => $employee->company_id,
            'assignable_type' => ShiftAssignment::ASSIGNABLE_TYPE_EMPLOYEE,
            'assignable_id' => $employee->id,
            'shift_pattern_id' => $nightPattern->id,
            'effective_date' => $date->copy()->startOfMonth()->toDateString(),
            'end_date' => null,
        ]);

        EmployeeSchedule::query()->create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'schedule_date' => $date->toDateString(),
            'shift_pattern_id' => null,
            'override_reason' => EmployeeSchedule::OVERRIDE_REASON_PERSONAL,
        ]);

        $this->assertNull($this->shiftResolverService->resolveShift($employee, $date));
    }

    public function test_shift_resolution_is_company_scoped(): void
    {
        $companyAEmployee = $this->makeEmployeeFrom($this->employee('andi.permanent@example.test'), [
            'branch_id' => null,
            'department_id' => null,
            'division_id' => null,
            'position_id' => null,
            'direct_supervisor_id' => null,
        ]);
        $companyB = $this->company('SUB-A');
        $companyBPattern = $this->shiftPattern($companyB->id, 'NIGHT-OPS');
        $date = $this->nextWeekday(Carbon::MONDAY);

        DB::table('shift_assignments')->insert([
            'company_id' => $companyB->id,
            'assignable_type' => ShiftAssignment::ASSIGNABLE_TYPE_EMPLOYEE,
            'assignable_id' => $companyAEmployee->id,
            'shift_pattern_id' => $companyBPattern->id,
            'effective_date' => $date->copy()->startOfMonth()->toDateString(),
            'end_date' => null,
            'work_location_id' => null,
            'assigned_by' => null,
            'notes' => 'Cross-company leak attempt.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $resolved = $this->shiftResolverService->resolveShift($companyAEmployee, $date);

        $this->assertInstanceOf(ShiftPattern::class, $resolved);
        $this->assertNotSame($companyBPattern->id, $resolved->id);
        $this->assertSame($companyAEmployee->company->default_shift_pattern_id, $resolved->id);
    }

    public function test_overnight_shift_detail_is_detected(): void
    {
        $detail = new ShiftPatternDetail([
            'day_of_week' => 1,
            'is_working_day' => true,
            'start_time' => '22:00',
            'end_time' => '06:00',
            'break_duration_minutes' => 60,
        ]);

        $this->assertTrue($detail->isOvernight());
        $this->assertSame(420, $detail->workDurationMinutes());
    }

    public function test_regular_shift_detail_is_not_overnight(): void
    {
        $detail = new ShiftPatternDetail([
            'day_of_week' => 1,
            'is_working_day' => true,
            'start_time' => '08:00',
            'end_time' => '17:00',
            'break_duration_minutes' => 60,
        ]);

        $this->assertFalse($detail->isOvernight());
        $this->assertSame(480, $detail->workDurationMinutes());
    }

    public function test_non_working_day_detail_has_no_scheduled_hours(): void
    {
        $detail = new ShiftPatternDetail([
            'day_of_week' => 6,
            'is_working_day' => false,
            'start_time' => null,
            'end_time' => null,
            'break_duration_minutes' => 0,
        ]);

        $this->assertFalse($detail->isOvernight());
        $this->assertSame(0, $detail->workDurationMinutes());
    }

    public function test_employee_direct_policy_is_returned(): void
    {
        $employee = Employee::query()
            ->whereNotNull('attendance_policy_id')
            ->firstOrFail();

        $resolved = $this->attendancePolicyResolverService->resolvePolicy($employee);

        $this->assertInstanceOf(AttendancePolicy::class, $resolved);
        $this->assertSame($employee->attendance_policy_id, $resolved->id);
    }

    public function test_company_default_policy_used_when_employee_has_none(): void
    {
        $employee = $this->makeEmployeeFrom($this->employee('maya.contract@example.test'), [
            'attendance_policy_id' => null,
        ]);

        $resolved = $this->attendancePolicyResolverService->resolvePolicy($employee);

        $this->assertInstanceOf(AttendancePolicy::class, $resolved);
        $this->assertSame($employee->company->default_attendance_policy_id, $resolved->id);
    }

    public function test_null_returned_when_no_policy_at_any_level(): void
    {
        $company = $this->company(Company::DEFAULT_CODE);
        $company->forceFill([
            'default_attendance_policy_id' => null,
        ])->save();

        $employee = $this->makeEmployeeFrom($this->employee('andi.permanent@example.test'), [
            'attendance_policy_id' => null,
        ]);

        $this->assertNull($this->attendancePolicyResolverService->resolvePolicy($employee));
    }

    public function test_location_mode_resolution_employee_override_wins(): void
    {
        $employee = $this->makeEmployeeFrom($this->employee('andi.permanent@example.test'), [
            'attendance_location_mode_override' => AttendancePolicy::LOCATION_MODE_FLEXIBLE,
            'attendance_policy_id' => $this->attendancePolicyByCode(Company::DEFAULT_CODE, 'OFFICE')->id,
        ]);

        $resolved = $this->attendancePolicyResolverService->resolveLocationMode($employee);

        $this->assertSame(AttendancePolicy::LOCATION_MODE_FLEXIBLE, $resolved);
    }

    public function test_location_mode_defaults_to_fixed_when_no_config(): void
    {
        $company = $this->company(Company::DEFAULT_CODE);
        $company->forceFill([
            'default_attendance_policy_id' => null,
        ])->save();

        $employee = $this->makeEmployeeFrom($this->employee('andi.permanent@example.test'), [
            'attendance_policy_id' => null,
            'attendance_location_mode_override' => null,
        ]);

        $resolved = $this->attendancePolicyResolverService->resolveLocationMode($employee);

        $this->assertSame(AttendancePolicy::LOCATION_MODE_FIXED, $resolved);
    }

    public function test_overlapping_shift_assignment_is_rejected(): void
    {
        $employee = $this->makeEmployeeFrom($this->employee('andi.permanent@example.test'));
        $pattern = $this->shiftPattern($employee->company_id, 'OFFICE-DAY');

        ShiftAssignment::query()->create([
            'company_id' => $employee->company_id,
            'assignable_type' => ShiftAssignment::ASSIGNABLE_TYPE_EMPLOYEE,
            'assignable_id' => $employee->id,
            'shift_pattern_id' => $pattern->id,
            'effective_date' => '2026-06-01',
            'end_date' => '2026-06-30',
        ]);

        $this->expectException(ValidationException::class);

        ShiftAssignment::query()->create([
            'company_id' => $employee->company_id,
            'assignable_type' => ShiftAssignment::ASSIGNABLE_TYPE_EMPLOYEE,
            'assignable_id' => $employee->id,
            'shift_pattern_id' => $pattern->id,
            'effective_date' => '2026-06-15',
            'end_date' => '2026-07-15',
        ]);
    }

    public function test_non_overlapping_assignments_are_allowed(): void
    {
        $employee = $this->makeEmployeeFrom($this->employee('andi.permanent@example.test'));
        $pattern = $this->shiftPattern($employee->company_id, 'OFFICE-DAY');

        ShiftAssignment::query()->create([
            'company_id' => $employee->company_id,
            'assignable_type' => ShiftAssignment::ASSIGNABLE_TYPE_EMPLOYEE,
            'assignable_id' => $employee->id,
            'shift_pattern_id' => $pattern->id,
            'effective_date' => '2026-06-01',
            'end_date' => '2026-06-30',
        ]);

        ShiftAssignment::query()->create([
            'company_id' => $employee->company_id,
            'assignable_type' => ShiftAssignment::ASSIGNABLE_TYPE_EMPLOYEE,
            'assignable_id' => $employee->id,
            'shift_pattern_id' => $pattern->id,
            'effective_date' => '2026-07-01',
            'end_date' => '2026-07-31',
        ]);

        $this->assertSame(2, ShiftAssignment::query()->where('assignable_id', $employee->id)->count());
    }

    public function test_open_ended_assignment_blocks_new_assignment(): void
    {
        $employee = $this->makeEmployeeFrom($this->employee('andi.permanent@example.test'));
        $pattern = $this->shiftPattern($employee->company_id, 'OFFICE-DAY');

        ShiftAssignment::query()->create([
            'company_id' => $employee->company_id,
            'assignable_type' => ShiftAssignment::ASSIGNABLE_TYPE_EMPLOYEE,
            'assignable_id' => $employee->id,
            'shift_pattern_id' => $pattern->id,
            'effective_date' => '2026-06-01',
            'end_date' => null,
        ]);

        $this->expectException(ValidationException::class);

        ShiftAssignment::query()->create([
            'company_id' => $employee->company_id,
            'assignable_type' => ShiftAssignment::ASSIGNABLE_TYPE_EMPLOYEE,
            'assignable_id' => $employee->id,
            'shift_pattern_id' => $pattern->id,
            'effective_date' => '2026-07-01',
            'end_date' => '2026-07-31',
        ]);
    }

    public function test_work_locations_now_has_gps_columns(): void
    {
        $this->assertTrue(Schema::hasColumn('work_locations', 'latitude'));
        $this->assertTrue(Schema::hasColumn('work_locations', 'longitude'));
        $this->assertTrue(Schema::hasColumn('work_locations', 'radius_meters'));
    }

    public function test_employees_has_attendance_policy_column(): void
    {
        $this->assertTrue(Schema::hasColumn('employees', 'attendance_policy_id'));
    }

    public function test_companies_has_default_attendance_policy_column(): void
    {
        $this->assertTrue(Schema::hasColumn('companies', 'default_attendance_policy_id'));
    }

    public function test_attendance_policy_resource_accessible_by_authorized_admin(): void
    {
        $this->actingAs($this->employee('admin@hrms.local'))
            ->get(AttendancePolicyResource::getUrl(isAbsolute: false, panel: 'admin'))
            ->assertOk();
    }

    public function test_shift_pattern_resource_accessible_by_authorized_admin(): void
    {
        $this->actingAs($this->employee('admin@hrms.local'))
            ->get(ShiftPatternResource::getUrl(isAbsolute: false, panel: 'admin'))
            ->assertOk();
    }

    public function test_shift_assignment_resource_accessible_by_authorized_admin(): void
    {
        $this->actingAs($this->employee('admin@hrms.local'))
            ->get(ShiftAssignmentResource::getUrl(isAbsolute: false, panel: 'admin'))
            ->assertOk();
    }

    public function test_resources_are_company_scoped_for_company_admin(): void
    {
        Filament::setCurrentPanel('admin');

        $companyAdmin = $this->makeCompanyAdmin($this->employee('andi.permanent@example.test'));
        $companyA = $this->company(Company::DEFAULT_CODE);
        $companyB = $this->company('SUB-A');

        $companyAPolicy = AttendancePolicy::query()->where('company_id', $companyA->id)->firstOrFail();
        $companyBPolicy = AttendancePolicy::query()->where('company_id', $companyB->id)->firstOrFail();
        $companyAPattern = ShiftPattern::query()->where('company_id', $companyA->id)->firstOrFail();
        $companyBPattern = ShiftPattern::query()->where('company_id', $companyB->id)->firstOrFail();
        $companyAAssignment = ShiftAssignment::query()->where('company_id', $companyA->id)->firstOrFail();
        $companyBAssignment = ShiftAssignment::query()->where('company_id', $companyB->id)->firstOrFail();

        Livewire::actingAs($companyAdmin)
            ->test(ListAttendancePolicies::class)
            ->assertCanSeeTableRecords([$companyAPolicy])
            ->assertCanNotSeeTableRecords([$companyBPolicy]);

        Livewire::actingAs($companyAdmin)
            ->test(ListShiftPatterns::class)
            ->assertCanSeeTableRecords([$companyAPattern])
            ->assertCanNotSeeTableRecords([$companyBPattern]);

        Livewire::actingAs($companyAdmin)
            ->test(ListShiftAssignments::class)
            ->assertCanSeeTableRecords([$companyAAssignment])
            ->assertCanNotSeeTableRecords([$companyBAssignment]);
    }

    public function test_existing_leave_tests_unaffected_by_attendance_foundation(): void
    {
        $company = $this->company(Company::DEFAULT_CODE);

        $this->assertTrue(LeaveType::query()->where('company_id', $company->id)->where('code', 'ANNUAL')->exists());
        $this->assertTrue(WorkdayPattern::query()->where('company_id', $company->id)->exists());
        $this->assertTrue(LeaveEntitlement::query()->where('company_id', $company->id)->exists());
    }

    private function company(string $code): Company
    {
        return Company::query()->where('code', $code)->firstOrFail();
    }

    private function employee(string $email): Employee
    {
        return Employee::query()->where('email', $email)->firstOrFail();
    }

    private function shiftPattern(int $companyId, string $code): ShiftPattern
    {
        return ShiftPattern::query()
            ->where('company_id', $companyId)
            ->where('code', $code)
            ->firstOrFail();
    }

    private function attendancePolicyByCode(string $companyCode, string $policyCode): AttendancePolicy
    {
        $company = $this->company($companyCode);

        return AttendancePolicy::query()
            ->where('company_id', $company->id)
            ->where('code', $policyCode)
            ->firstOrFail();
    }

    private function makeEmployeeFrom(Employee $template, array $overrides = []): Employee
    {
        $sequence = $this->employeeSequence++;

        return Employee::query()->create(array_merge([
            'employee_code' => sprintf('EMP-AT-%03d', $sequence),
            'full_name' => "Attendance User {$sequence}",
            'first_name' => 'Attendance',
            'last_name' => "User {$sequence}",
            'email' => sprintf('attendance-%03d@example.test', $sequence),
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
            'hire_date' => now()->toDateString(),
            'join_date' => now()->toDateString(),
            'is_active' => true,
            'password' => 'password123',
        ], $overrides));
    }

    private function makeCompanyAdmin(Employee $template): Employee
    {
        $employee = $this->makeEmployeeFrom($template, [
            'email' => 'attendance-company-admin@example.test',
            'employee_code' => 'EMP-AT-ADMIN',
            'full_name' => 'Attendance Company Admin',
            'first_name' => 'Attendance',
            'last_name' => 'Admin',
        ]);

        $employee->assignRole('company_admin');

        return $employee;
    }

    private function nextWeekday(int $dayConstant): Carbon
    {
        return now('Asia/Jakarta')->next($dayConstant)->startOfDay();
    }
}
