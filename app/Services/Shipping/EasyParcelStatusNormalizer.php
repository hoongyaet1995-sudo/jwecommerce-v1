<?php

namespace App\Services\Shipping;

final class EasyParcelStatusNormalizer
{
    public function normalize(int $code): string
    {
        return match ($code) {
            0 => 'cancelled',
            2, 7 => 'pending',
            8 => 'on_hold',
            3, 11 => 'shipped',
            4 => 'in_transit',
            5 => 'delivered',
            6 => 'returned',
            default => 'provider_update',
        };
    }

    public function description(mixed $value): string
    {
        if (! is_string($value) || ! mb_check_encoding($value, 'UTF-8')) {
            throw new EasyParcelTrackingException;
        }

        $normalized = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value);
        $normalized = is_string($normalized) ? preg_replace('/\s+/u', ' ', trim($normalized)) : null;
        if (! is_string($normalized) || $normalized === '' || mb_strlen($normalized) > 2000) {
            throw new EasyParcelTrackingException;
        }

        return $normalized;
    }
}
