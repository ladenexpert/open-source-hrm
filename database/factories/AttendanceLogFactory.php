<?php

namespace Database\Factories;

use App\Models\AttendanceLog;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceLog>
 */
class AttendanceLogFactory extends Factory
{
    protected $model = AttendanceLog::class;

    public function definition(): array
    {
        $clockedAt = now(config('app.timezone'))->copy()->subMinutes(fake()->numberBetween(0, 240));

        return [
            'employee_id' => Employee::factory(),
            'attendance_date' => $clockedAt->toDateString(),
            'event_type' => fake()->randomElement(AttendanceLog::eventTypes()),
            'clocked_at' => $clockedAt,
            'source' => AttendanceLog::SOURCE_WEB,
            'latitude' => null,
            'longitude' => null,
            'is_valid' => true,
            'validation_message' => null,
            'selfie_path' => null,
            'device_identifier' => null,
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'notes' => null,
            'created_by' => null,
        ];
    }
}
