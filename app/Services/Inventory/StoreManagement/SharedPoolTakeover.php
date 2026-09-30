<?php

namespace App\Services\Inventory\StoreManagement;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SharedPoolTakeover
{
    public function __construct(private readonly StoreEnrollmentService $enrollment) {}

    public function set(int $storeId, bool $enabled, User $actor): array
    {
        abort_unless($actor->is_admin && $actor->is_super_admin, 403);

        return DB::transaction(function () use ($storeId, $enabled): array {
            $mode = DB::table('inventory_configuration')->where('id', 1)->lockForUpdate()->value('operating_mode');
            if ($mode !== 'shared_pool') {
                throw ValidationException::withMessages(['mode' => 'Shared Pool takeover is available only in Shared Stock Pool mode.']);
            }
            $store = DB::table('marketplace_stores')->where('id', $storeId)->lockForUpdate()->first();
            if (! $store || ! in_array($store->platform, ['shopee', 'lazada'], true)) {
                throw ValidationException::withMessages(['store' => 'Choose a connected Shopee or Lazada store.']);
            }
            if ($enabled && $store->status !== 'connected') {
                throw ValidationException::withMessages(['store' => 'Connect this store before enabling Shared Pool takeover.']);
            }
            $this->enrollment->enroll($storeId);
            $account = DB::table('inventory_store_accounts')->where('marketplace_store_id', $storeId)->lockForUpdate()->first();
            if ((bool) $account->shared_pool_takeover_enabled !== $enabled) {
                DB::table('inventory_store_accounts')->where('id', $account->id)->update([
                    'shared_pool_takeover_enabled' => $enabled,
                    'version' => DB::raw('version + 1'), 'updated_at' => now(),
                ]);
            }

            return ['store_id' => $storeId, 'shared_pool_takeover_enabled' => $enabled];
        }, 3);
    }
}
