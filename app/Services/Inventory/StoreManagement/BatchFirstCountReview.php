<?php

namespace App\Services\Inventory\StoreManagement;

use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** One reviewed SKU count, conserved across the website and every mapped facility. */
final class BatchFirstCountReview
{
    public function __construct(
        private readonly ReviewService $stores,
        private readonly FacilityStockReview $facilities,
    ) {}

    public function review(User $actor, int $variantId, int $physicalCount, array $allocations, string $reason): array
    {
        $this->authorize($actor);

        return DB::transaction(function () use ($actor, $variantId, $physicalCount, $allocations, $reason): array {
            $configuration = DB::table('inventory_configuration')->where('id', 1)->lockForUpdate()->first();
            if ($configuration?->operating_mode !== 'independent_stores' || $configuration->active_rebaseline_epoch_id !== null) {
                throw ValidationException::withMessages(['inventory' => 'Batch First Count requires Independent Stock mode without an active rebaseline.']);
            }
            if (! DB::table('product_variants')->where('id', $variantId)->lockForUpdate()->exists()) {
                abort(404);
            }
            $expected = ['website'];
            $mappedStores = DB::table('marketplace_variant_mappings as mapping')
                ->join('marketplace_stores as store', 'store.id', '=', 'mapping.store_id')
                ->where('mapping.product_variant_id', $variantId)->where('store.status', 'connected')
                ->whereIn('store.platform', ['shopee', 'lazada'])->distinct()->pluck('store.id');
            foreach ($mappedStores as $storeId) {
                $scan = DB::table('marketplace_store_facility_scans')->where('marketplace_store_id', $storeId)->first();
                if (! $scan || $scan->status !== 'verified') {
                    throw ValidationException::withMessages(['allocations' => 'Verify every mapped store warehouse topology before batch counting.']);
                }
                if ($scan->topology === 'no_warehouse') {
                    $expected[] = 'marketplace:'.$storeId;
                    continue;
                }
                if (! in_array($scan->topology, ['single_warehouse', 'multi_warehouse'], true)) {
                    throw ValidationException::withMessages(['allocations' => 'Unsupported warehouse topology.']);
                }
                $activeFacilities = DB::table('marketplace_store_facilities')
                    ->where('marketplace_store_id', $storeId)->where('is_active', true)->orderBy('id')->get(['id', 'inventory_warehouse_id']);
                if ($activeFacilities->contains(fn ($facility) => $facility->inventory_warehouse_id === null)) {
                    throw ValidationException::withMessages(['allocations' => 'Bind every platform warehouse to a JW facility first.']);
                }
                $facilityIds = $activeFacilities->pluck('id');
                if ($facilityIds->isEmpty()) {
                    throw ValidationException::withMessages(['allocations' => 'Bind every platform warehouse to a JW facility first.']);
                }
                foreach ($facilityIds as $facilityId) {
                    $expected[] = 'facility:'.$storeId.':'.$facilityId;
                }
            }
            sort($expected);
            $actual = array_keys($allocations);
            sort($actual);
            foreach ($allocations as $quantity) {
                if (! is_int($quantity) || $quantity < 0 || $quantity > 2147483647) {
                    throw ValidationException::withMessages(['allocations' => 'Use non-negative whole numbers for every destination.']);
                }
            }
            if ($actual !== $expected || array_sum($allocations) !== $physicalCount) {
                throw ValidationException::withMessages(['allocations' => 'Every mapped store and warehouse must be allocated; their sum must equal Verified Total.']);
            }

            $subreviews = [];
            foreach ($expected as $key) {
                $quantity = $allocations[$key];
                if (! is_int($quantity) || $quantity < 0 || $quantity > 2147483647) {
                    throw ValidationException::withMessages(['allocations' => 'Use non-negative whole numbers for every destination.']);
                }
                if (str_starts_with($key, 'facility:')) {
                    [, $storeId, $facilityId] = explode(':', $key);
                    $review = $this->facilities->review($actor, (int) $storeId, (int) $facilityId,
                        $variantId, 'count', $quantity, $reason);
                    $subreviews[] = ['key' => $key, 'kind' => 'facility', 'id' => $review['id'], 'quantity' => $quantity];
                } else {
                    $review = $this->stores->create($actor, 'count', [
                        'variant_id' => $variantId, 'channel_key' => $key,
                        'physical_count' => $quantity, 'reason' => $reason,
                    ]);
                    $subreviews[] = ['key' => $key, 'kind' => 'store', 'id' => $review['id'], 'quantity' => $quantity];
                }
            }
            $id = (string) Str::uuid();
            $expiresAt = now()->addMinutes(10);
            DB::table('inventory_store_reviews')->insert([
                'id' => $id, 'kind' => 'batch_first_count', 'actor_id' => $actor->id,
                'configuration_version' => $configuration->version,
                'balance_versions' => json_encode([], JSON_THROW_ON_ERROR),
                'payload' => json_encode(['variant_id' => $variantId, 'physical_count' => $physicalCount,
                    'allocations' => $allocations, 'subreviews' => $subreviews], JSON_THROW_ON_ERROR),
                'before' => json_encode([], JSON_THROW_ON_ERROR),
                'after' => json_encode(['physical_total' => $physicalCount], JSON_THROW_ON_ERROR),
                'blockers' => json_encode([], JSON_THROW_ON_ERROR),
                'status' => 'pending', 'expires_at' => $expiresAt,
                'created_at' => now(), 'updated_at' => now(),
            ]);

            return ['id' => $id, 'status' => 'pending', 'physical_total' => $physicalCount,
                'allocations' => $allocations, 'expires_at' => $expiresAt->toIso8601String()];
        }, 3);
    }

    public function confirm(User $actor, string $reviewId, string $operationKey): array
    {
        $this->authorize($actor);
        if (! preg_match('/\A[A-Za-z0-9:_-]{1,120}\z/D', $operationKey)) {
            throw ValidationException::withMessages(['operation_key' => 'Use a valid operation key.']);
        }

        return DB::transaction(function () use ($actor, $reviewId, $operationKey): array {
            $existing = DB::table('inventory_store_operations')->where('operation_key', $operationKey)->lockForUpdate()->first();
            if ($existing) {
                if ($existing->review_id !== $reviewId) $this->conflict('operation_key_reused');
                return json_decode($existing->receipt, true, 512, JSON_THROW_ON_ERROR);
            }
            $review = DB::table('inventory_store_reviews')->where('id', $reviewId)->where('kind', 'batch_first_count')
                ->lockForUpdate()->first();
            abort_unless($review, 404);
            abort_unless((int) $review->actor_id === (int) $actor->id || $actor->is_super_admin, 403);
            if ($review->status !== 'pending' || now()->gte($review->expires_at)) $this->conflict('review_expired');
            $configuration = DB::table('inventory_configuration')->where('id', 1)->lockForUpdate()->first();
            if (! $configuration || (int) $configuration->version !== (int) $review->configuration_version) {
                $this->conflict('configuration_changed');
            }
            $payload = json_decode($review->payload, true, 512, JSON_THROW_ON_ERROR);
            $receipts = [];
            foreach ($payload['subreviews'] as $subreview) {
                $key = substr(hash('sha256', $operationKey.':'.$subreview['key']), 0, 64);
                $receipts[$subreview['key']] = $subreview['kind'] === 'facility'
                    ? $this->facilities->confirm($actor, $subreview['id'], $key)
                    : $this->stores->confirm($actor, $subreview['id'], $key);
            }
            $id = (string) Str::uuid();
            $receipt = ['operation_id' => $id, 'review_id' => $reviewId, 'ledger_status' => 'applied',
                'physical_total' => $payload['physical_count'], 'destinations' => $receipts];
            DB::table('inventory_store_operations')->insert([
                'id' => $id, 'review_id' => $reviewId, 'operation_key' => $operationKey,
                'kind' => 'batch_first_count', 'actor_id' => $actor->id, 'ledger_status' => 'applied',
                'receipt' => json_encode($receipt, JSON_THROW_ON_ERROR),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('inventory_store_reviews')->where('id', $reviewId)->update([
                'status' => 'confirmed', 'confirmed_at' => now(), 'updated_at' => now(),
            ]);

            return $receipt;
        }, 3);
    }

    private function authorize(User $actor): void
    {
        abort_unless($actor->exists && $actor->is_admin
            && ($actor->is_super_admin || $actor->permissions()->where('permission', 'inventory.edit')->exists()), 403);
    }

    private function conflict(string $code): never
    {
        throw new HttpResponseException(response()->json(['error' => [
            'code' => $code, 'message' => 'The count changed after review. Review current stock again.',
        ]], 409));
    }
}
