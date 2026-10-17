<?php
namespace App\Services\Support;
use App\Models\{SupportConversation,SupportMessage};
use Illuminate\Support\Facades\{Cache,DB};
class SupportConversationBoundary{
 public function settings(array $s):array{return array_replace(['enabled'=>true,'strength'=>'balanced','repeatLimit'=>3,'cooldownMinutes'=>10],$s['guardrails']['conversation']??[]);}
 public function topic(array $plan):?string{
  if(($plan['needs_business_answer']??true)||!empty($plan['tools'])||!empty($plan['questions']))return null;
  return in_array($plan['topic']??null,['off_topic','harassment'],true)?$plan['topic']:null;
 }
 private function key(string $c,array $s):string{return 'support-boundary:'.hash('sha256',$c.json_encode($this->settings($s)).($s['version']??0));}
 private function hash(string $text):string{return hash('sha256',mb_strtolower(trim(preg_replace('/\s+/u',' ',$text))));}
 public function cachedTopic(string $c,string $text,array $s):?string{return $this->settings($s)['enabled']?Cache::get($this->key($c,$s).':text:'.$this->hash($text)):null;}
 public function respond(string $c,string $text,string $topic,string $language,array $s):?string{
  $p=$this->settings($s);$key=$this->key($c,$s);$ttl=now()->addMinutes($p['cooldownMinutes']);
  Cache::put($key.':text:'.$this->hash($text),$topic,$ttl);$count=(int)Cache::get($key,0)+1;Cache::put($key,$count,$ttl);
  $limit=$p['strength']==='strict'&&$topic==='harassment'?1:$p['repeatLimit'];
  if($p['strength']!=='relaxed'&&$count>$limit)return null;
  return match($language){'zh'=>'我可以协助你了解我们的商品、服务、配送或订单。请告诉我你的业务问题。','ms'=>'Saya boleh membantu dengan produk, perkhidmatan, penghantaran atau pesanan kami. Apakah yang anda perlukan?',default=>'I can help with our products, services, delivery or orders. What would you like to know?'};
 }
 /** Only canonical customer messages; preserve takeover, settings-version and newest-message guards. */
 public function publish(SupportConversation $c,SupportMessage $source,string $topic,string $language,array $s,string $replyId,int $control,?string $cadenceBatch=null):void{
  DB::transaction(function()use($c,$source,$topic,$language,$s,$replyId,$control,$cadenceBatch){
   $locked=SupportConversation::whereKey($c->id)->lockForUpdate()->firstOrFail();$current=app(\App\Services\SupportFaqService::class)->read();
   if(!in_array($locked->status,['open','waiting_human'],true)||($current['version']??0)!==($s['version']??0)||(int)$locked->notes()->max('id')!==$control||(int)$locked->messages()->where('sender_role','customer')->max('id')!==$source->id||(int)$locked->messages()->where('sender_role','human_agent')->max('id')>=$source->id||$locked->messages()->where('client_message_id',$replyId)->exists()||($source->fresh()->ai_trace['filtered']??false))return;
   $answer=$this->respond($c->id,$source->content,$topic,$language,$s);$trace=['sourceMessageId'=>$source->id,'decision'=>$topic,'filtered'=>true,'settingsVersion'=>$s['version']??0,'tools'=>[],'retrievedSourceIds'=>[],'citedSourceIds'=>[],'language'=>$language];
   if($answer===null){$source->update(['ai_trace'=>array_merge($source->ai_trace??[],$trace,['suppressed'=>true])]);return;}
   if($cadenceBatch){app(SupportReplyCadenceService::class)->prepare($cadenceBatch,$locked,$source,$answer,[],$trace,$s);return;}
   $locked->messages()->create(['client_message_id'=>$replyId,'sender_role'=>'ai','content'=>$answer,'ai_trace'=>$trace,...(in_array($c->channel,['facebook','instagram','whatsapp'],true)?['ai_settings_version'=>$s['version']??0,'ai_control_version'=>$control]:[])]);
  });
 }
}
