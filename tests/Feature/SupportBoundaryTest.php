<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Services\Support\SupportConversationBoundary;
use Illuminate\Support\Facades\Cache;
class SupportBoundaryTest extends TestCase{
 public function test_balanced_repeat_limit_is_conversation_scoped_and_expires():void{
  config(['cache.default'=>'array']);Cache::flush();$p=new SupportConversationBoundary;$s=['guardrails'=>['conversation'=>['enabled'=>true,'strength'=>'balanced','repeatLimit'=>2,'cooldownMinutes'=>1]]];
  $this->assertNotNull($p->respond('one','moon','off_topic','en',$s));$this->assertNotNull($p->respond('one','moon','off_topic','en',$s));$this->assertNull($p->respond('one','moon','off_topic','en',$s));
  $this->assertSame('off_topic',$p->cachedTopic('one','moon',$s));$this->assertNull($p->cachedTopic('other','moon',$s));$this->assertNull($p->cachedTopic('one','delivery please',$s));$this->travel(61)->seconds();$this->assertNull($p->cachedTopic('one','moon',$s));
 }
 public function test_business_tools_or_questions_override_a_mislabelled_abuse_tag():void{
  $p=new SupportConversationBoundary;
  $this->assertNull($p->topic(['topic'=>'harassment','needs_business_answer'=>true,'tools'=>[['name'=>'faq.search']],'questions'=>['Where is my delivery?']]));
  $this->assertSame('harassment',$p->topic(['topic'=>'harassment','needs_business_answer'=>false,'tools'=>[],'questions'=>[]]));
 }
 public function test_strict_and_disabled_settings_are_real_runtime_controls():void{
  config(['cache.default'=>'array']);Cache::flush();$p=new SupportConversationBoundary;$s=['guardrails'=>['conversation'=>['enabled'=>true,'strength'=>'strict','repeatLimit'=>3,'cooldownMinutes'=>1]]];
  $this->assertNotNull($p->respond('one','abuse','harassment','zh',$s));$this->assertNull($p->respond('one','abuse','harassment','zh',$s));$s['guardrails']['conversation']['enabled']=false;$this->assertNull($p->cachedTopic('one','abuse',$s));
 }
}
