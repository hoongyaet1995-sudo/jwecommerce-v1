<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Social\SocialProtocol;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SocialHubConnectivityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge();
        $this->artisan('migrate', ['--path' => 'database/migrations/social', '--force' => true]);
        DB::table('social_hub_settings')->insert(['id' => 1, 'hub_url' => 'https://social.jwaistudio.online', 'client_id' => 'client-a', 'key_id' => 'key-a', 'encrypted_secret' => Crypt::encryptString('fixture-secret'), 'registered_domain' => 'a.example.com', 'enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function send(string $path, array $data, array $h = [])
    {
        $body = json_encode($data);
        $h = $h ?: SocialProtocol::headers('client-a', 'key-a', 'fixture-secret', 'hub-to-client', 'POST', $path, $body);

        return $this->call('POST', $path, [], [], [], array_merge(['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], collect($h)->mapWithKeys(fn ($v, $k) => ['HTTP_'.strtoupper(str_replace('-', '_', $k)) => $v])->all()), $body);
    }

    public function test_signed_challenge_durable_test_event_and_isolation(): void
    {
        $d = ['client_id' => 'client-a', 'registered_domain' => 'a.example.com', 'check_id' => 'check-a', 'challenge' => str_repeat('a', 64)];
        $this->send('/api/social/hub/challenge', $d)->assertOk()->assertJsonPath('challenge', $d['challenge']);
        $d = ['client_id' => 'client-a', 'registered_domain' => 'a.example.com', 'event_id' => 'event-a', 'correlation_id' => 'event-a', 'type' => 'connectivity.test', 'created_at' => now()->toIso8601String(), 'payload' => ['message' => 'test']];
        $this->send('/api/social/hub/events', $d)->assertOk();
        $this->send('/api/social/hub/events', $d)->assertOk();
        $this->assertSame(1, DB::table('social_hub_test_event_inbox')->count());
        $body = json_encode($d);
        $h = SocialProtocol::headers('client-a', 'key-a', 'fixture-secret', 'hub-to-client', 'POST', '/api/social/hub/events', $body);
        $this->send('/api/social/hub/events', $d, $h)->assertOk();
        $this->send('/api/social/hub/events', $d, $h)->assertStatus(409);
        $d['client_id'] = 'client-b';
        $this->send('/api/social/hub/events', $d)->assertStatus(403);
        $d['client_id'] = 'client-a';
        $d['registered_domain'] = 'b.example.com';
        $this->send('/api/social/hub/events', $d)->assertStatus(403);
        DB::table('social_hub_settings')->update(['enabled' => false]);
        $this->send('/api/social/hub/events', $d)->assertStatus(404);
    }

    public function test_ordinary_users_cannot_manage_installation(): void
    {
        $this->getJson('/admin/api/social-hub/installation')->assertUnauthorized();
        $u = new User;
        $u->forceFill(['id' => 1, 'is_admin' => true, 'is_super_admin' => false]);
        $this->actingAs($u)->getJson('/admin/api/social-hub/installation')->assertForbidden();
    }
}
