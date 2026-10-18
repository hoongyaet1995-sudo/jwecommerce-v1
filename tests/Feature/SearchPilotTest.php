<?php

namespace Tests\Feature;

use App\Models\AiConnection;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Models\User;
use App\Services\AI\AiConnectionService;
use App\Services\Search\BusinessIdentity;
use App\Services\Search\SearchPilotAudit;
use App\Services\Search\SearchRefreshService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchPilotTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_is_shared_and_private_business_fields_are_not_published(): void
    {
        StoreSetting::create(['key' => 'shop_profile', 'value' => ['data' => ['shopName' => 'Example Engineering', 'email' => 'private@example.com', 'businessRegNo' => 'PRIVATE-123']]]);
        StoreSetting::create(['key' => 'shop_name', 'value' => ['data' => 'Old Name']]);
        $this->assertSame('Example Engineering', app(BusinessIdentity::class)->read()['name']);
        $html = $this->get('/shop')->assertOk()->getContent();
        $graph = $this->graph($html);
        $this->assertSame('Example Engineering', $graph['@graph'][0]['name']);
        $this->assertStringNotContainsString('private@example.com', json_encode($graph));
        $this->assertStringNotContainsString('PRIVATE-123', json_encode($graph));
    }

    public function test_quote_product_has_no_fake_offer_and_script_payload_is_safe(): void
    {
        config(['product_enquiries.enabled' => true]);
        $p = Product::create(['title' => 'Container </script><script>alert(1)</script>', 'handle' => 'quote-container', 'status' => 'active', 'purchase_mode' => 'quote', 'price_display' => 'hide', 'base_price' => 100]);
        $graph = $this->graph($this->get('/products/'.$p->handle)->assertOk()->getContent());
        $product = collect($graph['@graph'])->firstWhere('@type', 'Product');
        $this->assertNotNull($product);
        $this->assertArrayNotHasKey('offers', $product);
        $this->assertArrayNotHasKey('aggregateRating', $product);
        $this->assertSame($p->title, $product['name']);
    }

    public function test_preview_and_private_pages_do_not_emit_public_graph(): void
    {
        StoreSetting::create(['key' => 'search_visibility', 'value' => ['preview' => true]]);
        $this->get('/shop')->assertOk()->assertDontSee('data-search-graph', false);
    }

    public function test_audit_distinguishes_local_readiness_from_external_indexing_and_has_no_private_contacts(): void
    {
        config(['app.url' => 'http://127.0.0.1:8500']);
        StoreSetting::create(['key' => 'shop_profile', 'value' => ['data' => ['shopName' => 'Other Store', 'email' => 'owner@example.com']]]);
        $p = Product::create(['title' => 'Visible item', 'handle' => 'visible', 'status' => 'active', 'base_price' => 100]);
        Product::create(['title' => 'Draft secret', 'handle' => 'secret', 'status' => 'draft', 'base_price' => 100]);
        $report = app(SearchPilotAudit::class)->capture();
        $this->assertSame('local_not_publicly_indexable', $report['environment']);
        $this->assertSame('not_verified', $report['indexing']['status']);
        $this->assertSame('not_measured', $report['ai_baseline']['status']);
        $this->assertCount(1, $report['records']);
        $this->assertSame($p->id, $report['records'][0]['id']);
        $this->assertStringNotContainsString('owner@example.com', json_encode($report));
        $this->assertSame('Other Store', StoreSetting::where('key', 'search_pilot_audit')->first()->value['identity']['name']);
    }

    public function test_existing_refresh_captures_audit_without_new_button_or_ai_calls(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true, 'is_super_admin' => true]));
        StoreSetting::create(['key' => 'search_visibility', 'value' => ['mode' => 'off', 'preview' => false]]);
        $this->mock(AiConnectionService::class)->shouldNotReceive('generateText');
        $this->postJson('/admin/api/search/refresh')->assertAccepted();
        $this->assertDatabaseHas('store_settings', ['key' => 'search_pilot_audit']);
        $this->assertSame('not_measured', StoreSetting::where('key', 'search_pilot_audit')->first()->value['ai_baseline']['status']);
    }

    public function test_refresh_final_audit_describes_completed_metadata_instead_of_pre_refresh_state(): void
    {
        Product::create(['title' => '20ft Storage Container', 'handle' => 'audit-final', 'status' => 'active', 'base_price' => 100]);
        StoreSetting::create(['key' => 'search_visibility', 'value' => ['mode' => 'auto', 'preview' => false]]);
        AiConnection::create(['provider' => 'openai', 'enabled' => true, 'search_enabled' => true, 'verified_at' => now(), 'text_model' => 'fixture', 'api_key' => 'fixture']);
        $this->mock(AiConnectionService::class)->shouldReceive('generateText')->once()->andReturn(json_encode(['title' => '20ft Storage Container', 'description' => '20ft Storage Container', 'suggestedTopics' => []]));
        $refresh = app(SearchRefreshService::class);
        $run = $refresh->start(User::factory()->create(['is_admin' => true, 'is_super_admin' => true])->id);
        $this->assertFalse(StoreSetting::where('key', 'search_pilot_audit')->first()->value['records'][0]['has_description']);
        $result = $refresh->advance($run['id']);
        $this->assertSame('completed', $result['status']);
        $this->assertTrue(StoreSetting::where('key', 'search_pilot_audit')->first()->value['records'][0]['has_description']);
    }

    private function graph(string $html): array
    {
        $this->assertSame(1, preg_match('/<script type="application\/ld\+json" data-search-graph>(.*?)<\/script>/s', $html, $matches));

        return json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
    }
}
