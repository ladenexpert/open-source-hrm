<?php

namespace Tests\Feature;

use App\Filament\Employee\Resources\AttendanceLogs\Pages\ListAttendanceLogs as PortalListAttendanceLogs;
use App\Models\AttendanceLog;
use App\Models\AttendancePolicy;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeDevice;
use App\Models\WorkLocation;
use App\Services\Attendance\AttendanceDeviceTrustService;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class AttendanceDeviceTrustManagementV148Test extends TestCase
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

    public function test_trusted_device_mode_none_allows_attendance_without_trusted_device(): void
    {
        Filament::setCurrentPanel('portal');

        $employee = $this->clockableEmployee([
            'trusted_device_mode' => AttendancePolicy::TRUSTED_DEVICE_MODE_NONE,
        ]);

        Livewire::actingAs($employee)
            ->test(PortalListAttendanceLogs::class)
            ->call(
                'submitAttendanceEvent',
                'clockIn',
                self::DEMO_LATITUDE,
                self::DEMO_LONGITUDE,
                null,
                $this->deviceInfo('device-none-001'),
                [],
                'device-none-001',
            )
            ->assertNotified('Clock in recorded.');

        $this->assertDatabaseHas('attendance_logs', [
            'employee_id' => $employee->id,
            'event_type' => AttendanceLog::EVENT_CLOCK_IN,
            'device_identifier' => 'device-none-001',
        ]);
        $this->assertDatabaseHas('employee_devices', [
            'employee_id' => $employee->id,
            'device_uuid' => 'device-none-001',
            'status' => EmployeeDevice::STATUS_PENDING,
        ]);
    }

    public function test_trusted_device_mode_warn_allows_attendance_and_registers_device(): void
    {
        Filament::setCurrentPanel('portal');

        $employee = $this->clockableEmployee([
            'trusted_device_mode' => AttendancePolicy::TRUSTED_DEVICE_MODE_WARN,
        ]);

        Livewire::actingAs($employee)
            ->test(PortalListAttendanceLogs::class)
            ->call(
                'submitAttendanceEvent',
                'clockIn',
                self::DEMO_LATITUDE,
                self::DEMO_LONGITUDE,
                null,
                $this->deviceInfo('device-warn-001'),
                [],
                'device-warn-001',
            )
            ->assertNotified('Clock in recorded.');

        $device = EmployeeDevice::query()
            ->where('employee_id', $employee->id)
            ->where('device_uuid', 'device-warn-001')
            ->firstOrFail();

        $this->assertSame(EmployeeDevice::STATUS_PENDING, $device->status);
        $this->assertNotNull($device->first_seen_at);
        $this->assertNotNull($device->last_used_at);
        $this->assertNotNull($device->last_ip_address);
    }

    public function test_trusted_device_mode_enforce_blocks_attendance_from_unknown_device(): void
    {
        Filament::setCurrentPanel('portal');

        $employee = $this->clockableEmployee([
            'trusted_device_mode' => AttendancePolicy::TRUSTED_DEVICE_MODE_ENFORCE,
        ]);

        Livewire::actingAs($employee)
            ->test(PortalListAttendanceLogs::class)
            ->call(
                'submitAttendanceEvent',
                'clockIn',
                self::DEMO_LATITUDE,
                self::DEMO_LONGITUDE,
                null,
                $this->deviceInfo('device-enforce-001'),
                [],
                'device-enforce-001',
            )
            ->assertNotified('This device is not trusted for attendance. Please contact HR/Admin to approve this device.');

        $this->assertSame(0, AttendanceLog::query()->forEmployee($employee)->count());
        $this->assertDatabaseHas('employee_devices', [
            'employee_id' => $employee->id,
            'device_uuid' => 'device-enforce-001',
            'status' => EmployeeDevice::STATUS_PENDING,
        ]);
    }

    public function test_trusted_device_mode_enforce_allows_attendance_from_trusted_device(): void
    {
        Filament::setCurrentPanel('portal');

        $employee = $this->clockableEmployee([
            'trusted_device_mode' => AttendancePolicy::TRUSTED_DEVICE_MODE_ENFORCE,
        ]);
        $device = $this->createEmployeeDevice($employee, 'device-trusted-001');

        $this->deviceTrustService()->trustDevice($device, $employee);

        Livewire::actingAs($employee)
            ->test(PortalListAttendanceLogs::class)
            ->call(
                'submitAttendanceEvent',
                'clockIn',
                self::DEMO_LATITUDE,
                self::DEMO_LONGITUDE,
                null,
                $this->deviceInfo('device-trusted-001'),
                [],
                'device-trusted-001',
            )
            ->assertNotified('Clock in recorded.');

        $this->assertSame(1, AttendanceLog::query()->forEmployee($employee)->count());
    }

    public function test_revoked_device_cannot_be_used(): void
    {
        Filament::setCurrentPanel('portal');

        $employee = $this->clockableEmployee([
            'trusted_device_mode' => AttendancePolicy::TRUSTED_DEVICE_MODE_ENFORCE,
        ]);
        $device = $this->createEmployeeDevice($employee, 'device-revoked-001');

        $device = $this->deviceTrustService()->trustDevice($device, $employee);
        $this->deviceTrustService()->revokeDevice($device, $employee, 'Revoked during test.');

        Livewire::actingAs($employee)
            ->test(PortalListAttendanceLogs::class)
            ->call(
                'submitAttendanceEvent',
                'clockIn',
                self::DEMO_LATITUDE,
                self::DEMO_LONGITUDE,
                null,
                $this->deviceInfo('device-revoked-001'),
                [],
                'device-revoked-001',
            )
            ->assertNotified('This device is not trusted for attendance. Please contact HR/Admin to approve this device.');

        $this->assertSame(0, AttendanceLog::query()->forEmployee($employee)->count());
    }

    public function test_auto_trust_first_device_works_correctly(): void
    {
        Filament::setCurrentPanel('portal');

        $employee = $this->clockableEmployee([
            'trusted_device_mode' => AttendancePolicy::TRUSTED_DEVICE_MODE_ENFORCE,
            'auto_trust_first_device' => true,
        ]);

        Livewire::actingAs($employee)
            ->test(PortalListAttendanceLogs::class)
            ->call(
                'submitAttendanceEvent',
                'clockIn',
                self::DEMO_LATITUDE,
                self::DEMO_LONGITUDE,
                null,
                $this->deviceInfo('device-auto-first-001'),
                [],
                'device-auto-first-001',
            )
            ->assertNotified('Clock in recorded.');

        $device = EmployeeDevice::query()
            ->where('employee_id', $employee->id)
            ->where('device_uuid', 'device-auto-first-001')
            ->firstOrFail();

        $this->assertSame(EmployeeDevice::STATUS_TRUSTED, $device->status);
        $this->assertNotNull($device->trusted_at);
    }

    public function test_max_trusted_devices_is_enforced(): void
    {
        $employee = $this->clockableEmployee([
            'trusted_device_mode' => AttendancePolicy::TRUSTED_DEVICE_MODE_WARN,
            'max_trusted_devices' => 1,
        ]);
        $policy = $employee->attendancePolicy()->firstOrFail();

        $firstDevice = $this->deviceTrustService()->registerAttempt(
            $employee,
            $policy,
            'device-max-001',
            $this->deviceInfo('device-max-001'),
        );
        $this->deviceTrustService()->trustDevice($firstDevice, $employee);

        $secondDevice = $this->deviceTrustService()->registerAttempt(
            $employee,
            $policy,
            'device-max-002',
            $this->deviceInfo('device-max-002'),
        );

        try {
            $this->deviceTrustService()->trustDevice($secondDevice, $employee);
            $this->fail('Expected the second trust attempt to be blocked by the device limit.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString(
                'maximum number of trusted devices',
                $exception->errors()['status'][0] ?? '',
            );
        }
    }

    public function test_unknown_device_auto_registration_works(): void
    {
        Filament::setCurrentPanel('portal');

        $employee = $this->clockableEmployee([
            'trusted_device_mode' => AttendancePolicy::TRUSTED_DEVICE_MODE_ENFORCE,
        ]);

        Livewire::actingAs($employee)
            ->test(PortalListAttendanceLogs::class)
            ->call(
                'submitAttendanceEvent',
                'clockIn',
                self::DEMO_LATITUDE,
                self::DEMO_LONGITUDE,
                null,
                $this->deviceInfo('device-auto-register-001'),
                [],
                'device-auto-register-001',
            );

        $device = EmployeeDevice::query()
            ->where('employee_id', $employee->id)
            ->where('device_uuid', 'device-auto-register-001')
            ->firstOrFail();

        $this->assertSame('PHPUnit Browser', $device->browser);
        $this->assertSame('phpunit-platform', $device->platform);
        $this->assertSame('PHPUnit Browser on phpunit-platform', $device->device_name);
        $this->assertNotNull($device->first_seen_at);
        $this->assertNotNull($device->last_used_at);
        $this->assertNotNull($device->last_ip_address);
    }

    public function test_device_last_used_at_updates_correctly(): void
    {
        Filament::setCurrentPanel('portal');

        $employee = $this->clockableEmployee([
            'trusted_device_mode' => AttendancePolicy::TRUSTED_DEVICE_MODE_NONE,
        ]);
        $deviceUuid = 'device-last-used-001';
        $firstAttemptAt = now(config('app.timezone'))->setTime(8, 0);
        $secondAttemptAt = now(config('app.timezone'))->setTime(17, 0);

        $this->travelTo($firstAttemptAt);

        Livewire::actingAs($employee)
            ->test(PortalListAttendanceLogs::class)
            ->call(
                'submitAttendanceEvent',
                'clockIn',
                self::DEMO_LATITUDE,
                self::DEMO_LONGITUDE,
                null,
                $this->deviceInfo($deviceUuid),
                [],
                $deviceUuid,
            );

        $this->travelTo($secondAttemptAt);

        Livewire::actingAs($employee)
            ->test(PortalListAttendanceLogs::class)
            ->call(
                'submitAttendanceEvent',
                'clockOut',
                self::DEMO_LATITUDE,
                self::DEMO_LONGITUDE,
                null,
                $this->deviceInfo($deviceUuid),
                [],
                $deviceUuid,
            );

        $this->travelBack();

        $device = EmployeeDevice::query()
            ->where('employee_id', $employee->id)
            ->where('device_uuid', $deviceUuid)
            ->firstOrFail();

        $this->assertTrue($device->first_seen_at?->equalTo($firstAttemptAt));
        $this->assertTrue($device->last_used_at?->equalTo($secondAttemptAt));
    }

    public function test_multiple_employees_can_share_the_same_device_uuid(): void
    {
        Filament::setCurrentPanel('portal');

        $employeeA = $this->clockableEmployee([
            'trusted_device_mode' => AttendancePolicy::TRUSTED_DEVICE_MODE_WARN,
        ]);
        $employeeB = $this->clockableEmployee([
            'trusted_device_mode' => AttendancePolicy::TRUSTED_DEVICE_MODE_WARN,
        ]);

        Livewire::actingAs($employeeA)
            ->test(PortalListAttendanceLogs::class)
            ->call(
                'submitAttendanceEvent',
                'clockIn',
                self::DEMO_LATITUDE,
                self::DEMO_LONGITUDE,
                null,
                $this->deviceInfo('shared-kiosk-001'),
                [],
                'shared-kiosk-001',
            )
            ->assertNotified('Clock in recorded.');

        Livewire::actingAs($employeeB)
            ->test(PortalListAttendanceLogs::class)
            ->call(
                'submitAttendanceEvent',
                'clockIn',
                self::DEMO_LATITUDE,
                self::DEMO_LONGITUDE,
                null,
                $this->deviceInfo('shared-kiosk-001'),
                [],
                'shared-kiosk-001',
            )
            ->assertNotified('Clock in recorded.');

        $this->assertSame(2, EmployeeDevice::query()->where('device_uuid', 'shared-kiosk-001')->count());
        $this->assertDatabaseHas('employee_devices', [
            'employee_id' => $employeeA->id,
            'device_uuid' => 'shared-kiosk-001',
        ]);
        $this->assertDatabaseHas('employee_devices', [
            'employee_id' => $employeeB->id,
            'device_uuid' => 'shared-kiosk-001',
        ]);
    }

    private function deviceTrustService(): AttendanceDeviceTrustService
    {
        return app(AttendanceDeviceTrustService::class);
    }

    private function createEmployeeDevice(Employee $employee, string $deviceUuid): EmployeeDevice
    {
        return EmployeeDevice::query()->create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'device_uuid' => $deviceUuid,
            'device_name' => 'PHPUnit Browser on phpunit-platform',
            'platform' => 'phpunit-platform',
            'browser' => 'PHPUnit Browser',
            'user_agent' => 'PHPUnit Browser',
            'status' => EmployeeDevice::STATUS_PENDING,
            'first_seen_at' => now(config('app.timezone')),
            'last_used_at' => now(config('app.timezone')),
            'last_ip_address' => '127.0.0.1',
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function deviceInfo(string $deviceUuid): array
    {
        return [
            'browser' => 'PHPUnit Browser',
            'device_name' => 'PHPUnit Browser on phpunit-platform',
            'device_uuid' => $deviceUuid,
            'platform' => 'phpunit-platform',
            'user_agent' => 'PHPUnit Browser',
        ];
    }

    /**
     * @param  array<string, mixed>  $policyOverrides
     */
    private function clockableEmployee(array $policyOverrides = []): Employee
    {
        $employee = $this->makeEmployee();
        $workLocation = $this->createGpsWorkLocation($employee, 'Device Trust Gate', self::DEMO_LATITUDE, self::DEMO_LONGITUDE, 100);
        $policy = $this->createAttendancePolicy($employee, $policyOverrides);

        return $this->makeEmployee([
            'company_id' => $employee->company_id,
            'company_group_id' => $employee->company_group_id,
            'work_location_id' => $workLocation->id,
            'attendance_policy_id' => $policy->id,
            'attendance_location_mode_override' => AttendancePolicy::LOCATION_MODE_FIXED,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createAttendancePolicy(Employee $template, array $overrides = []): AttendancePolicy
    {
        $code = sprintf('DEVICE-%03d', $this->employeeSequence);

        return AttendancePolicy::query()->create(array_merge([
            'company_id' => $template->company_id,
            'code' => $code,
            'name' => 'Device Trust Policy',
            'location_mode' => AttendancePolicy::LOCATION_MODE_FIXED,
            'gps_required' => true,
            'require_selfie' => false,
            'radius_validation_enabled' => true,
            'radius_meters' => 100,
            'trusted_device_mode' => AttendancePolicy::TRUSTED_DEVICE_MODE_NONE,
            'auto_trust_first_device' => false,
            'max_trusted_devices' => null,
            'late_tolerance_minutes' => 0,
            'early_out_tolerance_minutes' => 0,
            'minimum_work_minutes' => null,
            'auto_absent_after_minutes' => null,
            'overtime_threshold_minutes' => null,
            'is_active' => true,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeEmployee(array $attributes = []): Employee
    {
        $sequence = $this->employeeSequence++;
        $company = Company::query()->where('code', Company::DEFAULT_CODE)->firstOrFail();

        $employee = Employee::query()->create(array_merge([
            'employee_code' => sprintf('EMP-V148-%03d', $sequence),
            'full_name' => "Attendance Device {$sequence}",
            'first_name' => 'Attendance',
            'last_name' => "Device {$sequence}",
            'email' => sprintf('attendance-device-%03d@example.test', $sequence),
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
            'code' => sprintf('V148-WL-%03d', $sequence),
            'name' => "{$name} {$sequence}",
            'address' => 'Attendance device validation point',
            'latitude' => $latitude,
            'longitude' => $longitude,
            'radius_meters' => $radiusMeters,
            'is_active' => true,
        ]);
    }
}
