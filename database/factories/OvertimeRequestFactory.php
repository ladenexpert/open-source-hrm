<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Employee;
use App\Models\OvertimeRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OvertimeRequest>
 */
class OvertimeRequestFactory extends Factory
{
    protected $model = OvertimeRequest::class;

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
            'attendance_summary_id' => null,
            'overtime_date' => now(config('app.timezone'))->toDateString(),
            'requested_start_at' => null,
            'requested_end_at' => null,
            'requested_minutes' => 60,
            'reason' => fake()->sentence(),
            'status' => OvertimeRequest::STATUS_DRAFT,
            'submitted_at' => null,
            'submitted_by' => null,
            'approved_minutes' => null,
            'approved_by' => null,
            'approved_at' => null,
            'rejected_by' => null,
            'rejected_at' => null,
            'rejection_reason' => null,
            'cancelled_by' => null,
            'cancelled_at' => null,
            'cancellation_reason' => null,
            'approval_request_id' => null,
            'metadata' => null,
            'created_by' => $employee->id,
            'updated_by' => $employee->id,
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OvertimeRequest::STATUS_SUBMITTED,
            'submitted_at' => now(config('app.timezone')),
            'submitted_by' => $attributes['employee_id'],
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OvertimeRequest::STATUS_APPROVED,
            'submitted_at' => now(config('app.timezone'))->subHour(),
            'submitted_by' => $attributes['employee_id'],
            'approved_minutes' => $attributes['requested_minutes'],
            'approved_at' => now(config('app.timezone')),
            'approved_by' => $attributes['employee_id'],
        ]);
    }
}
