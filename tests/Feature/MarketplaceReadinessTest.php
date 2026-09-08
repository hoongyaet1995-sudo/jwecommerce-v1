<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplaceReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_readiness_fails_for_cross_deployment_callback_without_printing_credentials(): void
    {
        config(['app.url'=>'http://127.0.0.1:8500', 'marketplace.shopee.enabled'=>true,
            'marketplace.shopee.environment'=>'sandbox', 'marketplace.shopee.partner_id'=>'123',
            'marketplace.shopee.partner_key'=>'PRIVATE-KEY-NOT-FOR-OUTPUT',
            'marketplace.shopee.callback_url'=>'https://example.test/admin/marketplace/oauth/shopee/callback']);
        $this->artisan('marketplace:readiness')->assertExitCode(1);
        \Illuminate\Support\Facades\Artisan::call('marketplace:readiness', ['--json'=>true]);
        $output = \Illuminate\Support\Facades\Artisan::output();
        $this->assertStringNotContainsString('PRIVATE-KEY-NOT-FOR-OUTPUT', $output);
        $data = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        $this->assertFalse($data['oauth_callback_ready']);
        $this->assertTrue($data['provider_configured']);
        $this->assertSame('not_verified_by_this_check', $data['real_provider_acceptance']);
        $this->assertSame([], $data['missing_tables']);
    }
}
