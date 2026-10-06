<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Services\Support\FaqKnowledgeSearch;
use App\Services\AI\AiConnectionService;
class FaqRetrievalModesTest extends TestCase {
 private function scope($mode):array{return ['isEnabled'=>true,'retrievalDepth'=>$mode,'items'=>[
 ['id'=>'ship','category'=>'shipping','question'=>'Delivery time','answer'=>'Local delivery takes three days.','keywords'=>['shipping'],'isActive'=>true],
 ['id'=>'exclude','category'=>'shipping','question'=>'Delivery restrictions','answer'=>'No overseas delivery.','keywords'=>[],'isActive'=>true],
 ['id'=>'hidden','category'=>'shipping','question'=>'Secret policy','answer'=>'hidden','keywords'=>['shipping'],'isActive'=>false]]];}
 public function test_strict_is_deterministic_and_keeps_policy_exceptions():void{$this->mock(AiConnectionService::class)->shouldNotReceive('generateText');$this->assertEqualsCanonicalizing(['ship','exclude'],array_column(app(FaqKnowledgeSearch::class)->search('shipping',$this->scope('strict')),'id'));}
 public function test_semantic_expands_multilingual_question():void{$this->mock(AiConnectionService::class)->shouldReceive('generateText')->once()->andReturn('{"queries":["shipping delivery"]}');$this->assertEqualsCanonicalizing(['ship','exclude'],array_column(app(FaqKnowledgeSearch::class)->search('几时到？',$this->scope('semantic')),'id'));}
 public function test_bad_expansion_falls_back_to_lexical():void{$this->mock(AiConnectionService::class)->shouldReceive('generateText')->once()->andReturn('{"queries":"bad"}');$this->assertEqualsCanonicalizing(['ship','exclude'],array_column(app(FaqKnowledgeSearch::class)->search('shipping',$this->scope('semantic')),'id'));}
 public function test_deep_ranking_cannot_introduce_unknown_policy():void{$this->mock(AiConnectionService::class)->shouldReceive('generateText')->twice()->andReturn('{"queries":["shipping"]}','{"ids":["invented"]}');$this->assertEqualsCanonicalizing(['ship','exclude'],array_column(app(FaqKnowledgeSearch::class)->search('shipping',$this->scope('deep')),'id'));}
 public function test_empty_or_disabled_knowledge_does_not_call_provider():void{$this->mock(AiConnectionService::class)->shouldNotReceive('generateText');$s=$this->scope('deep');$s['isEnabled']=false;$this->assertSame([],app(FaqKnowledgeSearch::class)->search('shipping',$s));}
 public function test_long_question_does_not_discard_expansion_terms():void{$this->mock(AiConnectionService::class)->shouldReceive('generateText')->once()->andReturn('{"queries":["shipping"]}');$this->assertEqualsCanonicalizing(['ship','exclude'],array_column(app(FaqKnowledgeSearch::class)->search('Wondering whether somebody could kindly explain approximately when something might reach somewhere',$this->scope('semantic')),'id'));}
}
