<?php
namespace Tests\Feature;

use App\Models\{MarketingVideoFilm, User};
use App\Services\Marketing\{MarketingFilmPackaging, MarketingWorkspaceFilm};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Http, Queue, Storage};
use Illuminate\Support\Str;
use Tests\Concerns\MarketingEditingFixtures;
use Tests\TestCase;

class MarketingFilmPackagingTest extends TestCase
{
    use RefreshDatabase, MarketingEditingFixtures;

    private function setupFilm(): array
    {
        $rows=$this->editingFilm();
        $this->mock(\App\Support\ShopBrand::class, function($mock){$mock->shouldReceive('name')->andReturn('JTG Container & Logistics');$mock->shouldReceive('configuredLogo')->andReturn('/images/themes/logistics/jtg-text-brand.svg');});
        return $rows;
    }
    private function settings($p): array
    {
        $s=app(MarketingFilmPackaging::class);
        return ['enabled'=>true,'template'=>'minimal','duration'=>3,'company'=>'JTG Container & Logistics','cta'=>'联系我们咨询报价','track'=>'internal-calm','volume'=>18,'duck'=>true,'fade'=>true,'logo_sha256'=>$s->brand($p)['sha256']];
    }
    public function test_ai_speed_suggestion_cannot_disable_user_speech_protection_or_render():void {
        [$p,$f]=$this->setupFilm();$before=MarketingVideoFilm::count();$service=app(\App\Services\Marketing\MarketingSpeedDiscussion::class);
        $this->assertTrue($service->requested('第1段中间加速1.5倍'));$proposal=$service->suggestion($f,['scene_number'=>1,'start_frame'=>24,'end_frame'=>72,'rate'=>1.5]);$this->assertTrue($proposal['settings']['protect']);$this->assertSame($f->id,$proposal['base_film_id']);$this->assertSame($before,MarketingVideoFilm::count());
        $ctx=$service->context($f);$this->assertCount(count($f->input['scenes']),$ctx['scenes']);Http::assertNothingSent();
    }
    public function test_speed_adopts_inherits_and_resets_from_canonical_source_without_compounding():void {
        [$p,$base]=$this->setupFilm();$this->actingAs(User::findOrFail($p->owner_id));$settings=$this->settings($p);
        $speed=['scene_number'=>1,'start_frame'=>24,'end_frame'=>72,'rate'=>1.5,'protect'=>false];
        $body=['request_id'=>(string)Str::uuid(),'base_sha256'=>$base->output_sha256,'consent'=>true,'settings'=>$settings,'speed'=>$speed];
        $url=route('marketing.workspace.packaging.adopt',[$p->id,$base->id]);$r=$this->postJson($url,$body)->assertOk();$f=MarketingVideoFilm::findOrFail($r->json('film_id'));
        $source=(int)round($base->input['duration']*24);$this->assertSame($source-16,$f->input['content_frames']);$this->assertSame($source,$f->input['source_content_frames']);$this->assertSame($source-16+72,$f->input['frames']);$this->assertArrayNotHasKey('visual_preserve',$f->input);$this->assertNull($f->approved_at);
        $this->postJson($url,$body)->assertOk()->assertJsonPath('film_id',$f->id);$body['speed']['rate']=2;$this->postJson($url,$body)->assertConflict();
        Storage::disk('local')->put('marketing-films/speed-fixture.mp4','speed-fixture');$f->update(['status'=>'completed','output_path'=>'marketing-films/speed-fixture.mp4','output_sha256'=>hash('sha256','speed-fixture')]);
        $service=app(MarketingFilmPackaging::class);$inherited=$service->prepare($p->id,$p->owner_id,(string)Str::uuid(),$f->id,$f->output_sha256,$settings);$this->assertSame($source-16,$inherited->input['content_frames']);$this->assertSame($f->input['speed'],$inherited->input['speed']);
        $reset=$service->prepare($p->id,$p->owner_id,(string)Str::uuid(),$f->id,$f->output_sha256,$settings,null,null,null,null,null,null,true);$this->assertNull($reset->input['speed']);$this->assertSame($source,$reset->input['content_frames']);$this->assertArrayNotHasKey('visual_preserve',$reset->input);Http::assertNothingSent();
    }
    public function test_overlay_upload_adoption_inheritance_reset_and_foreign_access():void{
        [$p,$base]=$this->setupFilm();$this->actingAs(User::findOrFail($p->owner_id));$settings=$this->settings($p);
        $up=$this->postJson(route('marketing.workspace.packaging.overlay-upload',$p->id),['file'=>\Illuminate\Http\UploadedFile::fake()->image('detail.png',300,200)])->assertOk();$id=$up->json('image.id');$url=$up->json('image.url');$this->get($url)->assertOk();
        $layer=['id'=>'layer-1','image_id'=>$id,'enabled'=>true,'scene_number'=>1,'x'=>70,'y'=>30,'width'=>30,'radius'=>16,'border'=>3,'color'=>'#ffffff','start_frame'=>12,'end_frame'=>96];
        $body=['request_id'=>(string)Str::uuid(),'base_sha256'=>$base->output_sha256,'consent'=>true,'settings'=>$settings,'overlays'=>[$layer]];$adopt=route('marketing.workspace.packaging.adopt',[$p->id,$base->id]);$r=$this->postJson($adopt,$body)->assertOk();$film=MarketingVideoFilm::findOrFail($r->json('film_id'));$this->assertCount(1,$film->input['overlays']);$this->assertSame(1.5,$film->input['overlays'][0]['ratio']);$this->assertNotEmpty($film->input['overlay_assets'][$id]['sha256']);$this->assertNull($film->approved_at);
        $this->postJson($adopt,$body)->assertOk()->assertJsonPath('film_id',$film->id);$body['overlays'][0]['x']=60;$this->postJson($adopt,$body)->assertConflict();
        Storage::disk('local')->put('marketing-films/overlay-test.mp4','overlay-test');$film->update(['status'=>'completed','output_path'=>'marketing-films/overlay-test.mp4','output_sha256'=>hash('sha256','overlay-test')]);$s=app(MarketingFilmPackaging::class);$inherited=$s->prepare($p->id,$p->owner_id,(string)Str::uuid(),$film->id,$film->output_sha256,$settings);$this->assertSame($film->input['overlays'],$inherited->input['overlays']);$reset=$s->prepare($p->id,$p->owner_id,(string)Str::uuid(),$film->id,$film->output_sha256,$settings,null,null,null,null,[]);$this->assertSame([],$reset->input['overlays']);$this->assertSame($settings,$reset->input['settings']);$this->assertNotEmpty($reset->input['visual_recipe']);
        $asset=\App\Models\MarketingAsset::findOrFail($id);$changed=\Illuminate\Http\UploadedFile::fake()->image('changed.png',300,201);Storage::disk('local')->put($asset->path,file_get_contents($changed->getRealPath()));$this->postJson(route('marketing.workspace.packaging.adopt',[$p->id,$film->id]),['request_id'=>(string)Str::uuid(),'base_sha256'=>$film->output_sha256,'consent'=>true,'settings'=>$settings,'overlays'=>[$layer]])->assertConflict();
        $this->actingAs(User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]));$this->get($url)->assertNotFound();Http::assertNothingSent();
    }
    public function test_emphasis_adoption_is_idempotent_inherits_and_resets_without_losing_cards_or_packaging():void
    {
        [$p,$base]=$this->setupFilm();$this->actingAs(User::findOrFail($p->owner_id));$settings=$this->settings($p);$base->update(['input'=>array_replace($base->input,['framing'=>[['scene_number'=>1,'mode'=>'push','zoom'=>1.1,'x'=>50,'y'=>50,'start_frame'=>0,'end_frame'=>120]]])]);
        $card=['id'=>'card1','type'=>'contact','scene_number'=>1,'start_frame'=>12,'end_frame'=>84,'title'=>'Contact us','body'=>'','step'=>1,'channel'=>'none','contact'=>'','position'=>'top-left','replace_product_info'=>false];
        $effect=['scene_number'=>1,'kind'=>'spot','x'=>70,'y'=>48,'size'=>30,'angle'=>135,'color'=>'#d2f36b','strength'=>65,'start_frame'=>12,'end_frame'=>96];
        $payload=['request_id'=>(string)Str::uuid(),'base_sha256'=>$base->output_sha256,'consent'=>true,'settings'=>$settings,'information_cards'=>[$card],'emphasis'=>[$effect]];$url=route('marketing.workspace.packaging.adopt',[$p->id,$base->id]);
        $response=$this->postJson($url,$payload)->assertOk();$f=MarketingVideoFilm::findOrFail($response->json('film_id'));
        $this->assertSame($base->input['framing'],$f->input['framing']);$this->assertSame([$effect],$f->input['emphasis']);$this->assertCount(1,$f->input['visual_recipe']);$this->assertNull($f->approved_at);
        $this->postJson($url,$payload)->assertOk()->assertJsonPath('film_id',$f->id);Queue::assertPushed(\App\Jobs\ProcessMarketingWorkspaceVideo::class,1);
        $payload['emphasis'][0]['size']=35;$this->postJson($url,$payload)->assertConflict();
        $path='marketing-films/framing-fixture.mp4';Storage::disk('local')->put($path,'frame-fixture');$f->update(['status'=>'completed','output_path'=>$path,'output_sha256'=>hash('sha256','frame-fixture')]);$service=app(MarketingFilmPackaging::class);
        $inherited=$service->prepare($p->id,$p->owner_id,(string)Str::uuid(),$f->id,$f->output_sha256,$settings);
        $this->assertSame($f->input['emphasis'],$inherited->input['emphasis']);$this->assertSame($f->input['information_cards'],$inherited->input['information_cards']);
        $reset=$service->prepare($p->id,$p->owner_id,(string)Str::uuid(),$f->id,$f->output_sha256,$settings,null,null,null,[]);
        $this->assertSame($base->input['framing'],$reset->input['framing']);$this->assertSame([],$reset->input['emphasis']);$this->assertSame($f->input['visual_recipe'],$reset->input['visual_recipe']);$this->assertSame($f->input['information_cards'],$reset->input['information_cards']);$this->assertSame($settings,$reset->input['settings']);$this->assertSame($f->id,$reset->input['visual_preserve']['film_id']);Http::assertNothingSent();
    }
    public function test_framing_adoption_is_idempotent_inherits_and_resets_without_losing_cards_or_packaging():void
    {
        [$p,$base]=$this->setupFilm();$this->actingAs(User::findOrFail($p->owner_id));$settings=$this->settings($p);
        $card=['id'=>'card1','type'=>'contact','scene_number'=>1,'start_frame'=>12,'end_frame'=>84,'title'=>'Contact us','body'=>'','step'=>1,'channel'=>'none','contact'=>'','position'=>'top-left','replace_product_info'=>false];
        $effect=['scene_number'=>1,'mode'=>'push','zoom'=>1.18,'x'=>40,'y'=>48,'start_frame'=>12,'end_frame'=>96];
        $payload=['request_id'=>(string)Str::uuid(),'base_sha256'=>$base->output_sha256,'consent'=>true,'settings'=>$settings,'information_cards'=>[$card],'framing'=>[$effect]];$url=route('marketing.workspace.packaging.adopt',[$p->id,$base->id]);
        $response=$this->postJson($url,$payload)->assertOk();$f=MarketingVideoFilm::findOrFail($response->json('film_id'));
        $this->assertSame([$effect],$f->input['framing']);$this->assertCount(1,$f->input['visual_recipe']);$this->assertNull($f->approved_at);
        $this->postJson($url,$payload)->assertOk()->assertJsonPath('film_id',$f->id);Queue::assertPushed(\App\Jobs\ProcessMarketingWorkspaceVideo::class,1);
        $payload['framing'][0]['zoom']=1.25;$this->postJson($url,$payload)->assertConflict();
        $path='marketing-films/framing-fixture.mp4';Storage::disk('local')->put($path,'frame-fixture');$f->update(['status'=>'completed','output_path'=>$path,'output_sha256'=>hash('sha256','frame-fixture')]);$service=app(MarketingFilmPackaging::class);
        $inherited=$service->prepare($p->id,$p->owner_id,(string)Str::uuid(),$f->id,$f->output_sha256,$settings);
        $this->assertSame($f->input['framing'],$inherited->input['framing']);$this->assertSame($f->input['information_cards'],$inherited->input['information_cards']);
        $reset=$service->prepare($p->id,$p->owner_id,(string)Str::uuid(),$f->id,$f->output_sha256,$settings,null,null,[]);
        $this->assertSame([],$reset->input['framing']);$this->assertSame($f->input['visual_recipe'],$reset->input['visual_recipe']);$this->assertSame($f->input['information_cards'],$reset->input['information_cards']);$this->assertSame($settings,$reset->input['settings']);$this->assertSame($f->id,$reset->input['visual_preserve']['film_id']);Http::assertNothingSent();
    }
    public function test_scoped_recipe_resolves_previous_layers_instead_of_self_cycle():void
    {
        [$p,$base]=$this->setupFilm();$sub=app(\App\Services\Marketing\MarketingSubtitleService::class);$sub->prepare($base);$base->refresh();$result=$base->result;$rows=$sub->scenes($base);foreach($rows as &$row)$row['words']=[['text'=>$row['spoken_text'],'start_frame'=>0,'end_frame'=>72]];unset($row);$result['subtitle_alignment']['status']='ready';$result['subtitle_alignment']['scenes']=$rows;$base->update(['result'=>$result]);
        $plan=$sub->plan($base,['mode'=>'phrase','size'=>'medium','position'=>'lower','color'=>'#d2f36b','scope'=>'scene','scene'=>1]);
        $recipe=app(\App\Services\Marketing\MarketingVisualRecipe::class)->resolve($base,$plan);
        $this->assertSame('phrase',$recipe[0]['settings']['mode']);$this->assertFalse($recipe[0]['original']);
    }
    public function test_information_cards_are_inherited_deleted_and_idempotent_without_burning_old_layers():void
    {
        [$p,$base]=$this->setupFilm();$service=app(MarketingFilmPackaging::class);$settings=$this->settings($p);
        $card=['id'=>'card1','type'=>'contact','scene_number'=>1,'start_frame'=>12,'end_frame'=>84,'title'=>'Contact us','body'=>'','step'=>1,'channel'=>'none','contact'=>'','position'=>'top-right','replace_product_info'=>false];
        $request=(string)Str::uuid();$film=$service->prepare($p->id,$p->owner_id,$request,$base->id,$base->output_sha256,$settings,null,[$card]);
        $this->assertCount(1,$film->input['information_cards']);$this->assertCount(1,$film->input['visual_recipe']);$this->assertTrue($film->input['visual_recipe'][0]['original']);
        $this->assertSame($film->id,$service->prepare($p->id,$p->owner_id,$request,$base->id,$base->output_sha256,$settings,null,[$card])->id);
        $path='marketing-films/information-fixture.mp4';Storage::disk('local')->put($path,'information-fixture');$film->update(['status'=>'completed','output_path'=>$path,'output_sha256'=>hash('sha256','information-fixture')]);
        $inherited=$service->prepare($p->id,$p->owner_id,(string)Str::uuid(),$film->id,$film->output_sha256,$settings);
        $this->assertCount(1,$inherited->input['information_cards']);$this->assertSame($film->id,$inherited->input['visual_preserve']['film_id']);
        $deleted=$service->prepare($p->id,$p->owner_id,(string)Str::uuid(),$film->id,$film->output_sha256,$settings,null,[]);
        $this->assertSame([],$deleted->input['information_cards']);$this->assertSame($film->input['visual_recipe'],$deleted->input['visual_recipe']);$this->assertNull($deleted->approved_at);
    }
    public function test_unified_adoption_saves_subtitles_music_and_outro_as_one_idempotent_film(): void
    {
        [$p,$base]=$this->setupFilm();$sub=app(\App\Services\Marketing\MarketingSubtitleService::class);$sub->prepare($base);$base->refresh();$result=$base->result;$rows=$sub->scenes($base);foreach($rows as &$row)$row['words']=[['text'=>$row['spoken_text'],'start_frame'=>0,'end_frame'=>72]];unset($row);$result['subtitle_alignment']['status']='ready';$result['subtitle_alignment']['scenes']=$rows;$base->update(['result'=>$result]);
        $this->actingAs(User::findOrFail($p->owner_id));$settings=$this->settings($p);$settings['volume']=27;$settings['duration']=4;
        $payload=['request_id'=>(string)Str::uuid(),'base_sha256'=>$base->output_sha256,'consent'=>true,'settings'=>$settings,'subtitle_settings'=>['mode'=>'phrase','size'=>'large','position'=>'raised','color'=>'#ffdc78','scope'=>'all','scene'=>1]];
        $url=route('marketing.workspace.packaging.adopt',[$p->id,$base->id]);$response=$this->postJson($url,$payload)->assertOk();$film=MarketingVideoFilm::findOrFail($response->json('film_id'));
        $this->assertSame('phrase',$film->input['subtitles']['settings']['mode']??null);$this->assertSame(27,$film->input['settings']['volume']);$this->assertSame(4,$film->input['settings']['duration']);$this->assertNull($film->approved_at);
        $this->postJson($url,$payload)->assertOk()->assertJsonPath('film_id',$film->id);Queue::assertPushed(\App\Jobs\ProcessMarketingWorkspaceVideo::class,1);$this->assertDatabaseCount('marketing_packaging_requests',1);
        $payload['subtitle_settings']['mode']='highlight';$this->postJson($url,$payload)->assertConflict();Http::assertNothingSent();
    }
    public function test_unified_config_exposes_actual_subtitle_metadata_and_unprepared_changes_queue_nothing():void
    {
        [$p,$base]=$this->setupFilm();$this->actingAs(User::findOrFail($p->owner_id));
        $this->getJson(route('marketing.workspace.packaging.config',[$p->id,$base->id]))->assertOk()->assertJsonPath('subtitles.scenes.0.number',1);
        $this->postJson(route('marketing.workspace.packaging.adopt',[$p->id,$base->id]),['request_id'=>(string)Str::uuid(),'base_sha256'=>$base->output_sha256,'consent'=>true,'settings'=>$this->settings($p),'subtitle_settings'=>['mode'=>'highlight','size'=>'medium','position'=>'lower','color'=>'#d2f36b','scope'=>'all','scene'=>1]])->assertConflict();
        Queue::assertNotPushed(\App\Jobs\ProcessMarketingWorkspaceVideo::class);$this->assertDatabaseCount('marketing_packaging_requests',0);
    }
    public function test_unified_defaults_do_not_enable_an_outro_on_an_unpackaged_film():void
    {
        [$p,$base]=$this->setupFilm();$this->actingAs(User::findOrFail($p->owner_id));
        $this->getJson(route('marketing.workspace.packaging.config',[$p->id,$base->id]))->assertOk()->assertJsonPath('brand.available',true)->assertJsonPath('settings.enabled',false)->assertJsonPath('settings.track',null);
    }
    public function test_adoption_is_source_bound_idempotent_and_unapproved(): void
    {
        [$p,$base]=$this->setupFilm();$s=app(MarketingFilmPackaging::class);$id=(string)Str::uuid();$settings=$this->settings($p);
        $film=$s->prepare($p->id,$p->owner_id,$id,$base->id,$base->output_sha256,$settings);
        $again=$s->prepare($p->id,$p->owner_id,$id,$base->id,$base->output_sha256,$settings);
        $this->assertSame($film->id,$again->id);$this->assertSame('queued',$film->status);$this->assertNull($film->approved_at);
        $this->assertSame(432,$film->input['frames']);$this->assertSame(18,$film->input['duration']);
        $this->assertSame('internal_acceptance',$film->input['audio']['license_scope']);
        $this->assertSame($base->output_sha256,$base->fresh()->output_sha256);$this->assertSame(15,$base->input['duration']);
        $this->assertDatabaseCount('marketing_packaging_requests',1);Http::assertNothingSent();
    }
    public function test_same_request_with_changed_settings_is_rejected(): void
    {
        [$p,$base]=$this->setupFilm();$s=app(MarketingFilmPackaging::class);$id=(string)Str::uuid();$settings=$this->settings($p);$s->prepare($p->id,$p->owner_id,$id,$base->id,$base->output_sha256,$settings);$settings['volume']=90;
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);$s->prepare($p->id,$p->owner_id,$id,$base->id,$base->output_sha256,$settings);
    }
    public function test_changed_source_bytes_are_rejected_before_queueing(): void
    {
        [$p,$base]=$this->setupFilm();$settings=$this->settings($p);Storage::disk('local')->put($base->output_path,'changed');
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);app(MarketingFilmPackaging::class)->prepare($p->id,$p->owner_id,(string)Str::uuid(),$base->id,$base->output_sha256,$settings);
    }
    public function test_repackaging_replaces_outro_and_keeps_original_content(): void
    {
        [$p,$base]=$this->setupFilm();$s=app(MarketingFilmPackaging::class);$settings=$this->settings($p);$f=$s->prepare($p->id,$p->owner_id,(string)Str::uuid(),$base->id,$base->output_sha256,$settings);
        $path='marketing-films/'.$f->id.'/output.mp4';Storage::disk('local')->put($path,'packaged');$f->update(['status'=>'completed','output_path'=>$path,'output_sha256'=>hash('sha256','packaged')]);$settings['duration']=4;
        $next=$s->prepare($p->id,$p->owner_id,(string)Str::uuid(),$f->id,$f->output_sha256,$settings);
        $this->assertSame($base->id,$next->input['content_film_id']);$this->assertSame(456,$next->input['frames']);$this->assertSame(19,$next->input['duration']);
        app(MarketingWorkspaceFilm::class)->assertCurrent($next);Http::assertNothingSent();
    }
    public function test_internal_music_is_not_available_outside_open_local_testing(): void
    {
        [$p,$base]=$this->setupFilm();$settings=$this->settings($p);config(['marketing.conversation.open_testing'=>false]);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);app(MarketingFilmPackaging::class)->prepare($p->id,$p->owner_id,(string)Str::uuid(),$base->id,$base->output_sha256,$settings);
    }
    public function test_foreign_project_cannot_access_packaging_assets_or_config(): void
    {
        [$p,$base]=$this->setupFilm();$other=User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]);$this->actingAs($other);
        $this->getJson(route('marketing.workspace.packaging.config',[$p->id,$base->id]))->assertNotFound();
        $this->get(route('marketing.workspace.packaging.audio',[$p->id,'internal-calm']))->assertNotFound();Http::assertNothingSent();
    }
    public function test_settings_reject_arbitrary_track_paths_and_templates(): void
    {
        [$p,$base]=$this->setupFilm();$settings=$this->settings($p);$settings['template']='executable';
        $this->expectException(\Illuminate\Validation\ValidationException::class);app(MarketingFilmPackaging::class)->prepare($p->id,$p->owner_id,(string)Str::uuid(),$base->id,$base->output_sha256,$settings);
    }
    public function test_ai_packaging_discussion_creates_only_a_source_bound_suggestion(): void
    {
        [$p,$base]=$this->setupFilm();$s=app(\App\Services\Marketing\MarketingEditingConversation::class);$s->enqueue($p,(string)Str::uuid(),'加一个3秒品牌片尾，背景音乐安静一点',$base->id);
        $m=\App\Models\MarketingConversationMessage::where('status','pending')->firstOrFail();$candidate=$this->settings($p);unset($candidate['logo_sha256']);
        $this->mock(\App\Services\Marketing\MarketingTextClient::class)->shouldReceive('text')->once()->andReturn(json_encode(['reply'=>'建议三秒简洁片尾并配轻音乐，查看设置后再采用。','handoff_category'=>'none','proposal'=>null,'packaging'=>$candidate]));
        $this->mock(\App\Services\Marketing\MarketingMediaTools::class)->shouldReceive('run')->andReturn('{"budget_upper_estimate":2000}');
        $s->process($m->id);$m->refresh();$this->assertSame('completed',$m->status);$this->assertSame($base->id,$m->context['packaging_proposal']['base_film_id']);$this->assertSame(18,$m->context['packaging_proposal']['settings']['volume']);$this->assertDatabaseCount('marketing_video_films',1);$this->assertDatabaseCount('marketing_clip_analyses',0);Http::assertNothingSent();
    }
    public function test_music_only_remains_available_without_a_store_logo(): void
    {
        [$p,$base]=$this->setupFilm();$settings=$this->settings($p);
        $this->mock(\App\Support\ShopBrand::class,function($m){$m->shouldReceive('name')->andReturn('JTG Container & Logistics');$m->shouldReceive('configuredLogo')->andReturn('');});
        $this->getJson(route('marketing.workspace.packaging.config',[$p->id,$base->id]))->assertOk()->assertJsonPath('brand.available',false)->assertJsonPath('settings.enabled',false);
        $settings['enabled']=false;$settings['logo_sha256']=null;$f=app(MarketingFilmPackaging::class)->prepare($p->id,$p->owner_id,(string)Str::uuid(),$base->id,$base->output_sha256,$settings);$this->assertNull($f->input['brand']);$this->assertSame(15,$f->input['duration']);
    }
    public function test_revoked_old_track_can_be_replaced_without_reusing_its_audio(): void
    {
        [$p,$base]=$this->setupFilm();$s=app(MarketingFilmPackaging::class);$settings=$this->settings($p);$path='marketing-audio/private.wav';Storage::disk('local')->put($path,'private fixture');
        $a=\App\Models\MarketingAudioAsset::create(['project_id'=>$p->id,'owner_id'=>$p->owner_id,'name'=>'Private music','path'=>$path,'sha256'=>hash('sha256','private fixture'),'duration'=>24,'authorized'=>true,'license'=>['scope'=>'project_commercial','confirmed_by'=>$p->owner_id]]);$settings['track']=$a->id;
        $old=$s->prepare($p->id,$p->owner_id,(string)Str::uuid(),$base->id,$base->output_sha256,$settings);$out='marketing-films/'.$old->id.'/output.mp4';Storage::disk('local')->put($out,'pack');$old->update(['status'=>'completed','output_path'=>$out,'output_sha256'=>hash('sha256','pack')]);$a->update(['authorized'=>false]);
        $this->getJson(route('marketing.workspace.packaging.config',[$p->id,$old->id]))->assertOk()->assertJsonPath('settings.track',null);
        $settings['track']='internal-calm';$next=$s->prepare($p->id,$p->owner_id,(string)Str::uuid(),$old->id,$old->output_sha256,$settings);$this->assertSame($base->id,$next->input['content_film_id']);$this->assertSame('internal-calm',$next->input['audio']['id']);$this->assertSame(18,$next->input['duration']);
    }
    public function test_music_suggestion_without_logo_accepts_an_empty_cta(): void
    {
        [$p,$base]=$this->setupFilm();
        $this->mock(\App\Support\ShopBrand::class,function($m){$m->shouldReceive('name')->andReturn('JTG Container & Logistics');$m->shouldReceive('configuredLogo')->andReturn('');});
        $service=app(\App\Services\Marketing\MarketingPackagingDiscussion::class);
        $this->assertSame('JTG Container & Logistics',$service->context($p,$base)['brand']['name']);
        $proposal=$service->suggestion($p,$base,['enabled'=>false,'track'=>'internal-calm','cta'=>null]);
        $this->assertFalse($proposal['settings']['enabled']);$this->assertSame('',$proposal['settings']['cta']);$this->assertNull($proposal['settings']['logo_sha256']);
    }
    public function test_private_audio_upload_needs_only_a_file_and_serves_only_its_owner(): void
    {
        [$p,$base]=$this->setupFilm();
        config(['marketing.production.ffmpeg_binary'=>base_path('resources/marketing-renderer/node_modules/@remotion/compositor-win32-x64-msvc/ffmpeg.exe'),'marketing.production.ffprobe_binary'=>base_path('resources/marketing-renderer/node_modules/@remotion/compositor-win32-x64-msvc/ffprobe.exe')]);
        $file=fn()=>new \Illuminate\Http\UploadedFile(resource_path('marketing-audio/internal/calm.wav'),'authorized.wav','audio/wav',null,true);
        $this->postJson(route('marketing.workspace.packaging.upload',$p->id),[])->assertUnprocessable();$this->assertDatabaseCount('marketing_audio_assets',0);
        $response=$this->postJson(route('marketing.workspace.packaging.upload',$p->id),['file'=>$file()])->assertOk();$id=$response->json('id');
        $response->assertJsonPath('track.id',$id)->assertJsonPath('track.kind','upload');
        $a=\App\Models\MarketingAudioAsset::findOrFail($id);$this->assertSame($p->owner_id,$a->license['uploaded_by']);$this->assertSame('merchant_supplied',$a->license['scope']);$this->assertSame('authorized',$a->name);$this->assertEquals(24,$a->duration);$this->assertFalse($a->license['platform_verified']);
        $this->get(route('marketing.workspace.packaging.audio',[$p->id,$id]))->assertOk()->assertHeader('content-type','audio/wav');
        $this->actingAs(User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]));$this->get(route('marketing.workspace.packaging.audio',[$p->id,$id]))->assertNotFound();
        Http::assertNothingSent();
    }
    public function test_decodable_mp3_with_unrecognized_metadata_can_be_uploaded(): void
    {
        [$p]=$this->setupFilm();
        $binary=base_path('resources/marketing-renderer/node_modules/@remotion/compositor-win32-x64-msvc/');
        config(['marketing.production.ffmpeg_binary'=>$binary.'ffmpeg.exe','marketing.production.ffprobe_binary'=>$binary.'ffprobe.exe']);
        $path=Storage::disk('local')->path('tagged.mp3');
        app(\App\Services\Marketing\MarketingMediaTools::class)->run([$binary.'ffmpeg.exe','-v','error','-y','-i',resource_path('marketing-audio/internal/calm.wav'),'-t','2','-c:a','libmp3lame','-id3v2_version','0',$path],30,true);
        // A stale ID3 length makes finfo return octet-stream although the MP3 frames decode.
        file_put_contents($path,"ID3\x03\x00\x00\x00\x00\x00\x3c".str_repeat("\x00",108).file_get_contents($path));
        $file=new \Illuminate\Http\UploadedFile($path,'music.mp3','audio/mpeg',null,true);
        $this->assertSame('application/octet-stream',$file->getMimeType());
        $response=$this->postJson(route('marketing.workspace.packaging.upload',$p->id),['file'=>$file,'name'=>'My MP3'])->assertOk();
        $response->assertJsonPath('track.name','My MP3')->assertJsonPath('track.kind','upload');
        $asset=\App\Models\MarketingAudioAsset::findOrFail($response->json('id'));
        $this->assertGreaterThan(2,$asset->duration);
        $probe=app(\App\Services\Marketing\MarketingMediaTools::class)->probe(Storage::disk('local')->path($asset->path));
        $this->assertSame('pcm_s16le',$probe['streams'][0]['codec_name']);
        Http::assertNothingSent();
    }
    public function test_renaming_non_audio_to_mp3_does_not_create_an_asset(): void
    {
        [$p]=$this->setupFilm();
        $binary=base_path('resources/marketing-renderer/node_modules/@remotion/compositor-win32-x64-msvc/');
        config(['marketing.production.ffmpeg_binary'=>$binary.'ffmpeg.exe','marketing.production.ffprobe_binary'=>$binary.'ffprobe.exe']);
        $path=Storage::disk('local')->path('not-music.mp3');file_put_contents($path,'This is plain text, not an audio recording.');
        $file=new \Illuminate\Http\UploadedFile($path,'not-music.mp3','audio/mpeg',null,true);
        $this->postJson(route('marketing.workspace.packaging.upload',$p->id),['file'=>$file])->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->assertDatabaseCount('marketing_audio_assets',0);
        $this->assertSame([],Storage::disk('local')->allFiles('marketing-audio'));
        Http::assertNothingSent();
    }
    public function test_free_library_is_available_outside_internal_testing_and_is_pinned_in_recipe(): void
    {
        [$p,$base]=$this->setupFilm();$settings=$this->settings($p);config(['marketing.conversation.open_testing'=>false]);
        $tracks=app(\App\Services\Marketing\MarketingPackagingAssets::class)->catalogue($p);$free=array_values(array_filter($tracks,fn($t)=>($t['kind']??'')==='library'));$this->assertCount(12,$free);
        foreach($free as $track){$this->assertSame('CC0-1.0',$track['license']);$this->assertNotEmpty($track['author']);$this->assertGreaterThan(15,$track['duration']);}
        $settings['track']=$free[0]['id'];$film=app(MarketingFilmPackaging::class)->prepare($p->id,$p->owner_id,(string)Str::uuid(),$base->id,$base->output_sha256,$settings);$this->assertSame('cc0',$film->input['audio']['license_scope']);$this->assertSame($free[0]['sha256'],$film->input['audio']['sha256']);
        $this->get($free[0]['url'])->assertOk();app(MarketingWorkspaceFilm::class)->assertCurrent($film);Http::assertNothingSent();
    }
    public function test_selected_private_track_survives_catalogue_pagination(): void
    {
        [$p]=$this->setupFilm();$selected=null;
        for($i=0;$i<31;$i++){$a=\App\Models\MarketingAudioAsset::create(['project_id'=>$p->id,'owner_id'=>$p->owner_id,'name'=>'Music '.$i,'path'=>'fixture.wav','sha256'=>str_repeat('a',64),'duration'=>24,'authorized'=>true,'license'=>['scope'=>'merchant_supplied','uploaded_by'=>$p->owner_id],'created_at'=>now()->addMinutes($i)]);if($i===0)$selected=$a->id;}
        $tracks=app(\App\Services\Marketing\MarketingPackagingAssets::class)->catalogue($p,$selected);$this->assertContains($selected,array_column($tracks,'id'));
    }
}
