<?php

namespace Tests\Feature;

use App\Filament\Employee\Resources\AttendanceLogs\Pages\ListAttendanceLogs as PortalListAttendanceLogs;
use App\Filament\Resources\Attendance\AttendanceLogResource as AdminAttendanceLogResource;
use App\Models\AttendanceLog;
use App\Models\AttendancePolicy;
use App\Models\AttendanceSelfie;
use App\Models\Company;
use App\Models\Employee;
use App\Models\WorkLocation;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AttendanceSelfieCaptureV147Test extends TestCase
{
    use RefreshDatabase;

    private const DEMO_LATITUDE = -6.2000000;

    private const DEMO_LONGITUDE = 106.8166667;

    private int $employeeSequence = 1;

    private int $workLocationSequence = 1;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
    }

    public function test_selfie_required_policy_blocks_clock_in_without_selfie(): void
    {
        Filament::setCurrentPanel('portal');

        $employee = $this->clockableEmployee(requireSelfie: true);

        Livewire::actingAs($employee)
            ->test(PortalListAttendanceLogs::class)
            ->call('submitAttendanceEvent', 'clockIn', self::DEMO_LATITUDE, self::DEMO_LONGITUDE);

        $this->assertSame(0, AttendanceLog::query()->forEmployee($employee)->count());
        $this->assertSame(0, AttendanceSelfie::query()->forEmployee($employee)->count());
    }

    public function test_selfie_required_policy_allows_clock_in_with_selfie(): void
    {
        Filament::setCurrentPanel('portal');

        $employee = $this->clockableEmployee(requireSelfie: true);

        Livewire::actingAs($employee)
            ->test(PortalListAttendanceLogs::class)
            ->call(
                'submitAttendanceEvent',
                'clockIn',
                self::DEMO_LATITUDE,
                self::DEMO_LONGITUDE,
                $this->fakeSelfieDataUrl(),
                ['platform' => 'phpunit'],
                ['source' => 'attendance-selfie-test'],
            )
            ->assertNotified('Clock in recorded.');

        $log = AttendanceLog::query()->with('attendanceSelfie')->latest('id')->firstOrFail();
        $selfie = $log->attendanceSelfie;

        $this->assertTrue($log->is_valid);
        $this->assertInstanceOf(AttendanceSelfie::class, $selfie);
        $this->assertSame($log->id, $selfie->attendance_log_id);
        $this->assertSame($employee->id, $selfie->employee_id);
        $this->assertSame($employee->company_id, $selfie->company_id);
        $this->assertNotNull($selfie->captured_at);
        $this->assertSame($selfie->image_path, $log->selfie_path);
        $this->assertMatchesRegularExpression(
            sprintf(
                '#^attendance-selfies/%d/%d/\d{4}/\d{2}/[a-f0-9-]+\.jpg$#i',
                $employee->company_id,
                $employee->id,
            ),
            $selfie->image_path,
        );
        Storage::disk('local')->assertExists($selfie->image_path);
    }

    public function test_selfie_required_policy_allows_clock_out_with_selfie(): void
    {
        Filament::setCurrentPanel('portal');

        $employee = $this->clockableEmployee(requireSelfie: true);

        Livewire::actingAs($employee)
            ->test(PortalListAttendanceLogs::class)
            ->call(
                'submitAttendanceEvent',
                'clockOut',
                self::DEMO_LATITUDE,
                self::DEMO_LONGITUDE,
                $this->fakeSelfieDataUrl(),
            )
            ->assertNotified('Clock out recorded.');

        $log = AttendanceLog::query()->with('attendanceSelfie')->latest('id')->firstOrFail();

        $this->assertSame(AttendanceLog::EVENT_CLOCK_OUT, $log->event_type);
        $this->assertNotNull($log->attendanceSelfie);
    }

    public function test_selfie_optional_policy_allows_clock_in_without_selfie(): void
    {
        Filament::setCurrentPanel('portal');

        $employee = $this->clockableEmployee(requireSelfie: false);

        Livewire::actingAs($employee)
            ->test(PortalListAttendanceLogs::class)
            ->call('submitAttendanceEvent', 'clockIn', self::DEMO_LATITUDE, self::DEMO_LONGITUDE)
            ->assertNotified('Clock in recorded.');

        $log = AttendanceLog::query()->with('attendanceSelfie')->latest('id')->firstOrFail();

        $this->assertTrue($log->is_valid);
        $this->assertNull($log->attendanceSelfie);
        $this->assertNull($log->selfie_path);
    }

    public function test_selfie_optional_policy_allows_clock_out_without_selfie(): void
    {
        Filament::setCurrentPanel('portal');

        $employee = $this->clockableEmployee(requireSelfie: false);

        Livewire::actingAs($employee)
            ->test(PortalListAttendanceLogs::class)
            ->call('submitAttendanceEvent', 'clockOut', self::DEMO_LATITUDE, self::DEMO_LONGITUDE)
            ->assertNotified('Clock out recorded.');

        $log = AttendanceLog::query()->with('attendanceSelfie')->latest('id')->firstOrFail();

        $this->assertSame(AttendanceLog::EVENT_CLOCK_OUT, $log->event_type);
        $this->assertNull($log->attendanceSelfie);
    }

    public function test_selfie_record_is_linked_correctly_to_attendance_log(): void
    {
        Filament::setCurrentPanel('portal');

        $employee = $this->clockableEmployee(requireSelfie: true);

        Livewire::actingAs($employee)
            ->test(PortalListAttendanceLogs::class)
            ->call(
                'submitAttendanceEvent',
                'clockIn',
                self::DEMO_LATITUDE,
                self::DEMO_LONGITUDE,
                $this->fakeSelfieDataUrl(),
                ['platform' => 'phpunit'],
                ['capture_source' => 'test-suite'],
            );

        $log = AttendanceLog::query()->with('attendanceSelfie')->latest('id')->firstOrFail();
        $selfie = AttendanceSelfie::query()->where('attendance_log_id', $log->id)->firstOrFail();

        $this->assertTrue($log->relationLoaded('attendanceSelfie'));
        $this->assertSame($selfie->id, $log->attendanceSelfie?->id);
        $this->assertSame('phpunit', $selfie->device_info['platform'] ?? null);
        $this->assertSame('test-suite', $selfie->metadata['capture_source'] ?? null);
    }

    public function test_admin_attendance_log_view_page_shows_selfie_information_when_available(): void
    {
        Filament::setCurrentPanel('portal');

        $employee = $this->clockableEmployee(requireSelfie: true);

        Livewire::actingAs($employee)
            ->test(PortalListAttendanceLogs::class)
            ->call(
                'submitAttendanceEvent',
                'clockIn',
                self::DEMO_LATITUDE,
                self::DEMO_LONGITUDE,
                $this->fakeSelfieDataUrl(),
            );

        $log = AttendanceLog::query()->with('attendanceSelfie')->latest('id')->firstOrFail();

        Filament::setCurrentPanel('admin');

        $this->actingAs($this->adminUser())
            ->get(AdminAttendanceLogResource::getUrl('view', ['record' => $log], isAbsolute: false, panel: 'admin'))
            ->assertOk()
            ->assertSee('Selfie Verification')
            ->assertSee('Open secure selfie')
            ->assertSee($log->attendanceSelfie?->image_path ?? '');
    }

    private function adminUser(): Employee
    {
        return Employee::query()->where('email', 'admin@hrms.local')->firstOrFail();
    }

    private function clockableEmployee(bool $requireSelfie): Employee
    {
        $employee = $this->makeEmployee();
        $workLocation = $this->createGpsWorkLocation($employee, 'Selfie Gate', self::DEMO_LATITUDE, self::DEMO_LONGITUDE, 100);
        $policy = $this->createAttendancePolicy($employee, $requireSelfie);

        return $this->makeEmployee([
            'company_id' => $employee->company_id,
            'company_group_id' => $employee->company_group_id,
            'work_location_id' => $workLocation->id,
            'attendance_policy_id' => $policy->id,
            'attendance_location_mode_override' => AttendancePolicy::LOCATION_MODE_FIXED,
        ]);
    }

    private function createAttendancePolicy(Employee $template, bool $requireSelfie): AttendancePolicy
    {
        $code = sprintf('SELFIE-%s-%03d', $requireSelfie ? 'REQ' : 'OPT', $this->employeeSequence);

        return AttendancePolicy::query()->create([
            'company_id' => $template->company_id,
            'code' => $code,
            'name' => $requireSelfie ? 'Selfie Required Policy' : 'Selfie Optional Policy',
            'location_mode' => AttendancePolicy::LOCATION_MODE_FIXED,
            'gps_required' => true,
            'require_selfie' => $requireSelfie,
            'radius_validation_enabled' => true,
            'radius_meters' => 100,
            'late_tolerance_minutes' => 0,
            'early_out_tolerance_minutes' => 0,
            'minimum_work_minutes' => null,
            'auto_absent_after_minutes' => null,
            'overtime_threshold_minutes' => null,
            'is_active' => true,
        ]);
    }

    private function makeEmployee(array $attributes = []): Employee
    {
        $sequence = $this->employeeSequence++;
        $company = Company::query()->where('code', Company::DEFAULT_CODE)->firstOrFail();

        $employee = Employee::query()->create(array_merge([
            'employee_code' => sprintf('EMP-V147-%03d', $sequence),
            'full_name' => "Attendance Selfie {$sequence}",
            'first_name' => 'Attendance',
            'last_name' => "Selfie {$sequence}",
            'email' => sprintf('attendance-selfie-%03d@example.test', $sequence),
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
            'code' => sprintf('V147-WL-%03d', $sequence),
            'name' => "{$name} {$sequence}",
            'address' => 'Attendance selfie validation point',
            'latitude' => $latitude,
            'longitude' => $longitude,
            'radius_meters' => $radiusMeters,
            'is_active' => true,
        ]);
    }

    private function fakeSelfieDataUrl(): string
    {
        $file = UploadedFile::fake()->image('selfie.jpg', 120, 120);
        $contents = file_get_contents($file->getRealPath());

        if ($contents === false) {
            $this->fail('Unable to build a fake selfie image for the test.');
        }

        return 'data:image/jpeg;base64,'.base64_encode($contents);
    }
}
