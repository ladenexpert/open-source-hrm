<?php

namespace Tests\Feature;

use App\Filament\Employee\Resources\AttendanceLogs\AttendanceLogResource;
use App\Filament\Employee\Resources\AttendanceLogs\Pages\ListAttendanceLogs as PortalListAttendanceLogs;
use App\Filament\Resources\WorkLocations\Pages\CreateWorkLocation;
use App\Models\AttendanceLog;
use App\Models\AttendancePolicy;
use App\Models\Company;
use App\Models\Employee;
use App\Models\WorkLocation;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AttendanceGeoCaptureV146Test extends TestCase
{
    use RefreshDatabase;

    private const DEMO_LATITUDE = -6.2000000;

    private const DEMO_LONGITUDE = 106.8166667;

    private int $employeeSequence = 1;

    private int $workLocationSequence = 1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_portal_clock_in_action_dispatches_geolocation_request(): void
    {
        Filament::setCurrentPanel('portal');

        $employee = $this->makeEmployee();

        Livewire::actingAs($employee)
            ->test(PortalListAttendanceLogs::class)
            ->callAction('clockIn')
            ->assertDispatched('attendance-request-geolocation', method: 'clockIn');
    }

    public function test_portal_attendance_log_page_renders_geo_capture_bridge(): void
    {
        Filament::setCurrentPanel('portal');

        $employee = $this->clockableEmployeeWithOfficePolicy();

        $this->actingAs($employee)
            ->get(AttendanceLogResource::getUrl(panel: 'portal', isAbsolute: false))
            ->assertOk()
            ->assertSee('data-attendance-geo-capture-bridge="true"', false)
            ->assertSee('attendance-request-geolocation', false);
    }

    public function test_portal_submit_attendance_event_passes_gps_coordinates_and_shows_success_for_valid_log(): void
    {
        Filament::setCurrentPanel('portal');

        $employee = $this->clockableEmployeeWithOfficePolicy();

        Livewire::actingAs($employee)
            ->test(PortalListAttendanceLogs::class)
            ->call('submitAttendanceEvent', 'clockIn', -6.2000000, 106.8166667)
            ->assertNotified('Clock in recorded.');

        $log = AttendanceLog::query()->latest('id')->firstOrFail();

        $this->assertTrue($log->is_valid);
        $this->assertSame('-6.2000000', $log->latitude);
        $this->assertSame('106.8166667', $log->longitude);
    }

    public function test_portal_submit_attendance_event_shows_error_for_invalid_saved_log(): void
    {
        Filament::setCurrentPanel('portal');

        $employee = $this->clockableEmployeeWithOfficePolicy();

        Livewire::actingAs($employee)
            ->test(PortalListAttendanceLogs::class)
            ->call('submitAttendanceEvent', 'clockIn', -6.2500000, 106.8666667);

        $log = AttendanceLog::query()->latest('id')->firstOrFail();

        $this->assertFalse($log->is_valid);
        $this->assertNotNull($log->validation_message);

        Notification::assertNotified($log->validation_message);
        Notification::assertNotNotified('Clock in recorded.');
    }

    public function test_seeded_demo_employee_is_attendance_ready_for_gps_clocking(): void
    {
        $employee = $this->demoEmployee();
        $workLocation = $employee->workLocation()->firstOrFail();

        $this->assertNotNull($employee->branch_id);
        $this->assertNotNull($employee->work_location_id);
        $this->assertNotNull($employee->attendance_policy_id);
        $this->assertSame('OFFICE', $employee->attendancePolicy()->value('code'));
        $this->assertSame('-6.2000000', $workLocation->latitude);
        $this->assertSame('106.8166667', $workLocation->longitude);
        $this->assertSame(100, $workLocation->radius_meters);
    }

    public function test_seeded_demo_employee_can_clock_in_with_gps_inside_radius(): void
    {
        Filament::setCurrentPanel('portal');

        $employee = $this->demoEmployee();

        Livewire::actingAs($employee)
            ->test(PortalListAttendanceLogs::class)
            ->call('submitAttendanceEvent', 'clockIn', self::DEMO_LATITUDE, self::DEMO_LONGITUDE)
            ->assertNotified('Clock in recorded.');

        $log = AttendanceLog::query()->latest('id')->firstOrFail();

        $this->assertTrue($log->is_valid);
        $this->assertSame($employee->id, $log->employee_id);
        $this->assertSame('-6.2000000', $log->latitude);
        $this->assertSame('106.8166667', $log->longitude);
    }

    public function test_seeded_demo_employee_can_clock_out_with_gps_inside_radius(): void
    {
        Filament::setCurrentPanel('portal');

        $employee = $this->demoEmployee();

        Livewire::actingAs($employee)
            ->test(PortalListAttendanceLogs::class)
            ->call('submitAttendanceEvent', 'clockOut', self::DEMO_LATITUDE, self::DEMO_LONGITUDE)
            ->assertNotified('Clock out recorded.');

        $log = AttendanceLog::query()->latest('id')->firstOrFail();

        $this->assertTrue($log->is_valid);
        $this->assertSame($employee->id, $log->employee_id);
        $this->assertSame(AttendanceLog::EVENT_CLOCK_OUT, $log->event_type);
    }

    public function test_geolocation_permission_denied_blocks_gps_required_submission_before_log_is_saved(): void
    {
        Filament::setCurrentPanel('portal');

        $employee = $this->clockableEmployeeWithOfficePolicy();

        Livewire::actingAs($employee)
            ->test(PortalListAttendanceLogs::class)
            ->call('handleGeolocationFailure', 'clockIn', 'permission_denied')
            ->assertNotified('Location permission is required for attendance.');

        $this->assertSame(0, AttendanceLog::query()->forEmployee($employee)->count());
    }

    public function test_geolocation_failure_can_fall_back_when_policy_allows_non_gps_submission(): void
    {
        Filament::setCurrentPanel('portal');

        $employee = $this->makeEmployee([
            'attendance_policy_id' => AttendancePolicy::query()->create([
                'company_id' => Company::query()->where('code', Company::DEFAULT_CODE)->value('id'),
                'code' => 'NO-GPS',
                'name' => 'No GPS Policy',
                'location_mode' => AttendancePolicy::LOCATION_MODE_FIXED,
                'gps_required' => false,
                'selfie_required' => false,
                'radius_validation_enabled' => false,
                'radius_meters' => null,
                'late_tolerance_minutes' => 0,
                'early_out_tolerance_minutes' => 0,
                'minimum_work_minutes' => null,
                'auto_absent_after_minutes' => null,
                'overtime_threshold_minutes' => null,
                'is_active' => true,
            ])->id,
        ]);

        Livewire::actingAs($employee)
            ->test(PortalListAttendanceLogs::class)
            ->call('handleGeolocationFailure', 'clockIn', 'unsupported')
            ->assertNotified('Your browser does not support location capture.');

        $log = AttendanceLog::query()->latest('id')->firstOrFail();

        $this->assertTrue($log->is_valid);
        $this->assertNull($log->latitude);
        $this->assertNull($log->longitude);
    }

    public function test_work_location_create_form_exposes_gps_fields(): void
    {
        Filament::setCurrentPanel('admin');

        Livewire::actingAs($this->adminUser())
            ->test(CreateWorkLocation::class)
            ->assertFormFieldExists('latitude')
            ->assertFormFieldVisible('latitude')
            ->assertFormFieldExists('longitude')
            ->assertFormFieldVisible('longitude')
            ->assertFormFieldExists('radius_meters')
            ->assertFormFieldVisible('radius_meters');
    }

    public function test_admin_can_create_work_location_with_gps_configuration(): void
    {
        Filament::setCurrentPanel('admin');

        $admin = $this->adminUser();
        $company = $admin->company()->firstOrFail();

        Livewire::actingAs($admin)
            ->test(CreateWorkLocation::class)
            ->fillForm([
                'company_id' => $company->id,
                'branch_id' => $admin->branch_id,
                'code' => 'GPS-HQ',
                'name' => 'GPS HQ',
                'address' => 'Configured from v1.4.6 test',
                'latitude' => -6.2000000,
                'longitude' => 106.8166667,
                'radius_meters' => 150,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $workLocation = WorkLocation::query()->where('code', 'GPS-HQ')->firstOrFail();

        $this->assertSame('-6.2000000', $workLocation->latitude);
        $this->assertSame('106.8166667', $workLocation->longitude);
        $this->assertSame(150, $workLocation->radius_meters);
    }

    private function adminUser(): Employee
    {
        return Employee::query()->where('email', 'admin@hrms.local')->firstOrFail();
    }

    private function demoEmployee(): Employee
    {
        return Employee::query()
            ->where('email', 'employee@hrms.local')
            ->firstOrFail();
    }

    private function makeEmployee(array $attributes = []): Employee
    {
        $sequence = $this->employeeSequence++;
        $company = Company::query()->where('code', Company::DEFAULT_CODE)->firstOrFail();

        $employee = Employee::query()->create(array_merge([
            'employee_code' => sprintf('EMP-V146-%03d', $sequence),
            'full_name' => "Attendance Geo {$sequence}",
            'first_name' => 'Attendance',
            'last_name' => "Geo {$sequence}",
            'email' => sprintf('attendance-geo-%03d@example.test', $sequence),
            'company_id' => $company->id,
            'company_group_id' => $company->company_group_id,
            'branch_id' => null,
            'department_id' => null,
            'work_location_id' => null,
            'attendance_policy_id' => null,
            'attendance_location_mode_override' => null,
            'employment_type' => 'Permanent',
            'hire_date' => now(config('app.timezone'))->toDateString(),
            'join_date' => now(config('app.timezone'))->toDateString(),
            'is_active' => true,
            'password' => 'password123',
        ], $attributes));

        $employee->syncRoles(['employee']);

        return $employee;
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
        $template ??= $this->makeEmployee();
        $workLocation = $this->createGpsWorkLocation($template, 'Office HQ', -6.2000000, 106.8166667, 100);

        return $this->makeEmployee(array_merge([
            'company_id' => $template->company_id,
            'company_group_id' => $template->company_group_id,
        ], [
            'work_location_id' => $workLocation->id,
            'attendance_policy_id' => $this->attendancePolicyByCode($template->company_id, 'OFFICE')->id,
            'attendance_location_mode_override' => AttendancePolicy::LOCATION_MODE_FIXED,
        ]));
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
            'code' => sprintf('V146-WL-%03d', $sequence),
            'name' => "{$name} {$sequence}",
            'address' => 'Attendance validation point',
            'latitude' => $latitude,
            'longitude' => $longitude,
            'radius_meters' => $radiusMeters,
            'is_active' => true,
        ]);
    }
}
