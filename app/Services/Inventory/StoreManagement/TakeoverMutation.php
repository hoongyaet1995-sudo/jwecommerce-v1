<?php

namespace App\Services\Inventory\StoreManagement;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class TakeoverMutation
{
    public function __construct(private ReadinessReader $readiness) {}

    public function apply(int $storeId, User $actor, string $reason): array
    {
        $this->authorize($actor);
        return DB::transaction(function () use ($storeId, $actor, $reason): array {
            DB::table('marketplace_stores')->where('id', $storeId)->lockForUpdate()->first();
            $check = $this->readiness->takeover($storeId);
            if (! $check['ready']) throw ValidationException::withMessages(['takeover' => collect($check['blockers'])->pluck('code')->all()]);
            DB::table('inventory_store_accounts')->where('marketplace_store_id', $storeId)->update([
                'management_status' => 'managed', 'version' => DB::raw('version + 1'), 'updated_at' => now(),
            ]);
            foreach ($check['balances'] as $balance) DB::table('inventory_order_activations')->updateOrInsert([
                'channel_key' => 'marketplace:'.$storeId, 'product_variant_id' => $balance->product_variant_id,
            ], ['inventory_balance_id' => $balance->id, 'actor_id' => $actor->id, 'reason' => $reason,
                'orders_created_after' => now(), 'created_at' => now()]);
            return ['store_id' => $storeId, 'management_status' => 'managed', 'activated_skus' => count($check['balances'])];
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

    private function authorize(User $actor): void { abort_unless($actor->is_admin && $actor->is_super_admin, 403); }
}
