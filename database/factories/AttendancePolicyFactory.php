<?php

namespace Database\Factories;

use App\Models\AttendancePolicy;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AttendancePolicy>
 */
class AttendancePolicyFactory extends Factory
{
    protected $model = AttendancePolicy::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'company_id' => Company::query()->value('id') ?? Company::findOrCreateDefault()->id,
            'code' => Str::upper(Str::slug($name, '_')),
            'name' => Str::title($name),
            'location_mode' => fake()->randomElement(AttendancePolicy::locationModeOptions()),
            'gps_required' => false,
            'selfie_required' => false,
            'radius_validation_enabled' => false,
            'radius_meters' => null,
            'late_tolerance_minutes' => 0,
            'early_out_tolerance_minutes' => 0,
            'minimum_work_minutes' => null,
            'auto_absent_after_minutes' => null,
            'overtime_threshold_minutes' => null,
            'is_active' => true,
        ];
    }
}
