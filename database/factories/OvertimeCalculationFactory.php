<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Employee;
use App\Models\OvertimeCalculation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OvertimeCalculation>
 */
class OvertimeCalculationFactory extends Factory
{
    protected $model = OvertimeCalculation::class;

    public function definition(): array
    {
        $company = Company::query()->first() ?? Company::findOrCreateDefault();
        $employee = Employee::query()->where('company_id', $company->id)->first()
            ?? Employee::factory()->create([
                'company_id' => $company->id,
                'company_group_id' => $company->company_group_id,
            ]);

        return [
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'overtime_request_id' => null,
            'attendance_summary_id' => null,
            'calculation_date' => now(config('app.timezone'))->toDateString(),
            'scheduled_end_at' => null,
            'actual_clock_out_at' => null,
            'actual_overtime_minutes' => 0,
            'requested_minutes' => null,
            'approved_minutes' => null,
            'calculated_minutes' => 0,
            'calculation_status' => OvertimeCalculation::STATUS_DRAFT,
            'calculated_at' => null,
            'metadata' => null,
        ];
    }
}
