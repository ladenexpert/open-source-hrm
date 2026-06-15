<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Holiday;
use App\Models\HolidayCalendar;
use App\Models\LeavePolicy;
use App\Models\LeaveType;
use App\Models\WorkdayPattern;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LeaveFoundationSprint4ATest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_leave_foundation_tables_migrate_successfully(): void
    {
        $this->assertTrue(Schema::hasTable('leave_types'));
        $this->assertTrue(Schema::hasTable('leave_policies'));
        $this->assertTrue(Schema::hasTable('holiday_calendars'));
        $this->assertTrue(Schema::hasTable('holidays'));
        $this->assertTrue(Schema::hasTable('workday_patterns'));
        $this->assertTrue(Schema::hasTable('workday_pattern_days'));
    }

    public function test_leave_type_creation_respects_company_isolation(): void
    {
        $companyA = $this->company(Company::DEFAULT_CODE);
        $companyB = $this->company('SUB-A');

        $leaveTypeA = LeaveType::query()->create([
            'company_id' => $companyA->id,
            'code' => 'SPECIAL-A',
            'name' => 'Special Leave A',
            'is_paid' => true,
            'requires_attachment' => false,
            'allow_half_day' => true,
            'allow_carry_forward' => false,
            'is_active' => true,
        ]);

        $leaveTypeB = LeaveType::query()->create([
            'company_id' => $companyB->id,
            'code' => 'SPECIAL-B',
            'name' => 'Special Leave B',
            'is_paid' => true,
            'requires_attachment' => false,
            'allow_half_day' => true,
            'allow_carry_forward' => false,
            'is_active' => true,
        ]);

        $this->assertTrue(LeaveType::query()->forCompany($companyA->id)->whereKey($leaveTypeA->id)->exists());
        $this->assertFalse(LeaveType::query()->forCompany($companyA->id)->whereKey($leaveTypeB->id)->exists());
        $this->assertTrue(LeaveType::query()->forCompany($companyB->id)->whereKey($leaveTypeB->id)->exists());
    }

    public function test_duplicate_leave_type_code_is_blocked_within_same_company(): void
    {
        $company = $this->company(Company::DEFAULT_CODE);

        LeaveType::query()->create([
            'company_id' => $company->id,
            'code' => 'DUPLICATE',
            'name' => 'Duplicate One',
            'is_paid' => true,
            'requires_attachment' => false,
            'allow_half_day' => true,
            'allow_carry_forward' => false,
            'is_active' => true,
        ]);

        $this->expectException(QueryException::class);

        LeaveType::query()->create([
            'company_id' => $company->id,
            'code' => 'DUPLICATE',
            'name' => 'Duplicate Two',
            'is_paid' => false,
            'requires_attachment' => false,
            'allow_half_day' => false,
            'allow_carry_forward' => false,
            'is_active' => true,
        ]);
    }

    public function test_leave_policy_belongs_to_leave_type(): void
    {
        $company = $this->company(Company::DEFAULT_CODE);
        $leaveType = LeaveType::query()->create([
            'company_id' => $company->id,
            'code' => 'BEREAVEMENT',
            'name' => 'Bereavement Leave',
            'is_paid' => true,
            'requires_attachment' => true,
            'allow_half_day' => false,
            'allow_carry_forward' => false,
            'is_active' => true,
        ]);

        $leavePolicy = LeavePolicy::query()->create([
            'company_id' => $company->id,
            'leave_type_id' => $leaveType->id,
            'entitlement_days' => 3,
            'minimum_service_months' => 0,
            'effective_from' => now()->startOfYear()->toDateString(),
            'is_active' => true,
        ]);

        $this->assertSame($leaveType->id, $leavePolicy->leaveType->id);
        $this->assertTrue($leaveType->leavePolicies->contains('id', $leavePolicy->id));
    }

    public function test_holiday_calendar_can_contain_holidays(): void
    {
        $company = $this->company(Company::DEFAULT_CODE);
        $calendar = HolidayCalendar::query()->create([
            'company_id' => $company->id,
            'name' => 'Special Calendar',
            'year' => now('Asia/Jakarta')->year,
            'is_active' => true,
        ]);

        $holiday = Holiday::query()->create([
            'company_id' => $company->id,
            'holiday_calendar_id' => $calendar->id,
            'date' => now('Asia/Jakarta')->startOfYear()->toDateString(),
            'name' => 'Special Closure',
            'type' => Holiday::TYPE_COMPANY,
            'is_paid' => true,
        ]);

        $this->assertSame($calendar->id, $holiday->holidayCalendar->id);
        $this->assertCount(1, $calendar->holidays);
    }

    public function test_workday_pattern_has_seven_days(): void
    {
        $company = $this->company(Company::DEFAULT_CODE);
        $pattern = WorkdayPattern::query()->create([
            'company_id' => $company->id,
            'name' => 'Six Day Shift',
            'description' => 'Custom operational pattern.',
            'is_default' => false,
            'is_active' => true,
        ]);

        foreach (range(1, 7) as $dayOfWeek) {
            $pattern->days()->create([
                'day_of_week' => $dayOfWeek,
                'is_working_day' => $dayOfWeek <= 6,
                'working_hours' => $dayOfWeek <= 6 ? 8 : null,
            ]);
        }

        $this->assertCount(7, $pattern->fresh()->days);
    }

    public function test_default_monday_to_friday_pattern_is_created_by_seeder(): void
    {
        $company = $this->company(Company::DEFAULT_CODE);
        $pattern = WorkdayPattern::query()
            ->where('company_id', $company->id)
            ->where('name', 'Default Monday-Friday')
            ->where('is_default', true)
            ->firstOrFail();

        $days = $pattern->days()->get()->keyBy('day_of_week');

        $this->assertCount(7, $days);

        foreach (range(1, 5) as $dayOfWeek) {
            $this->assertTrue($days[$dayOfWeek]->is_working_day);
            $this->assertSame('8.00', $days[$dayOfWeek]->working_hours);
        }

        $this->assertFalse($days[6]->is_working_day);
        $this->assertFalse($days[7]->is_working_day);
    }

    public function test_company_isolation_works_for_leave_foundation_tables(): void
    {
        $companyA = $this->company(Company::DEFAULT_CODE);
        $companyB = $this->company('SUB-A');

        $leaveTypeA = LeaveType::query()->create([
            'company_id' => $companyA->id,
            'code' => 'ISO-A',
            'name' => 'Isolation A',
            'is_paid' => true,
            'requires_attachment' => false,
            'allow_half_day' => true,
            'allow_carry_forward' => false,
            'is_active' => true,
        ]);

        $leavePolicyA = LeavePolicy::query()->create([
            'company_id' => $companyA->id,
            'leave_type_id' => $leaveTypeA->id,
            'entitlement_days' => 1,
            'minimum_service_months' => 0,
            'effective_from' => now()->startOfYear()->toDateString(),
            'is_active' => true,
        ]);

        $calendarA = HolidayCalendar::query()->create([
            'company_id' => $companyA->id,
            'name' => 'Isolation Calendar A',
            'year' => now('Asia/Jakarta')->year,
            'is_active' => true,
        ]);

        $holidayA = Holiday::query()->create([
            'company_id' => $companyA->id,
            'holiday_calendar_id' => $calendarA->id,
            'date' => now('Asia/Jakarta')->startOfYear()->addDays(2)->toDateString(),
            'name' => 'Isolation Holiday A',
            'type' => Holiday::TYPE_OTHER,
            'is_paid' => false,
        ]);

        $patternA = WorkdayPattern::query()->create([
            'company_id' => $companyA->id,
            'name' => 'Isolation Pattern A',
            'is_default' => false,
            'is_active' => true,
        ]);

        $this->assertTrue(LeaveType::query()->forCompany($companyA->id)->whereKey($leaveTypeA->id)->exists());
        $this->assertFalse(LeaveType::query()->forCompany($companyB->id)->whereKey($leaveTypeA->id)->exists());

        $this->assertTrue(LeavePolicy::query()->forCompany($companyA->id)->whereKey($leavePolicyA->id)->exists());
        $this->assertFalse(LeavePolicy::query()->forCompany($companyB->id)->whereKey($leavePolicyA->id)->exists());

        $this->assertTrue(HolidayCalendar::query()->forCompany($companyA->id)->whereKey($calendarA->id)->exists());
        $this->assertFalse(HolidayCalendar::query()->forCompany($companyB->id)->whereKey($calendarA->id)->exists());

        $this->assertTrue(Holiday::query()->forCompany($companyA->id)->whereKey($holidayA->id)->exists());
        $this->assertFalse(Holiday::query()->forCompany($companyB->id)->whereKey($holidayA->id)->exists());

        $this->assertTrue(WorkdayPattern::query()->forCompany($companyA->id)->whereKey($patternA->id)->exists());
        $this->assertFalse(WorkdayPattern::query()->forCompany($companyB->id)->whereKey($patternA->id)->exists());
    }

    private function company(string $code): Company
    {
        return Company::query()->where('code', $code)->firstOrFail();
    }
}
