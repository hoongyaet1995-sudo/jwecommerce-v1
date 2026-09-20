<?php

namespace App\Services\Inventory\StoreManagement;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class SyncOutbox
{
    public function enqueueTransfer(string $operationId, object $source, object $destination): array
    {
        return [
            $this->enqueue($operationId, $source, 'source'),
            $this->enqueue($operationId, $destination, 'destination'),
        ];
    }

    public function enqueueBalance(?string $operationId, object $balance, string $stage = 'account', ?string $eventKey = null): array
    {
        return $this->enqueue($operationId, $balance, $stage, $eventKey);
    }

    private function enqueue(?string $operationId, object $balance, string $stage, ?string $eventKey = null): array
    {
        $pool = DB::table('inventory_pools')->where('id', $balance->inventory_pool_id)->first();
        $storeId = $pool?->marketplace_store_id === null ? null : (int) $pool->marketplace_store_id;
        $managed = $storeId === null || DB::table('inventory_store_accounts')
            ->where('marketplace_store_id', $storeId)->value('management_status') === 'managed';
        $id = (string) Str::uuid();
        $status = $storeId === null ? 'confirmed' : ($managed ? ($stage === 'destination' ? 'waiting_source' : 'queued') : 'not_applicable');
        $target = max(0, (int) $balance->physical_stock - (int) $balance->reserved_stock - (int) $balance->safety_stock);
        DB::table('inventory_store_sync_tasks')->insert([
            'id' => $id, 'operation_id' => $operationId, 'marketplace_store_id' => $storeId,
            'product_variant_id' => $balance->product_variant_id, 'stage' => $stage,
            'status' => $status, 'target_quantity' => $target, 'balance_version' => $balance->version,
            'event_key' => $eventKey ?? $operationId.':'.$stage.':'.($storeId ?? 'website'),
            'provider_receipt' => $storeId === null ? json_encode(['local' => true], JSON_THROW_ON_ERROR) : null,
            'available_at' => $status === 'queued' ? now() : null, 'attempts' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return ['id' => $id, 'stage' => $stage, 'store_id' => $storeId, 'status' => $status, 'target_quantity' => $target];
    }
}
