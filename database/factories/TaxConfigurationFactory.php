<?php

namespace Database\Factories;

use App\Models\TaxConfiguration;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TaxConfiguration> */
class TaxConfigurationFactory extends Factory
{
    protected $model = TaxConfiguration::class;

    public function definition(): array
    {
        return [
            'is_enabled' => false,
            'is_published' => false,
            'prices_include_tax' => false,
            'address_basis' => null,
            'merchant_compliance_metadata' => null,
        ];
    }
}
