<?php

namespace Tests\Feature;

use App\Models\Collection;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\User;
use App\Services\Support\ProductKnowledgeService;
use App\Services\Support\SupportActionResolver;
use App\Services\Support\SupportPageContext;
use App\Services\SupportFaqService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SupportPageContextTest extends TestCase
{
    use RefreshDatabase;

    private function settings(): array
    {
        return ['pageContext' => ['enabled' => true], 'productCatalog' => ['isEnabled' => true, 'canExplainSizingAndMaterials' => true, 'canRecommendUpsells' => true], 'faq' => ['isEnabled' => true]];
    }

    public function test_only_allowlisted_relative_public_paths_are_retained(): void
    {
        $s = app(SupportPageContext::class);
        foreach (['/admin', '/account/private', '/quotations/private', 'https://evil.test/products/a', '//evil.test', '/products/a/../admin'] as $path) {
            $this->assertNull($s->hint(['path' => $path], $this->settings()));
        }
        $this->assertNull($s->hint(['path' => '/products/a'], []));
        $this->assertSame(['path' => '/products/a', 'variant' => 12], $s->hint(['path' => '/products/a', 'variant' => 12, 'title' => 'injected'], $this->settings()));
    }

    public function test_product_context_is_server_owned_and_variant_must_belong_to_product(): void
    {
        $p = Product::create(['title' => 'Public Container', 'handle' => 'public-container', 'status' => 'active', 'description' => 'Storage only', 'base_price' => 100]);
        $v = $p->variants()->create(['title' => '20ft', 'sku' => 'CTX-20', 'price' => 100, 'stock' => 0, 'enabled' => true]);
        $p->pdpContent()->create(['faqs' => [['question' => 'Office?', 'answer' => 'Ask our team.']]]);
        $s = app(SupportPageContext::class);
        $hint = ['path' => '/products/public-container', 'variant' => $v->id];
        $facts = $s->facts($hint, $this->settings());
        $text = json_encode($facts);
        $this->assertStringContainsString('Public Container', $text);
        $this->assertStringContainsString('20ft', $text);
        $this->assertStringContainsString('Ask our team.', $text);
        $this->assertStringNotContainsString('base_price', $text);
        $hint['variant'] = 999999;
        $this->assertStringNotContainsString('CTX-20', json_encode($s->facts($hint, $this->settings())));
        $p->update(['status' => 'draft']);
        $this->assertSame([], $s->facts($hint, $this->settings()));
    }

    public function test_off_and_product_permission_prevent_retrieval(): void
    {
        Product::create(['title' => 'Secret', 'handle' => 'secret', 'status' => 'active', 'description' => 'Do not disclose', 'base_price' => 100]);
        $s = app(SupportPageContext::class);
        $h = ['path' => '/products/secret'];
        $this->assertSame([], $s->facts($h, []));
        $settings = $this->settings();
        $settings['productCatalog']['isEnabled'] = false;
        $this->assertSame([], $s->facts($h, $settings));
    }

    public function test_visible_cms_copy_excludes_hidden_items_and_nontext_configuration(): void
    {
        $s = app(SupportPageContext::class);
        $text = json_encode($s->visibleText([['title' => 'Visible', 'embed' => 'SECRET-CODE', 'items' => [['title' => 'Hidden', 'isVisible' => false], ['title' => 'Public']]], ['title' => 'Hidden section', 'isVisible' => false]]));
        $this->assertStringContainsString('Public', $text);
        $this->assertStringNotContainsString('Hidden', $text);
        $this->assertStringNotContainsString('SECRET-CODE', $text);
    }

    public function test_setting_round_trip_and_customer_message_snapshot(): void
    {
        $owner = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        $this->actingAs($owner);
        $payload = ['version' => 0, 'faq' => ['isEnabled' => true, 'retrievalDepth' => 'strict', 'items' => [], 'categories' => [['id' => 'others', 'label' => 'Others']]], 'pageContext' => ['enabled' => true]];
        $this->putJson('/admin/api/support/faq', $payload)->assertOk()->assertJsonPath('pageContext.enabled', true);
        $this->getJson('/admin/api/support/faq')->assertOk()->assertJsonPath('pageContext.enabled', true);
        $this->getJson('/support/faq')->assertOk()->assertJsonMissingPath('pageContext');
        $this->postJson('/support/chat/messages', ['content' => 'What is this?', 'client_message_id' => (string) Str::uuid(), 'page_context' => ['path' => '/products/public-container', 'variant' => 12]])->assertOk();
        $message = SupportMessage::where('sender_role', 'customer')->latest('id')->firstOrFail();
        $this->assertSame('/products/public-container', $message->ai_trace['pageContext']['path']);
        $payload['version'] = 1;
        $payload['pageContext'] = ['enabled' => false];
        $this->putJson('/admin/api/support/faq', $payload)->assertOk();
        $c = $message->conversation_id;
        $this->assertSame([], app(SupportPageContext::class)->current(SupportConversation::find($c), app(SupportFaqService::class)->read()));
    }

    public function test_visible_collection_and_shared_filters_exclude_hidden_products(): void
    {
        $collection = Collection::create(['title' => 'Containers', 'slug' => 'ctx-containers', 'is_active' => true]);
        $p = Product::create(['title' => '20ft Container', 'handle' => 'ctx-20ft', 'status' => 'active', 'base_price' => 100]);
        $hidden = Product::create(['title' => 'Private Hidden', 'handle' => 'ctx-hidden', 'status' => 'draft', 'base_price' => 100]);
        $collection->products()->attach([$p->id, $hidden->id]);
        $facts = app(SupportPageContext::class)->facts(['path' => '/collections/ctx-containers?search=20ft&secret=ignored'], $this->settings());
        $text = json_encode($facts);
        $this->assertStringContainsString('20ft Container', $text);
        $this->assertStringNotContainsString('Private Hidden', $text);
        $this->assertStringNotContainsString('secret', $text);
        $collection->update(['is_active' => false]);
        $this->assertSame([], app(SupportPageContext::class)->facts(['path' => '/collections/ctx-containers'], $this->settings()));
    }

    public function test_page_changes_keep_previous_identity_only_and_off_removes_both(): void
    {
        foreach (['a', 'b'] as $name) {
            Product::create(['title' => 'Product '.$name, 'handle' => 'ctx-'.$name, 'status' => 'active', 'base_price' => 100]);
        }
        $c = SupportConversation::create(['id' => (string) Str::uuid(), 'owner_hash' => hash('sha256', 'ctx-test'), 'channel' => 'web_guest']);
        foreach (['a', 'b'] as $name) {
            $c->messages()->create(['client_message_id' => (string) Str::uuid(), 'sender_role' => 'customer', 'content' => 'What about this?', 'ai_trace' => ['pageContext' => ['path' => '/products/ctx-'.$name]]]);
        }
        $s = app(SupportPageContext::class);
        $facts = $s->current($c, $this->settings());
        $this->assertCount(2, $facts);
        $this->assertStringContainsString('Product b', $facts[0]['answer']);
        $this->assertStringContainsString('Product a', $facts[1]['answer']);
        $this->assertSame([], $s->current($c, []));
    }

    public function test_quote_hidden_price_is_not_disclosed_by_chat_cards_or_catalogue_facts(): void
    {
        config(['product_enquiries.enabled' => true]);
        $p = Product::create(['title' => 'Quote Container', 'handle' => 'ctx-quote', 'status' => 'active', 'base_price' => 0, 'purchase_mode' => 'quote', 'price_display' => 'hide']);
        StoreSetting::create(['key' => 'support_faq', 'value' => ['productCatalog' => ['isEnabled' => true, 'canCheckLiveStock' => true, 'canRecommendUpsells' => true]]]);
        $actions = app(SupportActionResolver::class)->resolve([['type' => 'view_product', 'resource_id' => $p->id]], '')['actions'];
        $this->assertNull($actions[0]['price']);
        $facts = app(ProductKnowledgeService::class)->facts('Quote Container',['isEnabled' => true, 'canCheckLiveStock' => true, 'canRecommendUpsells' => true]);
        $this->assertStringNotContainsString('base_price',json_encode($facts));
    }
}
