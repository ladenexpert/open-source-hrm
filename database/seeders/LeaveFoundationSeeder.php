<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Holiday;
use App\Models\HolidayCalendar;
use App\Models\LeavePolicy;
use App\Models\LeaveType;
use App\Models\WorkdayPattern;
use Illuminate\Database\Seeder;

class LeaveFoundationSeeder extends Seeder
{
    public function run(): void
    {
        $currentYear = now('Asia/Jakarta')->year;

        Company::query()
            ->orderBy('id')
            ->each(function (Company $company) use ($currentYear): void {
                $leaveTypes = $this->seedLeaveTypes($company);
                $this->seedLeavePolicies($company, $leaveTypes, $currentYear);
                $this->seedHolidayCalendar($company, $currentYear);
                $this->seedWorkdayPattern($company);
            });
    }

    private function seedLeaveTypes(Company $company): array
    {
        $records = [
            [
                'code' => 'ANNUAL',
                'name' => 'Annual Leave / Cuti Tahunan',
                'description' => 'Standard annual paid leave entitlement.',
                'is_paid' => true,
                'requires_attachment' => false,
                'allow_half_day' => true,
                'allow_carry_forward' => true,
                'max_carry_forward_days' => 6,
            ],
            [
                'code' => 'SICK',
                'name' => 'Sick Leave / Cuti Sakit',
                'description' => 'Paid sick leave that requires supporting evidence.',
                'is_paid' => true,
                'requires_attachment' => true,
                'allow_half_day' => false,
                'allow_carry_forward' => false,
                'max_carry_forward_days' => null,
            ],
            [
                'code' => 'MARRIAGE',
                'name' => 'Marriage Leave / Cuti Menikah',
                'description' => 'Paid ceremonial leave for marriage events.',
                'is_paid' => true,
                'requires_attachment' => true,
                'allow_half_day' => false,
                'allow_carry_forward' => false,
                'max_carry_forward_days' => null,
            ],
            [
                'code' => 'MATERNITY',
                'name' => 'Maternity Leave / Cuti Melahirkan',
                'description' => 'Protected maternity leave baseline.',
                'is_paid' => true,
                'requires_attachment' => true,
                'allow_half_day' => false,
                'allow_carry_forward' => false,
                'max_carry_forward_days' => null,
            ],
            [
                'code' => 'UNPAID',
                'name' => 'Unpaid Leave / Cuti Tidak Dibayar',
                'description' => 'Unpaid leave for special cases.',
                'is_paid' => false,
                'requires_attachment' => false,
                'allow_half_day' => true,
                'allow_carry_forward' => false,
                'max_carry_forward_days' => null,
            ],
        ];

        $leaveTypes = [];

        foreach ($records as $record) {
            $leaveTypes[$record['code']] = LeaveType::query()->updateOrCreate(
                [
                    'company_id' => $company->id,
                    'code' => $record['code'],
                ],
                array_merge($record, [
                    'is_active' => true,
                ]),
            );
        }

        return $leaveTypes;
    }

    private function seedLeavePolicies(Company $company, array $leaveTypes, int $currentYear): void
    {
        $policyStartDate = "{$currentYear}-01-01";

        $records = [
            [
                'leave_type_id' => $leaveTypes['ANNUAL']->id,
                'entitlement_days' => 12,
                'minimum_service_months' => 12,
            ],
            [
                'leave_type_id' => $leaveTypes['SICK']->id,
                'entitlement_days' => 0,
                'minimum_service_months' => 0,
            ],
            [
                'leave_type_id' => $leaveTypes['UNPAID']->id,
                'entitlement_days' => 0,
                'minimum_service_months' => 0,
            ],
        ];

        foreach ($records as $record) {
            LeavePolicy::query()->updateOrCreate(
                [
                    'company_id' => $company->id,
                    'leave_type_id' => $record['leave_type_id'],
                    'employment_status_id' => null,
                    'job_level_id' => null,
                    'effective_from' => $policyStartDate,
                ],
                [
                    'entitlement_days' => $record['entitlement_days'],
                    'minimum_service_months' => $record['minimum_service_months'],
                    'effective_until' => null,
                    'is_active' => true,
                ],
            );
        }
    }

    private function seedHolidayCalendar(Company $company, int $currentYear): void
    {
        $calendar = HolidayCalendar::query()->updateOrCreate(
            [
                'company_id' => $company->id,
                'year' => $currentYear,
                'name' => "Indonesia {$currentYear}",
            ],
            [
                'description' => 'Starter holiday calendar for Indonesian operations.',
                'is_active' => true,
            ],
        );

        $holidays = [
            [
                'date' => "{$currentYear}-01-01",
                'name' => 'New Year Day',
                'type' => Holiday::TYPE_NATIONAL,
                'is_paid' => true,
            ],
            [
                'date' => "{$currentYear}-05-01",
                'name' => 'Labour Day',
                'type' => Holiday::TYPE_NATIONAL,
                'is_paid' => true,
            ],
            [
                'date' => "{$currentYear}-08-17",
                'name' => 'Independence Day',
                'type' => Holiday::TYPE_NATIONAL,
                'is_paid' => true,
            ],
            [
                'date' => "{$currentYear}-12-24",
                'name' => 'Year-End Collective Leave',
                'type' => Holiday::TYPE_COLLECTIVE_LEAVE,
                'is_paid' => true,
            ],
            [
                'date' => "{$currentYear}-12-25",
                'name' => 'Christmas Day',
                'type' => Holiday::TYPE_NATIONAL,
                'is_paid' => true,
            ],
        ];

        foreach ($holidays as $holiday) {
            Holiday::query()->updateOrCreate(
                [
                    'holiday_calendar_id' => $calendar->id,
                    'date' => $holiday['date'],
                    'name' => $holiday['name'],
                ],
                array_merge($holiday, [
                    'company_id' => $company->id,
                ]),
            );
        }
    }

    private function seedWorkdayPattern(Company $company): void
    {
        $pattern = WorkdayPattern::query()->updateOrCreate(
            [
                'company_id' => $company->id,
                'name' => 'Default Monday-Friday',
            ],
            [
                'description' => 'Standard five-day work week baseline.',
                'is_default' => true,
                'is_active' => true,
            ],
        );

        foreach (range(1, 7) as $dayOfWeek) {
            $isWorkingDay = $dayOfWeek <= 5;

            $pattern->days()->updateOrCreate(
                ['day_of_week' => $dayOfWeek],
                [
                    'is_working_day' => $isWorkingDay,
                    'working_hours' => $isWorkingDay ? 8 : null,
                ],
            );
        }
    }
}
