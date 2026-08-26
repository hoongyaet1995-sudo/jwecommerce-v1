<?php

namespace Database\Factories;

use App\Models\OrderTaxLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OrderTaxLine> */
class OrderTaxLineFactory extends Factory
{
    protected $model = OrderTaxLine::class;

    public function definition(): array
    {
        return [
            'order_id' => null,
            'tax_rule_id' => null,
            'tax_class_id' => null,
            'label' => fake()->words(3, true),
            'tax_type' => 'tax',
            'rate_percent' => '0.0000',
            'taxable_amount' => 0,
            'tax_amount' => 0,
            'configuration_snapshot' => [],
        ];
    }
}
