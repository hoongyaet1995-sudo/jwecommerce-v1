<?php

namespace App\Services\Inventory\StoreManagement;

use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class InventoryIngestionBoundary
{
    /** @return array<string,mixed> */
    public function create(int $storeId, int $variantId, int $balanceId, User $actor, string $reason): array
    {
        if (! $actor->exists || ! $actor->is_admin || trim($reason) === '') {
            throw ValidationException::withMessages(['activation' => 'An authenticated administrator and reason are required.']);
        }

        return DB::transaction(function () use ($storeId, $variantId, $balanceId, $actor, $reason): array {
            $channel = 'marketplace:'.$storeId;
            $existing = DB::table('inventory_order_activations')->where('channel_key', $channel)
                ->where('product_variant_id', $variantId)->lockForUpdate()->first();
            if ($existing) {
                if ((int) $existing->inventory_balance_id !== $balanceId) {
                    throw ValidationException::withMessages(['activation' => 'Existing order ownership cannot be moved.']);
                }

                return (array) $existing;
            }

            $validBalance = DB::table('inventory_balances as balance')
                ->join('inventory_pools as pool', 'pool.id', '=', 'balance.inventory_pool_id')
                ->where('balance.id', $balanceId)->where('balance.product_variant_id', $variantId)
                ->where(function ($query) use ($storeId): void {
                    $query->where('pool.marketplace_store_id', $storeId)->orWhere('pool.kind', 'shared');
                })->lockForUpdate()->exists();
            $mapped = DB::table('marketplace_variant_mappings')->where('store_id', $storeId)
                ->where('product_variant_id', $variantId)->exists();
            if (! $validBalance || ! $mapped) {
                throw ValidationException::withMessages(['activation' => 'The mapped store SKU balance is invalid.']);
            }

            $cutoff = now()->startOfSecond();
            $id = DB::table('inventory_order_activations')->insertGetId([
                'channel_key' => $channel, 'product_variant_id' => $variantId, 'inventory_balance_id' => $balanceId,
                'actor_id' => $actor->id, 'reason' => trim($reason), 'orders_created_after' => $cutoff, 'created_at' => $cutoff,
            ]);

            return (array) DB::table('inventory_order_activations')->find($id);
        }, 3);
    }

    /** @return array<string,mixed>|null */
    public function eligible(int $storeId, int $variantId, CarbonInterface $createdAt): ?array
    {
        $row = DB::table('inventory_order_activations')->where('channel_key', 'marketplace:'.$storeId)
            ->where('product_variant_id', $variantId)->where('orders_created_after', '<', $createdAt)->first();

        return $row ? (array) $row : null;
    }

    /** @return list<int> */
    public function unresolvedHistoricalOrderIds(int $storeId, int $variantId, CarbonInterface $cutoff): array
    {
        $identities = DB::table('marketplace_listing_variants as listing')
            ->join('marketplace_variant_mappings as mapping', 'mapping.listing_variant_id', '=', 'listing.id')
            ->where('listing.store_id', $storeId)->where('mapping.product_variant_id', $variantId)
            ->get(['listing.external_product_id', 'listing.external_variant_id'])
            ->mapWithKeys(fn (object $row): array => [$row->external_product_id.':'.$row->external_variant_id => true]);
        if ($identities->isEmpty()) {
            return [];
        }

        $orders = DB::table('marketplace_orders as orders')
            ->leftJoin('marketplace_order_inventory as inventory', 'inventory.order_id', '=', 'orders.id')
            ->where('orders.store_id', $storeId)->whereNull('inventory.terminal_status')
            ->get(['orders.id', 'orders.encrypted_payload']);

        return $orders->filter(function (object $order) use ($identities, $variantId, $cutoff): bool {
            if (DB::table('stock_allocations')->where('owner_type', 'marketplace_order')->where('owner_id', $order->id)
                ->where('product_variant_id', $variantId)->exists()) {
                return false;
            }
            try {
                $payload = json_decode(Crypt::decryptString($order->encrypted_payload), true, 512, JSON_THROW_ON_ERROR);
            } catch (\Throwable) {
                return true;
            }
            $createdAt = $this->createdAt($payload);
            if ($createdAt === null || $createdAt->gt($cutoff)) {
                return false;
            }

            foreach ($this->lineIdentities($payload) as $line) {
                if ($identities->has($line['identity']) && ! DB::table('marketplace_inventory_items')
                    ->where('order_id', $order->id)->where('line_key', $line['line_key'])
                    ->whereIn('status', ['skipped_unmapped', 'skipped_historical'])->exists()) {
                    return true;
                }
            }

            return false;
        })->pluck('id')->map(fn ($id): int => (int) $id)->values()->all();
    }

    private function createdAt(array $payload): ?CarbonImmutable
    {
        if (is_int($payload['create_time'] ?? null)) {
            return CarbonImmutable::createFromTimestampUTC($payload['create_time']);
        }
        if (is_string($payload['created_at'] ?? null)) {
            try {
                return CarbonImmutable::parse($payload['created_at']);
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    /** @return list<array{identity:string,line_key:string}> */
    private function lineIdentities(array $payload): array
    {
        $lines = is_array($payload['item_list'] ?? null) ? $payload['item_list'] : ($payload['order_items'] ?? []);

        return collect($lines)->filter(fn ($line): bool => is_array($line))->map(function (array $line): array {
            $product = $line['item_id'] ?? $line['product_id'] ?? '';
            $variant = $line['model_id'] ?? $line['sku_id'] ?? '';
            return ['identity' => (string) $product.':'.(string) $variant,
                'line_key' => (string) ($line['order_item_id'] ?? ((string) $product.':'.(string) $variant))];
        })->all();
    }
}
