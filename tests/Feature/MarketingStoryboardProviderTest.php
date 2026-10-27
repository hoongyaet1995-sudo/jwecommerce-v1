<?php

namespace Tests\Feature;

use App\Jobs\ProcessMarketingStoryboardPreparation;
use App\Models\AiConnection;
use App\Models\MarketingAsset;
use App\Models\MarketingPreparationAuthorization;
use App\Models\MarketingRevision;
use App\Models\MarketingScene;
use App\Models\MarketingSceneRevision;
use App\Models\MarketingStoryboardProviderCall;
use App\Models\MarketingStoryboardTask;
use App\Models\User;
use App\Services\Marketing\MarketingClipSource;
use App\Services\Marketing\MarketingCreativeProfiles;
use App\Services\Marketing\MarketingMaterialVisionClient;
use App\Services\Marketing\MarketingMediaTools;
use App\Services\Marketing\MarketingPreparationBudget;
use App\Services\Marketing\MarketingPresenterLibrary;
use App\Services\Marketing\MarketingStoryboardChanges;
use App\Services\Marketing\MarketingStoryboardDirector;
use App\Services\Marketing\MarketingStoryboardHandoff;
use App\Services\Marketing\MarketingStoryboardPreparation;
use App\Services\Marketing\MarketingStoryboardProvider;
use App\Services\Marketing\MarketingStoryboardSemanticClient;
use App\Services\Marketing\MarketingStoryboardUpload;
use App\Services\Marketing\MarketingStoryboardVoice;
use App\Services\Marketing\MarketingStoryboardWorkspace;
use App\Services\Marketing\MarketingWorkflow;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\MarketingResearchFixtures;
use Tests\TestCase;

class MarketingStoryboardProviderTest extends TestCase
{
    use MarketingResearchFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh')->run();
    }

    private function sample(): array
    {
        $p = $this->researchProject();
        $r = MarketingRevision::create(['project_id' => $p->id, 'actor_id' => $p->owner_id, 'number' => 1, 'document' => ['scenes' => [['duration' => 5, 'title' => 'Product detail', 'visual' => 'Product detail', 'subtitle' => '', 'voiceover' => '']]], 'context' => []]);
        $p->update(['current_revision_id' => $r->id]);
        $s = app(MarketingStoryboardWorkspace::class)->initialize($p->id, $p->owner_id, (string) Str::uuid(), $p->version);
        $a = app(MarketingPreparationBudget::class)->authorize($p->id, $p->owner_id, (string) Str::uuid(), 1, [$s['scenes'][0]['revision_id']], 1000);

        return [$p, $s, $a];
    }

    private function grant($p): void
    {
        config(['marketing.storyboard.live_enabled' => true, 'marketing.storyboard.live_grant' => ['reference' => 'SIMULATED FAKE HTTP APPROVAL', 'project_id' => $p->id, 'owner_id' => $p->owner_id, 'expires_at' => now()->addHour()->toIso8601String(), 'roles' => ['semantic', 'image', 'qa', 'voice'], 'max_total_microusd' => 1000, 'max_calls' => 10], 'marketing.storyboard.roles.semantic' => ['model' => 'fixture-model', 'version' => '1', 'cap_microusd' => 100, 'pricing_verified_at' => now()->toIso8601String()]]);
    }

    public function test_gate_prevents_transport_before_new_approval(): void
    {
        [$p,$s,$a] = $this->sample();
        $this->expectException(HttpException::class);
        app(MarketingStoryboardProvider::class)->call('semantic', $p->id, $a['id'], ['revision_id' => $s['scenes'][0]['revision_id']], fn () => throw new \LogicException('must never send'));
    }

    public function test_formal_transport_receipt_cache_and_budget_are_durable_outside_http(): void
    {
        [$p,$s,$a] = $this->sample();
        $this->grant($p);
        Http::fake(['https://api.openai.com/*' => Http::response(['actual_microusd' => 7, 'answer' => 'safe'], 200)]);
        $transport = function () {
            $this->assertSame(0, DB::transactionLevel());
            $this->assertSame('requesting', MarketingStoryboardProviderCall::firstOrFail()->status);

            return Http::post('https://api.openai.com/v1/responses')->json();
        };
        $client = app(MarketingStoryboardProvider::class);
        $payload = ['revision_id' => $s['scenes'][0]['revision_id'], 'intent' => 'More direct'];
        $one = $client->call('semantic', $p->id, $a['id'], $payload, $transport);
        $two = $client->call('semantic', $p->id, $a['id'], $payload, $transport);
        $this->assertSame($one, $two);
        Http::assertSentCount(1);
        $this->assertSame(7, MarketingPreparationAuthorization::find($a['id'])->spent_microusd);
    }

    public function test_unknown_response_survives_and_does_not_retry(): void
    {
        [$p,$s,$a] = $this->sample();
        $this->grant($p);
        $calls = 0;
        $client = app(MarketingStoryboardProvider::class);
        $payload = ['revision_id' => $s['scenes'][0]['revision_id'], 'intent' => 'Unknown'];
        for ($i = 0; $i < 2; $i++) {
            try {
                $client->call('semantic', $p->id, $a['id'], $payload, function () use (&$calls) {
                    $calls++;

                    return ['answer' => 'possibly billed', 'actual_microusd' => null];
                });
                $this->fail('unknown must pause');
            } catch (HttpException $e) {
                $this->assertSame(409, $e->getStatusCode());
            }
        }try {
            $client->call('semantic', $p->id, $a['id'], ['revision_id' => $s['scenes'][0]['revision_id'], 'intent' => 'Different request after unknown'], function () use (&$calls) {
                $calls++;

                return ['actual_microusd' => 1];
            });
            $this->fail('grant must pause after unknown');
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
        }$this->assertSame(1, $calls);
        $this->assertSame('unknown', MarketingStoryboardProviderCall::firstOrFail()->status);
        $this->assertSame(100, MarketingPreparationAuthorization::find($a['id'])->unknown_microusd);
    }

    public function test_semantic_client_uses_real_responses_payload_with_fake_http_and_no_long_transaction(): void
    {
        [$p,$s,$a] = $this->sample();
        $this->grant($p);
        config(['marketing.storyboard.roles.semantic.input_usd_per_million' => 1, 'marketing.storyboard.roles.semantic.output_usd_per_million' => 1]);
        $c = AiConnection::findOrFail('openai');
        $c->update(['models' => [['id' => 'fixture-model', 'text' => true, 'vision' => true]]]);
        $draft = ['scenes' => [['seconds' => 5, 'message' => 'Detail', 'visual' => 'A creative scene', 'voiceover' => '', 'subtitle' => '', 'presenter_required' => false, 'fact_refs' => []]], 'missing_facts' => []];
        $draft['scenes'][0] += ['scene_design'=>array_merge(array_fill_keys(\App\Services\Marketing\MarketingStoryboardSceneDesign::FIELDS,'explicit none'),['product_reference_indexes'=>[]]),'director'=>['subject'=>'subject','framing'=>'framing','motion'=>'motion','lighting'=>'lighting','continuity'=>'continuity','start_state'=>'start','end_state'=>'end','beats'=>[['start'=>0,'end'=>5,'action'=>'act']]],'frames'=>['start_frame'=>['required'=>true,'instruction'=>'start','state'=>'start','person_presence'=>'absent'],'end_frame'=>['required'=>false,'instruction'=>'end','state'=>'end','person_presence'=>'absent'],'extra_image'=>['required'=>false,'instruction'=>'act','state'=>'act','person_presence'=>'absent']]];
        Http::fake(['https://api.openai.com/v1/responses' => Http::response(['status' => 'completed', 'id' => 'fake-response', 'usage' => ['input_tokens' => 1, 'output_tokens' => 1], 'output' => [['content' => [['type' => 'output_text', 'text' => json_encode($draft)]]]]])]);
        $out = app(MarketingStoryboardSemanticClient::class)->propose([], $s, ['project_id' => $p->id, 'authorization_id' => $a['id'], 'scene_id' => $s['scenes'][0]['scene_id'], 'operation' => 'edit', 'text' => 'More direct']);
        $this->assertSame('Detail', $out['scenes'][0]['message']);
        Http::assertSent(fn ($r) => $r['model'] === 'fixture-model' && $r['store'] === false && $r['text']['format']['type'] === 'json_schema');
        Http::assertSentCount(1);
    }

    public function test_changes_commit_claim_before_http_and_preserve_completed_semantic_when_story_changes(): void
    {
        [$p,$s,$a] = $this->sample();
        $this->grant($p);
        config(['marketing.storyboard.roles.semantic.input_usd_per_million' => 1, 'marketing.storyboard.roles.semantic.output_usd_per_million' => 1]);
        AiConnection::find('openai')->update(['models' => [['id' => 'fixture-model']]]);
        $draft = ['scenes' => [['seconds' => 5, 'message' => 'Directed detail', 'visual' => 'A new visual', 'voiceover' => '', 'subtitle' => '', 'presenter_required' => false, 'fact_refs' => []]], 'missing_facts' => []];
        $draft['scenes'][0] += ['scene_design'=>array_merge(array_fill_keys(\App\Services\Marketing\MarketingStoryboardSceneDesign::FIELDS,'explicit none'),['product_reference_indexes'=>[]]),'director'=>['subject'=>'subject','framing'=>'framing','motion'=>'motion','lighting'=>'lighting','continuity'=>'continuity','start_state'=>'start','end_state'=>'end','beats'=>[['start'=>0,'end'=>5,'action'=>'act']]],'frames'=>['start_frame'=>['required'=>true,'instruction'=>'start','state'=>'start','person_presence'=>'absent'],'end_frame'=>['required'=>false,'instruction'=>'end','state'=>'end','person_presence'=>'absent'],'extra_image'=>['required'=>false,'instruction'=>'act','state'=>'act','person_presence'=>'absent']]];
        Http::fake(['https://api.openai.com/v1/responses' => function () use ($draft) {
            $this->assertSame(0, DB::transactionLevel());

            return Http::response(['status' => 'completed', 'usage' => ['input_tokens' => 1, 'output_tokens' => 1], 'output' => [['content' => [['type' => 'output_text', 'text' => json_encode($draft)]]]]]);
        }]);
        $id = (string) Str::uuid();
        $cmd = ['type' => 'edit', 'mode' => 'ai', 'scene_id' => $s['scenes'][0]['scene_id'], 'intent' => 'More direct', 'authorization_id' => $a['id']];
        $service = app(MarketingStoryboardChanges::class);
        $one = $service->change($p->id, $p->owner_id, $id, 1, $cmd);
        $two = $service->change($p->id, $p->owner_id, $id, 1, $cmd);
        $this->assertSame($one['snapshot'], $two['snapshot']);
        $this->assertSame('Directed detail', $one['snapshot']['scenes'][0]['message']);
        Http::assertSentCount(1);
    }

    public function test_three_owned_presenters_bind_real_reference_and_do_not_leak_to_other_project(): void
    {
        [$p,$s,$a] = $this->sample();
        $lib = app(MarketingPresenterLibrary::class);
        $saved = [];
        for ($i = 0; $i < 3; $i++) {
            $asset = app(MarketingStoryboardUpload::class)->store($p->id, $p->owner_id, UploadedFile::fake()->image('person'.$i.'.png', 90 + $i, 160), true);
            $saved[] = $lib->register($p->id, $p->owner_id, (string) Str::uuid(), 'Offline template '.($i + 1), 'synthetic', $asset['asset_id'], true);
        }$this->assertCount(3, $lib->options($p->id, $p->owner_id));
        $otherOwner = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        $other = app(MarketingWorkflow::class)->create($otherOwner, ['source_type' => 'theme', 'theme' => 'Other unrelated story']);
        $this->assertCount(0, $lib->options($other->id, $other->owner_id));
        $out = app(MarketingCreativeProfiles::class)->save($p->id, $p->owner_id, 1, ['presenter' => ['mode' => 'preset', 'id' => $saved[1]['id'], 'version' => 1], 'voice' => ['mode' => 'none']]);
        $this->assertSame($saved[1]['reference']['sha256'], $out['profile']['presenter']['reference']['sha256']);
        Http::assertNothingSent();
    }

    public function test_formal_image_and_visual_qa_use_selected_identity_real_bytes_and_independent_claims(): void
    {
        [$p,$s,$a] = $this->sample();
        $asset = app(MarketingStoryboardUpload::class)->store($p->id, $p->owner_id, UploadedFile::fake()->image('selected-portrait.png', 360, 640), true);
        $person = app(MarketingPresenterLibrary::class)->register($p->id, $p->owner_id, (string) Str::uuid(), 'Selected authorized identity', 'synthetic', $asset['asset_id'], true);
        app(MarketingCreativeProfiles::class)->save($p->id, $p->owner_id, 1, ['presenter' => ['mode' => 'preset', 'id' => $person['id'], 'version' => 1], 'voice' => ['mode' => 'none']]);
        $next = app(MarketingStoryboardWorkspace::class)->apply($p->id, $p->owner_id, (string) Str::uuid(), 2, ['type' => 'edit', 'scene_id' => $s['scenes'][0]['scene_id'], 'content' => array_replace($s['scenes'][0], ['presenter_required' => true,'frames'=>array_replace_recursive($s['scenes'][0]['frames'],['start_frame'=>['person_presence'=>'visible']])])]);
        $a = app(MarketingPreparationBudget::class)->authorize($p->id, $p->owner_id, (string) Str::uuid(), 3, [$next['scenes'][0]['revision_id']], 1000);
        $this->grant($p);
        $c = AiConnection::findOrFail('openai');
        $c->update(['image_model' => 'gpt-image-2', 'text_model' => 'gpt-5.6-luna', 'models' => [['id' => 'gpt-image-2', 'image' => true], ['id' => 'gpt-5.6-luna', 'text' => true, 'vision' => true]]]);
        config(['marketing.storyboard.roles.image' => ['model' => 'gpt-image-2', 'version' => '1', 'cap_microusd' => 500, 'billing_mode'=>'standard', 'text_usd_per_million' => 5, 'image_usd_per_million' => 8, 'output_usd_per_million' => 30, 'pricing_verified_at' => now()->toIso8601String()], 'marketing.storyboard.roles.qa' => ['model' => 'gpt-5.6-luna', 'version' => '1', 'cap_microusd' => 100, 'input_usd_per_million' => 1, 'output_usd_per_million' => 1, 'pricing_verified_at' => now()->toIso8601String()]]);
        $png = UploadedFile::fake()->image('generated.png', 864, 1536);
        $checks = array_fill_keys(MarketingMaterialVisionClient::CHECKS, 'pass');
        Http::fake(['https://api.openai.com/v1/images/*' => Http::response(['data' => [['b64_json' => base64_encode(file_get_contents($png->getRealPath()))]], 'usage' => ['input_tokens_details' => ['text_tokens' => 1, 'image_tokens' => 1], 'output_tokens' => 1]]), 'https://api.openai.com/v1/responses' => Http::response(['status' => 'completed', 'id' => 'qa-fixture', 'usage' => ['input_tokens' => 1, 'output_tokens' => 1], 'output' => [['content' => [['type' => 'output_text', 'text' => json_encode(['checks' => $checks, 'observations' => ['offline pixels inspected'], 'correction' => 'not needed', 'summary' => 'Offline pass'])]]]]])]);
        $svc = app(MarketingStoryboardPreparation::class);
        $tasks = $svc->prepare($p->id, $a['id']);
        $svc->process($tasks['tasks'][0]);
        $out = app(MarketingStoryboardWorkspace::class)->snapshot($p->id, $p->owner_id);
        $this->assertTrue($out['readiness']);
        $this->assertSame('qa_passed', $out['scenes'][0]['readiness']);
        $this->assertCount(2, MarketingStoryboardProviderCall::all());
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/images/edits') && count($request['images']) === 1 && hash('sha256', base64_decode(explode(',', $request['images'][0]['image_url'], 2)[1])) === $asset['sha256'] && str_contains($request['prompt'], $person['id']));
        Http::assertSentCount(2);
        $svc->process($tasks['tasks'][0]);
        Http::assertSentCount(2);
    }

    public function test_selected_voice_id_full_text_and_valid_audio_are_cached_by_actual_signature(): void
    {
        [$p,$s,$a] = $this->sample();
        $this->grant($p);
        config(['marketing.production.elevenlabs_key' => 'fixture-only', 'marketing.storyboard.roles.voice' => ['model' => 'eleven_multilingual_v2', 'version' => '1', 'cap_microusd' => 100, 'pricing_verified_at' => now()->toIso8601String(), 'credit_usd' => 0.000001], 'marketing.storyboard.voices' => [['id' => 'voice-a', 'name' => 'Offline voice A', 'version' => 1, 'provider_voice_id' => 'fixtureVoiceA1', 'model' => 'eleven_multilingual_v2']]]);
        $out = app(MarketingCreativeProfiles::class)->save($p->id, $p->owner_id, 1, ['presenter' => ['mode' => 'none'], 'voice' => ['mode' => 'preset', 'id' => 'voice-a', 'version' => 1]]);
        $ref = app(MarketingStoryboardUpload::class)->store($p->id, $p->owner_id, UploadedFile::fake()->image('voice-frame.png', 90, 160), true);
        $text = 'Approved information.';
        $row = $out['scenes'][0];
        $out = app(MarketingStoryboardWorkspace::class)->apply($p->id, $p->owner_id, (string) Str::uuid(), 2, ['type' => 'edit', 'scene_id' => $row['scene_id'], 'content' => ['seconds' => 5, 'message' => 'Approved', 'visual' => 'Photo', 'voiceover' => $text, 'subtitle' => ''], 'asset_id' => $ref['asset_id']]);
        $a = app(MarketingPreparationBudget::class)->authorize($p->id, $p->owner_id, (string) Str::uuid(), 3, [$out['scenes'][0]['revision_id']], 1000);
        $path = tempnam(sys_get_temp_dir(), 'sb-voice-').'.mp3';
        app(MarketingMediaTools::class)->run([(string) config('marketing.production.ffmpeg_binary'), '-v', 'error', '-f', 'lavfi', '-i', 'sine=frequency=880:duration=1', '-c:a', 'libmp3lame', '-y', $path], 30);
        $chars = mb_str_split($text);
        $starts = [];
        $ends = [];
        foreach ($chars as $i => $ch) {
            $starts[] = $i * .02;
            $ends[] = ($i + 1) * .02;
        }Http::fake(['https://api.elevenlabs.io/v1/text-to-speech/*' => Http::response(['audio_base64' => base64_encode(file_get_contents($path)), 'alignment' => ['characters' => $chars, 'character_start_times_seconds' => $starts, 'character_end_times_seconds' => $ends]], 200, ['character-cost' => (string) count($chars)])]);
        $svc = app(MarketingStoryboardVoice::class);
        $preparation = app(MarketingStoryboardPreparation::class);
        $batch = $preparation->prepare($p->id, $a['id']);
        $preparation->process($batch['tasks'][0]);
        $this->assertTrue(MarketingStoryboardTask::findOrFail($batch['tasks'][0])->result['image_reused']);
        Http::assertNothingSent();
        config(['marketing.native.api_key'=>'fixture-only','marketing.storyboard.live_grant.roles'=>['image','qa','voice','upload','video'],'marketing.storyboard.roles.upload'=>['model'=>'evolink-files','version'=>'1','cap_microusd'=>10,'fee_microusd'=>0,'pricing_verified_at'=>now()->toIso8601String()],'marketing.storyboard.roles.video'=>['model'=>'seedance-1.5-pro','version'=>'1','cap_microusd'=>100,'credit_usd'=>0.000001,'pricing_verified_at'=>now()->toIso8601String(),'access_verified_at'=>now()->toIso8601String()]]);
        app(MarketingStoryboardHandoff::class)->confirm($p->id,$p->owner_id,(string)Str::uuid(),3);$plan=app(\App\Services\Marketing\MarketingProductionPreparation::class)->prepareStoryboard($p->id,$p->owner_id,(string)Str::uuid(),3);$run=app(\App\Services\Marketing\MarketingStoryboardProduction::class)->start($p->id,$p->owner_id,3,$plan->id,true);$a=['id'=>$run->result['authorization_id']];
        $one = $svc->obtain($p->id, $p->owner_id, $out['scenes'][0]['scene_id'], $a['id'],$run->id);
        $two = $svc->obtain($p->id, $p->owner_id, $out['scenes'][0]['scene_id'], $a['id'],$run->id);
        $this->assertSame($one, $two);
        $this->assertGreaterThan(0, $one['duration_seconds']);
        $this->assertSame('fixtureVoiceA1', $one['voice_id']);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'fixtureVoiceA1/with-timestamps') && $r['text'] === $text && $r['model_id'] === 'eleven_multilingual_v2');
        Http::assertSentCount(1);
        $handoff = app(MarketingStoryboardHandoff::class)->build($p->id, $p->owner_id, $out['story_id']);
        $clips = app(MarketingClipSource::class)->storyboard($handoff);
        $this->assertFalse($clips[0]['generate_audio']);
        $this->assertNull($clips[0]['voice_asset']); // Step3 handoff never depends on finished audio.
        $video = tempnam(sys_get_temp_dir(), 'sb-video-').'.mp4';
        $muxed = tempnam(sys_get_temp_dir(), 'sb-mux-').'.mp4';
        $tools = app(MarketingMediaTools::class);
        $tools->run([(string) config('marketing.production.ffmpeg_binary'), '-v', 'error', '-loop', '1', '-i', Storage::disk('local')->path(MarketingAsset::findOrFail($ref['asset_id'])->path), '-t', '2', '-c:v', 'libx264', '-pix_fmt', 'yuv420p', '-an', '-y', $video], 30);
        $svc->mux($p->id, $p->owner_id, $video, $one, $muxed);
        $probe = $tools->probe($muxed);
        $this->assertCount(1, array_filter($probe['streams'], fn ($s) => $s['codec_type'] === 'audio'));
        $this->assertGreaterThanOrEqual(2, (float) $probe['format']['duration']); // Never inherit an old voice receipt through persisted revision content.
        $revision = MarketingSceneRevision::findOrFail($out['scenes'][0]['revision_id']);
        $c = $revision->content;
        $c['voice_asset'] = $one;
        $copy = MarketingSceneRevision::create(['scene_id' => $revision->scene_id, 'parent_id' => $revision->id, 'signature' => $revision->signature, 'content' => $c]);
        MarketingScene::findOrFail($revision->scene_id)->update(['current_revision_id' => $copy->id]);
        $voiceB = ['id' => 'voice-b', 'name' => 'Offline voice B', 'version' => 1, 'provider_voice_id' => 'fixtureVoiceB2', 'model' => 'eleven_multilingual_v2'];
        config(['marketing.storyboard.voices' => array_merge(config('marketing.storyboard.voices'), [$voiceB])]);
        $changed = app(MarketingCreativeProfiles::class)->save($p->id, $p->owner_id, 3, ['presenter' => ['mode' => 'none'], 'voice' => ['mode' => 'preset', 'id' => 'voice-b', 'version' => 1]]);
        $this->assertArrayNotHasKey('voice_asset', $changed['scenes'][0]);
        $this->assertTrue($changed['readiness']);
        $this->assertSame($ref['asset_id'], $changed['scenes'][0]['asset']['asset_id']);
        try {
            $svc->mux($p->id, $p->owner_id, $video, array_replace($one, ['duration_seconds' => 3]), $muxed);
            $this->fail('must not trim speech');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
        @unlink($video);
        @unlink($muxed);
        @unlink($path);
    }

    public function test_http_presenter_enrollment_and_scoped_options_never_send_provider_requests(): void
    {
        [$p,$s,$a] = $this->sample();
        $owner = User::findOrFail($p->owner_id);
        $this->actingAs($owner);
        $asset = app(MarketingStoryboardUpload::class)->store($p->id, $p->owner_id, UploadedFile::fake()->image('owned-person.png', 90, 160), true);
        $url = '/admin/marketing/workspace/'.$p->id.'/storyboard';
        $body = ['request_id' => (string) Str::uuid(), 'version' => 1, 'name' => 'Authorized offline template', 'kind' => 'synthetic', 'asset_id' => $asset['asset_id'], 'consent' => true];
        $one = $this->postJson($url.'/presenters', $body)->assertOk()->json();
        $this->postJson($url.'/presenters', $body)->assertOk()->assertJsonPath('presenter.id', $one['presenter']['id']);
        $this->getJson($url)->assertOk()->assertJsonCount(1, 'options.presenters')->assertJsonPath('generation_enabled', false);
        Http::assertNothingSent();
    }

    public function test_initializer_handles_empty_title_and_subtitle_without_inventing_facts(): void
    {
        $p = $this->researchProject();
        $r = MarketingRevision::create(['project_id' => $p->id, 'actor_id' => $p->owner_id, 'number' => 1, 'document' => ['scenes' => [['duration' => 5, 'title' => '', 'visual' => 'Observed photo', 'subtitle' => '', 'voiceover' => '']]], 'context' => []]);
        $p->update(['current_revision_id' => $r->id]);
        $out = app(MarketingStoryboardWorkspace::class)->initialize($p->id, $p->owner_id, (string) Str::uuid(), $p->version);
        $this->assertSame('分镜信息', $out['scenes'][0]['message']);
        $this->assertSame('', $out['scenes'][0]['voiceover']);
        Http::assertNothingSent();
    }

    public function test_director_prepares_only_affected_scenes_and_duplicate_employee_request_sends_once(): void
    {
        [$p,$s,$a] = $this->sample();
        $this->grant($p);
        foreach (['image', 'qa'] as $role) {
            config(['marketing.storyboard.roles.'.$role => config('marketing.storyboard.roles.semantic')]);
        }config(['marketing.storyboard.roles.semantic.input_usd_per_million' => 1, 'marketing.storyboard.roles.semantic.output_usd_per_million' => 1]);
        AiConnection::find('openai')->update(['models' => [['id' => 'fixture-model']]]);
        Queue::fake();
        $draft = ['scenes' => [['seconds' => 5, 'message' => 'Directed detail', 'visual' => 'New fictional visual', 'voiceover' => '', 'subtitle' => '', 'presenter_required' => false, 'fact_refs' => []]], 'missing_facts' => []];
        $draft['scenes'][0] += ['scene_design'=>array_merge(array_fill_keys(\App\Services\Marketing\MarketingStoryboardSceneDesign::FIELDS,'explicit none'),['product_reference_indexes'=>[]]),'director'=>['subject'=>'subject','framing'=>'framing','motion'=>'motion','lighting'=>'lighting','continuity'=>'continuity','start_state'=>'start','end_state'=>'end','beats'=>[['start'=>0,'end'=>5,'action'=>'act']]],'frames'=>['start_frame'=>['required'=>true,'instruction'=>'start','state'=>'start','person_presence'=>'absent'],'end_frame'=>['required'=>false,'instruction'=>'end','state'=>'end','person_presence'=>'absent'],'extra_image'=>['required'=>false,'instruction'=>'act','state'=>'act','person_presence'=>'absent']]];
        Http::fake(['https://api.openai.com/v1/responses' => Http::response(['status' => 'completed', 'usage' => ['input_tokens' => 1, 'output_tokens' => 1], 'output' => [['content' => [['type' => 'output_text', 'text' => json_encode($draft)]]]]])]);
        $id = (string) Str::uuid();
        $cmd = ['type' => 'edit', 'mode' => 'ai', 'scene_id' => $s['scenes'][0]['scene_id'], 'intent' => 'Reframe'];
        $director = app(MarketingStoryboardDirector::class);
        $one = $director->change($p->id, $p->owner_id, $id, 1, $cmd);
        $two = $director->change($p->id, $p->owner_id, $id, 1, $cmd);
        $this->assertSame($one['snapshot'], $two['snapshot']);
        $this->assertCount(1,$one['preparation']['tasks']);
        Queue::assertPushed(ProcessMarketingStoryboardPreparation::class,1);
        Http::assertSentCount(1);
    }

    public function test_save_endpoint_persists_design_and_updates_three_frames_with_fake_responses(): void
    {
        [$p,$s,$a]=$this->sample();$this->grant($p);Queue::fake();
        config(['marketing.storyboard.max_repairs'=>0]);
        $p->update(['snapshot'=>array_replace($p->snapshot,['images'=>[['path'=>'/images/themes/logistics/containers.png']]])]);
        // Snapshot changed deliberately before initialization: rebind its source fingerprint, not test production data.
        $w=\App\Models\MarketingStoryboardWorkspace::where('project_id',$p->id)->firstOrFail();$profile=$w->profile;
        $profile['source_fingerprint']=app(MarketingStoryboardWorkspace::class)->sourceFingerprint($p);$w->update(['profile'=>$profile]);
        AiConnection::findOrFail('openai')->update(['image_model'=>'gpt-image-2','text_model'=>'gpt-5.6-luna','models'=>[['id'=>'gpt-image-2','image'=>true],['id'=>'gpt-5.6-luna','text'=>true,'vision'=>true]]]);
        foreach(['semantic','qa'] as $role)config(['marketing.storyboard.roles.'.$role=>['model'=>'gpt-5.6-luna','version'=>'fake','cap_microusd'=>100,'input_usd_per_million'=>1,'output_usd_per_million'=>1,'pricing_verified_at'=>now()->toIso8601String()]]);
        config(['marketing.storyboard.roles.image'=>['model'=>'gpt-image-2','version'=>'fake','cap_microusd'=>100,'billing_mode'=>'standard','text_usd_per_million'=>5,'image_usd_per_million'=>8,'output_usd_per_million'=>30,'pricing_verified_at'=>now()->toIso8601String()]]);
        $director=['subject'=>'container','framing'=>'middle shot','motion'=>'one gesture','lighting'=>'soft','continuity'=>'same warehouse','start_state'=>'hands lowered','end_state'=>'gesture returned','beats'=>[['start'=>0,'end'=>5,'action'=>'point once']]];
        $design=array_merge(array_fill_keys(\App\Services\Marketing\MarketingStoryboardSceneDesign::FIELDS,'warehouse scene'),['product_reference_indexes'=>[0]]);
        $frames=[];foreach(['start_frame'=>'hands lowered','end_frame'=>'gesture returned','extra_image'=>'point once'] as $role=>$state)$frames[$role]=['required'=>true,'person_presence'=>'absent','state'=>$state,'instruction'=>'warehouse and blue container: '.$state];
        $draft=['scenes'=>[['seconds'=>5,'message'=>'Container form','visual'=>'Container in warehouse','voiceover'=>'','subtitle'=>'','presenter_required'=>false,'fact_refs'=>[],'director'=>$director,'scene_design'=>$design,'frames'=>$frames]],'missing_facts'=>[]];
        $png=UploadedFile::fake()->image('generated.png',864,1536);$n=0;$imageRefs=[];
        Http::preventStrayRequests();Http::fake(function($request)use($draft,$png,&$n,&$imageRefs){
            $n++;if(str_contains($request->url(),'/images/')){
                $imageRefs[]=count($request['images']);$this->assertStringContainsString('Persisted scene design',$request['prompt']);$this->assertStringContainsString('deliberate flight',$request['prompt']);
                return Http::response(['data'=>[['b64_json'=>base64_encode(file_get_contents($png->getRealPath()))]],'usage'=>['input_tokens_details'=>['text_tokens'=>1,'image_tokens'=>1],'output_tokens'=>1]]);
            }
            $this->assertStringContainsString($n===1?'Automatically complete scene_design':'WITHIN-SCENE',$request['input'][0]['content']);$this->assertStringContainsString($n===1?'impossible flight':'NOT whether the event could happen in reality',$request['input'][0]['content']);
            $answer=$n===1?$draft:['checks'=>array_fill_keys(MarketingMaterialVisionClient::CHECKS,'pass'),'observations'=>['FAKE only'],'correction'=>'','summary'=>'pass'];
            return Http::response(['status'=>'completed','usage'=>['input_tokens'=>1,'output_tokens'=>1],'output'=>[['content'=>[['type'=>'output_text','text'=>json_encode($answer)]]]]]);
        });
        $payload=['request_id'=>(string)Str::uuid(),'version'=>1,'command'=>['type'=>'edit','mode'=>'ai','scene_id'=>$s['scenes'][0]['scene_id'],'intent'=>'Complete scene design']];
        $url=route('marketing.workspace.storyboard.write',['project'=>$p->id,'action'=>'changes']);
        $reply=$this->actingAs(User::findOrFail($p->owner_id))->postJson($url,$payload)->assertOk();
        $task=$reply->json('preparation.tasks.0');$this->assertNotEmpty($task);
        for($i=0;$i<3;$i++)app(MarketingStoryboardPreparation::class)->process($task);
        $out=app(MarketingStoryboardWorkspace::class)->snapshot($p->id,$p->owner_id);
        $this->assertTrue($out['readiness']);$this->assertSame('warehouse scene',$out['scenes'][0]['scene_design']['environment']);
        $this->assertSame([1,2,3],$imageRefs);$this->assertSame(7,$n);
        $qaCalls=MarketingStoryboardProviderCall::where('role','qa')->get();$extra=$qaCalls->first(fn($call)=>$call->input['frame_role']==='extra_image');$this->assertArrayHasKey('scene_end',$extra->input['design']['reference_roles']);$this->assertSame($out['scenes'][0]['frames']['end_frame']['asset']['sha256'],$extra->input['design']['reference_roles']['scene_end']['sha256']);
        $this->postJson($url,$payload)->assertOk();Http::assertSentCount(7);
        $this->assertCount(7,MarketingStoryboardProviderCall::all());
        $this->assertSame('keyframe-scope-v2',MarketingStoryboardProviderCall::where('role','qa')->firstOrFail()->input['connection']['review_version']);
        $path=public_path('images/themes/logistics/containers.png');$this->assertSame(hash_file('sha256',$path),$out['scenes'][0]['scene_design']['product_reference_hashes'][0]);
        $manual=app(MarketingStoryboardChanges::class)->change($p->id,$p->owner_id,(string)Str::uuid(),2,['mode'=>'manual','type'=>'edit','scene_id'=>$out['scenes'][0]['scene_id'],'content'=>array_intersect_key($out['scenes'][0],array_flip(['seconds','message','visual','voiceover','subtitle']))]);
        $this->assertSame($out['scenes'][0]['scene_design'],$manual['snapshot']['scenes'][0]['scene_design']);
        $this->assertSame('hands lowered',$manual['snapshot']['scenes'][0]['frames']['start_frame']['state']);
        $this->assertTrue($manual['snapshot']['readiness']);Http::assertSentCount(7);
    }


    public function test_presenter_options_refresh_preview_host_without_invalidating_same_identity(): void
    {
        [$p,$s,$a]=$this->sample();
        $ref=app(MarketingStoryboardUpload::class)->store($p->id,$p->owner_id,UploadedFile::fake()->image('portrait.png',360,640),true);
        $person=app(MarketingPresenterLibrary::class)->register($p->id,$p->owner_id,(string)Str::uuid(),'Selected woman','synthetic',$ref['asset_id'],true);
        $profile=['presenter'=>['mode'=>'preset','id'=>$person['id'],'version'=>1],'voice'=>['mode'=>'auto']];
        $saved=app(MarketingCreativeProfiles::class)->save($p->id,$p->owner_id,1,$profile);
        $row=\App\Models\MarketingStoryboardPresenter::findOrFail($person['id']);$old=$row->reference;$old['local_preview']='http://127.0.0.1:8514/expired-host';$row->update(['reference'=>$old]);
        $w=\App\Models\MarketingStoryboardWorkspace::where('project_id',$p->id)->firstOrFail();$pr=$w->profile;$pr['presenter']['reference']['local_preview']=$old['local_preview'];$w->update(['profile'=>$pr]);
        $option=app(MarketingPresenterLibrary::class)->resolve($p->id,$p->owner_id,$person['id'],1);
        $this->assertSame(route('marketing.asset',[$p->id,$ref['asset_id']]),$option['preview_url']);
        $this->assertSame($option['preview_url'],$option['reference']['local_preview']);
        $again=app(MarketingCreativeProfiles::class)->save($p->id,$p->owner_id,2,$profile);
        $this->assertSame(2,$again['story_version']);
        $this->actingAs(User::findOrFail($p->owner_id))->get($option['preview_url'])->assertOk();Http::assertNothingSent();
    }


    public function test_blocked_candidate_survives_unchanged_manual_save_but_not_changed_visual(): void
    {
        [$p,$s,$a]=$this->sample();$w=app(MarketingStoryboardWorkspace::class);$content=$s['scenes'][0];
        $content['scene_design']=array_merge(array_fill_keys(\App\Services\Marketing\MarketingStoryboardSceneDesign::FIELDS,'explicit none'),['product_reference_indexes'=>[],'product_reference_hashes'=>[]]);
        $content['frames']['start_frame']['state']=$content['director']['start_state'];
        $out=$w->apply($p->id,$p->owner_id,(string)Str::uuid(),1,['type'=>'edit','scene_id'=>$content['scene_id'],'content'=>$content]);
        $scene=$out['scenes'][0];$ref=app(MarketingStoryboardUpload::class)->store($p->id,$p->owner_id,UploadedFile::fake()->image('candidate.png',90,160),true);
        $auth=app(MarketingPreparationBudget::class)->authorize($p->id,$p->owner_id,(string)Str::uuid(),2,[$scene['revision_id']],100);
        MarketingStoryboardTask::create(['project_id'=>$p->id,'authorization_id'=>$auth['id'],'revision_id'=>$scene['revision_id'],'signature'=>$scene['image_signature'],'status'=>'blocked','result'=>['frames'=>['start_frame'=>array_replace($scene['frames']['start_frame'],['asset'=>$ref,'readiness'=>'pending','qa'=>['status'=>'uncertain']])]]]);
        $basic=array_intersect_key($scene,array_flip(['seconds','message','visual','voiceover','subtitle']));
        $same=$w->apply($p->id,$p->owner_id,(string)Str::uuid(),2,['type'=>'edit','scene_id'=>$scene['scene_id'],'content'=>$basic]);
        $this->assertSame($ref['sha256'],$same['scenes'][0]['frames']['start_frame']['candidate_asset']['sha256']);
        $this->assertNull($same['scenes'][0]['asset']);$this->assertFalse($same['readiness']);
        $basic['visual']='Different environment';$changed=$w->apply($p->id,$p->owner_id,(string)Str::uuid(),3,['type'=>'edit','scene_id'=>$scene['scene_id'],'content'=>$basic]);
        $this->assertArrayNotHasKey('candidate_asset',$changed['scenes'][0]['frames']['start_frame']);Http::assertNothingSent();
    }


    public function test_manual_local_testing_removes_aggregate_locks_but_keeps_unknown_cost_stop_and_scope(): void
    {
        [$p,$s,$unused]=$this->sample();
        $c=AiConnection::findOrFail('openai');$c->update(['image_model'=>'gpt-image-2','text_model'=>'gpt-5.6-luna']);
        $path=tempnam(sys_get_temp_dir(),'manual-storyboard-');file_put_contents($path,json_encode(['enabled'=>true,'project_id'=>$p->id,'owner_id'=>$p->owner_id,'connection_version'=>(int)$c->version]));
        config(['marketing.storyboard.manual_testing_file'=>$path]);app()->detectEnvironment(fn()=>'local');
        try{
            $provider=app(MarketingStoryboardProvider::class);$this->assertTrue($provider->allowed('semantic',$p->id));$this->assertFalse($provider->allowed('video',$p->id));
            $a=app(MarketingPreparationBudget::class)->authorize($p->id,$p->owner_id,(string)Str::uuid(),1,[$s['scenes'][0]['revision_id']],100000000);
            $task=MarketingStoryboardTask::create(['project_id'=>$p->id,'authorization_id'=>$a['id'],'revision_id'=>$s['scenes'][0]['revision_id'],'signature'=>$s['scenes'][0]['image_signature'],'status'=>'queued','result'=>[]]);
            $this->assertSame('marketing-storyboard-manual',(new ProcessMarketingStoryboardPreparation($task->id))->queue);
            $this->assertFalse($provider->allowed('semantic',(string)Str::uuid()));
            $noBatch=app(MarketingStoryboardDirector::class)->prepare($p->id,$p->owner_id,['snapshot'=>$s]);$this->assertSame('manual_save_required',$noBatch['preparation']['blockers'][0]['code']);
            $calls=0;
            for($i=0;$i<3;$i++)$provider->call('semantic',$p->id,$a['id'],['revision_id'=>$s['scenes'][0]['revision_id'],'nonce'=>$i],function()use(&$calls){$calls++;return ['actual_microusd'=>1,'answer'=>'fake'];});
            $this->assertSame(3,$calls); // More than nominal max_calls=1: no cumulative employee approval lock.
            $provider->call('semantic',$p->id,$a['id'],['revision_id'=>$s['scenes'][0]['revision_id'],'nonce'=>2],fn()=>throw new \LogicException('duplicate sent'));
            try{$provider->call('semantic',$p->id,$a['id'],['revision_id'=>$s['scenes'][0]['revision_id'],'nonce'=>3],function()use(&$calls){$calls++;return ['actual_microusd'=>null];});$this->fail('unknown cost must stop');}catch(HttpException $e){$this->assertSame(409,$e->getStatusCode());}
            MarketingStoryboardProviderCall::where('project_id',$p->id)->where('status','unknown')->update(['grant_reference'=>'previous-pilot-reference']);
            try{$provider->call('semantic',$p->id,$a['id'],['revision_id'=>$s['scenes'][0]['revision_id'],'nonce'=>4],fn()=>throw new \LogicException('must not send'));$this->fail('unknown receipt must freeze next');}catch(HttpException $e){$this->assertSame(409,$e->getStatusCode());}
            $this->assertSame(4,$calls);
            $c->increment('version');$this->assertFalse($provider->allowed('semantic',$p->id),'Connection drift must close previously active grant');
            app()->detectEnvironment(fn()=>'production');$this->assertFalse($provider->allowed('semantic',$p->id));Http::assertNothingSent();
        }finally{app()->detectEnvironment(fn()=>'testing');unlink($path);}
    }

}
