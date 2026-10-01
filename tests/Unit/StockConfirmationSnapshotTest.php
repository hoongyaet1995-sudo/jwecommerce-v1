<?php

namespace Tests\Unit;

use App\Services\Inventory\StoreManagement\StockConfirmationSnapshot;
use PHPUnit\Framework\TestCase;

final class StockConfirmationSnapshotTest extends TestCase
{
    public function test_confirmed_snapshot_keeps_quantity_status_and_error_consistent_and_rejects_old_balance(): void
    {
        $task = (object) ['id' => 't', 'operation_id' => 'op', 'inventory_balance_id' => 3, 'product_variant_id' => 7,
            'marketplace_store_id' => 1, 'marketplace_store_facility_id' => null, 'listing_variant_id' => 2,
            'provider_location_id' => 'MYZ', 'balance_version' => 1, 'target_quantity' => 45, 'status' => 'confirmed',
            'provider_receipt' => json_encode(['observed_quantity' => 45, 'provider_location_id' => 'MYZ', 'message' => 'Old mismatch']),
            'readback_attempts' => 1, 'updated_at' => '2026-10-01'];
        $balance = (object) ['id' => 3, 'version' => 1, 'current_stock' => 45];
        $result = StockConfirmationSnapshot::task($task, $balance, 2);
        $this->assertSame('Synced', $result['status']); $this->assertSame(45, $result['quantity']); $this->assertNull($result['error']);
        $balance->version = 2;
        $this->assertSame('Stock changed', StockConfirmationSnapshot::task($task, $balance, 2)['status']);
        $this->assertFalse(StockConfirmationSnapshot::task($task, $balance, 2)['can_check']);
    }

    public function test_pending_failed_exhausted_zero_and_legacy_identity_remain_distinct(): void
    {
        $task = (object) ['id' => 't', 'operation_id' => 'op', 'inventory_balance_id' => 3,
            'product_variant_id' => 7, 'marketplace_store_id' => 1, 'marketplace_store_facility_id' => null,
            'listing_variant_id' => 2, 'provider_location_id' => 'MYZ', 'balance_version' => 1,
            'target_quantity' => 0, 'status' => 'uncertain', 'readback_attempts' => 1, 'readback_exhausted_at' => null,
            'provider_receipt' => json_encode(['provider_shop_id' => 'shop', 'write_accepted' => true,
                'write_request_id' => 'safe', 'observed_quantity' => 0, 'provider_location_id' => 'MYZ'])];
        $balance = (object) ['id' => 3, 'version' => 1, 'current_stock' => 0];
        $this->assertSame('Confirming stock', StockConfirmationSnapshot::task($task, $balance, 2, 'shop')['status']);
        $this->assertSame(0, StockConfirmationSnapshot::task($task, $balance, 2, 'shop')['quantity']);
        $task->readback_exhausted_at = '2026-10-01';
        $this->assertSame('Confirmation needs review', StockConfirmationSnapshot::task($task, $balance, 2, 'shop')['status']);
        $task->status = 'failed';
        $this->assertSame('Sync failed', StockConfirmationSnapshot::task($task, $balance, 2, 'shop')['status']);
        $this->assertSame('Stock changed', StockConfirmationSnapshot::task($task, $balance, 2, 'other-shop')['status']);
        $task->provider_receipt = '{}';
        $this->assertFalse(StockConfirmationSnapshot::task($task, $balance, 2, 'shop')['can_check']);
        $this->assertNull(StockConfirmationSnapshot::task($task, $balance, 2, 'shop')['quantity']);
    }

    public function test_uncertain_diagnostics_do_not_retain_tokens_or_raw_payloads(): void
    {
        $error = new \App\Exceptions\StoreInventoryResultUncertain('Safe message', [
            'write_request_id' => 'request', 'observed_quantity' => 0, 'access_token' => 'secret', 'raw_payload' => ['token' => 'secret'],
        ]);
        $this->assertSame(['write_request_id' => 'request', 'observed_quantity' => 0], $error->diagnostics);
    }
}
