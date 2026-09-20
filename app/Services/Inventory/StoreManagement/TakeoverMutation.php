<?php

namespace App\Services\Inventory\StoreManagement;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class TakeoverMutation
{
    public function __construct(private ReadinessReader $readiness, private SyncOutbox $outbox) {}

    public function apply(int $storeId, User $actor, string $reason, string $reviewToken): array
    {
        $this->authorize($actor);

        return DB::transaction(function () use ($storeId, $actor, $reason, $reviewToken): array {
            DB::table('marketplace_stores')->where('id', $storeId)->lockForUpdate()->first();
            $check = $this->readiness->takeover($storeId);
            if (! hash_equals($check['review_token'], $reviewToken)) {
                throw ValidationException::withMessages(['review_token' => 'Store, mapping, snapshot or inventory changed. Review takeover again.']);
            }
            if (! $check['ready']) {
                throw ValidationException::withMessages(['takeover' => collect($check['blockers'])->pluck('code')->all()]);
            }
            DB::table('inventory_store_accounts')->where('marketplace_store_id', $storeId)->update([
                'management_status' => 'managed', 'version' => DB::raw('version + 1'), 'updated_at' => now(),
            ]);
            $stages = [];
            foreach ($check['balances'] as $balance) {
                DB::table('inventory_order_activations')->updateOrInsert([
                    'channel_key' => 'marketplace:'.$storeId, 'product_variant_id' => $balance->product_variant_id,
                ], ['inventory_balance_id' => $balance->id, 'actor_id' => $actor->id, 'reason' => $reason,
                    'orders_created_after' => now(), 'created_at' => now()]);
                $fresh = DB::table('inventory_balances')->find($balance->id);
                $stages[] = $this->outbox->enqueueStore(null, $fresh, $storeId, 'takeover', 'takeover:'.$storeId.':'.$fresh->id.':'.$fresh->version);
            }

            $receipt = ['store_id' => $storeId, 'management_status' => 'managed', 'activated_skus' => count($check['balances']), 'external_stages' => $stages];
            $reviewId = (string) Str::uuid();
            $operationId = (string) Str::uuid();
            DB::table('inventory_store_reviews')->insert(['id' => $reviewId, 'kind' => 'takeover', 'actor_id' => $actor->id,
                'configuration_version' => $check['snapshot']['configuration_version'], 'balance_versions' => json_encode($check['snapshot']['balance_versions']),
                'payload' => json_encode(['store_id' => $storeId, 'reason' => $reason, 'review_token' => $reviewToken]), 'before' => json_encode(['management_status' => 'not_managed']),
                'after' => json_encode($receipt), 'blockers' => json_encode([]), 'status' => 'confirmed', 'expires_at' => now()->addMinutes(10), 'confirmed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            DB::table('inventory_store_operations')->insert(['id' => $operationId, 'review_id' => $reviewId, 'operation_key' => 'takeover:'.$storeId.':'.$reviewToken,
                'kind' => 'takeover', 'actor_id' => $actor->id, 'ledger_status' => 'applied', 'receipt' => json_encode($receipt), 'created_at' => now(), 'updated_at' => now()]);

            return [...$receipt, 'operation_id' => $operationId];
        }, 3);
    }

    public function pause(int $storeId, User $actor): array
    {
        $this->authorize($actor);
        DB::table('inventory_store_accounts')->where('marketplace_store_id', $storeId)->update([
            'management_status' => 'paused', 'version' => DB::raw('version + 1'), 'updated_at' => now(),
        ]);

        return ['store_id' => $storeId, 'management_status' => 'paused',
            'preserved_reservations' => (int) DB::table('inventory_store_order_lines')->where('channel_key', 'marketplace:'.$storeId)->sum('reserved_quantity')];
    }

    private function authorize(User $actor): void
    {
        abort_unless($actor->is_admin && $actor->is_super_admin, 403);
    }
}
