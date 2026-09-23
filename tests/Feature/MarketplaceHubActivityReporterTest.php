<?php

namespace Tests\Feature;

use App\Services\Marketplace\MarketplaceHubActivityReporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MarketplaceHubActivityReporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_failure_reports_safe_independent_statuses_without_provider_payload(): void
    {
        config(['app.url' => 'https://ecom.jwaistudio.online',
            'marketplace.hub.url' => 'https://marketplace.jwaistudio.online',
            'marketplace.hub.client_id' => 'jw_fixture_client',
            'marketplace.hub.client_secret' => 'fixture-client-secret',
            'marketplace.hub.activity_reporting_enabled' => true]);
        $storeId = DB::table('marketplace_stores')->insertGetId([
            'platform' => 'lazada', 'shop_name' => 'Test', 'external_shop_id' => '300927968304',
            'region' => 'MY', 'environment' => 'live', 'status' => 'connected',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('marketplace_catalog_runs')->insert([
            'store_id' => $storeId, 'status' => 'failed', 'error_code' => 'sensitive-provider-error',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        Http::fake(['marketplace.jwaistudio.online/api/activity/tasks' => Http::response(['accepted' => 1], 200)]);
        $this->assertSame(['reported' => 1, 'failed' => 0, 'disabled' => false],
            app(MarketplaceHubActivityReporter::class)->run());
        Http::assertSent(function ($request) {
            $body = $request->body();
            $data = json_decode($body, true);
            return ($data['tasks'][0]['type'] ?? null) === 'Fetch Products'
                && ($data['tasks'][0]['platformExecutionStatus'] ?? null) === 'Failed'
                && ($data['tasks'][0]['clientDeliveryStatus'] ?? null) === 'Delivered'
                && ($data['tasks'][0]['issue'] ?? null) === 'Provider request failed'
                && ! str_contains($body, 'sensitive-provider-error');
        });
    }
}
