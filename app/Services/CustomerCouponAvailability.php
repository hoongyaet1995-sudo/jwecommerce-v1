<?php

namespace App\Services;

final readonly class CustomerCouponAvailability
{
    private function __construct(public bool $usable, public string $message)
    {
    }

    public static function usable(): self
    {
        return new self(true, 'Coupon is available.');
    }

    public static function rejected(string $message): self
    {
        return new self(false, $message);
    }
}
