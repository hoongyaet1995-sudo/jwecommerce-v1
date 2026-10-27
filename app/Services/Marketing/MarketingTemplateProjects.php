<?php
namespace App\Services\Marketing;

use App\Models\{MarketingProject,MarketingTemplateRun,MarketingTemplateGrant,Product,User};
use Illuminate\Support\Facades\DB;

class MarketingTemplateProjects
{
 public const ID='product_showcase_15s';
 public const VERSION='1.0.0';
 public static function hash(array $data):string{return hash('sha256',json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));}
 public function catalog():array{return ['id'=>self::ID,'version'=>self::VERSION,'name'=>'15秒商品展示','seconds'=>15,'forms'=>['product_showcase'],'formats'=>['landscape'=>[1920,1080],'portrait'=>[1080,1920]],'source_policy'=>'existing-image-only','status'=>'local_acceptance'];}
 public function owned(string $id,int $owner):MarketingProject{return MarketingProject::where('owner_id',$owner)->where('template_id',self::ID)->findOrFail($id);}
 public function create(User $owner,array $data):array{
  return DB::transaction(function()use($owner,$data){
   User::whereKey($owner->id)->lockForUpdate()->firstOrFail();$prep=app(MarketingWorkspacePreparation::class);$request=$data['request_id'];unset($data['request_id']);
   if($old=$prep->replay($owner->id,$request,'template_created',$data))return [$old,false];
   $product=Product::with('images')->findOrFail($data['product_id']);abort_unless(($product->type??'product')!=='service',422,'此模板仅适用于实体商品。');
   $p=app(MarketingWorkflow::class)->create($owner,['source_type'=>'product','product_id'=>$product->id]);
   $pref=['source_kind'=>'product','video_type'=>'product_showcase','language'=>$data['language'],'duration'=>'15','formats'=>$data['formats'],'platforms'=>['YouTube'],'subtitles'=>$data['subtitles'],'narration'=>$data['narration'],'voice'=>$data['narration']?'ai':'none','music'=>'local-original-instrumental','production_tier'=>'ordinary','complete'=>true];
   $brief=array_replace($p->brief,['language'=>$data['language'],'goal'=>$data['goal'],'audience'=>'','facts'=>$data['request']??'','duration'=>15,'video_type'=>'product_showcase','platforms'=>['YouTube']]);
   $p->update(['template_id'=>self::ID,'template_version'=>self::VERSION,'template_snapshot'=>$this->catalog(),'preferences'=>$pref,'brief'=>$brief,'stage'=>'brief','status'=>'needs_confirmation','planning_state'=>['template'=>self::ID]]);
   $prep->record($p,$owner->id,$request,'template_created',$data);return [$p->fresh(),true];
  });
 }
 public function view(MarketingProject $p):array{
  $run=MarketingTemplateRun::where('project_id',$p->id)->latest('created_at')->latest('id')->first();
  $authorized=app(MarketingTemplateWorkflow::class)->grant($p);$g=$authorized??($run?MarketingTemplateGrant::where('owner_id',$p->owner_id)->find($run->grant_id):null);$summary=$g?app(MarketingTemplateBilling::class)->summary($g->id):null;
  $grantAvailable=(bool)$authorized&&$summary['unknown']===0&&$summary['llm']+2<=$g->max_llm&&$summary['reserved_microusd']+2*MarketingTemplateProvider::LLM_RESERVE_MICROUSD<=$g->cap_microusd;
  $image=Product::find($p->product_id)?->images()->orderBy('position')->first()?->path;
  $result=fn($r)=>$r->result?['formats'=>collect($r->result['formats']??[])->map(fn($f)=>array_intersect_key($f,array_flip(['width','height','seconds','frames','audio'])))->all()]:null;
  $success=MarketingTemplateRun::where('project_id',$p->id)->where('status','completed')->latest('created_at')->latest('id')->first();
  $last=$success?['id'=>$success->id,'version'=>$success->input['project_version']??null,'status'=>'completed','draft'=>$success->draft,'result'=>$result($success),'preferences'=>array_replace($success->input['preferences']??[],['language'=>($success->input['packet']['language']??'en')==='zh'?'zh-MY':'English'])]:null;
  return ['project'=>['id'=>$p->id,'name'=>$p->name,'image'=>$image,'version'=>$p->version,'template_id'=>$p->template_id,'preferences'=>$p->preferences,'brief'=>$p->brief,'product_id'=>$p->product_id],
   'run'=>$run?['id'=>$run->id,'status'=>$run->status,'phase'=>$run->phase,'draft'=>$run->draft,'checks'=>$run->checks,'quote'=>$run->quote,'result'=>$result($run),'error'=>$run->error,'corrections'=>$run->corrections]:null,'template'=>$this->catalog(),
   'grant'=>$g?['available'=>$grantAvailable,'consent_required'=>false,'cap_microusd'=>$g->cap_microusd,'remaining_microusd'=>$g->cap_microusd-$summary['reserved_microusd'],'actual_microusd'=>$summary['actual_microusd'],'reserved_microusd'=>$summary['reserved_microusd'],'unknown'=>$summary['unknown'],'planning_cap_microusd'=>4*MarketingTemplateProvider::LLM_RESERVE_MICROUSD,'max_llm'=>$g->max_llm,'max_tts'=>$g->max_tts,'model'=>MarketingTemplateProvider::MODEL,'voice'=>['name'=>$p->preferences['language']==='English'?'Bella':'Shan Shan'],'message'=>$grantAvailable?'费用受当前已确认的项目预算约束。':($summary['unknown']?'有费用结果待核对，暂不重复收费。':'已达到本次授权额度或次数，暂不追加费用。')]:['available'=>false,'consent_required'=>$run===null,'message'=>$run?'原授权已失效，已有方案与记录保留。':'先查看编排费用，确认后 AI 才会开始。'],
   'last_successful'=>$last,'versions'=>MarketingTemplateRun::where('project_id',$p->id)->where('status','completed')->get()->map(fn($r)=>['id'=>$r->id,'version'=>$r->input['project_version']??null,'result'=>$result($r)])->all()];
 }
}
