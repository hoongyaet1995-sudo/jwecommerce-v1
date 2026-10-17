<?php

namespace Tests\Feature;

use App\Models\ProductEnquiry;
use App\Models\SupportConversation;
use App\Services\Support\EnquiryReception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class EnquiryReceptionPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function thread(): SupportConversation
    {
        $c = SupportConversation::create(['id' => (string) Str::uuid(), 'owner_hash' => hash('sha256', Str::uuid()), 'status' => 'waiting_human']);
        ProductEnquiry::create(['id' => (string) Str::uuid(), 'reference' => 'TEST-ENQ', 'owner_hash' => hash('sha256', 'owner'), 'request_key' => (string) Str::uuid(), 'payload_hash' => str_repeat('a', 64), 'support_conversation_id' => $c->id, 'product_title' => 'Container', 'product_snapshot' => ['handle' => 'container', 'image' => '/images/themes/logistics/containers.png', 'variant' => '20ft', 'sku' => 'DEMO'], 'name' => 'Test', 'email' => 'test@example.test', 'requirements' => 'Need two containers']);

        return $c;
    }

    public function test_waiting_is_eligible_only_when_both_access_gates_allow_and_takeover_stops_it(): void
    {
        $c = $this->thread();
        $r = app(EnquiryReception::class);
        $s = ['autoReplyEnabled' => true, 'enquiryAccess' => ['isEnabled' => true]];
        $this->assertFalse($r->eligible($c, []));
        $this->assertTrue($r->eligible($c, $s));
        $c->update(['status' => 'human_mode']);
        $this->assertFalse($r->eligible($c, $s));
        $c->update(['status' => 'waiting_human']);
        $s['enquiryAccess']['isEnabled'] = false;
        $this->assertFalse($r->eligible($c, $s));
    }

    public function test_snapshot_card_is_private_and_does_not_expose_a_price(): void
    {
        $c = $this->thread();
        $card = app(EnquiryReception::class)->card($c);
        $this->assertSame('Container', $card['title']);
        $this->assertSame('20ft', $card['variant']);
        $this->assertArrayNotHasKey('price', $card);
        $e = ProductEnquiry::where('support_conversation_id', $c->id)->first();
        $e->update(['product_snapshot' => ['handle' => '//evil.test', 'image' => 'javascript:alert(1)']]);
        $card = app(EnquiryReception::class)->card($c);
        $this->assertNull($card['url']);
        $this->assertNull($card['image']);
    }
}
