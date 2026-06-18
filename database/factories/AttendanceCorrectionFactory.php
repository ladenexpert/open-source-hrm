<?php

namespace Database\Factories;

use App\Models\AttendanceCorrection;
use App\Models\Company;
use App\Models\Employee;
use App\Models\WorkLocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceCorrection>
 */
class AttendanceCorrectionFactory extends Factory
{
    protected $model = AttendanceCorrection::class;

    public function definition(): array
    {
        $company = Company::query()->first() ?? Company::findOrCreateDefault();
        $employee = Employee::query()->where('company_id', $company->id)->first()
            ?? Employee::factory()->create([
                'company_id' => $company->id,
                'company_group_id' => $company->company_group_id,
            ]);
        $workLocationId = WorkLocation::query()
            ->where('company_id', $company->id)
            ->value('id');
        $attendanceDate = now(config('app.timezone'))->toDateString();

        return [
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'attendance_summary_id' => null,
            'attendance_date' => $attendanceDate,
            'correction_type' => AttendanceCorrection::TYPE_MISSING_CLOCK_OUT,
            'reason' => fake()->sentence(),
            'requested_clock_in_at' => null,
            'requested_clock_out_at' => null,
            'requested_work_location_id' => $workLocationId,
            'requested_notes' => null,
            'approved_clock_in_at' => null,
            'approved_clock_out_at' => null,
            'approved_work_location_id' => null,
            'approved_notes' => null,
            'status' => AttendanceCorrection::STATUS_DRAFT,
            'submitted_at' => null,
            'submitted_by' => null,
            'approved_at' => null,
            'approved_by' => null,
            'rejected_at' => null,
            'rejected_by' => null,
            'cancelled_at' => null,
            'cancelled_by' => null,
            'approval_request_id' => null,
            'created_by' => $employee->id,
            'updated_by' => $employee->id,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AttendanceCorrection::STATUS_PENDING,
            'submitted_at' => now(config('app.timezone')),
            'submitted_by' => $attributes['employee_id'],
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AttendanceCorrection::STATUS_APPROVED,
            'submitted_at' => now(config('app.timezone'))->subDay(),
            'submitted_by' => $attributes['employee_id'],
            'approved_at' => now(config('app.timezone')),
            'approved_by' => $attributes['employee_id'],
            'approved_clock_in_at' => $attributes['requested_clock_in_at'],
            'approved_clock_out_at' => $attributes['requested_clock_out_at'],
            'approved_work_location_id' => $attributes['requested_work_location_id'],
            'approved_notes' => $attributes['requested_notes'],
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AttendanceCorrection::STATUS_REJECTED,
            'submitted_at' => now(config('app.timezone'))->subDay(),
            'submitted_by' => $attributes['employee_id'],
            'rejected_at' => now(config('app.timezone')),
            'rejected_by' => $attributes['employee_id'],
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AttendanceCorrection::STATUS_CANCELLED,
            'cancelled_at' => now(config('app.timezone')),
            'cancelled_by' => $attributes['employee_id'],
        ]);
    }
}
