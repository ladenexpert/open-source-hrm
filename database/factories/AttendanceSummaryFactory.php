<?php

namespace Database\Factories;

use App\Models\AttendanceSummary;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceSummary>
 */
class AttendanceSummaryFactory extends Factory
{
    protected $model = AttendanceSummary::class;

    public function definition(): array
    {
        $employeeId = Employee::query()->value('id') ?? Employee::factory()->create()->id;
        $companyId = Employee::query()->whereKey($employeeId)->value('company_id');
        $attendanceDate = now(config('app.timezone'))->toDateString();

        return [
            'company_id' => $companyId,
            'employee_id' => $employeeId,
            'attendance_date' => $attendanceDate,
            'shift_pattern_id' => null,
            'shift_pattern_detail_id' => null,
            'shift_assignment_id' => null,
            'employee_schedule_id' => null,
            'attendance_policy_id' => null,
            'work_location_id' => null,
            'scheduled_start_at' => null,
            'scheduled_end_at' => null,
            'break_duration_minutes' => 0,
            'actual_in_at' => null,
            'actual_out_at' => null,
            'first_log_id' => null,
            'last_log_id' => null,
            'work_minutes' => 0,
            'late_minutes' => 0,
            'early_out_minutes' => 0,
            'status' => AttendanceSummary::STATUS_NO_SCHEDULE,
            'is_complete' => false,
            'is_recalculated' => false,
            'calculated_at' => now(config('app.timezone')),
            'calculation_notes' => null,
            'created_by' => null,
            'updated_by' => null,
        ];
    }
}
