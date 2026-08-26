<?php

namespace Database\Factories;

use App\Models\TaxClass;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<TaxClass> */
class TaxClassFactory extends Factory
{
    protected $model = TaxClass::class;

    public function definition(): array
    {
        $code = Str::lower(fake()->unique()->bothify('tax-class-####'));

        return [
            'code' => $code,
            'name' => Str::headline($code),
            'description' => null,
            'is_active' => true,
        ];
    }
}
