<?php

namespace App\Services\Inventory;

use RuntimeException;

final class CurrentStockTarget
{
    public static function current(object $balance): int
    {
        if (! isset($balance->current_stock)) {
            throw new RuntimeException('First Count is required before marketplace stock sync.');
        }

        return (int) $balance->current_stock;
    }
}
