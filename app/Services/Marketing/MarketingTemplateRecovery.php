<?php
namespace App\Services\Marketing;
use App\Models\{MarketingProject,MarketingTemplateRun,MarketingTemplateCall};
use App\Jobs\MarketingTemplateJob;
use Illuminate\Support\Facades\DB;

/** Recovers local work only. Never requests or retries a paid provider. */
class MarketingTemplateRecovery
{
 public function enqueue(MarketingTemplateRun $run):bool{
  return DB::transaction(function()use($run){
   $r=MarketingTemplateRun::lockForUpdate()->findOrFail($run->id);$result=$r->result??[];$attempt=(int)($result['local_attempt']??0);
   if($r->status!=='paused'||!in_array($r->error['code']??'', ['template_local_render_failed','template_local_attempt_incomplete'],true)||$attempt>=2||empty($result['voice']))return false;
   $p=MarketingProject::findOrFail($r->project_id);if((int)$p->version!==(int)($r->input['project_version']??0)||app(MarketingTemplateBilling::class)->summary($r->grant_id)['unknown'])return false;
   if(!app(MarketingTemplateVoice::class)->known($r))return false;
   $result['local_attempt']=$attempt+1;$result['recovery_errors'][]=$r->error;
   $r->update(['status'=>'queued_recovery','phase'=>'正在恢复已有制作结果','result'=>$result]);MarketingTemplateJob::dispatch($r->id,'resume')->onQueue('marketing-template')->afterCommit();return true;
  });
 }
 public function sweep():int{
  $count=0;MarketingTemplateRun::where('status','paused')->whereIn('error->code',['template_local_render_failed','template_local_attempt_incomplete'])->orderBy('created_at')->limit(20)->get()->each(function($r)use(&$count){if($this->enqueue($r))$count++;});return $count;
 }
}
