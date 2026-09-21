<?php

namespace App\Services\Inventory\StoreManagement;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class StoreSyncStateMutation
{
    public function __construct(
        private readonly StoreEnrollmentService $enrollment,
        private readonly StoreSyncReadinessReader $readiness,
        private readonly SyncOutbox $outbox,
        private readonly InventoryIngestionBoundary $boundary,
    ) {}

    public function pause(int $storeId, User $actor, string $reason): array
    {
        $this->authorize($actor);
        $this->enrollment->enroll($storeId);

        return DB::transaction(function () use ($storeId, $actor, $reason): array {
            $account = DB::table('inventory_store_accounts')->where('marketplace_store_id', $storeId)->lockForUpdate()->first();
            if ($account->management_status !== 'paused') {
                DB::table('inventory_store_accounts')->where('id', $account->id)->update([
                    'management_status' => 'paused', 'version' => DB::raw('version + 1'), 'updated_at' => now(),
                ]);
                $this->audit($storeId, $actor, 'pause_sync', $reason, $account->management_status, 'paused');
            }

            return $this->receipt($storeId, 'paused');
        }, 3);
    }

    public function resume(int $storeId, User $actor, string $reason, string $reviewToken): array
    {
        $this->authorize($actor);
        $this->enrollment->enroll($storeId);

        return DB::transaction(function () use ($storeId, $actor, $reason, $reviewToken): array {
            DB::table('marketplace_stores')->where('id', $storeId)->lockForUpdate()->first();
            $check = $this->readiness->read($storeId);
            if (! hash_equals($check['review_token'], $reviewToken)) {
                throw ValidationException::withMessages(['review_token' => 'Store readiness changed. Review setup again.']);
            }
            if (! $check['ready']) {
                throw ValidationException::withMessages(['sync' => collect($check['requirements'])->pluck('code')->all()]);
            }
            $account = DB::table('inventory_store_accounts')->where('marketplace_store_id', $storeId)->lockForUpdate()->first();
            $stages = [];
            if ($account->management_status !== 'managed') {
                DB::table('inventory_store_accounts')->where('id', $account->id)->update([
                    'management_status' => 'managed', 'version' => DB::raw('version + 1'), 'updated_at' => now(),
                ]);
                foreach ($check['balances'] as $balance) {
                    $this->boundary->create($storeId, (int) $balance->product_variant_id, (int) $balance->id, $actor, $reason);
                    $fresh = DB::table('inventory_balances')->find($balance->id);
                    $stages[] = $this->outbox->enqueueStore(null, $fresh, $storeId, 'resume_sync', 'resume-sync:'.$storeId.':'.$fresh->id.':'.$fresh->version);
                }
                $this->audit($storeId, $actor, 'resume_sync', $reason, $account->management_status, 'managed');
            }

            return $this->receipt($storeId, 'managed') + ['external_stages' => $stages];
        }, 3);
    }

    private function receipt(int $storeId, string $status): array
    {
        return ['store_id' => $storeId, 'sync_state' => $status === 'managed' ? 'active' : 'paused',
            'inventory_membership' => 'enrolled',
            'preserved_reservations' => (int) DB::table('inventory_store_order_lines')->where('channel_key', 'marketplace:'.$storeId)->sum('reserved_quantity')];
    }

    private function audit(int $storeId, User $actor, string $kind, string $reason, string $before, string $after): void
    {
        $reviewId = (string) Str::uuid();
        $configurationVersion = (int) (DB::table('inventory_configuration')->where('id', 1)->value('version') ?? 0);
        $receipt = ['store_id' => $storeId, 'sync_state' => $after === 'managed' ? 'active' : 'paused'];
        DB::table('inventory_store_reviews')->insert(['id' => $reviewId, 'kind' => $kind, 'actor_id' => $actor->id,
            'configuration_version' => $configurationVersion, 'balance_versions' => json_encode([]),
            'payload' => json_encode(['store_id' => $storeId, 'reason' => $reason]), 'before' => json_encode(['sync_state' => $before]),
            'after' => json_encode($receipt), 'blockers' => json_encode([]), 'status' => 'confirmed', 'expires_at' => now()->addMinutes(10),
            'confirmed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('inventory_store_operations')->insert(['id' => (string) Str::uuid(), 'review_id' => $reviewId,
            'operation_key' => $kind.':'.$storeId.':'.($configurationVersion).':'.Str::uuid(), 'kind' => $kind,
            'actor_id' => $actor->id, 'ledger_status' => 'applied', 'receipt' => json_encode($receipt), 'created_at' => now(), 'updated_at' => now()]);
    }

    private function authorize(User $actor): void
    {
        abort_unless($actor->is_admin && $actor->is_super_admin, 403);
    }
}
