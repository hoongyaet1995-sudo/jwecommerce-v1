<?php

namespace Tests\Feature\Marketplace;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HubHeartbeatCommandTest extends TestCase
{
    public function test_signed_heartbeat_uses_configured_client_identity_and_domain(): void
    {
        config([
            'marketplace.hub.url' => 'https://marketplace.jwaistudio.online',
            'marketplace.hub.client_id' => 'jw_testclient123456789012',
            'marketplace.hub.client_secret' => 'jw_sec_test-key',
            'app.url' => 'https://ecom.jwaistudio.online',
        ]);
        Http::fake(['marketplace.jwaistudio.online/*' => Http::response(['status' => 'Connection Verified'], 200)]);

        $this->artisan('marketplace:hub-heartbeat')->assertSuccessful();

        Http::assertSent(function ($request) {
            $time = $request->header('X-Hub-Timestamp')[0] ?? '';
            $nonce = $request->header('X-Hub-Nonce')[0] ?? '';
            $signature = $request->header('X-Hub-Signature')[0] ?? '';
            return $request->url() === 'https://marketplace.jwaistudio.online/api/installations/heartbeat'
                && ($request->header('X-Hub-Client')[0] ?? '') === 'jw_testclient123456789012'
                && $request->body() === '{"domain":"ecom.jwaistudio.online"}'
                && hash_equals(hash_hmac('sha256', "{$time}.{$nonce}.{$request->body()}", 'jw_sec_test-key'), $signature);
        });
    }
}
