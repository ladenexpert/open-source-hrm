<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\PayrollComponent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayrollComponent>
 */
class PayrollComponentFactory extends Factory
{
    protected $model = PayrollComponent::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::query()->where('code', Company::DEFAULT_CODE)->value('id') ?? Company::query()->value('id'),
            'component_code' => strtoupper(fake()->unique()->bothify('CMP-###??')),
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->sentence(),
            'component_type' => PayrollComponent::TYPE_EARNING,
            'value_type' => PayrollComponent::VALUE_TYPE_FIXED,
            'default_amount' => '1500000.00',
            'default_percentage' => null,
            'taxable' => false,
            'tax_deductible' => false,
            'bpjs_applicable' => false,
            'thr_applicable' => false,
            'proratable' => false,
            'recurring' => true,
            'active' => true,
            'sort_order' => 0,
            'metadata' => null,
        ];
    }

    public function percentage(): static
    {
        return $this->state(fn (): array => [
            'value_type' => PayrollComponent::VALUE_TYPE_PERCENTAGE,
            'default_amount' => null,
            'default_percentage' => '5.0000',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'active' => false,
        ]);
    }
}
