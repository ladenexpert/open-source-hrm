<?php

namespace Tests\Feature;

use App\Filament\Employee\Pages\MyAttendance;
use App\Filament\Employee\Resources\AttendanceCorrections\AttendanceCorrectionResource as PortalAttendanceCorrectionResource;
use App\Filament\Employee\Resources\AttendanceCorrections\Pages\ListAttendanceCorrections as PortalListAttendanceCorrections;
use App\Filament\Employee\Resources\AttendanceLogs\Pages\ListAttendanceLogs as PortalListAttendanceLogs;
use App\Filament\Employee\Resources\AttendanceSummaries\AttendanceSummaryResource as PortalAttendanceSummaryResource;
use App\Filament\Employee\Resources\AttendanceSummaries\Pages\ListAttendanceSummaries as PortalListAttendanceSummaries;
use App\Models\AttendanceCorrection;
use App\Models\AttendanceLog;
use App\Models\AttendancePolicy;
use App\Models\AttendanceSummary;
use App\Models\Company;
use App\Models\Employee;
use App\Models\WorkLocation;
use App\Services\Attendance\AttendanceLogService;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class AttendanceStabilizationV145Test extends TestCase
{
    use RefreshDatabase;

    private AttendanceLogService $attendanceLogService;

    private int $employeeSequence = 1;

    private int $workLocationSequence = 1;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-06-19 09:00:00', config('app.timezone')));

        $this->seed(DatabaseSeeder::class);
        $this->attendanceLogService = app(AttendanceLogService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_dashboard_loads_with_today_status_badges_and_quick_actions(): void
    {
        $employee = $this->makeEmployee();
        $today = now(config('app.timezone'))->startOfDay();

        $summary = $this->createAttendanceSummary($employee, $today, [
            'status' => AttendanceSummary::STATUS_LATE,
            'actual_in_at' => $today->copy()->setTime(8, 15),
            'actual_out_at' => $today->copy()->setTime(16, 40),
            'late_minutes' => 15,
            'early_out_minutes' => 20,
            'work_minutes' => 445,
        ]);

        $this->createAttendanceCorrection($employee, [
            'attendance_summary_id' => $summary->id,
            'attendance_date' => $today->toDateString(),
            'status' => AttendanceCorrection::STATUS_PENDING,
            'submitted_at' => $today->copy()->setTime(18, 0),
            'submitted_by' => $employee->id,
        ]);

        $this->actingAs($employee)
            ->get(MyAttendance::getUrl(panel: 'portal'))
            ->assertOk()
            ->assertSee('Today Status Card')
            ->assertSee('Late Badge: 15 min')
            ->assertSee('Early Leave Badge: 20 min')
            ->assertSee('Pending Correction Badge: 1')
            ->assertSee('Open Attendance Log')
            ->assertSee('View History')
            ->assertSee('My Corrections')
            ->assertSee('Request Correction');
    }

    public function test_history_page_displays_summary_metrics_widget(): void
    {
        $employee = $this->makeEmployee();
        $today = now(config('app.timezone'))->startOfDay();

        $this->createAttendanceSummary($employee, $today->copy()->subDays(3), ['status' => AttendanceSummary::STATUS_PRESENT]);
        $this->createAttendanceSummary($employee, $today->copy()->subDays(2), ['status' => AttendanceSummary::STATUS_LATE]);
        $this->createAttendanceSummary($employee, $today->copy()->subDay(), ['status' => AttendanceSummary::STATUS_ABSENT]);
        $this->createAttendanceSummary($employee, $today, ['status' => AttendanceSummary::STATUS_LEAVE]);

        $this->actingAs($employee)
            ->get(PortalAttendanceSummaryResource::getUrl(isAbsolute: false, panel: 'portal'))
            ->assertOk()
            ->assertSee('Filtered attendance summaries')
            ->assertSee('Includes late arrival days')
            ->assertSee('No valid in and out logs')
            ->assertSee('Approved full-day leave');
    }

    public function test_history_page_remains_empty_when_no_attendance_summary_exists(): void
    {
        $employee = $this->clockableEmployeeWithOfficePolicy();

        $this->attendanceLogService->clockIn($employee, [
            'clocked_at' => now(config('app.timezone'))->copy()->setTime(8, 0),
            'source' => AttendanceLog::SOURCE_WEB,
        ]);
        $this->attendanceLogService->clockOut($employee, [
            'clocked_at' => now(config('app.timezone'))->copy()->setTime(17, 0),
            'source' => AttendanceLog::SOURCE_WEB,
        ]);

        $this->assertDatabaseCount('attendance_summaries', 0);

        $this->actingAs($employee)
            ->get(PortalAttendanceSummaryResource::getUrl(isAbsolute: false, panel: 'portal'))
            ->assertOk()
            ->assertSee('No My Attendance History');
    }

    public function test_history_this_month_filter_works(): void
    {
        Filament::setCurrentPanel('portal');

        $employee = $this->makeEmployee();
        $today = now(config('app.timezone'))->startOfDay();

        $thisMonthSummary = $this->createAttendanceSummary($employee, $today->copy()->subDays(2));
        $lastMonthSummary = $this->createAttendanceSummary($employee, $today->copy()->subMonthNoOverflow()->startOfMonth()->addDays(2));

        Livewire::actingAs($employee)
            ->test(PortalListAttendanceSummaries::class)
            ->filterTable('period', ['value' => 'this_month'])
            ->assertCanSeeTableRecords([$thisMonthSummary])
            ->assertCanNotSeeTableRecords([$lastMonthSummary]);
    }

    public function test_history_last_month_filter_works(): void
    {
        Filament::setCurrentPanel('portal');

        $employee = $this->makeEmployee();
        $today = now(config('app.timezone'))->startOfDay();

        $thisMonthSummary = $this->createAttendanceSummary($employee, $today->copy()->subDays(1));
        $lastMonthSummary = $this->createAttendanceSummary($employee, $today->copy()->subMonthNoOverflow()->startOfMonth()->addDays(4));

        Livewire::actingAs($employee)
            ->test(PortalListAttendanceSummaries::class)
            ->filterTable('period', ['value' => 'last_month'])
            ->assertCanSeeTableRecords([$lastMonthSummary])
            ->assertCanNotSeeTableRecords([$thisMonthSummary]);
    }

    public function test_history_custom_range_filter_works_and_remains_self_scoped(): void
    {
        Filament::setCurrentPanel('portal');

        $employee = $this->makeEmployee();
        $otherEmployee = $this->makeEmployee([
            'email' => 'attendance-v145-other@example.test',
        ]);

        $startDate = now(config('app.timezone'))->copy()->subDays(7)->startOfDay();
        $endDate = now(config('app.timezone'))->copy()->subDays(3)->startOfDay();

        $ownInRange = $this->createAttendanceSummary($employee, $startDate->copy()->addDay());
        $ownOutOfRange = $this->createAttendanceSummary($employee, now(config('app.timezone'))->copy()->subDay()->startOfDay());
        $otherInRange = $this->createAttendanceSummary($otherEmployee, $startDate->copy()->addDays(2));

        Livewire::actingAs($employee)
            ->test(PortalListAttendanceSummaries::class)
            ->filterTable('attendance_date', [
                'from' => $startDate->toDateString(),
                'until' => $endDate->toDateString(),
            ])
            ->assertCanSeeTableRecords([$ownInRange])
            ->assertCanNotSeeTableRecords([$ownOutOfRange, $otherInRange]);
    }

    public function test_duplicate_clock_in_is_prevented(): void
    {
        Filament::setCurrentPanel('portal');

        $employee = $this->makeEmployee();

        AttendanceLog::query()->create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'attendance_date' => now(config('app.timezone'))->toDateString(),
            'event_type' => AttendanceLog::EVENT_CLOCK_IN,
            'clocked_at' => now(config('app.timezone'))->copy()->setTime(8, 0),
            'source' => AttendanceLog::SOURCE_WEB,
            'is_valid' => true,
            'created_by' => $employee->id,
        ]);

        Livewire::actingAs($employee)
            ->test(PortalListAttendanceLogs::class)
            ->callAction('clockIn')
            ->assertNotified();

        $this->assertSame(
            1,
            AttendanceLog::query()
                ->forEmployee($employee)
                ->where('event_type', AttendanceLog::EVENT_CLOCK_IN)
                ->count()
        );
    }

    public function test_duplicate_clock_out_is_prevented(): void
    {
        Filament::setCurrentPanel('portal');

        $employee = $this->makeEmployee();

        AttendanceLog::query()->create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'attendance_date' => now(config('app.timezone'))->toDateString(),
            'event_type' => AttendanceLog::EVENT_CLOCK_OUT,
            'clocked_at' => now(config('app.timezone'))->copy()->subMinutes(5),
            'source' => AttendanceLog::SOURCE_WEB,
            'is_valid' => true,
            'created_by' => $employee->id,
        ]);

        Livewire::actingAs($employee)
            ->test(PortalListAttendanceLogs::class)
            ->callAction('clockOut')
            ->assertNotified();

        $this->assertSame(
            1,
            AttendanceLog::query()
                ->forEmployee($employee)
                ->where('event_type', AttendanceLog::EVENT_CLOCK_OUT)
                ->count()
        );
    }

    public function test_first_invalid_gps_required_attempt_is_still_stored_as_audit_log(): void
    {
        $employee = $this->clockableEmployeeWithOfficePolicy();

        $log = $this->attendanceLogService->clockIn($employee, [
            'clocked_at' => now(config('app.timezone'))->copy()->setTime(8, 0),
            'source' => AttendanceLog::SOURCE_WEB,
        ]);

        $this->assertFalse($log->is_valid);
        $this->assertSame('GPS coordinates are required for attendance logging.', $log->validation_message);
        $this->assertDatabaseHas('attendance_logs', [
            'id' => $log->id,
            'employee_id' => $employee->id,
            'event_type' => AttendanceLog::EVENT_CLOCK_IN,
            'is_valid' => false,
        ]);
    }

    public function test_rapid_repeated_invalid_clock_in_is_prevented(): void
    {
        $employee = $this->clockableEmployeeWithOfficePolicy();
        $clockedAt = now(config('app.timezone'))->copy()->setTime(8, 0);

        $firstAttempt = $this->attendanceLogService->clockIn($employee, [
            'clocked_at' => $clockedAt,
            'source' => AttendanceLog::SOURCE_WEB,
        ]);

        try {
            $this->attendanceLogService->clockIn($employee, [
                'clocked_at' => $clockedAt->copy()->addMinute(),
                'source' => AttendanceLog::SOURCE_WEB,
            ]);

            $this->fail('Expected repeated invalid clock in to be blocked.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString(
                'already recorded as invalid',
                collect($exception->errors())->flatten()->join(' ')
            );
        }

        $this->assertFalse($firstAttempt->is_valid);
        $this->assertSame(
            1,
            AttendanceLog::query()
                ->forEmployee($employee)
                ->where('event_type', AttendanceLog::EVENT_CLOCK_IN)
                ->count()
        );
    }

    public function test_rapid_repeated_invalid_clock_out_is_prevented(): void
    {
        $employee = $this->clockableEmployeeWithOfficePolicy();
        $clockedAt = now(config('app.timezone'))->copy()->setTime(17, 0);

        $firstAttempt = $this->attendanceLogService->clockOut($employee, [
            'clocked_at' => $clockedAt,
            'source' => AttendanceLog::SOURCE_WEB,
        ]);

        try {
            $this->attendanceLogService->clockOut($employee, [
                'clocked_at' => $clockedAt->copy()->addMinute(),
                'source' => AttendanceLog::SOURCE_WEB,
            ]);

            $this->fail('Expected repeated invalid clock out to be blocked.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString(
                'already recorded as invalid',
                collect($exception->errors())->flatten()->join(' ')
            );
        }

        $this->assertFalse($firstAttempt->is_valid);
        $this->assertSame(
            1,
            AttendanceLog::query()
                ->forEmployee($employee)
                ->where('event_type', AttendanceLog::EVENT_CLOCK_OUT)
                ->count()
        );
    }

    public function test_rapid_repeated_submission_is_prevented_across_midnight_boundary(): void
    {
        $employee = $this->makeEmployee();
        $firstClockIn = now(config('app.timezone'))->copy()->setTime(23, 59);
        $secondClockIn = $firstClockIn->copy()->addMinutes(2);

        AttendanceLog::query()->create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'attendance_date' => $firstClockIn->toDateString(),
            'event_type' => AttendanceLog::EVENT_CLOCK_IN,
            'clocked_at' => $firstClockIn,
            'source' => AttendanceLog::SOURCE_WEB,
            'is_valid' => true,
            'created_by' => $employee->id,
        ]);

        $this->expectException(ValidationException::class);

        $this->attendanceLogService->clockIn($employee, [
            'clocked_at' => $secondClockIn,
            'source' => AttendanceLog::SOURCE_WEB,
        ]);
    }

    public function test_valid_clock_in_then_clock_out_flow_still_works(): void
    {
        $employee = $this->makeEmployee();

        $clockIn = $this->attendanceLogService->clockIn($employee, [
            'clocked_at' => now(config('app.timezone'))->copy()->setTime(8, 0),
            'source' => AttendanceLog::SOURCE_WEB,
        ]);
        $clockOut = $this->attendanceLogService->clockOut($employee, [
            'clocked_at' => now(config('app.timezone'))->copy()->setTime(17, 5),
            'source' => AttendanceLog::SOURCE_WEB,
        ]);

        $this->assertSame(AttendanceLog::EVENT_CLOCK_IN, $clockIn->event_type);
        $this->assertSame(AttendanceLog::EVENT_CLOCK_OUT, $clockOut->event_type);
        $this->assertSame(
            2,
            AttendanceLog::query()
                ->forEmployee($employee)
                ->count()
        );
    }

    public function test_valid_retry_is_allowed_after_invalid_gps_attempt(): void
    {
        $employee = $this->clockableEmployeeWithOfficePolicy();
        $clockIn = now(config('app.timezone'))->copy()->setTime(8, 0);

        $invalidAttempt = $this->attendanceLogService->clockIn($employee, [
            'clocked_at' => $clockIn,
            'source' => AttendanceLog::SOURCE_WEB,
        ]);

        $validAttempt = $this->attendanceLogService->clockIn($employee, [
            'clocked_at' => $clockIn->copy()->addMinute(),
            'source' => AttendanceLog::SOURCE_WEB,
            'latitude' => -6.2000000,
            'longitude' => 106.8166667,
        ]);

        $this->assertFalse($invalidAttempt->is_valid);
        $this->assertTrue($validAttempt->is_valid);
        $this->assertSame(
            2,
            AttendanceLog::query()
                ->forEmployee($employee)
                ->where('event_type', AttendanceLog::EVENT_CLOCK_IN)
                ->count()
        );
    }

    public function test_correction_statuses_display_correctly(): void
    {
        $employee = $this->makeEmployee();
        $today = now(config('app.timezone'))->startOfDay();

        $this->createAttendanceCorrection($employee, [
            'attendance_date' => $today->toDateString(),
            'status' => AttendanceCorrection::STATUS_DRAFT,
        ]);
        $this->createAttendanceCorrection($employee, [
            'attendance_date' => $today->copy()->subDay()->toDateString(),
            'status' => AttendanceCorrection::STATUS_PENDING,
            'submitted_at' => $today->copy()->subDay()->setTime(18, 0),
            'submitted_by' => $employee->id,
        ]);
        $this->createAttendanceCorrection($employee, [
            'attendance_date' => $today->copy()->subDays(2)->toDateString(),
            'status' => AttendanceCorrection::STATUS_APPROVED,
            'submitted_at' => $today->copy()->subDays(2)->setTime(18, 0),
            'submitted_by' => $employee->id,
            'approved_at' => $today->copy()->subDay()->setTime(9, 0),
            'approved_by' => $employee->id,
        ]);
        $this->createAttendanceCorrection($employee, [
            'attendance_date' => $today->copy()->subDays(3)->toDateString(),
            'status' => AttendanceCorrection::STATUS_REJECTED,
            'submitted_at' => $today->copy()->subDays(3)->setTime(18, 0),
            'submitted_by' => $employee->id,
            'rejected_at' => $today->copy()->subDays(2)->setTime(9, 0),
            'rejected_by' => $employee->id,
        ]);

        $this->actingAs($employee)
            ->get(PortalAttendanceCorrectionResource::getUrl(isAbsolute: false, panel: 'portal'))
            ->assertOk()
            ->assertSee('Draft')
            ->assertSee('Pending')
            ->assertSee('Approved')
            ->assertSee('Rejected');
    }

    public function test_employee_cannot_access_another_employee_correction(): void
    {
        $employee = $this->makeEmployee();
        $otherEmployee = $this->makeEmployee([
            'email' => 'attendance-v145-restricted@example.test',
        ]);

        $correction = $this->createAttendanceCorrection($otherEmployee, [
            'status' => AttendanceCorrection::STATUS_PENDING,
            'submitted_at' => now(config('app.timezone'))->copy()->setTime(18, 0),
            'submitted_by' => $otherEmployee->id,
        ]);

        $this->actingAs($employee)
            ->get(PortalAttendanceCorrectionResource::getUrl('view', ['record' => $correction], panel: 'portal'))
            ->assertNotFound();
    }

    public function test_performance_sensitive_portal_pages_continue_working_after_optimizations(): void
    {
        $employee = $this->makeEmployee();
        $today = now(config('app.timezone'))->startOfDay();

        $summary = $this->createAttendanceSummary($employee, $today);
        $this->createAttendanceCorrection($employee, [
            'attendance_summary_id' => $summary->id,
            'attendance_date' => $today->toDateString(),
            'status' => AttendanceCorrection::STATUS_PENDING,
            'submitted_at' => $today->copy()->setTime(18, 0),
            'submitted_by' => $employee->id,
        ]);

        $this->actingAs($employee)
            ->get(MyAttendance::getUrl(panel: 'portal'))
            ->assertOk();

        $this->actingAs($employee)
            ->get(PortalAttendanceSummaryResource::getUrl(isAbsolute: false, panel: 'portal'))
            ->assertOk();

        $this->actingAs($employee)
            ->get(PortalAttendanceCorrectionResource::getUrl(isAbsolute: false, panel: 'portal'))
            ->assertOk();

        Livewire::actingAs($employee)
            ->test(PortalListAttendanceCorrections::class)
            ->assertOk();
    }

    private function makeEmployee(array $attributes = []): Employee
    {
        $sequence = $this->employeeSequence++;
        $company = Company::query()->where('code', Company::DEFAULT_CODE)->firstOrFail();

        $employee = Employee::query()->create(array_merge([
            'employee_code' => sprintf('EMP-V145-%03d', $sequence),
            'full_name' => "Attendance Stabilization {$sequence}",
            'first_name' => 'Attendance',
            'last_name' => "Stabilization {$sequence}",
            'email' => sprintf('attendance-stabilization-%03d@example.test', $sequence),
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
            'code' => sprintf('V145-WL-%03d', $sequence),
            'name' => "{$name} {$sequence}",
            'address' => 'Attendance validation point',
            'latitude' => $latitude,
            'longitude' => $longitude,
            'radius_meters' => $radiusMeters,
            'is_active' => true,
        ]);
    }

    private function createAttendanceSummary(Employee $employee, Carbon $date, array $overrides = []): AttendanceSummary
    {
        return AttendanceSummary::query()->create(array_merge([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'attendance_date' => $date->toDateString(),
            'break_duration_minutes' => 60,
            'late_minutes' => 0,
            'early_out_minutes' => 0,
            'work_minutes' => 480,
            'status' => AttendanceSummary::STATUS_PRESENT,
            'is_complete' => true,
            'is_recalculated' => false,
            'calculated_at' => now(config('app.timezone')),
            'created_by' => $employee->id,
            'updated_by' => $employee->id,
        ], $overrides));
    }

    private function createAttendanceCorrection(Employee $employee, array $overrides = []): AttendanceCorrection
    {
        $attendanceDate = $overrides['attendance_date'] ?? now(config('app.timezone'))->toDateString();

        return AttendanceCorrection::query()->create(array_merge([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'attendance_summary_id' => null,
            'attendance_date' => $attendanceDate,
            'correction_type' => AttendanceCorrection::TYPE_MISSING_CLOCK_OUT,
            'reason' => 'Attendance stabilization regression fixture.',
            'requested_clock_in_at' => null,
            'requested_clock_out_at' => null,
            'requested_work_location_id' => null,
            'requested_notes' => null,
            'approved_clock_in_at' => null,
            'approved_clock_out_at' => null,
            'approved_work_location_id' => null,
            'approved_notes' => null,
            'status' => AttendanceCorrection::STATUS_DRAFT,
            'submitted_at' => null,
            'submitted_by' => null,
            'approved_at' => null,
            'approved_by' => null,
            'rejected_at' => null,
            'rejected_by' => null,
            'cancelled_at' => null,
            'cancelled_by' => null,
            'approval_request_id' => null,
            'created_by' => $employee->id,
            'updated_by' => $employee->id,
        ], $overrides));
    }
}
