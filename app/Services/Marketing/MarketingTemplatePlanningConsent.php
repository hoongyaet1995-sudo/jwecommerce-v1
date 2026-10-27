<?php
namespace App\Services\Marketing;

use App\Models\{MarketingProject,MarketingTemplateGrant,MarketingTemplateRun,Product};
use Illuminate\Support\Facades\{Crypt,DB};
use Illuminate\Support\Carbon;

/** Quotes are data only. A grant requires explicit, owner-scoped, price-bound consent. */
class MarketingTemplatePlanningConsent
{
 public function pricing():array
 {
  $rates=json_decode(file_get_contents(resource_path('marketing-template-contract/pricing.json')),true,32,JSON_THROW_ON_ERROR);
  $verified=Carbon::parse($rates['verified_at']);
  abort_unless($verified->lte(now()->addMinutes(5))&&$verified->gte(now()->subDays(3))&&$rates['model']===MarketingTemplateProvider::MODEL&&$rates['input_per_million']==.10&&$rates['cached_per_million']==.01&&$rates['output_per_million']==.50&&$rates['tts_per_character_microusd']===80,409,'费率需要重新核对，暂不授权收费。');return $rates;
 }
 private function fingerprint(MarketingProject $p):string
 {
  $product=Product::findOrFail($p->product_id);$asset=app(MarketingTemplateAssets::class)->primary($product);
  return MarketingTemplateProjects::hash(['project'=>$p->id,'owner'=>$p->owner_id,'version'=>$p->version,'product'=>$product->toArray(),'template'=>$p->template_id,'template_version'=>$p->template_version,'preferences'=>$p->preferences,'brief'=>$p->brief,'asset'=>$asset]);
 }
 private function eligible(MarketingProject $p):void
 {
  abort_unless($p->template_id===MarketingTemplateProjects::ID&&$p->template_version===MarketingTemplateProjects::VERSION&&$p->source_type==='product'&&in_array($p->preferences['language']??null,['English','zh-MY'],true)&&(string)($p->preferences['duration']??'')==='15',422,'当前资料不符合此模板。');
  abort_if(MarketingTemplateRun::where('project_id',$p->id)->exists(),409,'已有编排记录，不会通过新授权重置费用或重试次数。');
 }
 public function quote(MarketingProject $p,int $version):array
 {
  abort_unless($p->version===$version,409,'资料已改变，请重新查看费用。');$this->eligible($p);abort_unless(app(MarketingTemplateRuntime::class)->ready(),409,'制作工具尚未就绪，请联系管理员；尚未发生费用。');$rates=$this->pricing();
  $llm=(int)ceil($rates['max_input_tokens']*$rates['input_per_million']+$rates['max_output_tokens']*$rates['output_per_million']);$planning=4*$llm;$voice=$p->preferences['narration']?2*$rates['max_tts_characters']*$rates['tts_per_character_microusd']:0;
  $q=['version'=>1,'project_id'=>$p->id,'owner_id'=>$p->owner_id,'project_version'=>$p->version,'fingerprint'=>$this->fingerprint($p),'pricing'=>$rates,'planning_cap_microusd'=>$planning,'voice_cap_microusd'=>$voice,'cap_microusd'=>$planning+$voice,'max_llm'=>4,'max_tts'=>$voice?2:0,'expires_at'=>now()->addMinutes(20)->toIso8601String()];
  return ['token'=>Crypt::encryptString(json_encode($q,JSON_THROW_ON_ERROR)),'digest'=>MarketingTemplateProjects::hash($q),'planning_cap_microusd'=>$planning,'voice_cap_microusd'=>$voice,'cap_microusd'=>$planning+$voice,'max_llm'=>4,'max_tts'=>$q['max_tts'],'model'=>$rates['model'],'voice'=>['name'=>$p->preferences['language']==='English'?'Bella':'Shan Shan','model'=>$rates['voice_model']],'expires_at'=>$q['expires_at'],'pricing_verified_at'=>$rates['verified_at']];
 }
 public function authorize(MarketingProject $project,int $version,string $token):MarketingTemplateGrant
 {
  try{$q=json_decode(Crypt::decryptString($token),true,32,JSON_THROW_ON_ERROR);}catch(\Throwable){abort(422,'报价凭证无效，请重新查看费用。');}
  abort_unless(is_array($q)&&($q['project_id']??null)===$project->id&&($q['owner_id']??null)===$project->owner_id,422,'报价与当前项目不符。');
  return DB::transaction(function()use($project,$version,$q){
   $p=MarketingProject::whereKey($project->id)->lockForUpdate()->firstOrFail();
   abort_unless($p->version===$version&&$version===($q['project_version']??null)&&now()->lt($q['expires_at'])&&hash_equals($q['fingerprint'],$this->fingerprint($p))&&$q['pricing']===$this->pricing(),409,'资料、素材或报价已变化，请重新查看费用。');
   $digest=MarketingTemplateProjects::hash($q);$existing=MarketingTemplateGrant::where('owner_id',$p->owner_id)->where('product_id',$p->product_id)->get()->first(fn($g)=>($g->policy['quote_digest']??null)===$digest);if($existing)return $existing;
   $this->eligible($p);abort_if(app(MarketingTemplateWorkflow::class)->grant($p),409,'此项目已有可用授权，不会再增加额度。');$lang=$p->preferences['language']==='English'?'en':'zh';
   return MarketingTemplateGrant::create(['owner_id'=>$p->owner_id,'product_id'=>$p->product_id,'cap_microusd'=>$q['cap_microusd'],'max_llm'=>$q['max_llm'],'max_tts'=>$q['max_tts'],'active'=>true,'expires_at'=>now()->addDay(),'pricing'=>$q['pricing'],'policy'=>['authorization_reference'=>'merchant-explicit-planning-consent-v1','quote_digest'=>$digest,'project_id'=>$p->id,'projects'=>[$lang=>$p->id],'languages'=>[$lang],'max_corrections_per_language'=>1,'automatic_corrections'=>false,'no_image_video_api'=>true,'asset_rights_confirmed_at'=>now()->toIso8601String(),'consented_at'=>now()->toIso8601String()]]);
  });
 }
}
