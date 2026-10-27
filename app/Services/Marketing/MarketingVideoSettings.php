<?php
namespace App\Services\Marketing;
use App\Models\{MarketingProject,MarketingStoryboardWorkspace as Workspace};
use Illuminate\Support\Facades\Validator;

/** Free preference edits; immutable scripts/media and historical production inputs remain intact. */
class MarketingVideoSettings
{
 public function defaults(MarketingProject $p):array{return ['aspect'=>'9:16','voice'=>($p->preferences['narration']??true)?'auto':'none','subtitles'=>($p->preferences['subtitles']??true)?'on':'off','language'=>$p->preferences['language']??$p->brief['language']??'English','music'=>$p->preferences['music']??'later'];}
 public function apply(array $scene,MarketingProject $p,array $profile,string $sceneId):array{
  return $this->projectScene($scene,array_replace($this->defaults($p),$profile['scene_video_settings'][$sceneId]??[]));
 }
 private function projectScene(array $scene,array $v):array{
  if(($v['voice']??'auto')==='none'){$scene['voiceover']='';if(isset($scene['director']['voiceover']))$scene['director']['voiceover']='';}
  if(($v['subtitles']??'on')==='off'){$scene['subtitle']='';if(isset($scene['director']['subtitle']))$scene['director']['subtitle']='';}
  return $scene;
 }
 public function productionScenes(array $snapshot):array{
  return array_map(fn($scene)=>$this->projectScene($scene,array_replace($snapshot['video_settings']['defaults']??[],$snapshot['video_settings']['scenes'][$scene['scene_id']??'']['overrides']??$snapshot['profile']['scene_video_settings'][$scene['scene_id']??'']??[])),$snapshot['scenes']);
 }
 public function tags(array $scene):array{
  $spoken=trim($scene['voiceover']??'')!=='';$person=(bool)($scene['presenter_required']??false);
  return ['aspect'=>['label'=>'竖屏 9:16','icon'=>'phone'],'voice'=>['label'=>$spoken?($person?'人物口播':'旁白'):'无口播','icon'=>$spoken?'mic':'mic-mute'],'subtitles'=>['label'=>trim($scene['subtitle']??'')!==''?'有字幕':'无字幕','icon'=>'badge-cc'],'person'=>['label'=>$person?'人物出镜':'无人物','icon'=>$person?'person':'person-dash']];
 }
 public function view(MarketingProject $p,array $profile,array $scenes):array{
  $rows=[];foreach($scenes as $s)$rows[$s['scene_id']]=['tags'=>$this->tags($this->apply($s,$p,$profile,$s['scene_id'])),'overrides'=>$profile['scene_video_settings'][$s['scene_id']]??[]];return ['defaults'=>$this->defaults($p),'scenes'=>$rows];
 }
 public function save(string $project,int $owner,string $request,int $version,array $command):array{
  $d=Validator::make($command,['field'=>'required|in:voice,subtitles','value'=>'required|in:auto,none,on,off,inherit','scope'=>'required|in:scene,global','scene_id'=>'nullable|uuid'])->validate();
  abort_unless(in_array($d['value'],$d['field']==='voice'?['auto','none','inherit']:['on','off','inherit'],true),422,'设定选项无效。');abort_if($d['scope']==='global'&&$d['value']==='inherit',422,'全片默认不能继承自身。');
  $svc=app(MarketingStoryboardWorkspace::class);
  return $svc->transact($project,$owner,$request,['video-settings',$version,$d],function($p)use($svc,$project,$version,$d){
   $w=Workspace::where('project_id',$project)->lockForUpdate()->firstOrFail();abort_unless($w->version===$version,409,'故事已变化，请刷新设定。');$fresh=$svc->sourceFresh($w,$p);$profile=$w->profile;
   if($d['scope']==='scene'){
    abort_unless(in_array($d['scene_id']??null,$w->scene_order,true),422,'本段不属于当前故事。');
    if($d['value']==='inherit')unset($profile['scene_video_settings'][$d['scene_id']][$d['field']]);else $profile['scene_video_settings'][$d['scene_id']][$d['field']]=$d['value'];
   }else{
    $prefs=$p->preferences??[];if($d['field']==='voice'){$prefs['narration']=$d['value']!=='none';$prefs['voice']=$prefs['narration']?'ai':'none';}else $prefs['subtitles']=$d['value']==='on';$p->update(['preferences'=>$prefs,'version'=>$p->version+1]);
   }
   if($fresh)$profile['source_fingerprint']=$svc->sourceFingerprint($p->fresh());
   $w->update(['profile'=>$profile,'version'=>$w->version+1,'last_confirmation'=>null]);
  });
 }
}
