<?php

namespace App\Services\Inventory;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class CurrentStockCutover
{
    public function isHistorical(string $channelKey, string $orderReference, string $createdAt): bool
    {
        if (DB::table('inventory_current_stock_historical_orders')
            ->where('channel_key', $channelKey)->where('order_reference', $orderReference)->exists()) {
            return true;
        }
        $cutover = DB::table('inventory_current_stock_cutovers')->where('status', 'complete')
            ->orderByDesc('cutover_at')->first();
        if (! $cutover) {
            return false;
        }

        return CarbonImmutable::parse($createdAt, 'UTC')->lessThanOrEqualTo(
            CarbonImmutable::parse($cutover->cutover_at, 'UTC')
        );
    }
}
