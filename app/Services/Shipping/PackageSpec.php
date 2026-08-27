<?php

namespace App\Services\Shipping;

use InvalidArgumentException;

final readonly class PackageSpec
{
    public function __construct(
        public float $weightKg,
        public float $lengthCm,
        public float $widthCm,
        public float $heightCm,
    ) {
        foreach ([$weightKg, $lengthCm, $widthCm, $heightCm] as $value) {
            if (! is_finite($value) || $value <= 0 || $value > 999999.99) {
                throw new InvalidArgumentException('The confirmed package specification is invalid.');
            }
        }
    }

    /** @return array{weight_kg: float, length_cm: float, width_cm: float, height_cm: float} */
    public function snapshot(): array
    {
        return [
            'weight_kg' => $this->decimal($this->weightKg),
            'length_cm' => $this->decimal($this->lengthCm),
            'width_cm' => $this->decimal($this->widthCm),
            'height_cm' => $this->decimal($this->heightCm),
        ];
    }

    private function decimal(float $value): float
    {
        return (float) number_format($value, 2, '.', '');
    }
}
