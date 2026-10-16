<?php

namespace Tests\Feature;

use App\Models\EnquiryQuotationVersion;
use App\Models\Product;
use App\Models\ProductEnquiry;
use App\Models\StoreSetting;
use App\Models\User;
use App\Services\EnquiryQuotationService;
use App\Support\CurrencySettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class EnquiryQuotationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_quotation_previews_exclude_customer_chat_but_public_quote_keeps_it(): void
    {
        [$e, $admin] = $this->fixture();
        $url = '/admin/api/enquiries/'.$e->id.'/quotation';
        $this->actingAs($admin, 'web')->putJson($url, $this->payload())->assertOk();
        $this->get('/admin/enquiries/'.$e->id.'/quotation-preview')->assertOk()->assertDontSee('id="sf-support-launcher"', false)->assertDontSee('id="sf-support-panel"', false);
        $sent = $this->postJson($url.'/send', ['lockVersion' => 1])->assertOk();
        $id = $sent->json('data.versions.0.id');
        $this->get('/admin/enquiries/'.$e->id.'/quotation-preview')->assertOk()->assertDontSee('id="sf-support-launcher"', false);
        $this->get('/admin/quotations/'.$id.'/preview')->assertOk()->assertDontSee('id="sf-support-launcher"', false);
        $this->get('/quotations/'.$id)->assertOk()->assertSee('id="sf-support-launcher"', false);
    }

    private function fixture(): array
    {
        config(['product_enquiries.enabled' => true]);
        $p = Product::create(['title' => 'Custom bottle', 'handle' => 'quote-bottle', 'purchase_mode' => 'quote', 'status' => 'active', 'base_price' => 0]);
        $this->post('/enquiries', ['product_id' => $p->id, 'request_key' => (string) Str::uuid(), 'name' => 'Quote buyer', 'email' => 'buyer@example.test', 'requirements' => 'Fifty bottles with printing.'])->assertRedirect();

        return [ProductEnquiry::firstOrFail(), User::factory()->create(['is_admin' => true])];
    }

    private function payload(int $lock = 0): array
    {
        return ['contextHash' => app(EnquiryQuotationService::class)->view(ProductEnquiry::firstOrFail())['contextHash'], 'lockVersion' => $lock, 'validUntil' => now('Asia/Kuala_Lumpur')->addDays(14)->toDateString(), 'notes' => 'Printing included.', 'items' => [['name' => 'Bottle', 'description' => 'Stone / 500 ml', 'qty' => '50', 'price' => '79.00'], ['name' => 'Printing', 'description' => 'One logo', 'qty' => '50', 'price' => '8.00']]];
    }

    public function test_real_draft_send_revision_and_private_customer_access(): void
    {
        [$e,$admin] = $this->fixture();
        $url = '/admin/api/enquiries/'.$e->id.'/quotation';
        $this->actingAs($admin, 'web')->getJson($url)->assertOk()->assertJsonPath('data.lockVersion', 0);
        $r = $this->putJson($url, $this->payload())->assertOk()->assertJsonPath('data.document.totalMinor', 435000);
        $lock = $r->json('data.lockVersion');
        $this->getJson($url)->assertOk()->assertJsonPath('data.document.items.0.name', 'Bottle');
        $sent = $this->postJson($url.'/send', ['lockVersion' => $lock])->assertOk();
        $path = $sent->json('data.versions.0.url');
        $this->get($path)->assertOk()->assertSee('Bottle')->assertSee('4,350.00');
        $this->postJson($url.'/send', ['lockVersion' => $lock])->assertOk();
        $this->assertDatabaseCount('enquiry_quotation_versions', 1);
        $this->putJson($url, $this->payload($sent->json('data.lockVersion')))->assertStatus(409);
        $revision = $this->postJson($url.'/revise', ['lockVersion' => $sent->json('data.lockVersion')])->assertOk()->assertJsonPath('data.revision', 2);
        $data = $this->payload($revision->json('data.lockVersion'));
        $data['items'][0]['price'] = '99.00';
        $this->putJson($url, $data)->assertOk()->assertJsonPath('data.document.totalMinor', 535000);
        $this->get($path)->assertOk()->assertSee('4,350.00')->assertDontSee('5,350.00');
        $this->assertDatabaseCount('orders', 0);
        $this->flushSession();
        $this->get($path)->assertNotFound();
    }

    public function test_permissions_stale_edits_invalid_numbers_and_expired_send(): void
    {
        [$e,$admin] = $this->fixture();
        $url = '/admin/api/enquiries/'.$e->id.'/quotation';
        $this->getJson($url)->assertUnauthorized();
        $this->actingAs($admin, 'web')->putJson($url, $this->payload())->assertOk();
        $this->putJson($url, $this->payload())->assertStatus(409);
        $data = $this->payload(1);
        $data['items'][0]['qty'] = '0';
        $this->putJson($url, $data)->assertUnprocessable();
        $data = $this->payload(1);
        $data['items'][0]['price'] = '-1';
        $this->putJson($url, $data)->assertUnprocessable();
        $this->travel(15)->days();
        $this->postJson($url.'/send', ['lockVersion' => 1])->assertUnprocessable();
        $this->assertDatabaseCount('enquiry_quotation_versions', 0);
    }

    public function test_pending_and_linked_chat_delivery_are_private_and_idempotent(): void
    {
        [$e,$admin] = $this->fixture();
        $url = '/admin/api/enquiries/'.$e->id.'/quotation';
        $this->actingAs($admin, 'web')->putJson($url, $this->payload())->assertOk();
        $this->get('/enquiries/'.$e->id)->assertOk()->assertDontSee('Your quotations');
        $sent = $this->postJson($url.'/send', ['lockVersion' => 1])->assertOk();
        $path = $sent->json('data.versions.0.url');
        $this->assertDatabaseCount('support_messages', 0);
        $this->get('/enquiries/'.$e->id)->assertOk()->assertSee('Your quotations')->assertSee($path);
        $this->post($path.'/support')->assertRedirect($path.'?support=1');
        $c = $e->fresh()->supportConversation;
        $this->assertSame(2, $c->messages()->count());
        $this->getJson('/support/chat?enquiry_id='.$e->id)->assertOk()->assertJsonPath('conversation.messages.1.actions.0.type', 'view_quotation')->assertJsonPath('conversation.messages.1.actions.0.url', $path);
        $this->post($path.'/support')->assertRedirect();
        $this->postJson($url.'/send', ['lockVersion' => 1])->assertOk();
        $this->assertSame(2, $c->messages()->count());
        $rev = $this->postJson($url.'/revise', ['lockVersion' => $sent->json('data.lockVersion')])->assertOk();
        $saved = $this->putJson($url, $this->payload($rev->json('data.lockVersion')))->assertOk();
        $c->update(['status' => 'resolved']);
        $this->postJson($url.'/send', ['lockVersion' => $saved->json('data.lockVersion')])->assertOk();
        $this->assertSame(3, $c->messages()->count());
        $this->assertSame('waiting_human', $c->fresh()->status);
        $this->assertSame('new', $e->fresh()->status);
        $this->flushSession();
        $this->post($path.'/support')->assertNotFound();
        $this->get($path)->assertNotFound();
    }

    public function test_customer_owner_only_and_admin_previews_require_admin(): void
    {
        $customer = User::factory()->create();
        $this->actingAs($customer, 'customer');
        [$e,$admin] = $this->fixture();
        $url = '/admin/api/enquiries/'.$e->id.'/quotation';
        $this->actingAs($admin, 'web')->putJson($url, $this->payload())->assertOk();
        $sent = $this->postJson($url.'/send', ['lockVersion' => 1])->assertOk();
        $path = $sent->json('data.versions.0.url');
        $this->flushSession();
        $this->get($path)->assertOk();
        $this->actingAs(User::factory()->create(), 'customer')->get($path)->assertNotFound();
        $this->actingAs(User::factory()->create(['is_admin' => false]), 'web')->getJson($url)->assertUnauthorized();
        $this->getJson('/admin/enquiries/'.$e->id.'/quotation-preview')->assertUnauthorized();
    }

    public function test_minor_unit_rounding_server_totals_expiry_and_immutable_snapshot(): void
    {
        [$e,$admin] = $this->fixture();
        $url = '/admin/api/enquiries/'.$e->id.'/quotation';
        $data = $this->payload();
        $data['items'] = [['name' => 'Fractional scope', 'description' => '', 'qty' => '0.29', 'price' => '0.50']];
        $data['totalMinor'] = 999;
        $this->actingAs($admin, 'web')->putJson($url, $data)->assertOk()->assertJsonPath('data.document.totalMinor', 15);
        $sent = $this->postJson($url.'/send', ['lockVersion' => 1])->assertOk();
        $path = $sent->json('data.versions.0.url');
        $v = EnquiryQuotationVersion::firstOrFail();
        $original = $v->document;
        $this->assertSame($data['validUntil'].' 23:59:59', $v->expires_at->timezone('Asia/Kuala_Lumpur')->format('Y-m-d H:i:s'));
        $e->update(['name' => 'Changed customer', 'product_title' => 'Changed product']);
        $this->get($path)->assertOk()->assertSee('Quote buyer')->assertDontSee('Changed customer');
        $this->travel(15)->days();
        $this->get($path)->assertOk()->assertSee('Expired');
        $this->assertSame($original, $v->fresh()->document);
        $this->expectException(\LogicException::class);
        $v->update(['document' => ['totalMinor' => 1]]);
    }

    public function test_real_send_form_keeps_csrf_and_ignores_duplicate_publication(): void
    {
        [$e,$admin] = $this->fixture();
        $url = '/admin/api/enquiries/'.$e->id.'/quotation';
        $this->actingAs($admin, 'web')->putJson($url, $this->payload())->assertOk();
        $this->app->instance('env', 'local');
        $this->withSession(['_token' => 'quotation-csrf']);
        $form = '/admin/enquiries/'.$e->id.'/quotation/send';
        $this->post($form, ['lockVersion' => 1])->assertStatus(419);
        $this->post($form, ['_token' => 'quotation-csrf', 'lockVersion' => 1])->assertRedirect('/admin/enquiries/'.$e->id.'/quotation-preview');
        $this->post($form, ['_token' => 'quotation-csrf', 'lockVersion' => 1])->assertRedirect();
        $this->assertDatabaseCount('enquiry_quotation_versions', 1);
    }

    public function test_currency_drift_requires_reconfirmation_and_pending_versions_arrive_oldest_first(): void
    {
        [$e,$admin] = $this->fixture();
        $url = '/admin/api/enquiries/'.$e->id.'/quotation';
        $data = $this->payload();
        $currencies = CurrencySettings::defaults();
        foreach ($currencies as &$currency) {
            $currency['isDefault'] = $currency['code'] === 'SGD';
        }unset($currency);
        StoreSetting::where('key', 'currencies')->update(['value' => json_encode(['data' => $currencies])]);
        $this->actingAs($admin, 'web')->putJson($url, $data)->assertStatus(409);
        $this->assertDatabaseCount('enquiry_quotations', 0);
        $saved = $this->putJson($url, $this->payload())->assertOk()->assertJsonPath('data.document.currency.code', 'SGD');
        $sent = $this->postJson($url.'/send', ['lockVersion' => $saved->json('data.lockVersion')])->assertOk();
        $revised = $this->postJson($url.'/revise', ['lockVersion' => $sent->json('data.lockVersion')])->assertOk();
        $saved = $this->putJson($url, $this->payload($revised->json('data.lockVersion')))->assertOk();
        $this->postJson($url.'/send', ['lockVersion' => $saved->json('data.lockVersion')])->assertOk();
        $this->post('/enquiries/'.$e->id.'/support')->assertRedirect();
        $versions = EnquiryQuotationVersion::orderBy('revision')->get();
        $this->assertLessThan($versions[1]->support_message_id,$versions[0]->support_message_id);
        $this->post('/enquiries/'.$e->id.'/support')->assertRedirect();
        $this->assertDatabaseCount('support_messages',3);
    }
}
