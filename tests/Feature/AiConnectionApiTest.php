<?php

namespace Tests\Feature;

use App\Models\AiConnection;
use App\Models\User;
use App\Services\AI\AiConnectionService;
use App\Services\AI\CompatibleClient;
use App\Services\AI\ProviderFailure;
use App\Services\AI\ProviderTransport;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AiConnectionApiTest extends TestCase
{
    private const KEY = 'fixture-secret-never-return-this';

    public function test_sampling_uses_none_reasoning_for_gpt56_and_rejects_unsupported_models(): void
    {
        Http::fake(['api.openai.com/v1/responses' => Http::response(['output' => [['content' => [['type' => 'output_text', 'text' => 'OK']]]]])]);
        $client = app(\App\Services\AI\OpenAiClient::class);
        $this->assertSame('OK', $client->text(self::KEY, null, 'gpt-5.6-luna', 'Fixture', 280, 0.25));
        Http::assertSent(fn ($r) => $r['temperature'] === 0.25 && $r['reasoning']['effort'] === 'none');
        $this->expectException(\App\Services\AI\ProviderFailure::class);
        $client->text(self::KEY, null, 'o3', 'Fixture', 280, 0.25);
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('is_admin')->default(false);
            $table->boolean('is_super_admin')->default(false);
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
        (require database_path('migrations/2026_10_02_180000_create_ai_connections.php'))->up();
        Schema::table('ai_connections', fn (Blueprint $table) => $table->boolean('search_enabled')->default(false));
        Http::preventStrayRequests();
    }

    private function owner(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true, 'is_super_admin' => true]));
    }

    private function payload(array $overrides = []): array
    {
        return array_replace(['version' => 0, 'enabled' => false, 'support_enabled' => true, 'builder_enabled' => true, 'text_model' => null, 'image_model' => null, 'api_key' => self::KEY], $overrides);
    }

    private function save(string $provider = 'openai', array $overrides = [])
    {
        return $this->putJson('/admin/api/integration/ai/'.$provider, $this->payload($overrides));
    }

    private function verify(string $provider = 'openai', int $version = 1)
    {
        return $this->postJson('/admin/api/integration/ai/'.$provider.'/verify', ['version' => $version]);
    }

    private function fakeOpenai(): void
    {
        Http::fake([
            'api.openai.com/v1/models' => Http::response(['data' => [['id' => 'gpt-fixture'], ['id' => 'gpt-image-fixture'], ['id' => 'whisper-fixture']]]),
            'api.openai.com/v1/responses' => Http::response(['output' => [['content' => [['type' => 'output_text', 'text' => 'OK']]]]]),
        ]);
    }

    private function ready(): void
    {
        $this->save()->assertOk(); $this->verify()->assertOk();
        $this->save(overrides: ['version' => 2, 'api_key' => null, 'text_model' => 'gpt-fixture', 'enabled' => true])->assertOk();
    }
    public function test_only_owner_can_read_or_change_credentials(): void
    {
        $this->getJson('/admin/api/integration/ai')->assertUnauthorized();
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $this->getJson('/admin/api/integration/ai')->assertForbidden();
        $this->save()->assertForbidden();
        $this->verify()->assertForbidden();
        $this->postJson('/admin/api/integration/ai/openai/test-text', ['version' => 1])->assertNotFound();
        $this->assertDatabaseCount('ai_connections', 0);
        Http::assertNothingSent();
    }

    public function test_saved_key_is_encrypted_hidden_and_blank_retains_it(): void
    {
        $this->owner();
        $this->save()->assertOk()->assertJsonPath('connection.has_key', true)->assertHeader('Cache-Control', 'no-store, private');
        $cipher = DB::table('ai_connections')->value('api_key');
        $this->assertStringNotContainsString(self::KEY, $cipher);
        $this->assertSame(self::KEY, AiConnection::first()->api_key);
        $this->assertArrayNotHasKey('api_key', AiConnection::first()->toArray());
        $response = $this->getJson('/admin/api/integration/ai')->assertOk();
        $this->assertStringNotContainsString(self::KEY, $response->getContent());
        $this->save(overrides: ['version' => 1, 'api_key' => null])->assertOk();
        $this->assertSame(self::KEY, AiConnection::first()->api_key);
        Http::assertNothingSent();
    }

    public function test_owner_can_replace_an_undecryptable_key_and_must_reverify(): void
    {
        $this->owner();
        $this->fakeOpenai();
        $this->ready();
        DB::table('ai_connections')->where('provider', 'openai')->update(['api_key' => 'invalid-old-ciphertext']);
        $this->save(overrides: ['version' => 3, 'enabled' => false])->assertOk()
            ->assertJsonPath('connection.verified_at', null)
            ->assertJsonPath('connection.models', []);
        $this->assertSame(self::KEY, AiConnection::first()->api_key);
        Http::assertSentCount(1); // Only the initial fixture verification, no request on replacement.
    }

    public function test_verification_generation_and_feature_gates_are_distinct(): void
    {
        $this->owner();
        $this->fakeOpenai();
        $this->save(overrides: ['enabled' => true])->assertUnprocessable();
        $this->ready();
        $this->assertSame('OK', app(AiConnectionService::class)->generateText('support', 'Fixture question', 100, 0.3));
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/responses') && $r['store'] === false && $r['max_output_tokens'] === 100 && $r['temperature'] === 0.3);
        $this->save(overrides: ['version' => 3, 'api_key' => null, 'text_model' => 'gpt-fixture', 'enabled' => true, 'support_enabled' => false])->assertOk();
        $this->expectException(ValidationException::class);
        app(AiConnectionService::class)->generateText('support', 'Blocked question');
    }

    public function test_key_rotation_invalidates_verification_and_stale_writes_fail(): void
    {
        $this->owner();
        $this->fakeOpenai();
        $this->ready();
        $this->save(overrides: ['version' => 0])->assertConflict();
        $this->save(overrides: ['version' => 3, 'api_key' => 'replacement-fixture'])->assertOk()
            ->assertJsonPath('connection.verified_at', null)
            ->assertJsonPath('connection.enabled', false)->assertJsonPath('connection.models', []);
    }

    public function test_concurrent_change_cannot_receive_old_verification(): void
    {
        $this->owner();
        $this->save()->assertOk();
        Http::fake(function () {
            AiConnection::where('provider', 'openai')->update(['version' => 2]);

            return Http::response(['data' => [['id' => 'gpt-fixture']]]);
        });
        $this->verify()->assertConflict();
        $this->assertNull(AiConnection::first()->verified_at);
    }

    public function test_provider_errors_never_echo_keys_and_clear_readiness(): void
    {
        $this->owner();
        $this->save()->assertOk();
        Http::fake(['*' => Http::response(['error' => ['message' => self::KEY]], 401)]);
        $result = $this->verify()->assertUnprocessable()->assertJsonPath('code', 'authentication');
        $this->assertStringNotContainsString(self::KEY, $result->getContent());
        $this->assertSame('authentication', AiConnection::first()->last_error);
        $this->assertNull(AiConnection::first()->verified_at);
    }

    public function test_empty_models_and_invalid_selection_are_not_success(): void
    {
        $this->owner();
        $this->save()->assertOk();
        Http::fake(['*' => Http::response(['data' => []])]);
        $this->verify()->assertUnprocessable()->assertJsonPath('code', 'models');
        $this->save(overrides: ['version' => 2, 'api_key' => null, 'text_model' => 'invented-model'])->assertUnprocessable();
    }

    public function test_gemini_and_anthropic_native_headers_and_text_payloads(): void
    {
        $this->owner();
        Http::fake([
            'generativelanguage.googleapis.com/v1beta/models/gemini-fixture:generateContent' => Http::response(['candidates' => [['content' => ['parts' => [['text' => 'OK']]]]]]),
            'generativelanguage.googleapis.com/v1beta/models*' => Http::response(['models' => [['name' => 'models/gemini-fixture', 'supportedGenerationMethods' => ['generateContent']]]]),
            'api.anthropic.com/v1/models*' => Http::response(['data' => [['id' => 'claude-fixture']], 'has_more' => false]),
            'api.anthropic.com/v1/messages' => Http::response(['content' => [['type' => 'text', 'text' => 'OK']]]),
        ]);
        foreach (['gemini' => 'gemini-fixture', 'anthropic' => 'claude-fixture'] as $provider => $model) {
            $this->save($provider)->assertOk();
            $this->verify($provider)->assertOk();
            $this->save($provider, ['version' => 2, 'api_key' => null, 'text_model' => $model])->assertOk();
            $this->assertSame('OK', app(\App\Services\AI\ProviderRegistry::class)->client($provider)->text(self::KEY, null, $model, 'Fixture question', 256));
        }
        Http::assertSent(fn ($r) => str_contains($r->url(), 'generateContent') && $r->hasHeader('x-goog-api-key', self::KEY) && ! str_contains($r->url(), self::KEY));
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/messages') && $r->hasHeader('x-api-key', self::KEY) && $r->hasHeader('anthropic-version', '2023-06-01') && $r['max_tokens'] === 256);
    }

    public function test_compatible_rejects_arbitrary_or_private_destinations(): void
    {
        $this->owner();
        $this->save('compatible', ['base_url' => 'https://127.0.0.1/v1'])->assertUnprocessable();
        config(['ai_connect.compatible_base_urls' => ['https://127.0.0.1/v1']]);
        $this->save('compatible', ['base_url' => 'https://127.0.0.1/v1'])->assertOk();
        $this->verify('compatible')->assertUnprocessable()->assertJsonPath('code', 'endpoint');
        Http::assertNothingSent();
    }

    public function test_compatible_protocol_and_multiple_active_provider_guard(): void
    {
        config(['ai_connect.compatible_base_urls' => ['https://llm.example.com/v1']]);
        $transport = \Mockery::mock(ProviderTransport::class);
        $transport->shouldReceive('json')->with('POST', 'https://llm.example.com/v1/chat/completions', ['Authorization' => 'Bearer '.self::KEY], ['model' => 'fixture', 'messages' => [['role' => 'user', 'content' => 'Hello']], 'max_tokens' => 64], true)
            ->once()->andReturn(['choices' => [['message' => ['content' => 'OK']]]]);
        $this->assertSame('OK', (new CompatibleClient($transport))->text(self::KEY, 'https://llm.example.com/v1', 'fixture', 'Hello', 64));
        $this->owner();
        $this->fakeOpenai();
        $this->ready();
        AiConnection::create(['provider' => 'anthropic', 'api_key' => self::KEY, 'version' => 1, 'verified_at' => now(), 'text_model' => 'claude-fixture', 'models' => [['id' => 'claude-fixture', 'text' => true, 'image' => false]]]);
        $this->save('anthropic', ['version' => 1, 'api_key' => null, 'enabled' => true, 'text_model' => 'claude-fixture'])->assertOk();
        $this->assertFalse(AiConnection::find('openai')->enabled);
        $this->assertSame(1, AiConnection::where('enabled', true)->count());
    }

    public function test_verification_limit_is_independent_of_saves_and_other_providers(): void
    {
        $this->owner();
        $this->fakeOpenai();
        $this->save()->assertOk();
        for ($version = 1; $version <= 7; $version++) {
            $this->save(overrides: ['version' => $version, 'api_key' => null])->assertOk();
        }
        for ($version = 8; $version <= 13; $version++) {
            $this->verify(version: $version)->assertOk();
        }
        $this->verify(version: 14)->assertTooManyRequests();
    }

    public function test_custom_transport_rejects_special_use_addresses_without_sending_credentials(): void
    {
        foreach (['100.64.0.1', '198.18.0.1', '224.0.0.1', '169.254.169.254'] as $ip) {
            try {
                app(ProviderTransport::class)->json('GET', 'https://'.$ip.'/v1/models', ['Authorization' => 'Bearer '.self::KEY], custom: true);
                $this->fail('Special-use destination was allowed.');
            } catch (ProviderFailure $failure) {
                $this->assertSame('endpoint', $failure->reason);
            }
        }
        Http::assertNothingSent();
    }

    public function test_native_model_pagination_collects_all_pages(): void
    {
        $this->owner();
        Http::fake(function ($request) {
            if (str_contains($request->url(), 'googleapis')) {
                return Http::response(str_contains($request->url(), 'pageToken=')
                    ? ['models' => [['name' => 'models/gemini-second', 'supportedGenerationMethods' => ['generateContent']]]]
                    : ['models' => [['name' => 'models/gemini-first', 'supportedGenerationMethods' => ['generateContent']]], 'nextPageToken' => 'next']);
            }

            return Http::response(str_contains($request->url(), 'after_id=')
                ? ['data' => [['id' => 'claude-second']], 'has_more' => false]
                : ['data' => [['id' => 'claude-first']], 'has_more' => true, 'last_id' => 'claude-first']);
        });
        foreach (['gemini', 'anthropic'] as $provider) {
            $this->save($provider)->assertOk();
            $this->verify($provider)->assertOk()->assertJsonCount(2, 'connection.models');
        }
    }
}
