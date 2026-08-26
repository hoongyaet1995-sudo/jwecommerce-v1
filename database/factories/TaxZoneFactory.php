<?php

namespace Database\Factories;

use App\Models\TaxZone;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<TaxZone> */
class TaxZoneFactory extends Factory
{
    protected $model = TaxZone::class;

    public function definition(): array
    {
        $code = Str::lower(fake()->unique()->bothify('zone-####'));

        return [
            'code' => $code,
            'name' => Str::headline($code),
            'country' => 'MY',
            'state' => null,
            'postcode' => null,
            'is_active' => true,
        ];
    }
}
