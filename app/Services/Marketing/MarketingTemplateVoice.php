<?php
namespace App\Services\Marketing;
use App\Models\{MarketingTemplateRun,MarketingTemplateCall};
class MarketingTemplateVoice
{
 public function known(MarketingTemplateRun $run):bool{
  if(empty($run->result['voice']))return false;
  $id=$run->input['voice_source_run_id']??$run->id;
  $source=$id===$run->id?$run:MarketingTemplateRun::where('project_id',$run->project_id)->where('owner_id',$run->owner_id)->where('status','completed')->find($id);
  if(!$source||($source->result['voice']??null)!==$run->result['voice'])return false;
  return MarketingTemplateCall::where('run_id',$source->id)->where('kind','tts')->where('status','completed')->exists();
 }
}
