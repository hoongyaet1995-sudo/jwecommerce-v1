<?php
namespace App\Services\Marketing;

use App\Models\{MarketingProject,MarketingTemplateGrant,MarketingTemplateRun,MarketingTemplateCall};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Durable reservations precede transport. Unknown outcomes never release a reservation. */
class MarketingTemplateBilling
{
 public function summary(string $grant):array {
  $rows=MarketingTemplateCall::where('grant_id',$grant)->get();
  return ['reserved_microusd'=>(int)$rows->sum(fn($c)=>$c->status==='completed'?$c->actual_microusd:$c->reserved_microusd),'actual_microusd'=>(int)$rows->where('status','completed')->sum('actual_microusd'),'llm'=>$rows->where('kind','llm')->count(),'tts'=>$rows->where('kind','tts')->count(),'unknown'=>$rows->whereIn('status',['unknown','requesting'])->count()];
 }
 public function call(MarketingTemplateRun $run,string $stage,string $kind,array $payload,int $reserve,callable $transport):array {
  $hash=MarketingTemplateProjects::hash($payload);
  $call=DB::transaction(function()use($run,$stage,$kind,$reserve,$hash){
   $g=MarketingTemplateGrant::whereKey($run->grant_id)->lockForUpdate()->firstOrFail();
   abort_unless($g->active&&$g->expires_at->isFuture()&&(int)$g->owner_id===(int)$run->owner_id,409,'授权不可用。');
   $p=MarketingProject::findOrFail($run->project_id);abort_unless((int)$p->owner_id===(int)$run->owner_id&&(int)$p->product_id===(int)$g->product_id&&(int)$p->version===(int)($run->input['project_version']??0),409,'资料已更新。');
   $old=MarketingTemplateCall::where('run_id',$run->id)->where('stage',$stage)->first();
   if($old){abort_unless($old->payload_hash===$hash&&$old->kind===$kind&&$old->reserved_microusd===$reserve&&$old->status==='completed',409,'原请求尚不能安全重复。');return $old;}
   $s=$this->summary($g->id);abort_unless(in_array($kind,['llm','tts'],true)&&$reserve>0&&!$s['unknown']&&$s['reserved_microusd']+$reserve<=$g->cap_microusd&&$s[$kind]<($kind==='llm'?$g->max_llm:$g->max_tts),409,'费用未知或达到授权边界，已暂停。');
   return MarketingTemplateCall::create(['id'=>hash('sha256',$run->id.':'.$stage),'run_id'=>$run->id,'grant_id'=>$g->id,'stage'=>$stage,'kind'=>$kind,'payload_hash'=>$hash,'client_request_id'=>(string)Str::uuid(),'status'=>'requesting','reserved_microusd'=>$reserve]);
  });
  if($call->status==='completed')return $call->receipt;
  try{$receipt=$transport($call->client_request_id);}catch(\Throwable $e){$call->refresh();$call->update(['status'=>'unknown','receipt'=>array_replace($call->receipt??[],['error_class'=>get_class($e)])]);throw new \RuntimeException('provider_outcome_unknown');}
  $actual=$receipt['actual_microusd']??null;
  if(!is_int($actual)||$actual<0||$actual>$reserve){$call->update(['status'=>'unknown','receipt'=>$receipt]);throw new \RuntimeException('provider_cost_unknown');}
  $call->update(['status'=>'completed','actual_microusd'=>$actual,'receipt'=>$receipt]);return $receipt;
 }
}
