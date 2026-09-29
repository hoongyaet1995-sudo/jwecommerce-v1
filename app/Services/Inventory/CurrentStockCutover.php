<?php

namespace App\Services\Inventory;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class CurrentStockCutover
{
    public function isHistorical(string $channelKey, string $orderReference, string $createdAt, ?int $balanceId = null): bool
    {
        if (DB::table('inventory_current_stock_historical_orders')
            ->where('channel_key', $channelKey)->where('order_reference', $orderReference)->exists()) {
            return true;
        }
        // A newly imported Marketplace baseline already includes earlier seller activity.
        // Its order boundary belongs to this exact SKU/location, not every store.
        if ($balanceId !== null && str_starts_with($channelKey, 'marketplace:')) {
            $countedAt = DB::table('inventory_balances')->where('id', $balanceId)->value('counted_at');
            if ($countedAt && CarbonImmutable::parse($createdAt, 'UTC')->lessThanOrEqualTo(
                CarbonImmutable::parse($countedAt, 'UTC'))) {
                return true;
            }
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
