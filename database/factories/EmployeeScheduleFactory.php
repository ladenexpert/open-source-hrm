<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\ShiftPattern;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeSchedule>
 */
class EmployeeScheduleFactory extends Factory
{
    protected $model = EmployeeSchedule::class;

    public function definition(): array
    {
        $employeeId = Employee::query()->value('id') ?? Employee::factory()->create()->id;
        $companyId = Employee::query()->whereKey($employeeId)->value('company_id')
            ?? Company::query()->value('id')
            ?? Company::findOrCreateDefault()->id;

        return [
            'company_id' => $companyId,
            'employee_id' => $employeeId,
            'schedule_date' => now()->addWeek()->toDateString(),
            'shift_pattern_id' => ShiftPattern::query()->forCompany($companyId)->value('id') ?? ShiftPattern::factory()->create(['company_id' => $companyId])->id,
            'work_location_id' => null,
            'override_reason' => EmployeeSchedule::OVERRIDE_REASON_TEMPORARY,
            'requested_by' => $employeeId,
            'approved_by' => $employeeId,
            'notes' => fake()->sentence(),
        ];
    }
}
