<?php

namespace Tests\Feature;

use App\Models\AiConnection;
use App\Models\MarketingAiRun;
use App\Models\MarketingRevision;
use App\Models\MarketingStoryboardProviderCall;
use App\Models\MarketingVideoClip;
use App\Models\MarketingVideoFilm;
use App\Services\Marketing\MarketingClipSource;
use App\Services\Marketing\MarketingCreativeProfiles;
use App\Services\Marketing\MarketingEvolinkMedia;
use App\Services\Marketing\MarketingMaterialVisionClient;
use App\Services\Marketing\MarketingMediaTools;
use App\Services\Marketing\MarketingPresenterLibrary;
use App\Services\Marketing\MarketingProductionPreparation;
use App\Services\Marketing\MarketingStoryboardDirector;
use App\Services\Marketing\MarketingStoryboardHandoff;
use App\Services\Marketing\MarketingStoryboardPreparation;
use App\Services\Marketing\MarketingStoryboardProduction;
use App\Services\Marketing\MarketingStoryboardUpload;
use App\Services\Marketing\MarketingStoryboardWorkspace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\Concerns\MarketingResearchFixtures;
use Tests\TestCase;

class MarketingStoryboardEndToEndTest extends TestCase
{
    use MarketingResearchFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh')->run();
        Queue::fake();
    }

    public static function audioRoutes(): array { return ['preset'=>['preset'], 'auto'=>['auto'], 'native'=>['native'], 'economy'=>['economy']]; }

    #[\PHPUnit\Framework\Attributes\DataProvider('audioRoutes')]
    public function test_real_code_fake_suppliers_reaches_voiced_film_without_repeating_paid_posts(string $route): void
    {
        $p = $this->researchProject();
        $text = 'Approved.';
        $r = MarketingRevision::create(['project_id' => $p->id, 'actor_id' => $p->owner_id, 'number' => 1, 'document' => ['scenes' => [['title' => 'Offline presenter story', 'visual' => 'A fictional presenter introduces a generic service.', 'duration' => 5, 'voiceover' => $text, 'subtitle' => 'Offline fixture', 'presenter_required' => true, 'frames' => ['start_frame' => ['required' => true, 'instruction' => 'Presenter opening'], 'end_frame' => ['required' => true, 'instruction' => 'Same presenter ends gesture'], 'extra_image' => ['required' => true, 'instruction' => 'Same identity reference']]]]], 'context' => ['offline_acceptance' => true]]);
        $d=$r->document;$d['scenes'][0]['material_plan']=['mode'=>'first_last_frames','roles'=>['start_frame','end_frame'],'reason'=>'控制开场与结尾状态'];$d['scenes'][0]['frames']['extra_image']['required']=false;$r->update(['document'=>$d]);
        if ($route === 'economy') { $d=$r->document; $d['scenes'][0]['material_plan']=['mode'=>'single_image','roles'=>['start_frame'],'reason'=>'单张画面足以表达连续动作']; $r->update(['document'=>$d]); }
        $p->update(['current_revision_id' => $r->id]);
        $w = app(MarketingStoryboardWorkspace::class);
        $s = $w->initialize($p->id, $p->owner_id, (string) Str::uuid(), $p->version);
        $ref = app(MarketingStoryboardUpload::class)->store($p->id, $p->owner_id, UploadedFile::fake()->image('authorized-offline-reference.png', 360, 640), true);
        $person = app(MarketingPresenterLibrary::class)->register($p->id, $p->owner_id, (string) Str::uuid(), 'OFFLINE reference, not real avatar quality', 'synthetic', $ref['asset_id'], true);
        if($route==='auto'){
            $lib=app(\App\Services\Marketing\MarketingAvatarLibrary::class);$actor=\App\Models\User::findOrFail($p->owner_id);
            $avatar=$lib->saveDraft($actor,$lib->home($actor)->id,(string)Str::uuid(),['name'=>'OFFLINE shared Avatar','kind'=>'synthetic','look'=>'Same appearance']);
            $lib->reference($actor,$avatar->id,UploadedFile::fake()->image('avatar-reference.png',360,640),true);$lib->publish($actor,$avatar->id,(string)Str::uuid(),true);
            $person=['id'=>'avatar:'.$avatar->id,'version'=>1];
        }
        $role = fn ($model, $cap) => ['model' => $model, 'version' => 'fixture-1', 'cap_microusd' => $cap, 'pricing_verified_at' => now()->toIso8601String()];
        config(['marketing.workspace_video.enabled' => true, 'marketing.storyboard.live_enabled' => true, 'marketing.storyboard.live_grant' => ['reference' => 'FAKE HTTP E2E ONLY', 'owner_id' => $p->owner_id, 'project_id' => $p->id, 'roles' => ['image', 'qa', 'voice', 'upload', 'video'], 'expires_at' => now()->addHour()->toIso8601String(), 'max_calls' => 20, 'max_total_microusd' => 10000],
            'marketing.storyboard.roles.image' => $role('gpt-image-2', 100) + ['billing_mode'=>'standard', 'text_usd_per_million' => 5, 'image_usd_per_million' => 8, 'output_usd_per_million' => 30], 'marketing.storyboard.roles.qa' => $role('gpt-5.6-luna', 100) + ['input_usd_per_million' => 1, 'output_usd_per_million' => 1], 'marketing.storyboard.roles.voice' => $role('eleven_multilingual_v2', 100) + ['credit_usd' => 0.000001], 'marketing.storyboard.roles.upload' => $role('evolink-files', 10) + ['fee_microusd' => 0], 'marketing.storyboard.roles.video' => $role(MarketingClipSource::MODEL, 100) + ['credit_usd' => 0.000001, 'access_verified_at' => now()->toIso8601String()],
            'marketing.storyboard.voices' => [['id' => 'offline-voice', 'name' => 'Offline fake voice', 'version' => 1, 'provider_voice_id' => 'fixtureVoiceA1', 'model' => 'eleven_multilingual_v2']], 'marketing.production.elevenlabs_key' => 'fake-only', 'marketing.native.api_key' => 'fake-only']);
        if ($route === 'economy') config(['marketing.storyboard.video_models'=>['wan2.5-image-to-video'=>$role('wan2.5-image-to-video',20)+['credit_usd'=>0.000001,'access_verified_at'=>'fixture','source_has_audio'=>false,'source_audio_verified_at'=>'fixture','quality_rank'=>1]]]);
        AiConnection::findOrFail('openai')->update(['image_model' => 'gpt-image-2', 'text_model' => 'gpt-5.6-luna', 'models' => [['id' => 'gpt-image-2', 'image' => true], ['id' => 'gpt-5.6-luna', 'text' => true, 'vision' => true]]]);
        if (in_array($route,['auto','economy'],true)) config(['marketing.storyboard.audio.auto_voice'=>['id'=>'offline-voice','version'=>1,'verified_at'=>'fixture','languages'=>['English']]]);
        if ($route === 'native') config(['marketing.storyboard.audio.native'=>['model'=>MarketingClipSource::MODEL,'version'=>'fixture-1','verified_at'=>'fixture','languages'=>['English'],'exact_script'=>true,'lip_sync'=>true,'consistent_voice'=>true]]);
        $s = app(MarketingCreativeProfiles::class)->save($p->id, $p->owner_id, 1, ['presenter' => ['mode' => 'preset', 'id' => $person['id'], 'version' => 1], 'voice' => $route === 'preset' ? ['mode' => 'preset', 'id' => 'offline-voice', 'version' => 1] : ['mode'=>'auto']]);
        if($route==='auto'){
            $this->assertSame(1,$s['profile']['presenter']['avatar_binding']['avatar_version']);
            $lib->saveDraft($actor,$avatar->library_id,(string)Str::uuid(),['id'=>$avatar->id,'name'=>'New version','kind'=>'synthetic','look'=>'New wardrobe']);$lib->publish($actor,$avatar->id,(string)Str::uuid(),true);
        }
        $png = UploadedFile::fake()->image('fake-provider-frame.png', 864, 1536);
        $videoImage = UploadedFile::fake()->image('fake-video-frame.png', 720, 1280);
        $mp3 = tempnam(sys_get_temp_dir(), 'sb-e2e-').'.mp3';
        $video = tempnam(sys_get_temp_dir(), 'sb-e2e-').'.mp4';
        $frame = tempnam(sys_get_temp_dir(), 'sb-frame-').'.png';
        file_put_contents($frame, file_get_contents($videoImage->getRealPath()));
        $tools = app(MarketingMediaTools::class);
        $tools->run([(string) config('marketing.production.ffmpeg_binary'), '-v', 'error', '-f', 'lavfi', '-i', 'sine=frequency=880:duration=1', '-c:a', 'libmp3lame', '-y', $mp3], 30);
        $tools->run([(string) config('marketing.production.ffmpeg_binary'), '-v', 'error', '-loop', '1', '-i', $frame, '-t', '5', '-r', '24', '-c:v', 'libx264', '-pix_fmt', 'yuv420p', '-an', '-y', $video], 60);
        if ($route === 'native') {
            $voiced = $video.'.voiced.mp4';
            $tools->run([(string)config('marketing.production.ffmpeg_binary'),'-v','error','-i',$video,'-i',$mp3,'-c:v','copy','-c:a','aac','-af','apad','-t','5','-y',$voiced],30);
            copy($voiced,$video);unlink($voiced);
        }
        $checks = array_fill_keys(MarketingMaterialVisionClient::CHECKS, 'pass');
        $chars = mb_str_split($text);
        $align = ['characters' => $chars, 'character_start_times_seconds' => array_map(fn ($i) => $i * .02, array_keys($chars)), 'character_end_times_seconds' => array_map(fn ($i) => ($i + 1) * .02, array_keys($chars))];
        app()->instance(MarketingEvolinkMedia::class, new class extends MarketingEvolinkMedia
        {
            protected function addresses(string $host): array
            {
                return ['8.8.8.8'];
            }
        });
        Http::fake([
            'https://api.openai.com/v1/images/edits' => Http::response(['data' => [['b64_json' => base64_encode(file_get_contents($png->getRealPath()))]], 'usage' => ['input_tokens_details' => ['text_tokens' => 1, 'image_tokens' => 1], 'output_tokens' => 1]]),
            'https://api.openai.com/v1/responses' => Http::response(['status' => 'completed', 'id' => 'fake-qa', 'usage' => ['input_tokens' => 1, 'output_tokens' => 1], 'output' => [['content' => [['type' => 'output_text', 'text' => json_encode(['checks' => $checks, 'observations' => ['offline fixture'], 'correction' => '', 'summary' => 'Offline fixture'])]]]]]),
            'https://api.elevenlabs.io/v1/text-to-speech/*' => Http::response(['audio_base64' => base64_encode(file_get_contents($mp3)), 'alignment' => $align], 200, ['character-cost' => (string) count($chars)]),
            'https://files-api.evolink.ai/api/v1/files/upload/stream' => function () {
                $this->assertSame(0, DB::transactionLevel());

                return Http::response(['success' => true, 'data' => ['file_id' => 'fake-file', 'file_url' => 'https://files.evolink.ai/fake-frame.png', 'expires_at' => now()->addHour()->toIso8601String()]]);
            },
            'https://api.evolink.ai/v1/videos/generations' => function ($request) use ($route) {
                $this->assertSame(0, DB::transactionLevel());
                if ($route === 'economy') { $this->assertSame('wan2.5-image-to-video',$request['model']); $this->assertArrayNotHasKey('generate_audio',$request->data()); $this->assertFalse($request['prompt_extend']); } else $this->assertSame($route === 'native',$request['generate_audio']);
                if ($route === 'native') $this->assertStringContainsString('Approved.', $request['prompt']);
                $this->assertCount($route === 'economy' ? 1 : 2, $request['image_urls']);

                return Http::response(['id' => 'fake-video-task', 'usage' => []]);
            },
            'https://api.evolink.ai/v1/tasks/fake-video-task' => Http::sequence()->push(['id' => 'fake-video-task', 'status' => 'processing', 'results' => [], 'usage' => []])->push(['id' => 'fake-video-task', 'status' => 'completed', 'results' => ['https://files.evolink.ai/fake-video.mp4'], 'usage' => ['credits_consumed' => 7]]),
            'https://files.evolink.ai/fake-video.mp4' => Http::response(file_get_contents($video), 200, ['Content-Type' => 'video/mp4']),
        ]);
        $prepared = app(MarketingStoryboardDirector::class)->prepare($p->id, $p->owner_id, ['snapshot' => $s]);
        $task = $prepared['preparation']['tasks'][0];
        for ($frameStep = 0; $frameStep < 3; $frameStep++) {
            app(MarketingStoryboardPreparation::class)->process($task);
        }
        $ready = $w->snapshot($p->id, $p->owner_id);
        $this->assertTrue($ready['readiness']);
        $this->assertArrayNotHasKey('voice_asset', $ready['scenes'][0]);
        Http::assertSentCount($route === 'economy' ? 2 : 4);
        $this->assertSame(0, MarketingStoryboardProviderCall::where('role', 'voice')->count());
        app(MarketingStoryboardHandoff::class)->confirm($p->id, $p->owner_id, (string) Str::uuid(), $ready['story_version']);
        $plan = app(MarketingProductionPreparation::class)->prepareStoryboard($p->id, $p->owner_id, (string) Str::uuid(), $ready['story_version']);
        $runtime = app(MarketingStoryboardProduction::class);
        $this->assertSame($route === 'native' ? 'native' : 'independent', $plan->manifest['configuration']['audio']['route']);
        $this->assertSame($route === 'native' ? 0 : 1, $plan->manifest['budget']['voice_requests']);
        $grantCap = config('marketing.storyboard.live_grant.max_total_microusd');
        config(['marketing.storyboard.live_grant.max_total_microusd'=>1]);
        $blocked = $runtime->manifest($plan->manifest['handoff'], $plan->manifest['clips'], $p->id);
        $this->assertNotEmpty($blocked['blockers']);
        config(['marketing.storyboard.live_grant.max_total_microusd'=>$grantCap]);
        $price = config('marketing.storyboard.roles.video.credit_usd');
        config(['marketing.storyboard.roles.video.credit_usd'=>null]); $models=config('marketing.storyboard.video_models',[]);if($route==='economy')config(['marketing.storyboard.video_models'=>[]]);
        $blocked = $runtime->manifest($plan->manifest['handoff'], $plan->manifest['clips'], $p->id);
        $this->assertNotEmpty($blocked['blockers']);
        config(['marketing.storyboard.roles.video.credit_usd'=>$price,'marketing.storyboard.video_models'=>$models]);
        $run = $runtime->start($p->id, $p->owner_id, $ready['story_version'], $plan->id, true);
        $again = $runtime->start($p->id, $p->owner_id, $ready['story_version'], $plan->id, true);
        $this->assertSame($run->id, $again->id);

        // Stop after the durable submit, then let a plan expire while its known task finishes.
        $runtime->advance($run->id);
        $runtime->advance($run->id);
        $runtime->advance($run->id);
        $pending = MarketingVideoClip::firstOrFail();
        $this->assertSame('external_pending', $pending->status);
        $expiry = $plan->expires_at;
        $plan->update(['expires_at' => now()->subMinute()]);
        $this->assertTrue($runtime->advance($run->id));
        $this->assertSame('preparing', $run->fresh()->status);
        $this->assertSame('external_pending', MarketingStoryboardProviderCall::findOrFail($pending->result['video_call_id'])->status);
        $run->update(['status' => 'unknown']);
        try {
            $runtime->resume($p->id, $p->owner_id, $run->id);
            $this->fail('Expired plan must not adopt result');
        } catch (HttpExceptionInterface $e) {
            $this->assertSame(409, $e->getStatusCode());
        }
        $this->assertSame('completed', MarketingStoryboardProviderCall::findOrFail($pending->result['video_call_id'])->status);
        $this->assertSame(0, MarketingVideoFilm::count());
        // Simulate a crash after settlement but before the clip checkpoint: restore from the saved receipt.
        $plan->update(['expires_at' => $expiry]);
        $pending->refresh();
        $saved = $pending->result;
        unset($saved['provider_results']);
        $pending->update(['status' => 'unknown', 'result' => $saved]);
        $runtime->resume($p->id, $p->owner_id, $run->id);
        $this->assertSame('download_pending', $pending->fresh()->status);
        for ($i = 0; $i < 12; $i++) {
            if (! $runtime->advance($run->id)) {
                break;
            }
        }$run->refresh();
        $this->assertSame('completed', $run->status);
        $film = MarketingVideoFilm::findOrFail($run->result['film_id']);
        $this->assertSame('completed', $film->status);
        $this->assertSame(MarketingStoryboardProduction::FILM, $film->input['version']);
        $probe = $tools->probe(Storage::disk('local')->path($film->output_path));
        $this->assertCount(1, array_filter($probe['streams'], fn ($stream) => $stream['codec_type'] === 'audio'));
        $this->assertGreaterThanOrEqual(4.9, (float) $probe['format']['duration']);

        $completedClip = MarketingVideoClip::firstOrFail();
        $this->assertSame($route === 'native' ? null : 'fixtureVoiceA1', $completedClip->result['render']['voice_id']);
        $this->assertSame($route === 'native' ? 'native' : 'selected_voice',$completedClip->result['render']['voice_source']);
        $this->assertSame($route === 'native' ? 0 : 1,MarketingStoryboardProviderCall::where('role','voice')->count());
        $editProbe = $tools->probe(Storage::disk('local')->path($completedClip->raw_path));
        $this->assertTrue(MarketingClipSource::hasAudio($completedClip));
        $this->assertCount(1, array_filter($editProbe['streams'], fn ($stream) => $stream['codec_type'] === 'audio'));
        $this->assertSame($completedClip->raw_sha256, $film->input['scenes'][0]['raw_sha256']);
        $this->assertCount($route === 'economy' ? 5 : ($route === 'native' ? 7 : 8), MarketingStoryboardProviderCall::all());
        $runtime->advance($run->id);
        Http::assertSentCount($route === 'economy' ? 8 : ($route === 'native' ? 10 : 11));
        $this->assertCount(1, MarketingVideoFilm::all());
        $this->assertSame(0, MarketingAiRun::where('project_id', $p->id)->count());
        $this->get(route('marketing.workspace.show', ['project' => $p->id, 'screen' => 'finished', 'film' => $film->id]))->assertOk()->assertSee('AI 制作版');
        Http::assertSentCount($route === 'economy' ? 8 : ($route === 'native' ? 10 : 11));
        if ($route !== 'native') {
            $reuse = $completedClip->replicate(['id', 'provider_task_id']);
            $reuse->fingerprint = hash('sha256', (string)Str::uuid());
            $reuse->request_id = (string)Str::uuid();
            $reuse->save();
            $reused = app(\App\Services\Marketing\MarketingVideoCompositor::class)->storyboardClip($reuse);
            $this->assertNotEmpty($reused['sha256']);
            Http::assertSentCount($route==='economy'?8:11);
        }
        file_put_contents(base_path('work/storyboard-e2e-closure/latest-film.json'),json_encode(['film_path' => Storage::disk('local')->path($film->output_path), 'voice_source_path' => Storage::disk('local')->path($completedClip->raw_path), 'status' => $film->status, 'fake_http' => true, 'assertions_scope' => 'image QA selected voice video upload submit queries local composition; no real generation'],JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        @unlink($mp3);
        @unlink($video);
        @unlink($frame);

    }
}
