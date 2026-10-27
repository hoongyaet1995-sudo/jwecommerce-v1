<?php
namespace App\Services\Marketing;
use App\Models\{MarketingProject,MarketingTemplateRun,MarketingTemplateGrant,Product};
use Illuminate\Support\Facades\{Crypt,DB};
use Illuminate\Support\Str;

/** Explicit incremental consent; original project cap and historical charges never reset. */
class MarketingTemplateRevisionConsent
{
 private function grant(MarketingProject $p,MarketingTemplateRun $r):MarketingTemplateGrant{
  $g=app(MarketingTemplateWorkflow::class)->grant($p,true);abort_unless($g&&$g->id===$r->grant_id,409,'原授权不可用，已有结果保留。');return $g;
 }
 private function fingerprint(MarketingProject $p,MarketingTemplateRun $r,MarketingTemplateGrant $g):string{
  $product=Product::with('images')->findOrFail($p->product_id);$asset=app(MarketingTemplateAssets::class)->primary($product);
  return MarketingTemplateProjects::hash([$p->version,$p->brief,$p->preferences,$product->toArray(),$asset,$r->draft,$g->cap_microusd,app(MarketingTemplateBilling::class)->summary($g->id)]);
 }
 public function quote(MarketingProject $p,array $data):array{
  abort_if(app(MarketingTemplateWorkflow::class)->busy($p)||(int)$p->version!==(int)$data['version'],409,'任务或资料已改变。');
  $r=MarketingTemplateRun::where('project_id',$p->id)->findOrFail($data['run_id']);abort_unless(in_array($r->status,['paused','planned','completed'],true),409);
  $g=$this->grant($p,$r);abort_unless(app(MarketingTemplateRuntime::class)->ready(),409,'制作工具尚未就绪，尚未发生费用。');$s=app(MarketingTemplateBilling::class)->summary($g->id);$amount=2*MarketingTemplateProvider::LLM_RESERVE_MICROUSD;
  abort_if($s['unknown']||$s['reserved_microusd']+$amount>$g->cap_microusd,409,'费用待核对或原预算不足；不会自动增加预算。');
  $q=['purpose'=>'template_revision','project'=>$p->id,'owner'=>$p->owner_id,'version'=>$p->version,'parent'=>$r->id,'grant'=>$g->id,'request_id'=>(string)Str::uuid(),'request'=>$data['request'],'fingerprint'=>$this->fingerprint($p,$r,$g),'rates'=>app(MarketingTemplatePlanningConsent::class)->pricing(),'expires'=>now()->addMinutes(20)->toIso8601String()];
  return ['token'=>Crypt::encryptString(json_encode($q,JSON_THROW_ON_ERROR)),'planning_cap_microusd'=>$amount,'actual_microusd'=>$s['actual_microusd'],'cap_microusd'=>$g->cap_microusd,'model'=>MarketingTemplateProvider::MODEL,'description'=>'仅重新编排并核对方案；制作与配音另行确认。原预算不增加，原图与旧版本保留。'];
 }
 public function authorize(MarketingProject $project,array $data):void{
  try{$q=json_decode(Crypt::decryptString($data['token']),true,32,JSON_THROW_ON_ERROR);}catch(\Throwable){abort(422,'修改报价无效。');}
  abort_unless(($q['purpose']??null)==='template_revision'&&($q['project']??null)===$project->id&&($q['owner']??null)===$project->owner_id,422);
  DB::transaction(function()use($project,$data,$q){
   $p=MarketingProject::lockForUpdate()->findOrFail($project->id);
   if(MarketingTemplateRun::where('project_id',$p->id)->where('request_id',$q['request_id'])->exists())return;
   abort_unless((int)$p->version===(int)$data['version']&&$p->version===$q['version']&&now()->lt($q['expires']),409,'修改报价已过期或资料已改变。');
   $r=MarketingTemplateRun::where('project_id',$p->id)->findOrFail($q['parent']);$g=$this->grant($p,$r);$g=MarketingTemplateGrant::lockForUpdate()->findOrFail($g->id);
   abort_unless($g->id===$q['grant']&&hash_equals($q['fingerprint'],$this->fingerprint($p,$r,$g))&&$q['rates']===app(MarketingTemplatePlanningConsent::class)->pricing(),409,'资料、费用或模型已改变。');
   $s=app(MarketingTemplateBilling::class)->summary($g->id);abort_if($s['unknown']||$s['reserved_microusd']+2*MarketingTemplateProvider::LLM_RESERVE_MICROUSD>$g->cap_microusd,409);
   $policy=$g->policy;$lang=$p->preferences['language']==='English'?'en':'zh';$policy['max_corrections_per_language']=($policy['corrections'][$lang]??0)+1;$policy['automatic_corrections']=false;$policy['asset_rights_confirmed_at']=now()->toIso8601String();$policy['revision_consents'][]=['digest'=>MarketingTemplateProjects::hash($q),'at'=>now()->toIso8601String(),'parent'=>$r->id,'request_id'=>$q['request_id']];
   $g->update(['policy'=>$policy,'max_llm'=>max($g->max_llm,$s['llm']+2),'expires_at'=>now()->addDay()]);
   app(MarketingTemplateWorkflow::class)->start($p,['version'=>$p->version,'request_id'=>$q['request_id'],'request'=>$q['request']],$r);
  });
 }
}
