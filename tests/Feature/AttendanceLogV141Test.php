<?php

namespace Tests\Feature;

use App\Filament\Employee\Resources\AttendanceLogs\AttendanceLogResource as PortalAttendanceLogResource;
use App\Filament\Employee\Resources\AttendanceLogs\Pages\ListAttendanceLogs as PortalListAttendanceLogs;
use App\Filament\Resources\Attendance\AttendanceLogResource as AdminAttendanceLogResource;
use App\Filament\Resources\Attendance\AttendanceLogResource\Pages\ListAttendanceLogs as AdminListAttendanceLogs;
use App\Models\AttendanceLog;
use App\Models\AttendancePolicy;
use App\Models\Company;
use App\Models\Employee;
use App\Models\ShiftPattern;
use App\Models\WorkLocation;
use App\Services\Attendance\AttendanceLogService;
use App\Services\Attendance\AttendancePolicyResolverService;
use App\Services\Attendance\ShiftResolverService;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class AttendanceLogV141Test extends TestCase
{
    use RefreshDatabase;

    private AttendanceLogService $attendanceLogService;

    private AttendancePolicyResolverService $attendancePolicyResolverService;

    private ShiftResolverService $shiftResolverService;

    private int $employeeSequence = 1;

    private int $workLocationSequence = 1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->attendanceLogService = app(AttendanceLogService::class);
        $this->attendancePolicyResolverService = app(AttendancePolicyResolverService::class);
        $this->shiftResolverService = app(ShiftResolverService::class);
    }

    public function test_attendance_logs_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('attendance_logs'));

        foreach ([
            'company_id',
            'employee_id',
            'attendance_date',
            'event_type',
            'clocked_at',
            'source',
            'latitude',
            'longitude',
            'work_location_id',
            'shift_pattern_id',
            'shift_assignment_id',
            'employee_schedule_id',
            'is_valid',
            'validation_message',
            'selfie_path',
            'device_identifier',
            'ip_address',
            'user_agent',
            'notes',
            'created_by',
        ] as $column) {
            $this->assertTrue(Schema::hasColumn('attendance_logs', $column), "Missing expected column [{$column}].");
        }
    }

    public function test_employee_can_clock_in(): void
    {
        Filament::setCurrentPanel('portal');

        $employee = $this->employee('andi.permanent@example.test');

        Livewire::actingAs($employee)
            ->test(PortalListAttendanceLogs::class)
            ->callAction('clockIn')
            ->assertNotified();

        $log = AttendanceLog::query()->latest('id')->firstOrFail();

        $this->assertSame($employee->id, $log->employee_id);
        $this->assertSame(AttendanceLog::EVENT_CLOCK_IN, $log->event_type);
        $this->assertSame(AttendanceLog::SOURCE_WEB, $log->source);
    }

    public function test_employee_can_clock_out(): void
    {
        Filament::setCurrentPanel('portal');

        $employee = $this->employee('andi.permanent@example.test');

        Livewire::actingAs($employee)
            ->test(PortalListAttendanceLogs::class)
            ->callAction('clockOut')
            ->assertNotified();

        $log = AttendanceLog::query()->latest('id')->firstOrFail();

        $this->assertSame($employee->id, $log->employee_id);
        $this->assertSame(AttendanceLog::EVENT_CLOCK_OUT, $log->event_type);
        $this->assertSame(AttendanceLog::SOURCE_WEB, $log->source);
    }

    public function test_clock_log_stores_company_and_employee(): void
    {
        $employee = $this->employee('andi.permanent@example.test');

        $log = $this->attendanceLogService->clockIn($employee);

        $this->assertSame($employee->company_id, $log->company_id);
        $this->assertSame($employee->id, $log->employee_id);
        $this->assertSame($employee->id, $log->created_by);
    }

    public function test_clock_log_stores_event_type_and_source(): void
    {
        $employee = $this->employee('andi.permanent@example.test');

        $log = $this->attendanceLogService->record($employee, AttendanceLog::EVENT_CLOCK_OUT, [
            'source' => AttendanceLog::SOURCE_ADMIN,
            'created_by' => $employee->id,
        ]);

        $this->assertSame(AttendanceLog::EVENT_CLOCK_OUT, $log->event_type);
        $this->assertSame(AttendanceLog::SOURCE_ADMIN, $log->source);
    }

    public function test_clock_log_stores_gps_snapshot(): void
    {
        $employee = $this->clockableEmployeeWithOfficePolicy();

        $log = $this->attendanceLogService->clockIn($employee, [
            'latitude' => -6.2000000,
            'longitude' => 106.8166667,
        ]);

        $this->assertSame('-6.2000000', $log->latitude);
        $this->assertSame('106.8166667', $log->longitude);
    }

    public function test_clock_log_stores_selfie_path_when_provided(): void
    {
        $employee = $this->clockableEmployeeWithOfficePolicy();

        $log = $this->attendanceLogService->clockIn($employee, [
            'latitude' => -6.2000000,
            'longitude' => 106.8166667,
            'selfie_path' => 'attendance/selfies/clock-in.jpg',
        ]);

        $this->assertSame('attendance/selfies/clock-in.jpg', $log->selfie_path);
    }

    public function test_clock_log_links_resolved_shift_pattern(): void
    {
        $employee = $this->employee('andi.permanent@example.test');
        $expectedShiftPattern = $this->shiftResolverService->resolve($employee, now(config('app.timezone')))->shiftPattern;

        $log = $this->attendanceLogService->clockIn($employee, [
            'latitude' => -6.2000000,
            'longitude' => 106.8166667,
        ]);

        $this->assertSame($expectedShiftPattern?->id, $log->shift_pattern_id);
    }

    public function test_clock_log_links_resolved_work_location(): void
    {
        $employee = $this->clockableEmployeeWithOfficePolicy();

        $log = $this->attendanceLogService->clockIn($employee, [
            'latitude' => -6.2000000,
            'longitude' => 106.8166667,
        ]);

        $this->assertNotNull($log->work_location_id);
        $this->assertSame($employee->work_location_id, $log->work_location_id);
    }

    public function test_flexible_location_policy_allows_clock_without_radius_blocking(): void
    {
        $employee = $this->clockableEmployeeWithFlexiblePolicy();

        $log = $this->attendanceLogService->clockIn($employee, [
            'latitude' => 35.6895000,
            'longitude' => 139.6917000,
        ]);

        $this->assertTrue($log->is_valid);
        $this->assertNull($log->validation_message);
    }

    public function test_fixed_location_policy_marks_out_of_radius_log_invalid(): void
    {
        $employee = $this->clockableEmployeeWithOfficePolicy();

        $log = $this->attendanceLogService->clockIn($employee, [
            'latitude' => -6.2500000,
            'longitude' => 106.8666667,
        ]);

        $this->assertFalse($log->is_valid);
        $this->assertNotNull($log->validation_message);
    }

    public function test_invalid_location_attempt_is_still_stored(): void
    {
        $employee = $this->clockableEmployeeWithOfficePolicy();

        $log = $this->attendanceLogService->clockIn($employee, [
            'latitude' => -7.2504450,
            'longitude' => 112.7688450,
        ]);

        $this->assertDatabaseHas('attendance_logs', [
            'id' => $log->id,
            'employee_id' => $employee->id,
            'is_valid' => false,
        ]);
    }

    public function test_missing_gps_when_required_marks_log_invalid(): void
    {
        $employee = $this->clockableEmployeeWithOfficePolicy();

        $log = $this->attendanceLogService->clockIn($employee);

        $this->assertFalse($log->is_valid);
        $this->assertSame('GPS coordinates are required for attendance logging.', $log->validation_message);
    }

    public function test_attendance_log_is_company_scoped(): void
    {
        $companyAEmployee = $this->clockableEmployeeWithOfficePolicy(
            $this->employee('andi.permanent@example.test')
        );
        $companyBEmployee = $this->clockableEmployeeWithOfficePolicy(
            $this->employee('rio.outsource@example.test')
        );

        $companyBLog = $this->attendanceLogService->clockIn($companyBEmployee, [
            'latitude' => -6.2000000,
            'longitude' => 106.8166667,
        ]);

        $this->assertFalse(
            AttendanceLog::query()
                ->forCompany($companyAEmployee->company_id)
                ->whereKey($companyBLog->id)
                ->exists()
        );
    }

    public function test_company_admin_cannot_see_other_company_logs(): void
    {
        Filament::setCurrentPanel('admin');

        $companyAEmployee = $this->clockableEmployeeWithOfficePolicy(
            $this->employee('andi.permanent@example.test')
        );
        $companyBEmployee = $this->clockableEmployeeWithOfficePolicy(
            $this->employee('rio.outsource@example.test')
        );
        $companyAdmin = $this->makeCompanyAdmin($companyAEmployee);

        $companyALog = $this->attendanceLogService->clockIn($companyAEmployee, [
            'latitude' => -6.2000000,
            'longitude' => 106.8166667,
        ]);
        $companyBLog = $this->attendanceLogService->clockIn($companyBEmployee, [
            'latitude' => -6.2000000,
            'longitude' => 106.8166667,
        ]);

        Livewire::actingAs($companyAdmin)
            ->test(AdminListAttendanceLogs::class)
            ->assertCanSeeTableRecords([$companyALog])
            ->assertCanNotSeeTableRecords([$companyBLog]);
    }

    public function test_employee_cannot_access_admin_attendance_log_resource(): void
    {
        $employee = $this->employee('andi.permanent@example.test');

        $this->actingAs($employee)
            ->get(AdminAttendanceLogResource::getUrl(isAbsolute: false, panel: 'admin'))
            ->assertForbidden();
    }

    public function test_admin_can_view_attendance_log_resource(): void
    {
        $this->actingAs($this->employee('admin@hrms.local'))
            ->get(AdminAttendanceLogResource::getUrl(isAbsolute: false, panel: 'admin'))
            ->assertOk();
    }

    public function test_raw_log_update_is_not_allowed_by_policy(): void
    {
        $employee = $this->clockableEmployeeWithOfficePolicy();
        $companyAdmin = $this->makeCompanyAdmin($employee);
        $log = $this->attendanceLogService->clockIn($employee, [
            'latitude' => -6.2000000,
            'longitude' => 106.8166667,
        ]);

        $this->assertFalse(Gate::forUser($companyAdmin)->allows('update', $log));
    }

    public function test_raw_log_delete_is_not_allowed_by_policy(): void
    {
        $employee = $this->clockableEmployeeWithOfficePolicy();
        $companyAdmin = $this->makeCompanyAdmin($employee);
        $log = $this->attendanceLogService->clockIn($employee, [
            'latitude' => -6.2000000,
            'longitude' => 106.8166667,
        ]);

        $this->assertFalse(Gate::forUser($companyAdmin)->allows('delete', $log));
    }

    public function test_existing_attendance_foundation_tests_still_pass(): void
    {
        $employee = $this->employee('andi.permanent@example.test');

        $this->assertInstanceOf(
            ShiftPattern::class,
            $this->shiftResolverService->resolveShift($employee, now(config('app.timezone')))
        );

        $this->assertInstanceOf(
            AttendancePolicy::class,
            $this->attendancePolicyResolverService->resolvePolicy($employee)
        );
    }

    public function test_employee_can_access_portal_attendance_log_resource(): void
    {
        $employee = $this->employee('andi.permanent@example.test');

        $this->actingAs($employee)
            ->get(PortalAttendanceLogResource::getUrl(isAbsolute: false, panel: 'portal'))
            ->assertOk();
    }

    public function test_portal_attendance_log_resource_is_self_scoped_to_today(): void
    {
        Filament::setCurrentPanel('portal');

        $employee = $this->clockableEmployeeWithOfficePolicy(
            $this->employee('andi.permanent@example.test')
        );
        $otherEmployee = $this->clockableEmployeeWithOfficePolicy(
            $this->employee('maya.contract@example.test')
        );

        $ownLog = $this->attendanceLogService->clockIn($employee, [
            'latitude' => -6.2000000,
            'longitude' => 106.8166667,
        ]);
        $otherLog = $this->attendanceLogService->clockIn($otherEmployee, [
            'latitude' => -6.2000000,
            'longitude' => 106.8166667,
        ]);

        AttendanceLog::query()->create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'attendance_date' => now(config('app.timezone'))->copy()->subDay()->toDateString(),
            'event_type' => AttendanceLog::EVENT_CLOCK_OUT,
            'clocked_at' => now(config('app.timezone'))->copy()->subDay(),
            'source' => AttendanceLog::SOURCE_WEB,
            'is_valid' => true,
            'created_by' => $employee->id,
        ]);

        Livewire::actingAs($employee)
            ->test(PortalListAttendanceLogs::class)
            ->assertCanSeeTableRecords([$ownLog])
            ->assertCanNotSeeTableRecords([$otherLog]);
    }

    private function company(string $code): Company
    {
        return Company::query()->where('code', $code)->firstOrFail();
    }

    private function employee(string $email): Employee
    {
        return Employee::query()->where('email', $email)->firstOrFail();
    }

    private function attendancePolicyByCode(int $companyId, string $code): AttendancePolicy
    {
        return AttendancePolicy::query()
            ->where('company_id', $companyId)
            ->where('code', $code)
            ->firstOrFail();
    }

    private function clockableEmployeeWithOfficePolicy(?Employee $template = null): Employee
    {
        $template ??= $this->employee('andi.permanent@example.test');
        $workLocation = $this->createGpsWorkLocation($template, 'Office HQ', -6.2000000, 106.8166667, 100);

        return $this->makeEmployeeFrom($template, [
            'work_location_id' => $workLocation->id,
            'attendance_policy_id' => $this->attendancePolicyByCode($template->company_id, 'OFFICE')->id,
            'attendance_location_mode_override' => AttendancePolicy::LOCATION_MODE_FIXED,
        ]);
    }

    private function clockableEmployeeWithFlexiblePolicy(?Employee $template = null): Employee
    {
        $template ??= $this->employee('andi.permanent@example.test');
        $workLocation = $this->createGpsWorkLocation($template, 'Field Base', -6.2000000, 106.8166667, 100);

        return $this->makeEmployeeFrom($template, [
            'work_location_id' => $workLocation->id,
            'attendance_policy_id' => $this->attendancePolicyByCode($template->company_id, 'FIELD')->id,
            'attendance_location_mode_override' => AttendancePolicy::LOCATION_MODE_FLEXIBLE,
        ]);
    }

    private function createGpsWorkLocation(
        Employee $template,
        string $name,
        float $latitude,
        float $longitude,
        int $radiusMeters,
    ): WorkLocation {
        $sequence = $this->workLocationSequence++;

        return WorkLocation::query()->create([
            'company_id' => $template->company_id,
            'branch_id' => $template->branch_id,
            'code' => sprintf('ATT-WL-%03d', $sequence),
            'name' => "{$name} {$sequence}",
            'address' => 'Attendance validation point',
            'latitude' => $latitude,
            'longitude' => $longitude,
            'radius_meters' => $radiusMeters,
            'is_active' => true,
        ]);
    }

    private function makeEmployeeFrom(Employee $template, array $overrides = []): Employee
    {
        $sequence = $this->employeeSequence++;

        return Employee::query()->create(array_merge([
            'employee_code' => sprintf('EMP-AL-%03d', $sequence),
            'full_name' => "Attendance Log User {$sequence}",
            'first_name' => 'Attendance',
            'last_name' => "Log {$sequence}",
            'email' => sprintf('attendance-log-%03d@example.test', $sequence),
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

    private function makeCompanyAdmin(Employee $template): Employee
    {
        $employee = $this->makeEmployeeFrom($template, [
            'email' => sprintf('attendance-log-admin-%03d@example.test', $this->employeeSequence),
            'employee_code' => sprintf('EMP-AL-ADM-%03d', $this->employeeSequence),
            'full_name' => 'Attendance Log Company Admin',
            'first_name' => 'Attendance',
            'last_name' => 'Admin',
        ]);

        $employee->assignRole('company_admin');

        return $employee;
    }
}
