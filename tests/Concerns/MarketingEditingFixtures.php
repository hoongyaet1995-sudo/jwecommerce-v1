<?php
namespace Tests\Concerns;
use Illuminate\Support\Facades\{Storage,Http,Queue};
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;
use App\Models\{MarketingRevision,MarketingVideoClip,MarketingVideoFilm};
use App\Services\Marketing\{MarketingMaterialPackage,MarketingMaterialReferences,MarketingMaterialImages,MarketingMaterialImageClient,MarketingWorkflow};
use Tests\Feature\{MarketingMaterialPackageTest,MarketingDirectorContractTest};
trait MarketingEditingFixtures {
 use MarketingResearchFixtures;
 protected function editingProject():array{
  Storage::fake('local');Storage::fake('public');$p=$this->researchProject();config(['marketing.workspace_video.enabled'=>true,'marketing.native.api_key'=>'fixture-key']);
  \App\Models\AiConnection::where('provider','openai')->update(['image_model'=>'gpt-image-2','models'=>[['id'=>'gpt-image-2','image'=>true]]]);$p->update(['concepts'=>[['title'=>'用途介绍','video_type'=>'presenter','ratio'=>'9:16']],'selected_concept'=>0,'planning_state'=>['status'=>'confirmed'],'preferences'=>['complete'=>true,'subtitles'=>true,'narration'=>true,'language'=>'English']]);
  $doc=['summary'=>'用途','duration'=>15,'cta'=>'Enquire','scenes'=>MarketingMaterialPackageTest::facts()['scenes'],'director_plan'=>MarketingDirectorContractTest::directorOutput(),'gaps'=>[]];
  $r=MarketingRevision::create(['project_id'=>$p->id,'actor_id'=>$p->owner_id,'number'=>1,'document'=>$doc,'context'=>['brief'=>$p->brief,'snapshot'=>$p->snapshot,'concept'=>$p->concepts[0]]]);$doc['director_plan']['source_revision_id']=$r->id;$doc['director_plan']['source_digest']=app(\App\Services\Marketing\MarketingDirectorWorkflow::class)->digest($p,$r);$r->update(['document'=>$doc]);
  $facts=MarketingMaterialPackageTest::facts();$facts['source_revision_id']=$r->id;$facts['source_digest']=app(MarketingMaterialPackage::class)->digest($p,$r);$doc['material_package']=app(MarketingMaterialPackage::class)->build(MarketingMaterialPackageTest::materialOutput(),$facts);$r->update(['document'=>$doc]);$context=$r->context;$context['director_review']=['plan_digest'=>hash('sha256',json_encode($doc['director_plan'],JSON_THROW_ON_ERROR))];$r->update(['context'=>$context]);$p->update(['current_revision_id'=>$r->id]);
  app(MarketingMaterialReferences::class)->save($p->id,$p->owner_id,$p->version,$doc['material_package']['requirements'][0]['id'],(string)Str::uuid(),['permission'=>true],UploadedFile::fake()->image('reference.png'));$p->refresh();$images=app(MarketingMaterialImages::class);$run=$images->quote($p->id,$p->owner_id,$p->version,(string)Str::uuid());$images->authorize($p->id,$p->owner_id,$run->id);$file=UploadedFile::fake()->image('ai.png',864,1536);$this->partialMock(MarketingMaterialImageClient::class)->shouldReceive('generate')->times(3)->andReturn(['bytes'=>file_get_contents($file->getRealPath()),'usage'=>[],'actual_usd'=>.01,'request_id'=>'fixture']);foreach($run->usage['outputs'] as $out){$images->advance($p->id,$p->owner_id,$run->id);$images->review($p->id,$p->owner_id,$run->id,$out['id'],true);}
  return [$p->fresh(),app(\App\Services\Marketing\MarketingClipProduction::class)];
 }
 protected function editingFilm():array {
  [$p,$production]=$this->editingProject();config(['marketing.editing.enabled'=>true,'marketing.conversation.enabled'=>true,'marketing.conversation.open_testing'=>true]);$clips=[];
  foreach($p->currentRevision->document['scenes'] as $i=>$scene){
   $c=$production->quote($p->id,$p->owner_id,$p->version,$i+1,(string)Str::uuid());
   $raw='marketing-clips/'.$c->id.'/raw.mp4';$out='marketing-clips/'.$c->id.'/output.mp4';Storage::disk('local')->put($raw,'raw-fixture-'.$i);Storage::disk('local')->put($out,'output-fixture-'.$i);
   $c->update(['status'=>'completed','raw_path'=>$raw,'raw_sha256'=>hash('sha256','raw-fixture-'.$i),'output_path'=>$out,'output_sha256'=>hash('sha256','output-fixture-'.$i)]);$clips[]=$c;
  }
  $built=app(\App\Services\Marketing\MarketingWorkspaceFilm::class)->inputs($p,array_map(fn($c)=>$c->id,$clips));$path='marketing-films/fixture/output.mp4';Storage::disk('local')->put($path,'film-fixture');
  $film=MarketingVideoFilm::create(['project_id'=>$p->id,'owner_id'=>$p->owner_id,'revision_id'=>$p->current_revision_id,'fingerprint'=>$built['fingerprint'],'status'=>'completed','input'=>$built['input'],'output_path'=>$path,'output_sha256'=>hash('sha256','film-fixture')]);
  return [$p,$film,$clips];
 }
 protected function editingMessage($p,array $context=[]){return \App\Models\MarketingConversationMessage::create(['project_id'=>$p->id,'actor_id'=>$p->owner_id,'request_id'=>(string)Str::uuid(),'sequence'=>(int)\App\Models\MarketingConversationMessage::where('project_id',$p->id)->max('sequence')+1,'role'=>'assistant','screen'=>'finished','content'=>'fixture editing discussion','status'=>'completed','base_version'=>$p->version,'context'=>array_merge(['kind'=>'editing','head_trim_requested'=>false],$context)]);}
 protected function editingAnalyses(array $clips):array {
  $out=[];foreach($clips as $clip){$a=app(\App\Services\Marketing\MarketingClipAnalysis::class)->ensure($clip);$e=['version'=>\App\Services\Marketing\MarketingClipAnalysis::VERSION,'clip_id'=>$clip->id,'raw_sha256'=>$clip->raw_sha256,'output_sha256'=>$clip->output_sha256,'fps'=>24,'frame_count'=>$clip->input['seconds']*24,'audio_present'=>true,'silence_intervals'=>[['kind'=>'start','frame'=>0],['kind'=>'end','frame'=>24],['kind'=>'start','frame'=>60],['kind'=>'end','frame'=>$clip->input['seconds']*24]],'speech_end_frame'=>60,'minimum_frames'=>24,'protected_intervals'=>[['in_frame'=>0,'out_frame'=>72,'reason'=>'fixture required speech/action']],'can_trim_head'=>false,'can_trim_tail'=>true,'candidate_in_frame'=>0,'candidate_out_frame'=>108,'vision'=>[],'frame_samples'=>[],'limitations'=>['fixture only']];$e['digest']=\App\Services\Marketing\MarketingEditingDigest::hash($e);$a->update(['status'=>'completed','evidence'=>$e]);$out[]=$a;}
  return $out;
 }
 protected function editingCandidate(array $clips):array{return ['scenes'=>array_map(fn($clip)=>['clip_id'=>$clip->id,'in_frame'=>0,'out_frame'=>108,'reason'=>'缩短说完后的等待，保留完整口播。'],$clips),'duration_policy'=>'allow_shorter','target_frames'=>null,'cta_hold_frames'=>24];}
}
