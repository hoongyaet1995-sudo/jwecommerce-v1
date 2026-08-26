<?php

namespace Database\Factories;

use App\Models\TaxClass;
use App\Models\TaxRule;
use App\Models\TaxZone;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TaxRule> */
class TaxRuleFactory extends Factory
{
    protected $model = TaxRule::class;

    public function definition(): array
    {
        return [
            'tax_class_id' => TaxClass::factory(),
            'tax_zone_id' => TaxZone::factory(),
            'label' => fake()->words(3, true),
            'tax_type' => 'tax',
            'rate_percent' => fake()->randomFloat(4, 0, 100),
            'applies_to_shipping' => false,
            'priority' => 0,
            'is_enabled' => true,
            'effective_from' => null,
            'effective_until' => null,
        ];
    }
}
