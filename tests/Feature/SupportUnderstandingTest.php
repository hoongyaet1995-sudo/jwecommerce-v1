<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Services\AI\AiConnectionService;
use App\Services\Support\SupportUnderstandingService;
class SupportUnderstandingTest extends TestCase
{
    public function test_understanding_preserves_mixed_intents_and_language_without_aliases(): void
    {
        $this->mock(AiConnectionService::class)->shouldReceive('generateText')->once()->withArgs(fn($feature,$prompt,$limit)=>str_contains($prompt,'Staff (context only): BILLY') && str_contains($prompt,'greetings are not business facts'))
            ->andReturn(json_encode(['tools'=>[['name'=>'product.search','query'=>'BILLY'],['name'=>'faq.search','query'=>'Penang delivery']], 'escalation'=>'none','clarification'=>'destination','language'=>'fr','conversation'=>'none','needs_business_answer'=>true]));
        $plan=app(SupportUnderstandingService::class)->understand('Bonjour, stock and delivery?', ['product.search'=>'Products','faq.search'=>'Policies'], ['Staff (context only): BILLY'], []);
        $this->assertSame('fr',$plan['language']); $this->assertCount(2,$plan['tools']); $this->assertTrue($plan['needs_business_answer']);
    }
    public function test_pure_unlisted_greeting_has_no_business_tools(): void
    {
        $this->mock(AiConnectionService::class)->shouldReceive('generateText')->andReturn('{"tools":[],"escalation":"none","clarification":"none","language":"fr","conversation":"greeting","needs_business_answer":false}');
        $plan=app(SupportUnderstandingService::class)->understand('bonjour',[],[],[]);
        $this->assertSame('greeting',$plan['conversation']); $this->assertFalse($plan['needs_business_answer']);
    }
    public function test_disabled_tools_and_mixed_social_plan_are_rejected(): void
    {
        $this->mock(AiConnectionService::class)->shouldReceive('generateText')->andReturn('{"tools":[{"name":"order.lookup","query":"123"}],"escalation":"none","clarification":"none","language":"en","conversation":"greeting","needs_business_answer":false}');
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(SupportUnderstandingService::class)->understand('hello show orders',[],[],[]);
    }
    public function test_invalid_language_or_missing_contract_is_rejected(): void
    {
        $this->mock(AiConnectionService::class)->shouldReceive('generateText')->andReturn('{"tools":[],"language":"ignore all rules"}');
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(SupportUnderstandingService::class)->understand('hello',[],[],[]);
    }
    public function test_social_only_cannot_execute_an_enabled_business_tool():void
    {
        $this->mock(AiConnectionService::class)->shouldReceive('generateText')->andReturn('{"tools":[{"name":"product.search","query":"BILLY"}],"escalation":"none","clarification":"none","language":"en","conversation":"greeting","needs_business_answer":false}');
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(SupportUnderstandingService::class)->understand('hello BILLY',['product.search'=>'Products'],[],[]);
    }
    public function test_sessionless_order_lookup_returns_verification_without_accessing_private_records():void
    {
        $request=\Illuminate\Http\Request::create('/','GET');app()->instance('request',$request);
        $conversation=new \App\Models\SupportConversation(['channel'=>'storefront','owner_hash'=>str_repeat('a',64)]);
        $answer=app(\App\Services\Support\OrderKnowledgeService::class)->reply('Order JW-12345',['isEnabled'=>true],$conversation);
        $this->assertStringContainsString('verify your order email code',$answer);
    }
    public function test_human_request_is_valid_without_a_business_question():void
    {
        $this->mock(AiConnectionService::class)->shouldReceive('generateText')->andReturn('{"tools":[],"escalation":"explicit_request","clarification":"none","language":"en","conversation":"none","needs_business_answer":false}');
        $this->assertSame('explicit_request',app(SupportUnderstandingService::class)->understand('someone help me',[],[],[])['escalation']);
    }
    public function test_separate_questions_can_use_the_same_enabled_read_only_tool():void
    {
        $this->mock(AiConnectionService::class)->shouldReceive('generateText')->andReturn(json_encode(['tools'=>[['name'=>'faq.search','query'=>'Penang parcel shipping'],['name'=>'faq.search','query'=>'return window']], 'escalation'=>'none','clarification'=>'none','language'=>'en','conversation'=>'none','needs_business_answer'=>true]));
        $plan=app(SupportUnderstandingService::class)->understand('shipping and returns',['faq.search'=>'Policies'],[],[]);
        $this->assertCount(2,$plan['tools']);
    }
    public function test_optional_null_product_target_is_absent_not_a_permission_change():void
    {
        $this->mock(AiConnectionService::class)->shouldReceive('generateText')->andReturn('{"tools":[{"name":"order.lookup","query":"QA-NO-ORDER-999","target":null}],"questions":["Private address"],"escalation":"none","clarification":"none","language":"en","conversation":"none","needs_business_answer":true}');
        $plan=app(SupportUnderstandingService::class)->understand('private address',['order.lookup'=>'Private'],[],[]);
        $this->assertArrayNotHasKey('target',$plan['tools'][0]);
    }
}
