<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Services\Support\{FaqKnowledgeSearch,FaqQueryExpansion};
class FaqMultilingualTest extends TestCase{
 public function test_english_and_malay_queries_find_one_authoritative_chinese_faq_even_in_strict_mode():void{
  $this->mock(FaqQueryExpansion::class)->shouldReceive('match')->twice()->andReturn(['origin']);
  $scope=['isEnabled'=>true,'retrievalDepth'=>'strict','items'=>[['id'=>'origin','question'=>'你们在哪里出货？','answer'=>'我们从巴生港口出货。','category'=>'delivery','isActive'=>true]]];
  foreach(['Where do you ship from?','Dari manakah barang dihantar?'] as $q){$facts=app(FaqKnowledgeSearch::class)->search($q,$scope);$this->assertSame('origin',$facts[0]['id']??null);$this->assertSame('我们从巴生港口出货。',$facts[0]['answer']);}
 }
 public function test_cross_language_selection_cannot_introduce_an_invented_or_inactive_source():void{
  $this->mock(\App\Services\AI\AiConnectionService::class)->shouldReceive('generateText')->once()->andReturn('{"ids":["invented"]}');
  $scope=['isEnabled'=>true,'retrievalDepth'=>'strict','items'=>[['id'=>'real-source','question'=>'出货地点','answer'=>'已确认地点','isActive'=>true],['id'=>'invented','question'=>'停用资料','answer'=>'不可使用','isActive'=>false]]];
  $this->assertSame([],app(FaqKnowledgeSearch::class)->search('Which dispatch location?',$scope));
 }
 public function test_disabled_faq_does_not_invoke_translation():void{
  $this->mock(FaqQueryExpansion::class)->shouldNotReceive('queries');$this->assertSame([],app(FaqKnowledgeSearch::class)->search('Where?',['isEnabled'=>false,'items'=>[]]));
 }
}
