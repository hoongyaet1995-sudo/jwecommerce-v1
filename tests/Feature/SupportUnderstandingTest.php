<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Services\AI\AiConnectionService;
use App\Services\Support\SupportUnderstandingService;
class SupportUnderstandingTest extends TestCase
{
    public function test_generic_constraints_are_carried_from_multilingual_context_without_industry_fields():void
    {
        $this->mock(AiConnectionService::class)->shouldReceive('generateText')->once()->withArgs(fn($feature,$prompt,$limit)=>$limit===1000 && str_contains($prompt,'requirements') && str_contains($prompt,'customer_context'))->andReturn(json_encode([
            'tools'=>[['name'=>'product.search','query'=>'cotton shirt XL','requirements'=>['required'=>['cotton','XL'],'excluded'=>['polyester']]]],
            'escalation'=>'none','clarification'=>'product','language'=>'zh','conversation'=>'none','needs_business_answer'=>true,
        ]));
        $result=app(SupportUnderstandingService::class)->understand('要大一码的纯棉款',['product.search'=>'Published products'],['Customer: size L is too small'],[]);
        $this->assertSame(['cotton','XL'],$result['tools'][0]['requirements']['required']);
        $this->assertSame('none',$result['escalation']);
    }
    public function test_preference_and_memory_recall_have_valid_non_business_intents(): void
    {
        $this->mock(AiConnectionService::class)->shouldReceive('generateText')->twice()->andReturn(
            '{"tools":[],"escalation":"none","clarification":"none","language":"zh","conversation":"preference","needs_business_answer":false}',
            '{"tools":[],"escalation":"none","clarification":"none","language":"zh","conversation":"memory_recall","needs_business_answer":false}'
        );
        $service=app(SupportUnderstandingService::class);
        $this->assertSame('preference',$service->understand('我喜欢浅色橡木家具',[],[],[])['conversation']);
        $this->assertSame('memory_recall',$service->understand('我之前说喜欢什么家具？',[],[],[])['conversation']);
    }
    public function test_memory_recall_cannot_execute_private_order_tool():void
    {
        $this->mock(AiConnectionService::class)->shouldReceive('generateText')->andReturn('{"tools":[{"name":"order.lookup","query":"QA-00000"}],"escalation":"none","clarification":"none","language":"en","conversation":"memory_recall","needs_business_answer":false}');
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(SupportUnderstandingService::class)->understand('Recall order address',['order.lookup'=>'Private'],[],[]);
    }
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
    public function test_old_failure_cannot_escalate_without_current_message_evidence():void
    {
        $this->mock(AiConnectionService::class)->shouldReceive('generateText')->andReturn('{"tools":[],"escalation":"unresolved","clarification":"none","language":"en","conversation":"none","needs_business_answer":true,"unresolved_evidence":"why are you hanging?"}');
        $plan=app(SupportUnderstandingService::class)->understand('How much is shipping?',[],['Customer: why are you hanging?'],[]);
        $this->assertSame('none',$plan['escalation']);
    }
    public function test_current_unresolved_statement_keeps_escalation():void
    {
        $this->mock(AiConnectionService::class)->shouldReceive('generateText')->andReturn('{"tools":[],"escalation":"unresolved","clarification":"none","language":"en","conversation":"none","needs_business_answer":true,"unresolved_evidence":"That did not help"}');
        $this->assertSame('unresolved',app(SupportUnderstandingService::class)->understand('That did not help, try again',[],[],[])['escalation']);
    }
}
