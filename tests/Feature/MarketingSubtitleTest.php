<?php
namespace Tests\Feature;
use Tests\TestCase;
use Tests\Concerns\MarketingEditingFixtures;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\Marketing\MarketingSubtitleService;
use App\Models\User;

class MarketingSubtitleTest extends TestCase {
 use RefreshDatabase,MarketingEditingFixtures;
 public function test_metadata_uses_actual_scene_count_titles_and_cut_times():void{
  [$p,$film,$clips]=$this->editingFilm();
  $input=$clips[0]->input;$input['scene']['title']='面料细节';$clips[0]->update(['input'=>$input]);
  $other=$clips[0]->replicate(['id']);$other->request_id=(string)\Illuminate\Support\Str::uuid();$other->fingerprint=str_repeat('b',64);$other->scene_number=2;$otherInput=$other->input;unset($otherInput['scene']['title']);$otherInput['director']['purpose']='';$other->input=$otherInput;$other->save();
  $input=$film->input;$input['scenes']=[$input['scenes'][0],array_replace($input['scenes'][0],['clip_id'=>$other->id])];$input['scenes'][0]['in_frame']=24;$input['scenes'][0]['frames']=72;$input['scenes'][0]['out_frame']=96;$film->input=$input;
  $rows=app(MarketingSubtitleService::class)->scenes($film);
  $this->assertCount(2,$rows);$this->assertSame('面料细节',$rows[0]['title']);$this->assertSame(72,$rows[0]['frames']);$this->assertSame(24,$rows[0]['in_frame']);$this->assertSame(72,$rows[1]['start_frame']);$this->assertSame('第2段',$rows[1]['title']);
 }
 public function test_foreign_owner_cannot_read_or_prepare_subtitles():void{
  [$p,$f]=$this->editingFilm();$this->actingAs(User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]));
  $this->getJson(route('marketing.workspace.subtitles.config',[$p->id,$f->id]))->assertNotFound();
  $this->postJson(route('marketing.workspace.subtitles.prepare',[$p->id,$f->id]))->assertNotFound();
 }
 public function test_settings_reject_unbounded_or_arbitrary_values():void{
  $this->expectException(\Illuminate\Validation\ValidationException::class);
  app(MarketingSubtitleService::class)->settings(['mode'=>'script','size'=>'giant','position'=>'anywhere','color'=>'url(evil)','scope'=>'all','scene'=>1]);
 }
 public function test_alignment_preparation_is_idempotent_and_adoption_pins_plan_without_approval():void{
  [$p,$f]=$this->editingFilm();\Illuminate\Support\Facades\Queue::fake();$s=app(MarketingSubtitleService::class);
  $s->prepare($f);$s->prepare($f->fresh());\Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\PrepareMarketingSubtitles::class,1);
  $f->refresh();$result=$f->result;$rows=$s->scenes($f);foreach($rows as &$row)$row['words']=[['text'=>$row['spoken_text'],'start_frame'=>0,'end_frame'=>72]];unset($row);
  $result['subtitle_alignment']['status']='ready';$result['subtitle_alignment']['scenes']=$rows;$f->update(['result'=>$result]);
  $settings=['mode'=>'highlight','size'=>'medium','position'=>'lower','color'=>'#d2f36b','scope'=>'all','scene'=>1];$request=(string)\Illuminate\Support\Str::uuid();
  $new=$s->adopt($f,$request,$f->output_sha256,$settings);$again=$s->adopt($f,$request,$f->output_sha256,$settings);
  $this->assertSame($new->id,$again->id);$this->assertNull($new->approved_at);$this->assertSame('queued',$new->status);$this->assertSame($rows,$new->input['subtitles']['scenes']);
  $this->assertSame('ready',$s->config($new)['alignment']['status']);$this->assertSame($f->id,$new->input['subtitles']['reference_film_id']);
  $new->update(['status'=>'completed','output_path'=>'next.mp4','output_sha256'=>hash('sha256','next-film')]);\Illuminate\Support\Facades\Storage::disk('local')->put('next.mp4','next-film');
  $nextPlan=$s->plan($new,array_replace($settings,['scope'=>'scene']));$this->assertSame($new->id,$nextPlan['reference_film_id']);$this->assertSame($rows,$nextPlan['scenes']);
  $this->assertSame($f->input['duration'],$new->input['duration']);app(\App\Services\Marketing\MarketingWorkspaceFilm::class)->assertCurrent($new);
  \Illuminate\Support\Facades\Storage::disk('local')->put($f->input['scenes'][0]['raw_path'],'changed bytes');
  $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);$s->adopt($f,(string)\Illuminate\Support\Str::uuid(),$f->output_sha256,$settings);
 }
 public function test_alignment_uses_frozen_audio_language_and_planned_duration():void{
  [$p,$f,$clips]=$this->editingFilm();
  $input=$clips[0]->input;unset($input['language']);$input['configuration']['audio']['language']='zh-MY';$clips[0]->update(['input'=>$input]);
  \Illuminate\Support\Facades\Queue::fake();$service=app(MarketingSubtitleService::class);$service->prepare($f);
  $commands=[];$tools=\Mockery::mock(\App\Services\Marketing\MarketingMediaTools::class);
  $tools->shouldReceive('run')->andReturnUsing(function($command)use(&$commands){$commands[]=$command;if(in_array('--output',$command,true)){file_put_contents($command[array_search('--output',$command)+1],json_encode(['words'=>[['text'=>'test','start_frame'=>0,'end_frame'=>24]]]));}return '';});$this->app->instance(\App\Services\Marketing\MarketingMediaTools::class,$tools);
  $service->align($f->id,$f->fresh()->result['subtitle_alignment']['source_digest']);
  $this->assertSame('ready',$f->fresh()->result['subtitle_alignment']['status']);
  $this->assertContains('-t',$commands[0]);$this->assertSame((string)$clips[0]->input['seconds'],$commands[0][array_search('-t',$commands[0])+1]);
  $this->assertSame('zh',$commands[1][array_search('--language',$commands[1])+1]);
  $this->assertSame(1,$f->fresh()->result['subtitle_alignment']['completed_scenes']);
 }

 public function test_local_alignment_failure_is_terminal_and_preserves_original():void{
  [$p,$f]=$this->editingFilm();$hash=$f->output_sha256;$path=$f->output_path;
  \Illuminate\Support\Facades\Queue::fake();$service=app(MarketingSubtitleService::class);$service->prepare($f);
  $tools=\Mockery::mock(\App\Services\Marketing\MarketingMediaTools::class);$tools->shouldReceive('run')->once()->andThrow(new \App\Services\Marketing\MarketingProductionFailure('media_processing_failed'));$this->app->instance(\App\Services\Marketing\MarketingMediaTools::class,$tools);
  $service->align($f->id,$f->fresh()->result['subtitle_alignment']['source_digest']);$f->refresh();$state=$service->config($f)['alignment'];
  $this->assertSame('failed',$state['status']);$this->assertSame('media_processing_failed',$state['error_code']);$this->assertFalse($state['automatic_retry']);$this->assertNotEmpty($state['failed_at']);$this->assertSame($hash,$f->output_sha256);$this->assertSame($path,$f->output_path);
  \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\PrepareMarketingSubtitles::class,1);
 }

}
