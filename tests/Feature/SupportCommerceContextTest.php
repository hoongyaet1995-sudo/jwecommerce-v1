<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\EnquiryQuotation;
use App\Models\EnquiryQuotationVersion;
use App\Models\Order;
use App\Models\OrderTrackingVerification;
use App\Models\Product;
use App\Models\ProductEnquiry;
use App\Models\StoreSetting;
use App\Models\SupportConversation;
use App\Models\User;
use App\Services\AI\AiConnectionService;
use App\Services\Support\SupportChatService;
use App\Services\Support\SupportCommerceContext;
use App\Services\Support\SupportPageContext;
use App\Services\Support\UnifiedKnowledgeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class SupportCommerceContextTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Route::post('/_test/commerce-context', function (Request $r) {
            $c = app(SupportChatService::class)->sendCustomer($r, ['client_message_id' => (string) Str::uuid(), 'content' => 'Local private context test']);

            return app(SupportCommerceContext::class)->facts($r->input('hint'), $r->input('settings'), $r, $c);
        })->middleware('web');
        Route::post('/_test/context-retrieval', function (Request $r) {
            $c = app(SupportChatService::class)->sendCustomer($r, ['client_message_id' => (string) Str::uuid(), 'content' => 'Summarize this cart', 'page_context' => $r->input('hint')]);

            return app(UnifiedKnowledgeService::class)->retrieve('Summarize this cart', $r->input('settings'), [], $c, 'en', true);
        })->middleware('web');
    }

    private function settings(): array
    {
        return ['pageContext' => ['enabled' => true, 'cart' => true, 'enquiries' => true, 'quotations' => true, 'account' => true], 'enquiryAccess' => ['isEnabled' => true], 'productCatalog' => ['isEnabled' => true, 'canExplainSizingAndMaterials' => true], 'orderLookup' => ['isEnabled' => true, 'allowedFields' => ['orderStatus' => true, 'itemSummary' => true]]];
    }

    private function probe(string $path, ?array $settings = null)
    {
        return $this->postJson('/_test/commerce-context', ['hint' => ['path' => $path], 'settings' => $settings ?? $this->settings()])->assertOk();
    }

    public function test_cart_is_session_owned_and_excludes_nonpurchasable_or_disabled_variants(): void
    {
        config(['product_enquiries.enabled' => true]);
        $p = Product::create(['handle' => 'ctx-cart', 'title' => 'Cart item', 'status' => 'active', 'base_price' => 500]);
        $v = $p->variants()->create(['sku' => 'CTX-CART', 'title' => 'Blue', 'price' => 500, 'stock' => 3, 'enabled' => true]);
        $q = Product::create(['handle' => 'ctx-quote', 'title' => 'Hidden quote item', 'status' => 'active', 'purchase_mode' => 'quote', 'price_display' => 'hide', 'base_price' => 0]);
        $v2 = $q->variants()->create(['sku' => 'CTX-QUOTE', 'title' => 'Quote', 'price' => 0, 'stock' => 0, 'enabled' => true]);
        $response = $this->withSession(['cart' => [$v->id => 2, $v2->id => 1]])->probe('/cart');
        $text = $response->getContent();
        $this->assertStringContainsString('Cart item', $text);
        $this->assertStringNotContainsString('Hidden quote item', $text);
        $this->assertStringContainsString('1000', $text);
        $s = $this->settings();
        $s['pageContext']['cart'] = false;
        $this->probe('/cart', $s)->assertExactJson([]);
    }

    public function test_account_requires_customer_authentication_and_masks_contacts(): void
    {
        $this->probe('/account')->assertExactJson([]);
        $u = User::factory()->create(['is_admin' => false, 'email' => 'private-buyer@example.test']);
        Customer::create(['user_id' => $u->id, 'email' => $u->email, 'first_name' => 'Test', 'status' => 'active', 'phone' => '0123456789']);
        $this->actingAs($u, 'customer');
        $response = $this->probe('/account');
        $this->assertStringContainsString('account', $response->getContent());
        $this->assertStringNotContainsString($u->email, $response->getContent());
        $this->assertStringNotContainsString('0123456789', $response->getContent());
    }

    public function test_enquiry_ownership_and_scope_are_enforced(): void
    {
        $id = (string) Str::uuid();
        ProductEnquiry::create(['id' => $id, 'reference' => 'CTX-ENQ', 'owner_hash' => hash('sha256', 'own-token'), 'request_key' => (string) Str::uuid(), 'payload_hash' => str_repeat('a', 64), 'product_snapshot' => [], 'product_title' => 'Customer container', 'name' => 'Private Name', 'email' => 'private@example.test', 'requirements' => 'Need two units']);
        $this->probe('/enquiries/'.$id)->assertExactJson([]);
        $response = $this->withSession(['enquiry_owner' => 'own-token'])->probe('/enquiries/'.$id);
        $this->assertStringContainsString('Need two units', $response->getContent());
        $this->assertStringNotContainsString('private@example.test', $response->getContent());
        $s = $this->settings();
        $s['enquiryAccess']['isEnabled'] = false;
        $this->probe('/enquiries/'.$id, $s)->assertExactJson([]);
    }

    public function test_quotation_reads_sent_snapshot_only_for_owner(): void
    {
        $e = ProductEnquiry::create(['id' => (string) Str::uuid(), 'reference' => 'CTX-Q-ENQ', 'owner_hash' => hash('sha256', 'quote-owner'), 'request_key' => (string) Str::uuid(), 'payload_hash' => str_repeat('b', 64), 'product_title' => 'Service', 'product_snapshot' => [], 'name' => 'Do not disclose', 'email' => 'secret@example.test', 'requirements' => 'Agreed scope']);
        $q = EnquiryQuotation::create(['id' => (string) Str::uuid(), 'product_enquiry_id' => $e->id, 'number' => 'CTX-QT', 'status' => 'sent', 'document' => ['notes' => 'UNSENT-DRAFT-SECRET']]);
        $v = EnquiryQuotationVersion::create(['id' => (string) Str::uuid(), 'enquiry_quotation_id' => $q->id, 'revision' => 1, 'document' => ['number' => 'CTX-QT', 'currency' => ['code' => 'MYR', 'decimals' => 2], 'totalMinor' => 50000, 'notes' => 'Includes agreed travel', 'items' => [['name' => 'Session', 'qty' => '1', 'price' => '500.00', 'amountMinor' => 50000]]], 'sent_at' => now(), 'expires_at' => now()->addDay()]);
        $this->probe('/quotations/'.$v->id)->assertExactJson([]);
        $response = $this->withSession(['enquiry_owner' => 'quote-owner'])->probe('/quotations/'.$v->id);
        $this->assertStringContainsString('50000', $response->getContent());
        $this->assertStringContainsString('Includes agreed travel', $response->getContent());
        $this->assertStringNotContainsString('UNSENT-DRAFT-SECRET', $response->getContent());
        $this->assertStringNotContainsString('secret@example.test', $response->getContent());
        $u = User::factory()->create(['is_admin' => false]);
        Customer::create(['user_id' => $u->id, 'email' => $u->email, 'status' => 'active']);
        $e->update(['customer_user_id' => $u->id]);
        $this->actingAs($u, 'customer');
        \App\Models\QuotationAcceptance::create(['enquiry_quotation_id' => $q->id, 'enquiry_quotation_version_id' => $v->id, 'accepted_at' => now(), 'accepted_by' => $u->id]);
        $list = $this->postJson('/_test/commerce-context', ['hint' => ['path' => '/account', 'tab' => 'quotations'], 'settings' => $this->settings()])->assertOk();
        $this->assertStringContainsString('"status":"accepted"', $list->json()[0]['answer']);
        $this->assertStringContainsString('"orderCreated":false', $list->json()[0]['answer']);

    }

    public function test_order_context_respects_owner_and_each_field_and_otp_expiry(): void
    {
        $u = User::factory()->create(['is_admin' => false]);
        $customer = Customer::create(['user_id' => $u->id, 'email' => $u->email, 'status' => 'active']);
        $order = Order::create(['number' => 'CTX-1001', 'customer_id' => $customer->id, 'email' => $u->email, 'currency' => 'MYR', 'status' => 'shipped', 'tracking_number' => 'PRIVATE-TRACKING', 'subtotal' => 100, 'total' => 100]);
        Order::create(['number' => 'CTX-2002', 'email' => 'other@example.test', 'currency' => 'MYR', 'status' => 'FOREIGN-SECRET', 'subtotal' => 100, 'total' => 100]);
        $this->actingAs($u, 'customer');
        $response = $this->postJson('/_test/commerce-context', ['hint' => ['path' => '/account', 'tab' => 'purchase_history'], 'settings' => $this->settings()])->assertOk();
        $text = $response->getContent();
        $this->assertStringContainsString('shipped', $text);
        $this->assertStringNotContainsString('FOREIGN-SECRET', $text);
        $this->assertStringNotContainsString('PRIVATE-TRACKING', $text);
        $this->assertStringNotContainsString($u->email, $text);
        auth('customer')->logout();
        $this->probe('/order-tracking')->assertExactJson([]);
        $attempt = OrderTrackingVerification::create(['public_id' => (string) Str::uuid(), 'order_id' => $order->id, 'email_hash' => str_repeat('a', 64), 'code_hash' => 'fixture', 'expires_at' => now()->addMinutes(15), 'verified_at' => now()->subMinutes(16)]);
        $this->withSession(['order_tracking.verified_attempt_id' => $attempt->public_id])->probe('/order-tracking')->assertExactJson([]);
        $attempt->update(['verified_at' => now()]);
        $this->assertStringContainsString('CTX-1001', $this->probe('/order-tracking')->getContent());
    }

    public function test_master_off_and_foreign_conversation_prevent_any_read(): void
    {
        $s = $this->settings();
        $s['pageContext']['enabled'] = false;
        $this->probe('/cart', $s)->assertExactJson([]);
        $r = Request::create('/support/chat/messages');
        $r->setLaravelSession(app('session')->driver());
        $c = new SupportConversation(['owner_hash' => 'foreign', 'channel' => 'web_guest']);
        $this->assertSame([], app(SupportCommerceContext::class)->facts(['path' => '/cart'], $this->settings(), $r, $c));
    }

    public function test_private_tool_routes_without_putting_cart_records_in_understanding_prompt(): void
    {
        $settings = $this->settings();
        StoreSetting::create(['key' => 'support_faq', 'value' => $settings]);
        $p = Product::create(['handle' => 'tool-cart', 'title' => 'UNIQUE-PRIVATE-CART-PRODUCT', 'status' => 'active', 'base_price' => 500]);
        $v = $p->variants()->create(['sku' => 'TOOL-CART', 'title' => 'Blue', 'price' => 500, 'stock' => 3, 'enabled' => true]);
        $this->mock(AiConnectionService::class)->shouldReceive('generateText')->once()->withArgs(function ($feature, $prompt, $tokens) {
            $this->assertStringNotContainsString('UNIQUE-PRIVATE-CART-PRODUCT', $prompt);
            $this->assertStringContainsString('commerce.read', $prompt);

            return true;
        })->andReturn(json_encode(['tools' => [['name' => 'commerce.read', 'query' => 'current cart']], 'escalation' => 'none', 'clarification' => 'none', 'language' => 'en', 'conversation' => 'none', 'needs_business_answer' => true, 'negotiation' => false, 'questions' => []]));
        $response = $this->withSession(['cart' => [$v->id => 2]])->postJson('/_test/context-retrieval', ['hint' => ['path' => '/cart'], 'settings' => $settings])->assertOk();
        $this->assertStringContainsString('UNIQUE-PRIVATE-CART-PRODUCT', $response->getContent());
    }

    public function test_private_scope_settings_round_trip_and_no_session_fail_closed(): void
    {
        $owner = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        $this->actingAs($owner, 'web');
        $payload = ['version' => 0, 'faq' => ['isEnabled' => true, 'retrievalDepth' => 'strict', 'categories' => [['id' => 'others', 'label' => 'Others']], 'items' => []], 'pageContext' => $this->settings()['pageContext']];
        $this->putJson('/admin/api/support/faq', $payload)->assertOk()->assertJsonPath('pageContext.cart', true)->assertJsonPath('pageContext.account', true);
        $this->getJson('/admin/api/support/faq')->assertOk()->assertJsonPath('pageContext.quotations', true);
        $this->getJson('/support/faq')->assertOk()->assertJsonMissingPath('pageContext');
        $payload['version'] = 1;
        $payload['pageContext']['allPrivateSecrets'] = true;
        $this->putJson('/admin/api/support/faq', $payload)->assertUnprocessable();
        $r = Request::create('/support/chat/messages');
        $c = new SupportConversation(['channel' => 'web_guest']);
        $this->assertSame([], app(SupportCommerceContext::class)->facts(['path' => '/cart'], $this->settings(), $r, $c));
        $hint = app(SupportPageContext::class)->hint(['path' => '/quotations/'.Str::uuid().'?email=secret@example.test&token=SECRET'], $this->settings());
        $this->assertStringNotContainsString('SECRET', json_encode($hint));
        $this->assertStringNotContainsString('secret@example.test', json_encode($hint));
    }

    public function test_rendered_cart_and_checkout_totals_share_server_snapshot_and_invalidate_on_cart_change(): void
    {
        $settings = $this->settings();
        StoreSetting::create(['key' => 'support_faq', 'value' => $settings]);
        $p = Product::create(['handle' => 'estimate-cart', 'title' => 'Estimate cart', 'status' => 'active', 'base_price' => 500]);
        $v = $p->variants()->create(['sku' => 'ESTIMATE-CART', 'title' => 'Blue', 'price' => 500, 'stock' => 3, 'enabled' => true]);
        $this->withSession(['cart' => [$v->id => 2]])->get('/cart')->assertOk();
        $this->assertStringContainsString('renderedEstimate', $this->probe('/cart')->getContent());
        $this->get('/checkout')->assertOk();
        $this->assertStringContainsString('renderedEstimate', $this->probe('/checkout')->getContent());
        $this->withSession(['cart' => [$v->id => 1]]);
        $this->assertStringNotContainsString('renderedEstimate', $this->probe('/checkout')->getContent());
    }
}
