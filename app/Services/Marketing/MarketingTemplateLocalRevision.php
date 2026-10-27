<?php
namespace App\Services\Marketing;
use App\Models\{MarketingProject,MarketingTemplateRun};
use App\Jobs\MarketingTemplateJob;
use Illuminate\Support\Facades\DB;

/** Human-confirmed display copy only; never synthesizes speech or calls an LLM. */
class MarketingTemplateLocalRevision
{
 public function create(MarketingProject $project,array $data):MarketingTemplateRun{
  return DB::transaction(function()use($project,$data){
   $p=MarketingProject::lockForUpdate()->findOrFail($project->id);if($old=MarketingTemplateRun::where('project_id',$p->id)->where('request_id',$data['request_id'])->first())return $old;
   abort_if(app(MarketingTemplateWorkflow::class)->busy($p)||(int)$p->version!==(int)$data['version'],409,'任务处理中或资料已更新。');
   $parent=MarketingTemplateRun::where('project_id',$p->id)->where('status','completed')->findOrFail($data['run_id']);$input=$parent->input;$draft=$parent->draft;$voice=$parent->result['voice']??null;
   abort_if(($input['preferences']['narration']??false)&&!$voice,409,'原口播暂不可复用，已停止免费修改。');
   $draft['copy']=array_replace($draft['copy'],$data['copy']);$valid=app(MarketingTemplateContract::class)->call('validate',['packet'=>$input['packet'],'response'=>['decision'=>'ready','reasons'=>[],'draft'=>$draft]]);
   abort_unless(($valid['status']??'')==='awaiting_semantic_check',422,'修改的文字不符合模板空间或内容结构，请完整改写短句。');
   $p->update(['version'=>$p->version+1]);$input['project_version']=$p->version;$input['parent_run_id']=$parent->id;$input['voice_source_run_id']=$parent->input['voice_source_run_id']??$parent->id;$input['change_scope']='merchant_display_copy';
   $r=MarketingTemplateRun::create(['project_id'=>$p->id,'owner_id'=>$p->owner_id,'grant_id'=>$parent->grant_id,'request_id'=>$data['request_id'],'input'=>$input,'input_digest'=>MarketingTemplateProjects::hash($input),'draft'=>$draft,'checks'=>['status'=>'pass','mode'=>'merchant_confirmed_display_copy','actor_id'=>$p->owner_id,'confirmed_at'=>now()->toIso8601String(),'parent_check'=>$parent->checks],'result'=>['voice'=>$voice],'status'=>'queued_production','phase'=>'正在更新文字，复用已有画面与口播']);
   app(MarketingProductTemplateRenderer::class)->validateDraft($r);MarketingTemplateJob::dispatch($r->id,'produce')->onQueue('marketing-template')->afterCommit();return $r;
  });
 }
}
