<?php
namespace App\Services\Marketing;
use App\Models\{MarketingProject,MarketingRevision};
use Illuminate\Support\Facades\DB;
/** Explicit evidence-bound factual acknowledgements; never clears raw requirements or grants production spend. */
class MarketingRequirementReview
{
 public function key(MarketingProject $p,MarketingRevision $r,array $item):string{return hash('sha256',json_encode([$item,$r->document['scenes'][$item['scene_number']-1]??null,$p->brief,$p->snapshot],JSON_THROW_ON_ERROR));}
 public function pending(MarketingProject $p,MarketingRevision $r):array{
  $gaps=$r->document['gaps']??[];$items=[];
  foreach($r->document['requirements']??[] as $item){
   $message=$item['scene_number']>0?'Scene '.$item['scene_number'].': '.$item['message']:$item['message'];if(!in_array($message,$gaps,true))continue;
   $key=$this->key($p,$r,$item);$ack=$r->context['fact_reviews'][$key]??null;
   if($item['kind']==='required_fact'&&($ack['actor_id']??null)===$p->owner_id&&!empty($ack['at'])){$gaps=array_values(array_diff($gaps,[$message]));continue;}
   $items[]=['key'=>$key,'kind'=>$item['kind'],'message'=>$message,'evidence'=>$item['evidence']];
  }
  return ['gaps'=>$gaps,'items'=>$items];
 }
 public function acknowledge(string $id,int $actor,int $version,array $keys):void{
  DB::transaction(function()use($id,$actor,$version,$keys){$p=app(MarketingWorkflow::class)->locked($id,$actor,$version);$r=$p->currentRevision;abort_unless($r,422);$pending=$this->pending($p,$r);$allowed=collect($pending['items'])->where('kind','required_fact')->pluck('key')->all();abort_unless(count($keys)>0&&count(array_unique($keys))===count($keys)&&!array_diff($keys,$allowed),422,'请确认当前待核实的真实资料。');
   $context=$r->context;foreach($keys as $key)$context['fact_reviews'][$key]=['actor_id'=>$actor,'at'=>now()->toIso8601String()];
   $new=MarketingRevision::create(['project_id'=>$id,'actor_id'=>$actor,'number'=>(int)$p->revisions()->max('number')+1,'document'=>$r->document,'context'=>$context,'parent_id'=>$r->id,'feedback'=>'已明确确认分镜使用的真实资料；未授权制作或费用']);$p->update(['current_revision_id'=>$new->id,'confirmed_revision_id'=>null,'version'=>$p->version+1]);
  });
 }
}
