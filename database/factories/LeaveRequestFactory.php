<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveRequest>
 */
class LeaveRequestFactory extends Factory
{
    protected $model = LeaveRequest::class;

    public function definition(): array
    {
        $company = Company::query()->first() ?? Company::findOrCreateDefault();
        $employee = Employee::query()->where('company_id', $company->id)->first()
            ?? Employee::factory()->create([
                'company_id' => $company->id,
                'company_group_id' => $company->company_group_id,
            ]);
        $leaveType = LeaveType::query()->where('company_id', $company->id)->first()
            ?? LeaveType::query()->create([
                'company_id' => $company->id,
                'code' => 'FACTORY-'.strtoupper(fake()->unique()->lexify('????')),
                'name' => fake()->unique()->words(2, true),
                'description' => fake()->sentence(),
                'is_paid' => true,
                'requires_attachment' => false,
                'allow_half_day' => true,
                'allow_carry_forward' => false,
                'is_active' => true,
            ]);

        $startDate = now('Asia/Jakarta')->startOfDay()->addDays(7);
        $endDate = (clone $startDate)->addDay();

        return [
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'leave_entitlement_id' => null,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'is_half_day' => false,
            'half_day_type' => null,
            'requested_days' => 2,
            'reason' => fake()->sentence(),
            'status' => LeaveRequest::STATUS_DRAFT,
            'submitted_at' => null,
            'cancelled_at' => null,
            'cancelled_by' => null,
            'cancellation_reason' => null,
            'notes' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (): array => [
            'status' => LeaveRequest::STATUS_PENDING,
            'submitted_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => [
            'status' => LeaveRequest::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);
    }
}
