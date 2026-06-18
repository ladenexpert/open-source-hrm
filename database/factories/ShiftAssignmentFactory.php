<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Employee;
use App\Models\ShiftAssignment;
use App\Models\ShiftPattern;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShiftAssignment>
 */
class ShiftAssignmentFactory extends Factory
{
    protected $model = ShiftAssignment::class;

    public function definition(): array
    {
        $employeeId = Employee::query()->value('id') ?? Employee::factory()->create()->id;
        $companyId = Employee::query()->whereKey($employeeId)->value('company_id')
            ?? Company::query()->value('id')
            ?? Company::findOrCreateDefault()->id;

        return [
            'company_id' => $companyId,
            'assignable_type' => ShiftAssignment::ASSIGNABLE_TYPE_EMPLOYEE,
            'assignable_id' => $employeeId,
            'shift_pattern_id' => ShiftPattern::query()->forCompany($companyId)->value('id') ?? ShiftPattern::factory()->create(['company_id' => $companyId])->id,
            'effective_date' => now()->startOfMonth()->toDateString(),
            'end_date' => null,
            'work_location_id' => null,
            'assigned_by' => $employeeId,
            'notes' => fake()->sentence(),
        ];
    }
}
