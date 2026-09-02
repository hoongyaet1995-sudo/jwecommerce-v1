<?php

namespace App\Services;

final readonly class CustomerCouponApplicationResult
{
    private function __construct(public bool $applied, public string $message, public ?string $code)
    {
    }

    public static function applied(string $code): self
    {
        return new self(true, 'Coupon "'.$code.'" has been applied to your shopping cart.', $code);
    }

    public static function rejected(string $message): self
    {
        return new self(false, $message, null);
    }
}
