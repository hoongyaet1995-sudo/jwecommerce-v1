<?php

namespace App\Services\Inventory\StoreManagement;

use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class AllocationReviewService
{
    public function __construct(private readonly AccountResolver $accounts, private readonly CountMutation $counts,
        private readonly SyncOutbox $outbox) {}

    public function create(User $actor, array $input): array
    {
        $this->authorize($actor);

        return DB::transaction(function () use ($actor, $input): array {
            $configuration = DB::table('inventory_configuration')->where('id', 1)->lockForUpdate()->first();
            if ($configuration?->operating_mode !== 'independent_stores') {
                throw ValidationException::withMessages(['allocations' => 'Allocate Stock is available only in Independent Stock mode.']);
            }
            $variantId = (int) $input['variant_id'];
            $allocations = $this->validatedAllocations($variantId, (int) $input['physical_count'], $input['allocations']);
            $resolved = collect($allocations)->mapWithKeys(fn (int $quantity, string $key) => [
                $key => $this->accounts->balance($variantId, $key),
            ]);
            $ids = $resolved->pluck('id')->map(fn ($id) => (int) $id)->sort()->values();
            $balances = DB::table('inventory_balances')->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $before = [];
            $after = [];
            $versions = [];
            foreach ($resolved as $key => $account) {
                $balance = $balances[(int) $account['id']];
                $projection = $this->counts->preview('count', $balance, ['physical_count' => $allocations[$key]]);
                $before[$key] = $projection['before'];
                $after[$key] = $projection['after'];
                $versions[(string) $balance->id] = (int) $balance->version;
            }
            $review = [
                'id' => (string) Str::uuid(), 'kind' => 'allocation', 'actor_id' => $actor->id,
                'configuration_version' => (int) $configuration->version, 'balance_versions' => $versions,
                'payload' => [...$input, 'allocations' => $allocations, 'balance_ids' => $resolved->map(fn ($a) => $a['id'])->all()],
                'before' => ['accounts' => $before, 'total_physical' => collect($before)->sum(fn ($s) => (int) ($s['physical'] ?? 0))],
                'after' => ['accounts' => $after, 'total_physical' => array_sum($allocations)],
                'blockers' => [], 'status' => 'pending', 'expires_at' => now()->addMinutes(10)->toIso8601String(),
            ];
            DB::table('inventory_store_reviews')->insert([
                ...$review, 'balance_versions' => json_encode($versions, JSON_THROW_ON_ERROR),
                'payload' => json_encode($review['payload'], JSON_THROW_ON_ERROR),
                'before' => json_encode($review['before'], JSON_THROW_ON_ERROR),
                'after' => json_encode($review['after'], JSON_THROW_ON_ERROR),
                'blockers' => json_encode([], JSON_THROW_ON_ERROR), 'expires_at' => Carbon::parse($review['expires_at']),
                'created_at' => now(), 'updated_at' => now(),
            ]);

            return $review;
        }, 3);
    }

    public function confirm(User $actor, string $reviewId, string $operationKey): array
    {
        $this->authorize($actor);

        return DB::transaction(function () use ($actor, $reviewId, $operationKey): array {
            $configuration = DB::table('inventory_configuration')->where('id', 1)->lockForUpdate()->first();
            $byKey = DB::table('inventory_store_operations')->where('operation_key', $operationKey)->lockForUpdate()->first();
            if ($byKey) {
                if ($byKey->review_id !== $reviewId) $this->conflict('operation_key_reused', 'This operation key belongs to another allocation.');
                return json_decode($byKey->receipt, true, 512, JSON_THROW_ON_ERROR);
            }
            $review = DB::table('inventory_store_reviews')->where('id', $reviewId)->lockForUpdate()->first();
            abort_unless($review && $review->kind === 'allocation', 404);
            abort_unless((int) $review->actor_id === (int) $actor->id || $actor->is_super_admin, 403);
            if (DB::table('inventory_store_operations')->where('review_id', $reviewId)->exists()) $this->conflict('review_confirmed', 'This allocation was already confirmed.');
            if (Carbon::parse($review->expires_at)->lte(now())) $this->conflict('review_expired', 'Review the current allocation again.');
            if ((int) $review->configuration_version !== (int) $configuration->version) $this->conflict('configuration_changed', 'Inventory settings changed. Review again.');
            $payload = json_decode($review->payload, true, 512, JSON_THROW_ON_ERROR);
            $versions = json_decode($review->balance_versions, true, 512, JSON_THROW_ON_ERROR);
            $allocations = $this->validatedAllocations((int) $payload['variant_id'], (int) $payload['physical_count'], $payload['allocations']);
            $ids = collect($payload['balance_ids'])->map(fn ($id) => (int) $id)->sort()->values();
            $balances = DB::table('inventory_balances')->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            abort_unless($balances->count() === $ids->count(), 404);
            $savedAfter = json_decode($review->after, true, 512, JSON_THROW_ON_ERROR);
            $projected = [];
            foreach ($payload['balance_ids'] as $key => $id) {
                $balance = $balances[(int) $id];
                if ((int) $balance->version !== (int) ($versions[(string) $id] ?? -1)) $this->conflict('balance_changed', 'Inventory changed after review.');
                $projected[$key] = $this->counts->preview('count', $balance, ['physical_count' => $allocations[$key]])['after'];
            }
            if ($projected !== $savedAfter['accounts']) $this->conflict('balance_changed', 'Allocation inputs changed after review.');
            $operationId = (string) Str::uuid();
            $result = [];
            foreach ($payload['balance_ids'] as $key => $id) $result[] = $this->counts->apply(
                $balances[(int) $id], $projected[$key], 'stock-allocation:'.$reviewId.':'.$id);
            $receipt = ['operation_id' => $operationId, 'review_id' => $reviewId, 'ledger_status' => 'applied',
                'balances' => $result,
                'physical_total' => collect($result)->sum(config('inventory.current_stock_only') ? 'current_stock' : 'physical'),
                'external_stages' => []];
            DB::table('inventory_store_operations')->insert(['id' => $operationId, 'review_id' => $reviewId,
                'operation_key' => $operationKey, 'kind' => 'allocation', 'actor_id' => $actor->id,
                'ledger_status' => 'applied', 'receipt' => json_encode($receipt, JSON_THROW_ON_ERROR),
                'created_at' => now(), 'updated_at' => now()]);
            app(\App\Services\Inventory\CatalogInventoryState::class)
                ->markVerified((int) $payload['variant_id'], $operationId);
            app(\App\Services\Inventory\CatalogStockProjection::class)
                ->refresh((int) $payload['variant_id']);
            foreach ($result as $saved) {
                $fresh = DB::table('inventory_balances')->find($saved['id']);
                $receipt['external_stages'] = [...$receipt['external_stages'], ...$this->outbox->enqueueMutation($operationId, $fresh, 'allocation')];
            }
            DB::table('inventory_store_operations')->where('id', $operationId)->update(['receipt' => json_encode($receipt, JSON_THROW_ON_ERROR), 'updated_at' => now()]);
            DB::table('inventory_store_reviews')->where('id', $reviewId)->update(['status' => 'confirmed', 'confirmed_at' => now(), 'updated_at' => now()]);

            return $receipt;
        }, 3);
    }

    private function validatedAllocations(int $variantId, int $physicalCount, array $allocations): array
    {
        $stores = DB::table('marketplace_stores')->whereIn('platform', ['shopee', 'lazada'])->where('status', 'connected')->orderBy('id')->get();
        $mapped = DB::table('marketplace_variant_mappings')->where('product_variant_id', $variantId)->whereIn('store_id', $stores->pluck('id'))->pluck('store_id')->map(fn ($id) => (int) $id);
        $expected = collect(['website'])->merge($mapped->map(fn ($storeId) => 'marketplace:'.$storeId))->sort()->values()->all();
        $actual = collect(array_keys($allocations))->sort()->values()->all();
        if ($expected !== $actual) throw ValidationException::withMessages(['allocations' => 'Provide one allocation for every eligible store account.']);
        $normalized = [];
        foreach ($allocations as $key => $quantity) {
            if (! is_int($quantity) || $quantity < 0) throw ValidationException::withMessages(['allocations' => 'Store allocations must be non-negative whole numbers.']);
            $normalized[$key] = $quantity;
        }
        if (array_sum($normalized) !== $physicalCount) throw ValidationException::withMessages(['allocations' => 'Allocated stock must exactly equal the verified physical quantity.']);

        return $normalized;
    }

    private function authorize(User $actor): void
    {
        abort_unless($actor->exists && $actor->is_admin && ($actor->is_super_admin || $actor->permissions()->where('permission', 'inventory.edit')->exists()), 403);
    }

    private function conflict(string $code, string $message): never
    {
        throw new HttpResponseException(response()->json(['error' => ['code' => $code, 'message' => $message,
            'field' => null, 'variant_id' => null, 'store_id' => null, 'next_action' => 'Review the current allocation again.']], 409));
    }
}
