<?php

namespace App\Services\Inventory\StoreManagement;

use Illuminate\Support\Facades\DB;

final class StoreSyncReadinessReader
{
    public function __construct(
        private readonly ReadinessReader $legacy,
        private readonly StoreSkuSyncReadiness $skuReadiness,
    ) {}

    public function read(int $storeId): array
    {
        $result = $this->legacy->takeover($storeId);
        $account = DB::table('inventory_store_accounts')->where('marketplace_store_id', $storeId)->first();
        $requirements = collect($result['blockers'])->groupBy('code')->map(function ($rows, string $code): array {
            $ids = $rows->flatMap(fn (array $row) => $row['ids'] ?? (isset($row['variant_id']) ? [$row['variant_id']] : []))
                ->filter()->unique()->values()->all();

            return [
                'code' => $code,
                'count' => max(count($ids), $rows->count()),
                'ids' => $ids,
                'next_action' => (string) $rows->first()['next_action'],
            ];
        })->values()->all();

        $status = $account?->management_status ?? 'setup_required';
        if (($result['store']?->status ?? null) !== 'connected' && $status === 'managed') {
            $status = 'setup_required';
        }
        $variantIds = DB::table('marketplace_variant_mappings')->where('store_id', $storeId)
            ->distinct()->pluck('product_variant_id');
        $skuStates = $variantIds->map(fn ($variantId): array => $this->skuReadiness->read($storeId, (int) $variantId));
        $readySkuCount = $skuStates->where('ready', true)->count();
        $blockedSkuCount = $skuStates->count() - $readySkuCount;
        $resumableSkuCount = $skuStates->filter(function (array $state): bool {
            $codes = collect($state['blockers'])->pluck('code');

            return $codes->isNotEmpty() && $codes->every(fn (string $code): bool => $code === 'sync_paused');
        })->count();
        $effectiveState = match (true) {
            $status === 'paused' => 'paused',
            $readySkuCount > 0 && $blockedSkuCount > 0 => 'partially_ready',
            $readySkuCount > 0 => 'active',
            default => 'setup_required',
        };

        return [
            'ready' => $readySkuCount > 0 || ($status === 'paused' && $resumableSkuCount === $skuStates->count() && $skuStates->isNotEmpty()),
            'effective_state' => $effectiveState,
            'inventory_membership' => 'enrolled',
            'requirements' => $requirements,
            'sku_summary' => ['ready' => $readySkuCount, 'blocked' => $blockedSkuCount],
            'setup_info' => $result['setup_info'] ?? ['unmapped_active_listings' => 0],
            'review_token' => $result['review_token'],
            'balances' => $result['balances'],
            'snapshot' => $result['snapshot'],
        ];
    }
}
