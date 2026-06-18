<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\ShiftPattern;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ShiftPattern>
 */
class ShiftPatternFactory extends Factory
{
    protected $model = ShiftPattern::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'company_id' => Company::query()->value('id') ?? Company::findOrCreateDefault()->id,
            'code' => Str::upper(Str::slug($name, '_')),
            'name' => Str::title($name),
            'description' => fake()->sentence(),
            'is_overnight' => false,
            'color' => fake()->hexColor(),
            'is_active' => true,
        ];
    }
}
