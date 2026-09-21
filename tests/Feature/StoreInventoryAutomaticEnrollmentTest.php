<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Inventory\StoreManagement\StoreEnrollmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\StoreInventoryFixture;
use Tests\TestCase;

class StoreInventoryAutomaticEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_enrollment_is_idempotent_and_does_not_import_provider_stock(): void
    {
        $store = (new StoreInventoryFixture)->store('shopee', 'AUTO-ENROLL');
        $service = app(StoreEnrollmentService::class);

        $first = $service->enroll($store->id);
        $second = $service->enroll($store->id);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, DB::table('inventory_store_accounts')->where('marketplace_store_id', $store->id)->count());
        $this->assertSame(0, DB::table('inventory_balances')->count());
        $this->assertSame('setup_required', $second->management_status);
    }

    public function test_overview_enrolls_connected_store_and_always_activates_website(): void
    {
        $store = (new StoreInventoryFixture)->store('lazada', 'AUTO-STATE');
        $admin = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);

        $response = $this->actingAs($admin)
            ->getJson('/admin/api/inventory/store-management/overview')
            ->assertOk();

        $response->assertJsonPath('website_account.inventory_membership', 'enrolled')
            ->assertJsonPath('website_account.sync_state', 'active');
        $listed = collect($response->json('stores'))->firstWhere('id', $store->id);
        $this->assertSame('enrolled', $listed['inventory_membership']);
        $this->assertSame('setup_required', $listed['sync_state']);
        $this->assertDatabaseHas('inventory_store_accounts', ['account_key' => 'website', 'management_status' => 'managed']);
        $this->assertDatabaseHas('inventory_store_accounts', ['marketplace_store_id' => $store->id, 'management_status' => 'setup_required']);
        $this->assertSame(0, DB::table('inventory_balances')->count());
    }
}
