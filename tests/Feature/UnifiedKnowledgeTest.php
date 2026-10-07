<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Services\Support\UnifiedKnowledgeService;
use App\Services\Support\FaqKnowledgeSearch;
use App\Services\Support\ProductKnowledgeService;
use App\Services\Support\DiscountKnowledgeService;
use App\Services\AI\AiConnectionService;
class UnifiedKnowledgeTest extends TestCase {
 public function test_saved_catalogue_phrases_are_optional_hints_for_model():void{
 $s=$this->settings();$s['productCatalog']['browseAliases']=['what exactly you are selling with'];
 $this->mock(AiConnectionService::class)->shouldReceive('generateText')->once()->andReturn('{"tools":[{"name":"product.search","query":""},{"name":"taxonomy.search","query":""}],"escalation":"none","clarification":"none","language":"en","conversation":"none","needs_business_answer":true}');
 $this->mock(ProductKnowledgeService::class)->shouldReceive('facts')->once()->with('__catalogue_list__',$s['productCatalog'])->andReturn([['id'=>'product:1','question'=>'BILLY','answer'=>'Published shelf']]);
 $this->mock(\App\Services\Support\TaxonomyKnowledgeService::class)->shouldReceive('facts')->once()->with('',$s['productCatalog'])->andReturn([]);
 $this->assertSame(['product:1'],array_column(app(UnifiedKnowledgeService::class)->retrieve('What exactly you are selling with?',$s),'id'));
 }
 public function test_disabled_policy_supplement_keeps_product_only_plan():void{
 $s=$this->settings();$s['productCatalog']['includeStorePolicies']=false;
 $this->mock(AiConnectionService::class)->shouldReceive('generateText')->once()->andReturn('{"tools":[{"name":"product.search","query":"BILLY"}],"escalation":"none","clarification":"none","language":"en","conversation":"none","needs_business_answer":true}');
 $this->mock(ProductKnowledgeService::class)->shouldReceive('facts')->once()->andReturn([['id'=>'product:1','question'=>'BILLY','answer'=>'Published product']]);
 $this->mock(FaqKnowledgeSearch::class)->shouldNotReceive('search');
 $this->assertSame(['product:1'],array_column(app(UnifiedKnowledgeService::class)->retrieve('BILLY delivery?',$s),'id'));
 }
 public function test_product_only_plan_still_checks_applicable_store_policy():void{
 $s=$this->settings();$s['faq']['items']=[['id'=>'assembly','category'=>'service','question'=>'Is assembly included?','answer'=>'Assembly is not included. Staff must confirm any separate service.','keywords'=>['assembly'],'isActive'=>true]];
 $this->mock(AiConnectionService::class)->shouldReceive('generateText')->once()->andReturn('{"tools":[{"name":"product.search","query":"BILLY"}],"escalation":"none","clarification":"none","language":"en","conversation":"none","needs_business_answer":true}');
 $this->mock(ProductKnowledgeService::class)->shouldReceive('facts')->once()->andReturn([['id'=>'product:1','question'=>'BILLY','answer'=>'Wall anchoring required']]);
 $facts=app(UnifiedKnowledgeService::class)->retrieve('Is assembly included for BILLY?',$s);
 $this->assertEqualsCanonicalizing(['product:1','assembly'],array_column($facts,'id'));
 }
 private function settings():array{return ['faq'=>['isEnabled'=>true,'items'=>[
 ['id'=>'ship','category'=>'shipping','question'=>'Delivery time','answer'=>'Local delivery 3 days','keywords'=>['shipping'],'isActive'=>true],
 ['id'=>'overseas','category'=>'shipping','question'=>'Overseas shipping','answer'=>'No overseas delivery','keywords'=>[],'isActive'=>true],
 ['id'=>'pay','category'=>'payment','question'=>'Payment options','answer'=>'Card','keywords'=>[],'isActive'=>true],
 ['id'=>'hidden','category'=>'shipping','question'=>'Hidden shipping','answer'=>'Hidden','isActive'=>false]
 ]],'productCatalog'=>['isEnabled'=>true,'canRecommendUpsells'=>true],'discountKnowledge'=>['isEnabled'=>false,'activeIds'=>[]]];}
 public function test_policy_query_passes_only_index_to_planner_and_keeps_restrictions():void{
 $this->mock(AiConnectionService::class)->shouldReceive('generateText')->once()->withArgs(fn($purpose,$prompt,$tokens)=>$purpose==='support'&&$tokens===350&&str_contains($prompt,'faq.search')&&!str_contains($prompt,'No overseas delivery')&&!str_contains($prompt,'order.get'))->andReturn('{"tools":[{"name":"faq.search","query":"shipping delivery"}],"escalation":"none","clarification":"none","language":"en","conversation":"none","needs_business_answer":true}');
 $this->mock(ProductKnowledgeService::class)->shouldNotReceive('facts');$this->mock(DiscountKnowledgeService::class)->shouldNotReceive('facts');
 $facts=app(UnifiedKnowledgeService::class)->retrieve('I am from China, when is delivery?',$this->settings());$this->assertEqualsCanonicalizing(['ship','overseas'],array_column($facts,'id'));
 }
 public function test_disabled_tools_cannot_be_selected():void{
 $this->mock(AiConnectionService::class)->shouldReceive('generateText')->once()->andReturn('{"tools":[{"name":"order.get","query":"123"}],"escalation":"none","clarification":"none","language":"en","conversation":"none","needs_business_answer":true}');
 $this->expectException(\Illuminate\Validation\ValidationException::class);app(UnifiedKnowledgeService::class)->retrieve('order',$this->settings());
 }
 public function test_mixed_query_uses_one_plan_and_two_bounded_sources():void{
 $s=$this->settings();$s['discountKnowledge']['isEnabled']=true;
 $this->mock(AiConnectionService::class)->shouldReceive('generateText')->once()->andReturn('{"tools":[{"name":"product.search","query":"speaker"},{"name":"discount.search","query":"offers"}],"escalation":"none","clarification":"none","language":"en","conversation":"none","needs_business_answer":true}');
 $this->mock(ProductKnowledgeService::class)->shouldReceive('facts')->once()->with('speaker',$s['productCatalog'])->andReturn([['id'=>'product:1','question'=>'Speaker','answer'=>'RM100']]);
 $this->mock(DiscountKnowledgeService::class)->shouldReceive('facts')->with($s['discountKnowledge'])->once()->andReturn([['id'=>'discount:1','question'=>'Offer','answer'=>'10% conditional']]);
 $facts=app(UnifiedKnowledgeService::class)->retrieve('Speaker offers?',$s);$this->assertSame(['product:1','discount:1'],array_column($facts,'id'));
 }
 public function test_no_sources_means_no_planner_and_large_policy_fails_closed():void{
 $this->mock(AiConnectionService::class)->shouldNotReceive('generateText');$this->assertSame([],app(UnifiedKnowledgeService::class)->retrieve('hello',[]));$s=$this->settings();$s['faq']['items'][0]['answer']=str_repeat('x',13000);$this->expectException(\RuntimeException::class);app(FaqKnowledgeSearch::class)->search('shipping',$s['faq']);
 }
}
