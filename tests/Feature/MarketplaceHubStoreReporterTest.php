<?php

namespace Tests\Feature;

use App\Services\Marketplace\MarketplaceHubStoreReporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MarketplaceHubStoreReporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_shopee_shop_id_in_two_environments_sends_distinct_token_free_receipts(): void
    {
        config(['app.url' => 'https://ecom.jwaistudio.online',
            'marketplace.hub.url' => 'https://marketplace.jwaistudio.online',
            'marketplace.hub.client_id' => 'jw_fixture_client',
            'marketplace.hub.client_secret' => 'fixture-client-secret',
            'marketplace.hub.store_reporting_enabled' => true]);
        foreach (['sandbox' => '123', 'production' => '456'] as $environment => $applicationId) {
            $storeId = DB::table('marketplace_stores')->insertGetId([
                'platform' => 'shopee', 'environment' => $environment, 'external_shop_id' => '227929252',
                'shop_name' => 'Singapore Store', 'region' => 'SG', 'status' => 'connected',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            \App\Models\MarketplaceStoreToken::create(['store_id' => $storeId, 'application_id' => $applicationId,
                'access_token' => 'private-'.$environment, 'refresh_token' => 'refresh-'.$environment,
                'access_expires_at' => now()->addHour()]);
        }
        Http::fake(['marketplace.jwaistudio.online/api/stores/receipt' => Http::response(['result' => 'connected'])]);

        $this->assertSame(2, app(MarketplaceHubStoreReporter::class)->run()['reported']);
        $requests = Http::recorded()->map(fn ($entry) => json_decode($entry[0]->body(), true)['store'])->all();
        $this->assertEqualsCanonicalizing(['sandbox', 'production'], array_column($requests, 'environment'));
        $this->assertEqualsCanonicalizing(['123', '456'], array_column($requests, 'applicationId'));
        foreach ($requests as $receipt) {
            $this->assertSame('SG', $receipt['region']);
            $this->assertArrayNotHasKey('accessToken', $receipt);
            $this->assertArrayNotHasKey('refreshToken', $receipt);
        }
    }

    public function test_only_store_metadata_is_signed_and_sent_to_hub(): void
    {
        config(['app.url' => 'https://ecom.jwaistudio.online',
            'marketplace.hub.url' => 'https://marketplace.jwaistudio.online',
            'marketplace.hub.client_id' => 'jw_fixture_client',
            'marketplace.hub.client_secret' => 'fixture-client-secret',
            'marketplace.hub.store_reporting_enabled' => true]);
        $storeId = DB::table('marketplace_stores')->insertGetId([
            'platform' => 'lazada', 'shop_name' => 'Lazada MY 300927968304',
            'external_shop_id' => '300927968304', 'region' => 'MY', 'environment' => 'live',
            'status' => 'connected', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('marketplace_store_tokens')->insert([
            'store_id' => $storeId, 'application_id' => '139209',
            'access_token' => 'encrypted-secret-access', 'refresh_token' => 'encrypted-secret-refresh',
            'access_expires_at' => now()->addHour(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        Http::fake(['marketplace.jwaistudio.online/api/stores/receipt' => Http::response(['result' => 'connected'], 200)]);

        $this->assertSame(['reported' => 1, 'failed' => 0, 'disabled' => false],
            app(MarketplaceHubStoreReporter::class)->run());
        Http::assertSent(function ($request) use ($storeId) {
            $body = $request->body();
            $data = json_decode($body, true);
            $timestamp = $request->header('X-Hub-Timestamp')[0] ?? '';
            $nonce = $request->header('X-Hub-Nonce')[0] ?? '';
            $signature = $request->header('X-Hub-Signature')[0] ?? '';
            return ($data['domain'] ?? null) === 'ecom.jwaistudio.online'
                && ($data['store']['clientStoreId'] ?? null) === (string) $storeId
                && ($data['store']['shopId'] ?? null) === '300927968304'
                && ($data['store']['environment'] ?? null) === 'production'
                && ! str_contains($body, 'encrypted-secret-access')
                && ! str_contains($body, 'encrypted-secret-refresh')
                && hash_equals(hash_hmac('sha256', "$timestamp.$nonce.$body", 'fixture-client-secret'), $signature);
        });
    }

    public function test_reporting_is_off_by_default(): void
    {
        config(['marketplace.hub.store_reporting_enabled' => false]);
        Http::fake();
        $this->assertSame(['reported' => 0, 'failed' => 0, 'disabled' => true],
            app(MarketplaceHubStoreReporter::class)->run());
        Http::assertNothingSent();
    }
}
