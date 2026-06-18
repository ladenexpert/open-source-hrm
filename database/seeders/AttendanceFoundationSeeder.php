<?php

namespace Database\Seeders;

use App\Models\AttendancePolicy;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\ShiftAssignment;
use App\Models\ShiftPattern;
use App\Models\WorkLocation;
use Illuminate\Database\Seeder;

class AttendanceFoundationSeeder extends Seeder
{
    public function run(): void
    {
        $today = now('Asia/Jakarta')->startOfDay();
        $periodStart = $today->copy()->startOfMonth()->toDateString();

        Company::query()
            ->orderBy('id')
            ->each(function (Company $company) use ($today, $periodStart): void {
                $policies = $this->seedPolicies($company);
                $patterns = $this->seedShiftPatterns($company);

                $company->forceFill([
                    'default_attendance_policy_id' => $policies['office']->id,
                    'default_shift_pattern_id' => $patterns['office']->id,
                ])->save();

                $this->seedAssignmentsAndSchedules($company, $policies, $patterns, $today, $periodStart);
            });
    }

    /**
     * @return array<string, AttendancePolicy>
     */
    private function seedPolicies(Company $company): array
    {
        $officePolicy = AttendancePolicy::query()->updateOrCreate(
            [
                'company_id' => $company->id,
                'code' => 'OFFICE',
            ],
            [
                'name' => 'Office Policy',
                'location_mode' => AttendancePolicy::LOCATION_MODE_FIXED,
                'gps_required' => true,
                'selfie_required' => false,
                'radius_validation_enabled' => true,
                'radius_meters' => 100,
                'late_tolerance_minutes' => 10,
                'early_out_tolerance_minutes' => 10,
                'minimum_work_minutes' => 420,
                'auto_absent_after_minutes' => 120,
                'overtime_threshold_minutes' => 60,
                'is_active' => true,
            ],
        );

        $fieldPolicy = AttendancePolicy::query()->updateOrCreate(
            [
                'company_id' => $company->id,
                'code' => 'FIELD',
            ],
            [
                'name' => 'Field Policy',
                'location_mode' => AttendancePolicy::LOCATION_MODE_FLEXIBLE,
                'gps_required' => true,
                'selfie_required' => false,
                'radius_validation_enabled' => false,
                'radius_meters' => null,
                'late_tolerance_minutes' => 15,
                'early_out_tolerance_minutes' => 15,
                'minimum_work_minutes' => 360,
                'auto_absent_after_minutes' => 180,
                'overtime_threshold_minutes' => 90,
                'is_active' => true,
            ],
        );

        return [
            'office' => $officePolicy,
            'field' => $fieldPolicy,
        ];
    }

    /**
     * @return array<string, ShiftPattern>
     */
    private function seedShiftPatterns(Company $company): array
    {
        $officePattern = ShiftPattern::query()->updateOrCreate(
            [
                'company_id' => $company->id,
                'code' => 'OFFICE-DAY',
            ],
            [
                'name' => 'Office Day Shift',
                'description' => 'Standard office schedule for weekdays.',
                'is_overnight' => false,
                'color' => '#1D4ED8',
                'is_active' => true,
            ],
        );

        $nightPattern = ShiftPattern::query()->updateOrCreate(
            [
                'company_id' => $company->id,
                'code' => 'NIGHT-OPS',
            ],
            [
                'name' => 'Night Operations Shift',
                'description' => 'Overnight operational coverage.',
                'is_overnight' => true,
                'color' => '#0F766E',
                'is_active' => true,
            ],
        );

        foreach (range(1, 7) as $dayOfWeek) {
            $officePattern->details()->updateOrCreate(
                ['day_of_week' => $dayOfWeek],
                [
                    'company_id' => $company->id,
                    'start_time' => $dayOfWeek <= 4 ? '08:00' : ($dayOfWeek === 5 ? '08:00' : null),
                    'end_time' => $dayOfWeek <= 4 ? '17:00' : ($dayOfWeek === 5 ? '16:30' : null),
                    'break_duration_minutes' => $dayOfWeek <= 5 ? 60 : 0,
                    'is_working_day' => $dayOfWeek <= 5,
                ],
            );

            $nightPattern->details()->updateOrCreate(
                ['day_of_week' => $dayOfWeek],
                [
                    'company_id' => $company->id,
                    'start_time' => $dayOfWeek <= 5 ? '22:00' : null,
                    'end_time' => $dayOfWeek <= 5 ? '06:00' : null,
                    'break_duration_minutes' => $dayOfWeek <= 5 ? 60 : 0,
                    'is_working_day' => $dayOfWeek <= 5,
                ],
            );
        }

        $officePattern->refresh()->syncOvernightFlag();
        $nightPattern->refresh()->syncOvernightFlag();

        return [
            'office' => $officePattern->refresh(),
            'night' => $nightPattern->refresh(),
        ];
    }

    /**
     * @param array<string, AttendancePolicy> $policies
     * @param array<string, ShiftPattern> $patterns
     */
    private function seedAssignmentsAndSchedules(
        Company $company,
        array $policies,
        array $patterns,
        \Carbon\Carbon $today,
        string $periodStart,
    ): void {
        $employees = Employee::query()
            ->where('company_id', $company->id)
            ->orderBy('id')
            ->get();

        if ($employees->isEmpty()) {
            return;
        }

        $firstWorkLocationId = WorkLocation::query()
            ->where('company_id', $company->id)
            ->orderBy('id')
            ->value('id');
        $assignedByEmployeeId = $employees->first()?->id;

        $employees->each(function (Employee $employee, int $index) use ($company, $policies, $patterns, $periodStart, $firstWorkLocationId, $assignedByEmployeeId): void {
            $employee->forceFill([
                'attendance_policy_id' => $index === 0 ? $policies['field']->id : null,
            ])->save();

            ShiftAssignment::query()->updateOrCreate(
                [
                    'company_id' => $company->id,
                    'assignable_type' => ShiftAssignment::ASSIGNABLE_TYPE_EMPLOYEE,
                    'assignable_id' => $employee->id,
                    'effective_date' => $periodStart,
                ],
                [
                    'shift_pattern_id' => $index === 0 ? $patterns['night']->id : $patterns['office']->id,
                    'end_date' => null,
                    'work_location_id' => $firstWorkLocationId,
                    'assigned_by' => $assignedByEmployeeId,
                    'notes' => 'Seeded attendance foundation assignment.',
                ],
            );
        });

        $exceptionEmployee = $employees->first();

        EmployeeSchedule::query()->updateOrCreate(
            [
                'company_id' => $company->id,
                'employee_id' => $exceptionEmployee->id,
                'schedule_date' => $today->copy()->next(\Carbon\Carbon::TUESDAY)->toDateString(),
            ],
            [
                'shift_pattern_id' => $patterns['office']->id,
                'work_location_id' => $firstWorkLocationId,
                'override_reason' => EmployeeSchedule::OVERRIDE_REASON_HR_OVERRIDE,
                'requested_by' => $exceptionEmployee->id,
                'approved_by' => $exceptionEmployee->id,
                'notes' => 'Seeded attendance schedule override.',
            ],
        );
    }
}
