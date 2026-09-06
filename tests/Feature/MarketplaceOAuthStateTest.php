<?php

namespace Tests\Feature;

use App\Services\Marketplace\OAuthStateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MarketplaceOAuthStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_state_is_hashed_bound_and_single_use(): void
    {
        $service = app(OAuthStateService::class);
        $state = $service->issue(42, 'shopee', 'https://shop.example', null);
        $this->assertNotSame($state, DB::table('marketplace_oauth_states')->value('state_hash'));
        $this->assertNull($service->consume($state, 43, 'shopee', 'https://shop.example'));
        $this->assertNull($service->consume($state, 42, 'lazada', 'https://shop.example'));
        $this->assertNull($service->consume($state, 42, 'shopee', 'https://other.example'));
        $this->assertNotNull($service->consume($state, 42, 'shopee', 'https://shop.example'));
        $this->assertNull($service->consume($state, 42, 'shopee', 'https://shop.example'));
    }

    public function test_expired_state_cannot_be_consumed(): void
    {
        $service = app(OAuthStateService::class);
        $state = $service->issue(42, 'shopee', 'https://shop.example', null);
        $this->travel(11)->minutes();
        $this->assertNull($service->consume($state, 42, 'shopee', 'https://shop.example'));
    }

    public function test_provider_configuration_change_invalidates_pending_authorization(): void
    {
        config(['marketplace.shopee.environment'=>'sandbox', 'marketplace.shopee.partner_id'=>'123']);
        $service = app(OAuthStateService::class);
        $state = $service->issue(42, 'shopee', 'https://shop.example', null);
        config(['marketplace.shopee.environment'=>'production']);
        $this->assertNull($service->consume($state, 42, 'shopee', 'https://shop.example'));
    }
}
