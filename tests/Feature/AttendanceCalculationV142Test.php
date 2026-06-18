<?php

namespace Tests\Feature;

use App\Filament\Resources\Attendance\AttendanceSummaryResource as AdminAttendanceSummaryResource;
use App\Filament\Resources\Attendance\AttendanceSummaryResource\Pages\ListAttendanceSummaries as AdminListAttendanceSummaries;
use App\Models\AttendanceLog;
use App\Models\AttendancePolicy;
use App\Models\AttendanceSummary;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\Holiday;
use App\Models\HolidayCalendar;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\ShiftAssignment;
use App\Models\ShiftPattern;
use App\Models\WorkdayPattern;
use App\Services\Attendance\AttendanceCalculationService;
use App\Services\Attendance\AttendanceLogService;
use App\Services\Attendance\AttendancePolicyResolverService;
use App\Services\Attendance\ShiftResolverService;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class AttendanceCalculationV142Test extends TestCase
{
    use RefreshDatabase;

    private AttendanceCalculationService $attendanceCalculationService;

    private AttendanceLogService $attendanceLogService;

    private AttendancePolicyResolverService $attendancePolicyResolverService;

    private ShiftResolverService $shiftResolverService;

    private int $employeeSequence = 1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->attendanceCalculationService = app(AttendanceCalculationService::class);
        $this->attendanceLogService = app(AttendanceLogService::class);
        $this->attendancePolicyResolverService = app(AttendancePolicyResolverService::class);
        $this->shiftResolverService = app(ShiftResolverService::class);
    }

    public function test_attendance_summaries_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('attendance_summaries'));

        foreach ([
            'company_id',
            'employee_id',
            'attendance_date',
            'shift_pattern_id',
            'shift_pattern_detail_id',
            'shift_assignment_id',
            'employee_schedule_id',
            'attendance_policy_id',
            'work_location_id',
            'scheduled_start_at',
            'scheduled_end_at',
            'break_duration_minutes',
            'actual_in_at',
            'actual_out_at',
            'first_log_id',
            'last_log_id',
            'work_minutes',
            'late_minutes',
            'early_out_minutes',
            'status',
            'is_complete',
            'is_recalculated',
            'calculated_at',
            'calculation_notes',
            'created_by',
            'updated_by',
        ] as $column) {
            $this->assertTrue(Schema::hasColumn('attendance_summaries', $column), "Missing expected column [{$column}].");
        }
    }

    public function test_calculation_creates_summary_for_employee_date(): void
    {
        $employee = $this->newDefaultShiftEmployee();
        $date = $this->nextWeekday(Carbon::MONDAY);

        $this->createAttendanceLog($employee, $date->copy()->setTime(8, 0), AttendanceLog::EVENT_CLOCK_IN);
        $this->createAttendanceLog($employee, $date->copy()->setTime(17, 0), AttendanceLog::EVENT_CLOCK_OUT);

        $summary = $this->attendanceCalculationService->calculateForEmployeeDate($employee, $date);

        $this->assertDatabaseHas('attendance_summaries', [
            'id' => $summary->id,
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'attendance_date' => $date->toDateString(),
        ]);
    }

    public function test_calculation_is_rebuildable_and_updates_existing_summary(): void
    {
        $employee = $this->newDefaultShiftEmployee();
        $date = $this->nextWeekday(Carbon::TUESDAY);

        $firstSummary = $this->attendanceCalculationService->calculateForEmployeeDate($employee, $date);
        $this->assertSame(AttendanceSummary::STATUS_ABSENT, $firstSummary->status);

        $clockIn = $this->createAttendanceLog($employee, $date->copy()->setTime(8, 0), AttendanceLog::EVENT_CLOCK_IN);
        $clockOut = $this->createAttendanceLog($employee, $date->copy()->setTime(17, 0), AttendanceLog::EVENT_CLOCK_OUT);

        $recalculatedSummary = $this->attendanceCalculationService->calculateForEmployeeDate($employee, $date);

        $this->assertSame($firstSummary->id, $recalculatedSummary->id);
        $this->assertTrue($recalculatedSummary->is_recalculated);
        $this->assertSame(AttendanceSummary::STATUS_PRESENT, $recalculatedSummary->status);
        $this->assertSame($clockIn->id, $recalculatedSummary->first_log_id);
        $this->assertSame($clockOut->id, $recalculatedSummary->last_log_id);
    }

    public function test_present_status_for_complete_on_time_logs(): void
    {
        $employee = $this->newEmployeeWithPolicy(['late_tolerance_minutes' => 15, 'early_out_tolerance_minutes' => 10]);
        $date = $this->nextWeekday(Carbon::WEDNESDAY);

        $this->createAttendanceLog($employee, $date->copy()->setTime(8, 0), AttendanceLog::EVENT_CLOCK_IN);
        $this->createAttendanceLog($employee, $date->copy()->setTime(17, 0), AttendanceLog::EVENT_CLOCK_OUT);

        $summary = $this->attendanceCalculationService->calculateForEmployeeDate($employee, $date);

        $this->assertSame(AttendanceSummary::STATUS_PRESENT, $summary->status);
        $this->assertTrue($summary->is_complete);
    }

    public function test_late_minutes_calculated_after_tolerance(): void
    {
        $employee = $this->newEmployeeWithPolicy(['late_tolerance_minutes' => 15]);
        $date = $this->nextWeekday(Carbon::THURSDAY);

        $this->createAttendanceLog($employee, $date->copy()->setTime(8, 16), AttendanceLog::EVENT_CLOCK_IN);
        $this->createAttendanceLog($employee, $date->copy()->setTime(17, 0), AttendanceLog::EVENT_CLOCK_OUT);

        $summary = $this->attendanceCalculationService->calculateForEmployeeDate($employee, $date);

        $this->assertSame(AttendanceSummary::STATUS_LATE, $summary->status);
        $this->assertSame(1, $summary->late_minutes);
    }

    public function test_late_tolerance_boundary_is_on_time(): void
    {
        $employee = $this->newEmployeeWithPolicy(['late_tolerance_minutes' => 15]);
        $date = $this->nextWeekday(Carbon::FRIDAY);

        $this->createAttendanceLog($employee, $date->copy()->setTime(8, 15), AttendanceLog::EVENT_CLOCK_IN);
        $this->createAttendanceLog($employee, $date->copy()->setTime(17, 0), AttendanceLog::EVENT_CLOCK_OUT);

        $summary = $this->attendanceCalculationService->calculateForEmployeeDate($employee, $date);

        $this->assertSame(AttendanceSummary::STATUS_PRESENT, $summary->status);
        $this->assertSame(0, $summary->late_minutes);
    }

    public function test_early_out_minutes_calculated_after_tolerance(): void
    {
        $employee = $this->newEmployeeWithPolicy(['early_out_tolerance_minutes' => 10]);
        $date = $this->nextWeekday(Carbon::MONDAY);

        $this->createAttendanceLog($employee, $date->copy()->setTime(8, 0), AttendanceLog::EVENT_CLOCK_IN);
        $this->createAttendanceLog($employee, $date->copy()->setTime(16, 49), AttendanceLog::EVENT_CLOCK_OUT);

        $summary = $this->attendanceCalculationService->calculateForEmployeeDate($employee, $date);

        $this->assertSame(AttendanceSummary::STATUS_EARLY_OUT, $summary->status);
        $this->assertSame(1, $summary->early_out_minutes);
    }

    public function test_early_out_tolerance_boundary_is_on_time(): void
    {
        $employee = $this->newEmployeeWithPolicy(['early_out_tolerance_minutes' => 10]);
        $date = $this->nextWeekday(Carbon::TUESDAY);

        $this->createAttendanceLog($employee, $date->copy()->setTime(8, 0), AttendanceLog::EVENT_CLOCK_IN);
        $this->createAttendanceLog($employee, $date->copy()->setTime(16, 50), AttendanceLog::EVENT_CLOCK_OUT);

        $summary = $this->attendanceCalculationService->calculateForEmployeeDate($employee, $date);

        $this->assertSame(AttendanceSummary::STATUS_PRESENT, $summary->status);
        $this->assertSame(0, $summary->early_out_minutes);
    }

    public function test_absent_status_when_no_logs_on_scheduled_day(): void
    {
        $employee = $this->newDefaultShiftEmployee();
        $date = $this->nextWeekday(Carbon::WEDNESDAY);

        $summary = $this->attendanceCalculationService->calculateForEmployeeDate($employee, $date);

        $this->assertSame(AttendanceSummary::STATUS_ABSENT, $summary->status);
        $this->assertFalse($summary->is_complete);
    }

    public function test_incomplete_status_when_clock_in_without_clock_out(): void
    {
        $employee = $this->newDefaultShiftEmployee();
        $date = $this->nextWeekday(Carbon::THURSDAY);

        $clockIn = $this->createAttendanceLog($employee, $date->copy()->setTime(8, 0), AttendanceLog::EVENT_CLOCK_IN);

        $summary = $this->attendanceCalculationService->calculateForEmployeeDate($employee, $date);

        $this->assertSame(AttendanceSummary::STATUS_INCOMPLETE, $summary->status);
        $this->assertSame($clockIn->id, $summary->first_log_id);
        $this->assertNull($summary->last_log_id);
    }

    public function test_incomplete_status_when_clock_out_without_clock_in(): void
    {
        $employee = $this->newDefaultShiftEmployee();
        $date = $this->nextWeekday(Carbon::FRIDAY);

        $clockOut = $this->createAttendanceLog($employee, $date->copy()->setTime(17, 0), AttendanceLog::EVENT_CLOCK_OUT);

        $summary = $this->attendanceCalculationService->calculateForEmployeeDate($employee, $date);

        $this->assertSame(AttendanceSummary::STATUS_INCOMPLETE, $summary->status);
        $this->assertNull($summary->first_log_id);
        $this->assertSame($clockOut->id, $summary->last_log_id);
    }

    public function test_work_minutes_subtracts_break_duration(): void
    {
        $employee = $this->newDefaultShiftEmployee();
        $date = $this->nextWeekday(Carbon::MONDAY);

        $this->createAttendanceLog($employee, $date->copy()->setTime(8, 0), AttendanceLog::EVENT_CLOCK_IN);
        $this->createAttendanceLog($employee, $date->copy()->setTime(17, 0), AttendanceLog::EVENT_CLOCK_OUT);

        $summary = $this->attendanceCalculationService->calculateForEmployeeDate($employee, $date);

        $this->assertSame(480, $summary->work_minutes);
    }

    public function test_overnight_shift_calculates_single_workday(): void
    {
        $employee = $this->newNightShiftEmployee();
        $date = $this->nextWeekday(Carbon::MONDAY);

        $this->createAttendanceLog($employee, $date->copy()->setTime(21, 58), AttendanceLog::EVENT_CLOCK_IN);
        $this->createAttendanceLog($employee, $date->copy()->addDay()->setTime(6, 5), AttendanceLog::EVENT_CLOCK_OUT);

        $summary = $this->attendanceCalculationService->calculateForEmployeeDate($employee, $date);

        $this->assertSame($date->toDateString(), $summary->attendance_date->toDateString());
        $this->assertSame(1, AttendanceSummary::query()->forEmployee($employee)->forDate($date)->count());
    }

    public function test_overnight_shift_selects_next_day_clock_out(): void
    {
        $employee = $this->newNightShiftEmployee();
        $date = $this->nextWeekday(Carbon::TUESDAY);

        $this->createAttendanceLog($employee, $date->copy()->setTime(21, 58), AttendanceLog::EVENT_CLOCK_IN);
        $clockOut = $this->createAttendanceLog($employee, $date->copy()->addDay()->setTime(6, 5), AttendanceLog::EVENT_CLOCK_OUT);

        $summary = $this->attendanceCalculationService->calculateForEmployeeDate($employee, $date);

        $this->assertSame($date->copy()->addDay()->toDateString(), $summary->actual_out_at?->toDateString());
        $this->assertSame($clockOut->id, $summary->last_log_id);
    }

    public function test_employee_schedule_override_affects_calculation(): void
    {
        $employee = $this->newDefaultShiftEmployee();
        $date = $this->nextWeekday(Carbon::WEDNESDAY);
        $nightPattern = $this->shiftPattern($employee->company_id, 'NIGHT-OPS');

        EmployeeSchedule::query()->create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'schedule_date' => $date->toDateString(),
            'shift_pattern_id' => $nightPattern->id,
            'override_reason' => EmployeeSchedule::OVERRIDE_REASON_HR_OVERRIDE,
        ]);

        $this->createAttendanceLog($employee, $date->copy()->setTime(21, 58), AttendanceLog::EVENT_CLOCK_IN);
        $this->createAttendanceLog($employee, $date->copy()->addDay()->setTime(6, 2), AttendanceLog::EVENT_CLOCK_OUT);

        $summary = $this->attendanceCalculationService->calculateForEmployeeDate($employee, $date);

        $this->assertSame($nightPattern->id, $summary->shift_pattern_id);
        $this->assertSame(AttendanceSummary::STATUS_PRESENT, $summary->status);
    }

    public function test_employee_schedule_day_off_results_no_schedule_or_weekend(): void
    {
        $employee = $this->newDefaultShiftEmployee();
        $date = $this->nextWeekday(Carbon::THURSDAY);

        EmployeeSchedule::query()->create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'schedule_date' => $date->toDateString(),
            'shift_pattern_id' => null,
            'override_reason' => EmployeeSchedule::OVERRIDE_REASON_PERSONAL,
        ]);

        $summary = $this->attendanceCalculationService->calculateForEmployeeDate($employee, $date);

        $this->assertSame(AttendanceSummary::STATUS_NO_SCHEDULE, $summary->status);
    }

    public function test_company_default_shift_used_for_calculation(): void
    {
        $employee = $this->newDefaultShiftEmployee();
        $date = $this->nextWeekday(Carbon::FRIDAY);

        $this->createAttendanceLog($employee, $date->copy()->setTime(8, 0), AttendanceLog::EVENT_CLOCK_IN);
        $this->createAttendanceLog($employee, $date->copy()->setTime(17, 0), AttendanceLog::EVENT_CLOCK_OUT);

        $summary = $this->attendanceCalculationService->calculateForEmployeeDate($employee, $date);

        $this->assertSame($employee->company->default_shift_pattern_id, $summary->shift_pattern_id);
    }

    public function test_leave_status_takes_priority_over_absent(): void
    {
        $employee = $this->newDefaultShiftEmployee();
        $date = $this->nextWeekday(Carbon::MONDAY);

        $this->createApprovedFullDayLeave($employee, $date);

        $summary = $this->attendanceCalculationService->calculateForEmployeeDate($employee, $date);

        $this->assertSame(AttendanceSummary::STATUS_LEAVE, $summary->status);
    }

    public function test_holiday_status_takes_priority_over_absent(): void
    {
        $employee = $this->newDefaultShiftEmployee();
        $date = $this->nextWeekday(Carbon::TUESDAY);

        $this->createHoliday($employee->company_id, $date, 'Test Holiday');

        $summary = $this->attendanceCalculationService->calculateForEmployeeDate($employee, $date);

        $this->assertSame(AttendanceSummary::STATUS_HOLIDAY, $summary->status);
    }

    public function test_weekend_or_non_working_day_status(): void
    {
        $employee = $this->newDefaultShiftEmployee();
        $date = $this->nextSaturday();

        $summary = $this->attendanceCalculationService->calculateForEmployeeDate($employee, $date);

        $this->assertSame(AttendanceSummary::STATUS_WEEKEND, $summary->status);
    }

    public function test_invalid_logs_are_ignored_for_actual_in_out(): void
    {
        $employee = $this->newDefaultShiftEmployee();
        $date = $this->nextWeekday(Carbon::WEDNESDAY);

        $this->createAttendanceLog($employee, $date->copy()->setTime(7, 30), AttendanceLog::EVENT_CLOCK_IN, false, 'Outside radius.');
        $validClockIn = $this->createAttendanceLog($employee, $date->copy()->setTime(8, 2), AttendanceLog::EVENT_CLOCK_IN);
        $validClockOut = $this->createAttendanceLog($employee, $date->copy()->setTime(17, 1), AttendanceLog::EVENT_CLOCK_OUT);
        $this->createAttendanceLog($employee, $date->copy()->setTime(18, 0), AttendanceLog::EVENT_CLOCK_OUT, false, 'Outside radius.');

        $summary = $this->attendanceCalculationService->calculateForEmployeeDate($employee, $date);

        $this->assertSame($validClockIn->id, $summary->first_log_id);
        $this->assertSame($validClockOut->id, $summary->last_log_id);
        $this->assertSame('08:02', $summary->actual_in_at?->format('H:i'));
        $this->assertSame('17:01', $summary->actual_out_at?->format('H:i'));
    }

    public function test_invalid_logs_note_is_recorded_when_present(): void
    {
        $employee = $this->newDefaultShiftEmployee();
        $date = $this->nextWeekday(Carbon::THURSDAY);

        $this->createAttendanceLog($employee, $date->copy()->setTime(8, 0), AttendanceLog::EVENT_CLOCK_IN, false, 'GPS missing.');
        $this->createAttendanceLog($employee, $date->copy()->setTime(8, 1), AttendanceLog::EVENT_CLOCK_IN);
        $this->createAttendanceLog($employee, $date->copy()->setTime(17, 0), AttendanceLog::EVENT_CLOCK_OUT);

        $summary = $this->attendanceCalculationService->calculateForEmployeeDate($employee, $date);

        $this->assertNotNull($summary->calculation_notes);
        $this->assertStringContainsString('invalid logs ignored for calculation', $summary->calculation_notes);
    }

    public function test_summary_is_company_scoped(): void
    {
        $companyAEmployee = $this->newDefaultShiftEmployee($this->employee('andi.permanent@example.test'));
        $companyBEmployee = $this->newDefaultShiftEmployee($this->employee('rio.outsource@example.test'));
        $date = $this->nextWeekday(Carbon::FRIDAY);

        $companyBSummary = $this->attendanceCalculationService->calculateForEmployeeDate($companyBEmployee, $date);

        $this->assertFalse(
            AttendanceSummary::query()
                ->forCompany($companyAEmployee->company_id)
                ->whereKey($companyBSummary->id)
                ->exists()
        );
    }

    public function test_company_admin_cannot_view_other_company_summary(): void
    {
        Filament::setCurrentPanel('admin');

        $companyAEmployee = $this->newDefaultShiftEmployee($this->employee('andi.permanent@example.test'));
        $companyBEmployee = $this->newDefaultShiftEmployee($this->employee('rio.outsource@example.test'));
        $companyAdmin = $this->makeCompanyAdmin($companyAEmployee);
        $date = $this->nextWeekday(Carbon::MONDAY);

        $companyASummary = $this->attendanceCalculationService->calculateForEmployeeDate($companyAEmployee, $date);
        $companyBSummary = $this->attendanceCalculationService->calculateForEmployeeDate($companyBEmployee, $date);

        Livewire::actingAs($companyAdmin)
            ->test(AdminListAttendanceSummaries::class)
            ->assertCanSeeTableRecords([$companyASummary])
            ->assertCanNotSeeTableRecords([$companyBSummary]);
    }

    public function test_employee_cannot_access_admin_summary_resource(): void
    {
        $employee = $this->employee('andi.permanent@example.test');

        $this->actingAs($employee)
            ->get(AdminAttendanceSummaryResource::getUrl(isAbsolute: false, panel: 'admin'))
            ->assertForbidden();
    }

    public function test_admin_can_view_attendance_summary_resource(): void
    {
        $this->actingAs($this->employee('admin@hrms.local'))
            ->get(AdminAttendanceSummaryResource::getUrl(isAbsolute: false, panel: 'admin'))
            ->assertOk();
    }

    public function test_manual_update_is_not_allowed_by_policy(): void
    {
        $employee = $this->newDefaultShiftEmployee();
        $companyAdmin = $this->makeCompanyAdmin($employee);
        $summary = $this->attendanceCalculationService->calculateForEmployeeDate($employee, $this->nextWeekday(Carbon::TUESDAY));

        $this->assertFalse(Gate::forUser($companyAdmin)->allows('update', $summary));
    }

    public function test_manual_delete_is_not_allowed_by_policy(): void
    {
        $employee = $this->newDefaultShiftEmployee();
        $companyAdmin = $this->makeCompanyAdmin($employee);
        $summary = $this->attendanceCalculationService->calculateForEmployeeDate($employee, $this->nextWeekday(Carbon::WEDNESDAY));

        $this->assertFalse(Gate::forUser($companyAdmin)->allows('delete', $summary));
    }

    public function test_existing_attendance_log_tests_still_pass(): void
    {
        $employee = $this->employee('andi.permanent@example.test');

        $this->assertInstanceOf(
            ShiftPattern::class,
            $this->shiftResolverService->resolveShift($employee, now(config('app.timezone')))
        );

        $log = $this->attendanceLogService->clockIn($employee);

        $this->assertInstanceOf(AttendanceLog::class, $log);
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

        $this->assertTrue(WorkdayPattern::query()->where('company_id', $employee->company_id)->exists());
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

    private function leaveType(int $companyId, string $code): LeaveType
    {
        return LeaveType::query()
            ->where('company_id', $companyId)
            ->where('code', $code)
            ->firstOrFail();
    }

    private function newDefaultShiftEmployee(?Employee $template = null): Employee
    {
        $template ??= $this->employee('andi.permanent@example.test');

        return $this->makeEmployeeFrom($template, [
            'attendance_policy_id' => null,
            'attendance_location_mode_override' => null,
            'branch_id' => null,
            'department_id' => null,
            'division_id' => null,
            'position_id' => null,
            'direct_supervisor_id' => null,
        ]);
    }

    private function newNightShiftEmployee(?Employee $template = null): Employee
    {
        $employee = $this->newDefaultShiftEmployee($template);
        $nightPattern = $this->shiftPattern($employee->company_id, 'NIGHT-OPS');
        $date = now(config('app.timezone'))->startOfDay()->startOfMonth()->toDateString();

        ShiftAssignment::query()->create([
            'company_id' => $employee->company_id,
            'assignable_type' => ShiftAssignment::ASSIGNABLE_TYPE_EMPLOYEE,
            'assignable_id' => $employee->id,
            'shift_pattern_id' => $nightPattern->id,
            'effective_date' => $date,
            'end_date' => null,
        ]);

        return $employee->fresh();
    }

    private function newEmployeeWithPolicy(array $policyOverrides = [], ?Employee $template = null): Employee
    {
        $template ??= $this->employee('andi.permanent@example.test');
        $employee = $this->newDefaultShiftEmployee($template);
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
            'code' => sprintf('TEST-%03d', $this->employeeSequence),
            'name' => 'Attendance Test Policy',
            'location_mode' => AttendancePolicy::LOCATION_MODE_FIXED,
            'gps_required' => false,
            'selfie_required' => false,
            'radius_validation_enabled' => false,
            'radius_meters' => null,
            'late_tolerance_minutes' => 10,
            'early_out_tolerance_minutes' => 10,
            'minimum_work_minutes' => 0,
            'auto_absent_after_minutes' => 120,
            'overtime_threshold_minutes' => null,
            'is_active' => true,
        ], $overrides));
    }

    private function createAttendanceLog(
        Employee $employee,
        Carbon $clockedAt,
        string $eventType,
        bool $isValid = true,
        ?string $validationMessage = null,
    ): AttendanceLog {
        return AttendanceLog::query()->create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'attendance_date' => $clockedAt->toDateString(),
            'event_type' => $eventType,
            'clocked_at' => $clockedAt,
            'source' => AttendanceLog::SOURCE_WEB,
            'is_valid' => $isValid,
            'validation_message' => $validationMessage,
            'created_by' => $employee->id,
        ]);
    }

    private function createApprovedFullDayLeave(Employee $employee, Carbon $date): LeaveRequest
    {
        return LeaveRequest::query()->create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'leave_type_id' => $this->leaveType($employee->company_id, 'ANNUAL')->id,
            'leave_entitlement_id' => null,
            'start_date' => $date->toDateString(),
            'end_date' => $date->toDateString(),
            'is_half_day' => false,
            'half_day_type' => null,
            'requested_days' => 1,
            'reason' => 'Attendance calculation leave coverage.',
            'status' => LeaveRequest::STATUS_APPROVED,
            'submitted_at' => now(config('app.timezone')),
        ]);
    }

    private function createHoliday(int $companyId, Carbon $date, string $name): Holiday
    {
        $calendar = HolidayCalendar::query()
            ->where('company_id', $companyId)
            ->where('year', $date->year)
            ->where('is_active', true)
            ->firstOrFail();

        return Holiday::query()->create([
            'company_id' => $companyId,
            'holiday_calendar_id' => $calendar->id,
            'date' => $date->toDateString(),
            'name' => $name,
            'type' => Holiday::TYPE_COMPANY,
            'is_paid' => true,
        ]);
    }

    private function makeEmployeeFrom(Employee $template, array $overrides = []): Employee
    {
        $sequence = $this->employeeSequence++;

        return Employee::query()->create(array_merge([
            'employee_code' => sprintf('EMP-AS-%03d', $sequence),
            'full_name' => "Attendance Summary User {$sequence}",
            'first_name' => 'Attendance',
            'last_name' => "Summary {$sequence}",
            'email' => sprintf('attendance-summary-%03d@example.test', $sequence),
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
            'email' => sprintf('attendance-summary-admin-%03d@example.test', $this->employeeSequence),
            'employee_code' => sprintf('EMP-AS-ADM-%03d', $this->employeeSequence),
            'full_name' => 'Attendance Summary Company Admin',
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

    private function nextSaturday(): Carbon
    {
        return now('Asia/Jakarta')->next(Carbon::SATURDAY)->startOfDay();
    }
}
