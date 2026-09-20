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

    public function test_shared_to_independent_blocks_active_catalog_sku_without_first_count(): void
    {
        DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'shared_pool', 'version' => 7]);
        $sku = (new StoreInventoryFixture)->sku();
        DB::table('products')->where('id', $sku['product']->id)->update(['status' => 'active']);
        $admin = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);

        $this->actingAs($admin)->postJson('/admin/api/inventory/store-management/mode', [
            'target' => 'independent_stores', 'configuration_version' => 7, 'distribution' => [],
        ])->assertUnprocessable()->assertJsonValidationErrors('mode');

        $this->assertSame('shared_pool', DB::table('inventory_configuration')->where('id', 1)->value('operating_mode'));
    }

    public function test_shared_to_independent_excludes_test_and_inactive_skus_from_distribution(): void
    {
        DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'shared_pool', 'version' => 9]);
        $fixture = new StoreInventoryFixture;
        $testSku = $fixture->sku();
        DB::table('product_variants')->where('id', $testSku['variant']->id)->update(['data_classification' => 'test']);
        $testBalance = app(AccountResolver::class)->balance($testSku['variant']->id, 'website');
        DB::table('inventory_balances')->where('id', $testBalance['id'])->update([
            'physical_stock' => 15, 'reserved_stock' => 0, 'safety_stock' => 0, 'counted_at' => now(),
        ]);
        $archivedSku = $fixture->sku();
        DB::table('products')->where('id', $archivedSku['product']->id)->update(['status' => 'archived']);
        $archivedBalance = app(AccountResolver::class)->balance($archivedSku['variant']->id, 'website');
        DB::table('inventory_balances')->where('id', $archivedBalance['id'])->update([
            'physical_stock' => 8, 'reserved_stock' => 0, 'safety_stock' => 0, 'counted_at' => now(),
        ]);
        $admin = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);

        $this->actingAs($admin)->postJson('/admin/api/inventory/store-management/mode', [
            'target' => 'independent_stores', 'configuration_version' => 9, 'distribution' => [],
        ])->assertOk()->assertJsonPath('physical_total', 23);

        $this->assertSame('independent_stores', DB::table('inventory_configuration')->where('id', 1)->value('operating_mode'));
        $this->assertSame(15, (int) DB::table('inventory_balances')->where('id', $testBalance['id'])->value('physical_stock'));
        $this->assertSame(8, (int) DB::table('inventory_balances')->where('id', $archivedBalance['id'])->value('physical_stock'));
    }
}
