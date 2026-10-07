<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Services\Support\SupportAnswerCoverage;
class SupportAnswerCoverageTest extends TestCase {
 public function test_missing_question_is_not_a_complete_answer():void {
  $r=app(SupportAnswerCoverage::class)->inspect(['coverage'=>[['index'=>0,'status'=>'answered','source_ids'=>['faq:1']]]],['shipping','returns'],['faq:1'],['faq.search'=>'Policies']);
  $this->assertFalse($r['valid']);
 }
 public function test_claim_without_source_and_unknown_source_fail_closed():void {
  foreach([[],['private:invented']] as $ids)$this->assertFalse(app(SupportAnswerCoverage::class)->inspect(['coverage'=>[['index'=>0,'status'=>'answered','source_ids'=>$ids]]],['shipping'],['faq:1'],[])['valid']);
 }
 public function test_one_retry_is_public_enabled_and_missing_topic_only():void {
  $result=['coverage'=>[['index'=>0,'status'=>'missing','source_ids'=>[]]],'follow_up'=>['name'=>'faq.search','query'=>'return exceptions']];
  $r=app(SupportAnswerCoverage::class)->inspect($result,['returns'],[],['faq.search'=>'Policies']);$this->assertSame('faq.search',$r['retry']['name']);
  foreach(['order.lookup','delete','product.search'] as $tool){$result['follow_up']['name']=$tool;$this->assertNull(app(SupportAnswerCoverage::class)->inspect($result,['returns'],[],['order.lookup'=>'private','faq.search'=>'Policies'])['retry']);}
 }
}
