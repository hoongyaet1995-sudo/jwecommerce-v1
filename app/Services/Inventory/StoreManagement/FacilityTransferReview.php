<?php

namespace App\Services\Inventory\StoreManagement;

use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Atomic movement between two counted physical facility owners. */
final class FacilityTransferReview
{
    public function review(User $actor, int $variantId, int $sourceId, int $destinationId,
        int $quantity, string $reason): array
    {
        $this->authorize($actor);
        if ($sourceId === $destinationId || $quantity < 1 || $quantity > 2147483647
            || trim($reason) === '' || mb_strlen($reason) > 255) {
            throw ValidationException::withMessages(['transfer' => 'Choose two different facilities, a positive quantity and a reason.']);
        }

        return DB::transaction(function () use ($actor, $variantId, $sourceId, $destinationId, $quantity, $reason): array {
            $configuration = $this->configuration();
            $this->assertTestAcceptance($sourceId, $variantId);
            $this->assertTestAcceptance($destinationId, $variantId);
            $source = $this->endpoint($sourceId, $variantId);
            $destination = $this->endpoint($destinationId, $variantId);
            if ((int) $source['balance']->id === (int) $destination['balance']->id) {
                throw ValidationException::withMessages(['transfer' => 'Source and destination share the same physical stock owner.']);
            }
            $available = $this->available($source['balance']);
            if ($quantity > $available || (int) $destination['balance']->physical_stock > 2147483647 - $quantity) {
                throw ValidationException::withMessages(['quantity' => 'Quantity exceeds source ATP or destination capacity.']);
            }
            $id = (string) Str::uuid();
            DB::table('marketplace_facility_transfer_reviews')->insert([
                'id' => $id, 'product_variant_id' => $variantId,
                'source_facility_id' => $sourceId, 'destination_facility_id' => $destinationId,
                'source_balance_id' => $source['balance']->id,
                'destination_balance_id' => $destination['balance']->id,
                'actor_id' => $actor->id, 'configuration_version' => $configuration->version,
                'source_topology_revision' => $source['scan']->revision,
                'destination_topology_revision' => $destination['scan']->revision,
                'source_balance_version' => $source['balance']->version,
                'destination_balance_version' => $destination['balance']->version,
                'quantity' => $quantity, 'reason' => trim($reason),
                'expires_at' => now()->addMinutes(10), 'created_at' => now(), 'updated_at' => now(),
            ]);

            return ['id' => $id, 'status' => 'pending', 'quantity' => $quantity,
                'source' => $this->summary($source, -$quantity),
                'destination' => $this->summary($destination, $quantity)];
        }, 3);
    }

    public function confirm(User $actor, string $reviewId, string $operationKey): array
    {
        $this->authorize($actor);
        if (! preg_match('/\A[A-Za-z0-9:_-]{1,120}\z/D', $operationKey)) {
            throw ValidationException::withMessages(['operation_key' => 'Use a valid operation key.']);
        }

        return DB::transaction(function () use ($actor, $reviewId, $operationKey): array {
            $review = DB::table('marketplace_facility_transfer_reviews')->where('id', $reviewId)
                ->lockForUpdate()->first();
            abort_unless($review, 404);
            if ($review->status === 'confirmed') {
                if ($review->operation_key !== $operationKey) {
                    $this->conflict('review_already_confirmed');
                }

                return json_decode($review->receipt, true, 512, JSON_THROW_ON_ERROR);
            }
            if ($review->status !== 'pending' || now()->gte($review->expires_at)) {
                $this->conflict('review_expired');
            }
            if ((int) $review->actor_id !== (int) $actor->id && ! $actor->is_super_admin) {
                abort(403);
            }
            if (DB::table('marketplace_facility_transfer_reviews')->where('operation_key', $operationKey)
                ->where('id', '!=', $reviewId)->exists()) {
                $this->conflict('operation_key_reused');
            }
            $configuration = $this->configuration();
            $this->assertTestAcceptance((int) $review->source_facility_id, (int) $review->product_variant_id);
            $this->assertTestAcceptance((int) $review->destination_facility_id, (int) $review->product_variant_id);
            if ((int) $configuration->version !== (int) $review->configuration_version) {
                $this->conflict('configuration_changed');
            }
            $ids = [(int) $review->source_balance_id, (int) $review->destination_balance_id];
            sort($ids);
            $locked = DB::table('inventory_balances')->whereIn('id', $ids)->orderBy('id')
                ->lockForUpdate()->get()->keyBy('id');
            $source = $this->endpoint((int) $review->source_facility_id, (int) $review->product_variant_id);
            $destination = $this->endpoint((int) $review->destination_facility_id, (int) $review->product_variant_id);
            if ((int) $source['scan']->revision !== (int) $review->source_topology_revision
                || (int) $destination['scan']->revision !== (int) $review->destination_topology_revision
                || (int) $source['balance']->id !== (int) $review->source_balance_id
                || (int) $destination['balance']->id !== (int) $review->destination_balance_id
                || (int) $locked[$review->source_balance_id]->version !== (int) $review->source_balance_version
                || (int) $locked[$review->destination_balance_id]->version !== (int) $review->destination_balance_version) {
                $this->conflict('facility_or_balance_changed');
            }
            $quantity = (int) $review->quantity;
            if ($this->available($source['balance']) < $quantity
                || (int) $destination['balance']->physical_stock > 2147483647 - $quantity) {
                $this->conflict('insufficient_source_atp');
            }
            foreach ([[$source, -$quantity], [$destination, $quantity]] as [$endpoint, $delta]) {
                $balance = $endpoint['balance'];
                DB::table('inventory_balances')->where('id', $balance->id)->update([
                    'physical_stock' => (int) $balance->physical_stock + $delta,
                    'version' => (int) $balance->version + 1, 'updated_at' => now(),
                ]);
            }
            $sync = [];
            foreach ([$source, $destination] as $endpoint) {
                $balance = DB::table('inventory_balances')->find($endpoint['balance']->id);
                $sync[] = app(SyncOutbox::class)->enqueueMutation(
                    'facility-transfer:'.$reviewId, $balance, 'transfer',
                    'facility-transfer:'.$reviewId.':'.$balance->id);
            }
            $receipt = ['review_id' => $reviewId, 'status' => 'applied', 'quantity' => $quantity,
                'source' => $this->summary($source, -$quantity),
                'destination' => $this->summary($destination, $quantity), 'sync' => $sync];
            DB::table('marketplace_facility_transfer_reviews')->where('id', $reviewId)->update([
                'status' => 'confirmed', 'operation_key' => $operationKey,
                'receipt' => json_encode($receipt, JSON_THROW_ON_ERROR),
                'confirmed_at' => now(), 'updated_at' => now(),
            ]);

            return $receipt;
        }, 3);
    }

    private function endpoint(int $facilityId, int $variantId): array
    {
        $facility = DB::table('marketplace_store_facilities as facility')
            ->join('marketplace_stores as store', 'store.id', '=', 'facility.marketplace_store_id')
            ->where('facility.id', $facilityId)->where('facility.is_active', true)
            ->where('store.status', 'connected')->first(['facility.*']);
        if (! $facility || ! $facility->inventory_warehouse_id) {
            throw ValidationException::withMessages(['facility' => 'Link an active provider facility first.']);
        }
        $scan = DB::table('marketplace_store_facility_scans')
            ->where('marketplace_store_id', $facility->marketplace_store_id)->first();
        $balance = DB::table('inventory_pools as pool')
            ->join('inventory_warehouses as warehouse', 'warehouse.id', '=', 'pool.warehouse_id')
            ->join('inventory_balances as balance', 'balance.inventory_pool_id', '=', 'pool.id')
            ->where('pool.kind', 'facility')->where('pool.warehouse_id', $facility->inventory_warehouse_id)
            ->where('warehouse.is_active', true)->where('warehouse.status', 'active')
            ->where('balance.product_variant_id', $variantId)->first(['balance.*']);
        if (! $scan || $scan->status !== 'verified' || ! $balance || $balance->counted_at === null
            || $balance->physical_stock === null || $balance->reserved_stock === null
            || $balance->safety_stock === null
            || DB::table('marketplace_variant_mappings')->where('store_id', $facility->marketplace_store_id)
                ->where('product_variant_id', $variantId)->count() !== 1) {
            throw ValidationException::withMessages(['facility' => 'Verify topology, SKU mapping and a physical count first.']);
        }
        if (DB::table('inventory_order_activations')->where('channel_key', 'marketplace:'.$facility->marketplace_store_id)
            ->where('product_variant_id', $variantId)->exists()) {
            throw ValidationException::withMessages(['facility' => 'Reconcile the old store-level order route first.']);
        }
        $legacy = DB::table('inventory_balances as balance')
            ->join('inventory_pools as pool', 'pool.id', '=', 'balance.inventory_pool_id')
            ->where('pool.kind', 'store')->where('pool.marketplace_store_id', $facility->marketplace_store_id)
            ->where('balance.product_variant_id', $variantId)
            ->first(['balance.physical_stock', 'balance.reserved_stock']);
        if ($legacy && ((int) $legacy->physical_stock > 0 || (int) $legacy->reserved_stock > 0)) {
            throw ValidationException::withMessages(['facility' => 'Migrate and reconcile old store stock first.']);
        }

        return ['facility' => $facility, 'scan' => $scan, 'balance' => $balance];
    }

    private function configuration(): object
    {
        $configuration = DB::table('inventory_configuration')->where('id', 1)->first();
        if (! $configuration || $configuration->operating_mode !== 'independent_stores') {
            throw ValidationException::withMessages(['mode' => 'Independent Stock mode is required for facility transfers.']);
        }

        return $configuration;
    }

    private function available(object $balance): int
    {
        return max(0, (int) $balance->physical_stock - (int) $balance->reserved_stock - (int) $balance->safety_stock);
    }

    private function summary(array $endpoint, int $delta): array
    {
        $balance = $endpoint['balance'];

        return ['facility_id' => (int) $endpoint['facility']->id,
            'provider_facility_id' => $endpoint['facility']->provider_facility_id,
            'balance_id' => (int) $balance->id,
            'physical_before' => (int) $balance->physical_stock,
            'physical_after' => (int) $balance->physical_stock + $delta];
    }

    private function authorize(User $actor): void
    {
        abort_unless($actor->exists && $actor->is_admin
            && ($actor->is_super_admin || $actor->permissions()->where('permission', 'inventory.edit')->exists()), 403);
        abort_unless(config('marketplace.multiwarehouse_operations_enabled') === true
            || config('marketplace.multiwarehouse_test_acceptance_enabled') === true, 404);
    }

    private function assertTestAcceptance(int $facilityId, int $variantId): void
    {
        if (config('marketplace.multiwarehouse_test_acceptance_enabled') !== true) {
            return;
        }
        $storeId = DB::table('marketplace_store_facilities')->where('id', $facilityId)
            ->value('marketplace_store_id');
        app(LazadaTestFacilityAcceptance::class)->assertEligible((int) $storeId, $variantId, $facilityId);
    }

    private function conflict(string $code): never
    {
        throw new HttpResponseException(response()->json(['error' => [
            'code' => $code, 'message' => 'Facility inventory changed after review. Review current stock again.',
        ]], 409));
    }
}
