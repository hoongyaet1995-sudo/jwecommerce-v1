<?php

namespace App\Services\Inventory;

use Illuminate\Support\Facades\DB;

final class CatalogInventoryState
{
    /** @return array{initialized:bool,verified:bool,editable:bool,physical:?int,reserved:int,available:int,revision:string,balance_id:?int,adjustment_url:string,repair_required:bool} */
    public function snapshot(int $variantId): array
    {
        $variant = DB::table('product_variants')->where('id', $variantId)->first();
        abort_unless($variant, 404);

        $balances = $this->activeBalances($variantId);
        $hasPhysical = $balances->contains(fn (object $balance) => $balance->physical_stock !== null);
        $initialized = $variant->inventory_initialized_at !== null || $hasPhysical;
        $explicitlyVerified = $variant->first_physical_verified_at !== null;
        $evidencedOperation = $explicitlyVerified ? null : $this->legacyVerificationOperation($variantId);
        $verified = $explicitlyVerified || $evidencedOperation !== null;
        $hasGeneratedCount = $balances->contains(fn (object $balance) => $balance->counted_at !== null);
        $hasAnyLedgerHistory = DB::table('inventory_balances')->where('product_variant_id', $variantId)
            ->where(fn ($query) => $query->whereNotNull('physical_stock')->orWhereNotNull('counted_at'))
            ->exists();
        $repairRequired = ! $verified && $variant->inventory_initialized_at === null
            && ($hasGeneratedCount || ($hasAnyLedgerHistory && $balances->isEmpty()));

        $physical = $hasPhysical
            ? (int) $balances->sum(fn (object $balance) => (int) ($balance->physical_stock ?? 0))
            : null;
        $reserved = (int) $balances->sum(fn (object $balance) => (int) ($balance->reserved_stock ?? 0));
        $safety = (int) $balances->sum(fn (object $balance) => (int) ($balance->safety_stock ?? 0));
        $versionMaterial = $balances->map(fn (object $balance) => [
            (int) $balance->id,
            (int) $balance->version,
            $balance->updated_at,
        ])->values()->all();

        return [
            'initialized' => $initialized,
            'verified' => $verified,
            'editable' => ! $verified && ! $repairRequired,
            'physical' => $physical,
            'reserved' => $reserved,
            'available' => $physical === null ? 0 : max(0, $physical - $reserved - $safety),
            'revision' => hash('sha256', json_encode([
                $variantId,
                $variant->inventory_initialized_at,
                $variant->first_physical_verified_at,
                $versionMaterial,
            ], JSON_THROW_ON_ERROR)),
            'balance_id' => $balances->count() === 1 ? (int) $balances->first()->id : null,
            'adjustment_url' => '/admin/marketplace/inventory?variant_id='.$variantId.'&action=adjustment',
            'repair_required' => $repairRequired,
        ];
    }

    public function markVerified(int $variantId, string $operationId): void
    {
        DB::transaction(function () use ($variantId, $operationId): void {
            $variant = DB::table('product_variants')->where('id', $variantId)->lockForUpdate()->first();
            abort_unless($variant, 404);

            DB::table('product_variants')->where('id', $variantId)->update([
                'inventory_initialized_at' => $variant->inventory_initialized_at ?? now(),
                'first_physical_verified_at' => $variant->first_physical_verified_at ?? now(),
                'first_physical_verification_operation' => $variant->first_physical_verification_operation ?? $operationId,
                'updated_at' => now(),
            ]);
        }, 3);
    }

    private function activeBalances(int $variantId): \Illuminate\Support\Collection
    {
        $mode = DB::table('inventory_configuration')->where('id', 1)->value('operating_mode');
        $query = DB::table('inventory_balances as balance')
            ->join('inventory_pools as pool', 'pool.id', '=', 'balance.inventory_pool_id')
            ->leftJoin('marketplace_stores as store', 'store.id', '=', 'pool.marketplace_store_id')
            ->where('balance.product_variant_id', $variantId)
            ->select('balance.*');

        if ($mode === 'shared_pool') {
            $query->where('pool.pool_key', 'shared')->where('pool.kind', 'shared');
        } elseif ($mode === 'independent_stores') {
            $query->where('pool.kind', 'store')
                ->where(fn ($active) => $active->whereNull('pool.marketplace_store_id')->orWhere('store.status', 'connected'));
        } else {
            return collect();
        }

        return $query->orderBy('balance.id')->get();
    }

    private function legacyVerificationOperation(int $variantId): ?string
    {
        $balances = DB::table('inventory_balances')->where('product_variant_id', $variantId)->pluck('id')
            ->map(fn ($id) => (int) $id)->all();
        if ($balances === []) {
            return null;
        }

        $operations = DB::table('inventory_store_operations as operation')
            ->join('inventory_store_reviews as review', 'review.id', '=', 'operation.review_id')
            ->where('operation.ledger_status', 'applied')
            ->whereIn('operation.kind', ['count', 'allocation'])
            ->select('operation.id', 'review.payload')
            ->orderBy('operation.created_at')
            ->get();

        foreach ($operations as $operation) {
            $payload = json_decode((string) $operation->payload, true);
            $referenced = collect($payload['balance_ids'] ?? [$payload['balance_id'] ?? null])
                ->filter(fn ($id) => $id !== null)->map(fn ($id) => (int) $id);
            if ($referenced->intersect($balances)->isNotEmpty()) {
                return (string) $operation->id;
            }
        }

        return null;
    }
}
