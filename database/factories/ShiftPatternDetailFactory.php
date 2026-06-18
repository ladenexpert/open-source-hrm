<?php

namespace Database\Factories;

use App\Models\ShiftPattern;
use App\Models\ShiftPatternDetail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShiftPatternDetail>
 */
class ShiftPatternDetailFactory extends Factory
{
    protected $model = ShiftPatternDetail::class;

    public function definition(): array
    {
        return [
            'shift_pattern_id' => ShiftPattern::factory(),
            'company_id' => function (array $attributes): ?int {
                return ShiftPattern::query()->whereKey($attributes['shift_pattern_id'] ?? null)->value('company_id');
            },
            'day_of_week' => fake()->numberBetween(1, 7),
            'start_time' => '08:00',
            'end_time' => '17:00',
            'break_duration_minutes' => 60,
            'is_working_day' => true,
        ];
    }
}
