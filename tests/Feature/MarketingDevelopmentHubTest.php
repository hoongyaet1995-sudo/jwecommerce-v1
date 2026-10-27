<?php

namespace Tests\Feature;

use App\Models\MarketingChannelConnection;
use App\Models\User;
use App\Services\Social\SocialProtocol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Crypt, DB, Http, Queue};
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MarketingDevelopmentHubTest extends TestCase
{
    use RefreshDatabase;

    private const ORIGIN = 'https://studio-dev.example.test';
    private const SECRET = 'FAKE-DEVELOPMENT-INSTALLATION-SECRET';
    private string $transaction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--path' => 'database/migrations/social', '--force' => true])->assertExitCode(0);
        $this->actingAs(User::factory()->create(['is_admin' => true, 'is_super_admin' => true]));
        config(['marketing.execution_enabled' => false, 'app.url' => self::ORIGIN, 'services.facebook.client_id' => 'test-app',
            'services.facebook.client_secret' => 'test-app-secret',
            'services.facebook.redirect' => 'https://social.jwaistudio.online/oauth/facebook/callback']);
        DB::table('social_hub_settings')->insert(['id' => 1, 'hub_url' => 'https://social.jwaistudio.online',
            'client_id' => 'independent-development-client', 'key_id' => 'independent-development-key',
            'encrypted_secret' => Crypt::encryptString(self::SECRET), 'registered_domain' => 'studio-dev.example.test',
            'enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
        $this->transaction = (string) Str::uuid();
        Http::preventStrayRequests();
        Queue::fake();
    }

    public static function platforms(): array
    {
        return [['facebook'], ['instagram']];
    }

    private function fakeHub(string $platform, string $signingSecret = self::SECRET): void
    {
        Http::fake(['https://social.jwaistudio.online/api/v1/oauth/*' => function ($request) use ($platform, $signingSecret) {
            $this->assertSame('POST', $request->method());
            $this->assertSame('studio-dev.example.test', $request['registered_domain']);
            $this->assertSame('independent-development-client', $request->header('X-Social-Client')[0]);
            $this->assertSame('independent-development-key', $request->header('X-Social-Key-Id')[0]);
            $action = basename(parse_url($request->url(), PHP_URL_PATH));
            $result = match ($action) {
                'start' => ['launch_url' => 'https://social.jwaistudio.online/oauth/'.$platform.'/launch?ticket='.str_repeat('a', 64)],
                'redeem' => ['result' => 'select', 'pages' => [['id' => '123', 'name' => 'Development account']]],
                'select' => ['permissions' => ['pages_show_list', 'pages_read_engagement', 'instagram_basic'],
                    'page' => ['id' => '123', 'name' => 'Development account', 'facebook_page_id' => '456', 'access_token' => 'FAKE-ACCOUNT-TOKEN']],
                default => throw new \RuntimeException('Unexpected action'),
            };
            $body = json_encode(['app_id' => 'test-app', 'transaction_id' => $this->transaction] + $result);
            $headers = collect($request->headers())->map(fn ($values) => $values[0])->all();
            return Http::response($body, 200, ['X-Social-Response-Signature' =>
                SocialProtocol::responseSignature($headers, $signingSecret, 'client-to-hub', 200, $body)]);
        }]);
    }

    #[DataProvider('platforms')]
    public function test_independent_development_installation_completes_signed_marketing_handoff(string $platform): void
    {
        $this->fakeHub($platform);
        $this->post(self::ORIGIN.'/admin/marketing/channels/'.$platform.'/authorize')
            ->assertRedirect('https://social.jwaistudio.online/oauth/'.$platform.'/launch?ticket='.str_repeat('a', 64));
        $state = session($platform.'_oauth.state');
        $return = self::ORIGIN.'/admin/social/'.$platform.'/hub-return?'.http_build_query([
            'state' => $state, 'transaction' => $this->transaction, 'handoff' => str_repeat('b', 64)]);
        $this->get($return)->assertRedirect('/admin/marketing/channels?platform='.$platform.'&result=select');
        $this->postJson(self::ORIGIN.'/admin/marketing/channels/'.$platform.'/select', ['account_id' => '123'])
            ->assertOk()->assertDontSee('FAKE-ACCOUNT-TOKEN')->assertDontSee(self::SECRET)
            ->assertJsonPath('accounts.0.capabilities.publishing_enabled', false);
        $row = MarketingChannelConnection::sole();
        $this->assertSame('FAKE-ACCOUNT-TOKEN', $row->access_token);
        $this->assertNotSame($row->access_token, $row->getRawOriginal('access_token'));
        $this->assertDatabaseCount('managed_social_accounts', 0);
        $this->get($return)->assertRedirect('/admin/social/'.$platform.'?'.$platform.'=invalid_state');
        $this->postJson(self::ORIGIN.'/admin/marketing/channels/'.$platform.'/select', ['account_id' => '123'])->assertStatus(409);
        Http::assertSentCount(3);
        Queue::assertNothingPushed();
    }

    public function test_rebinding_development_installation_invalidates_the_old_handoff(): void
    {
        $this->fakeHub('facebook');
        $this->post(self::ORIGIN.'/admin/marketing/channels/facebook/authorize')->assertRedirect();
        $state = session('facebook_oauth.state');
        DB::table('social_hub_settings')->update(['client_id' => 'another-client', 'key_id' => 'another-key']);
        $this->get(self::ORIGIN.'/admin/social/facebook/hub-return?'.http_build_query([
            'state' => $state, 'transaction' => $this->transaction, 'handoff' => str_repeat('b', 64)]))
            ->assertRedirect('/admin/marketing/channels?platform=facebook&result=invalid_state');
        Http::assertSentCount(1);
        $this->assertDatabaseCount('marketing_channel_connections', 0);
    }

    public function test_a_different_installations_signature_cannot_start_authorization(): void
    {
        $this->fakeHub('facebook', 'OTHER-INSTALLATION-SECRET');
        $this->post(self::ORIGIN.'/admin/marketing/channels/facebook/authorize')
            ->assertRedirect('/admin/marketing/channels?platform=facebook&result=hub_unavailable');
        $this->assertNull(session('facebook_oauth'));
        Http::assertSentCount(1);
        $this->assertDatabaseCount('marketing_channel_connections', 0);
    }
}
