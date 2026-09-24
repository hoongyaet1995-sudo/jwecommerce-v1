<?php

namespace Tests\Feature;

use App\Services\Inventory\StoreManagement\FacilityDiscoverySweep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\StoreInventoryFixture;
use Tests\TestCase;

class MarketplaceFacilityDiscoverySweepTest extends TestCase
{
    use RefreshDatabase;

    public function test_background_sweep_records_pending_without_changing_inventory(): void
    {
        $first = (new StoreInventoryFixture)->store('shopee', 'SWEEP-A');
        $second = (new StoreInventoryFixture)->store('shopee', 'SWEEP-B');
        Http::preventStrayRequests();

        $result = app(FacilityDiscoverySweep::class)->run();

        $this->assertSame(1, $result['stores']);
        $this->assertSame(0, $result['verified']);
        $this->assertSame(1, $result['pending']);
        $this->assertDatabaseHas('marketplace_store_facility_scans', ['marketplace_store_id' => $first->id]);
        $this->assertDatabaseMissing('marketplace_store_facility_scans', ['marketplace_store_id' => $second->id]);
        app(FacilityDiscoverySweep::class)->run();
        $this->assertDatabaseHas('marketplace_store_facility_scans', ['marketplace_store_id' => $second->id]);
        $this->assertDatabaseCount('inventory_balances', 0);
        Http::assertNothingSent();
    }
}
