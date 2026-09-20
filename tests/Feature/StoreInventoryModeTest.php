<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Inventory\StoreManagement\AccountResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\StoreInventoryFixture;
use Tests\TestCase;

class StoreInventoryModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_mode_switch_is_admin_versioned_conserves_physical_total_and_blocks_pending_sync(): void
    {
        DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'independent_stores', 'version' => 3]);
        $sku = (new StoreInventoryFixture)->sku();
        $balance = app(AccountResolver::class)->balance($sku['variant']->id, 'website');
        DB::table('inventory_balances')->where('id', $balance['id'])->update(['physical_stock' => 20, 'reserved_stock' => 0, 'safety_stock' => 0, 'counted_at' => now()]);
        $admin = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        $url = '/admin/api/inventory/store-management/mode';
        $this->actingAs($admin)->postJson($url, ['target' => 'shared_pool', 'configuration_version' => 2, 'distribution' => []])
            ->assertUnprocessable()->assertJsonValidationErrors('version');
        $this->postJson($url, ['target' => 'shared_pool', 'configuration_version' => 3, 'distribution' => []])
            ->assertOk()->assertJsonPath('physical_total', 20)->assertJsonPath('configuration_version', 4);
        $this->assertSame(1, DB::table('inventory_balances')->whereNotNull('counted_at')->count());
        $this->postJson($url, ['target' => 'independent_stores', 'configuration_version' => 4, 'distribution' => [(string) $sku['variant']->id => ['website' => 20]]])
            ->assertOk()->assertJsonPath('physical_total', 20)->assertJsonPath('configuration_version', 5);
        $this->postJson($url, ['target' => 'shared_pool', 'configuration_version' => 5, 'distribution' => []])
            ->assertOk()->assertJsonPath('physical_total', 20)->assertJsonPath('configuration_version',6);
    }
}
