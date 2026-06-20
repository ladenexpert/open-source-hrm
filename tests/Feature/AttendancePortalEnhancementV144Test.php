<?php

namespace Tests\Feature;

use App\Filament\Employee\Pages\MyAttendance;
use App\Filament\Employee\Resources\AttendanceCorrections\Pages\CreateAttendanceCorrection as PortalCreateAttendanceCorrection;
use App\Filament\Employee\Resources\AttendanceSummaries\AttendanceSummaryResource as PortalAttendanceSummaryResource;
use App\Filament\Employee\Resources\AttendanceSummaries\Pages\ListAttendanceSummaries as PortalListAttendanceSummaries;
use App\Models\AttendanceCorrection;
use App\Models\AttendanceSummary;
use App\Models\Company;
use App\Models\Employee;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AttendancePortalEnhancementV144Test extends TestCase
{
    use RefreshDatabase;

    private int $employeeSequence = 1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_employee_can_access_own_attendance_dashboard(): void
    {
        $employee = $this->makeEmployee('employee');

        $this->actingAs($employee)
            ->get(MyAttendance::getUrl(panel: 'portal'))
            ->assertOk();
    }

    public function test_dashboard_shows_today_attendance_summary_when_available(): void
    {
        $employee = $this->makeEmployee('employee');
        $otherEmployee = $this->makeEmployee('employee', [
            'email' => 'portal-attendance-other@example.test',
        ]);
        $today = now(config('app.timezone'))->startOfDay();

        $ownSummary = $this->createAttendanceSummary($employee, $today, [
            'status' => AttendanceSummary::STATUS_LATE,
            'actual_in_at' => $today->copy()->setTime(8, 15),
            'actual_out_at' => $today->copy()->setTime(17, 10),
            'late_minutes' => 15,
            'work_minutes' => 475,
        ]);

        $this->createAttendanceSummary($otherEmployee, $today, [
            'status' => AttendanceSummary::STATUS_ABSENT,
            'work_minutes' => 999,
        ]);

        $this->actingAs($employee)
            ->get(MyAttendance::getUrl(panel: 'portal'))
            ->assertOk()
            ->assertSee($ownSummary->attendance_date->toFormattedDateString())
            ->assertSee('Late')
            ->assertSee('08:15')
            ->assertSee('17:10')
            ->assertSee('475')
            ->assertDontSeeHtml('>999<');
    }

    public function test_dashboard_only_displays_authenticated_employee_recent_attendance_data(): void
    {
        $employee = $this->makeEmployee('employee');
        $otherEmployee = $this->makeEmployee('employee', [
            'email' => 'portal-attendance-scope@example.test',
        ]);
        $today = now(config('app.timezone'))->startOfDay();

        $ownRecentSummary = $this->createAttendanceSummary($employee, $today->copy()->subDay(), [
            'status' => AttendanceSummary::STATUS_PRESENT,
            'work_minutes' => 480,
        ]);

        $otherRecentSummary = $this->createAttendanceSummary($otherEmployee, $today->copy()->subDays(2), [
            'status' => AttendanceSummary::STATUS_PRESENT,
            'work_minutes' => 777,
        ]);

        $this->actingAs($employee)
            ->get(MyAttendance::getUrl(panel: 'portal'))
            ->assertOk()
            ->assertSee($ownRecentSummary->attendance_date->toDateString())
            ->assertDontSee($otherRecentSummary->attendance_date->toDateString())
            ->assertSee((string) $ownRecentSummary->work_minutes);
    }

    public function test_employee_can_view_own_attendance_history(): void
    {
        $employee = $this->makeEmployee('employee');

        $this->actingAs($employee)
            ->get(PortalAttendanceSummaryResource::getUrl(isAbsolute: false, panel: 'portal'))
            ->assertOk();
    }

    public function test_history_only_returns_authenticated_employee_records(): void
    {
        Filament::setCurrentPanel('portal');

        $employee = $this->makeEmployee('employee');
        $otherEmployee = $this->makeEmployee('employee', [
            'email' => 'portal-history-other@example.test',
        ]);
        $today = now(config('app.timezone'))->startOfDay();

        $ownSummary = $this->createAttendanceSummary($employee, $today);
        $otherSummary = $this->createAttendanceSummary($otherEmployee, $today->copy()->subDay());

        Livewire::actingAs($employee)
            ->test(PortalListAttendanceSummaries::class)
            ->assertCanSeeTableRecords([$ownSummary])
            ->assertCanNotSeeTableRecords([$otherSummary]);
    }

    public function test_employee_can_initiate_correction_request_only_for_own_attendance_record(): void
    {
        Filament::setCurrentPanel('portal');

        $employee = $this->makeEmployee('employee');
        $today = now(config('app.timezone'))->startOfDay();
        $ownSummary = $this->createAttendanceSummary($employee, $today, [
            'work_minutes' => 450,
        ]);

        Livewire::withQueryParams([
            'attendance_summary' => $ownSummary->id,
        ])
            ->actingAs($employee)
            ->test(PortalCreateAttendanceCorrection::class)
            ->assertFormSet([
                'attendance_date' => $ownSummary->attendance_date->toDateString(),
            ])
            ->fillForm([
                'attendance_date' => $ownSummary->attendance_date->toDateString(),
                'correction_type' => AttendanceCorrection::TYPE_MISSING_CLOCK_OUT,
                'reason' => 'Forgot to clock out from the portal history.',
                'requested_clock_out_at' => $today->copy()->setTime(17, 5)->toDateTimeString(),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $correction = AttendanceCorrection::query()->latest('id')->firstOrFail();

        $this->assertSame($employee->id, $correction->employee_id);
        $this->assertSame($ownSummary->id, $correction->attendance_summary_id);
        $this->assertSame(AttendanceCorrection::STATUS_DRAFT, $correction->status);
    }

    public function test_employee_cannot_initiate_correction_request_for_another_employees_attendance_record(): void
    {
        Filament::setCurrentPanel('portal');

        $employee = $this->makeEmployee('employee');
        $otherEmployee = $this->makeEmployee('employee', [
            'email' => 'portal-correction-other@example.test',
        ]);
        $today = now(config('app.timezone'))->startOfDay();
        $otherSummary = $this->createAttendanceSummary($otherEmployee, $today);

        Livewire::withQueryParams([
            'attendance_summary' => $otherSummary->id,
        ])
            ->actingAs($employee)
            ->test(PortalCreateAttendanceCorrection::class)
            ->fillForm([
                'attendance_date' => $otherSummary->attendance_date->toDateString(),
                'correction_type' => AttendanceCorrection::TYPE_MANUAL_ADJUSTMENT,
                'reason' => 'Attempted cross-employee correction request.',
                'requested_clock_in_at' => $today->copy()->setTime(8, 0)->toDateTimeString(),
                'requested_clock_out_at' => $today->copy()->setTime(17, 0)->toDateTimeString(),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $correction = AttendanceCorrection::query()->latest('id')->firstOrFail();

        $this->assertSame($employee->id, $correction->employee_id);
        $this->assertNotSame($otherEmployee->id, $correction->employee_id);
        $this->assertNull($correction->attendance_summary_id);
    }

    private function makeEmployee(string $role, array $attributes = []): Employee
    {
        $sequence = $this->employeeSequence++;
        $company = Company::query()->where('code', Company::DEFAULT_CODE)->firstOrFail();

        $employee = Employee::query()->create(array_merge([
            'employee_code' => sprintf('EMP-V144-%03d', $sequence),
            'full_name' => "Attendance Portal {$sequence}",
            'first_name' => 'Attendance',
            'last_name' => "Portal {$sequence}",
            'email' => sprintf('attendance-portal-%03d@example.test', $sequence),
            'company_id' => $company->id,
            'company_group_id' => $company->company_group_id,
            'employment_type' => 'Permanent',
            'hire_date' => now(config('app.timezone'))->toDateString(),
            'join_date' => now(config('app.timezone'))->toDateString(),
            'is_active' => true,
            'password' => 'password123',
        ], $attributes));

        $employee->syncRoles([$role]);

        return $employee;
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
}
