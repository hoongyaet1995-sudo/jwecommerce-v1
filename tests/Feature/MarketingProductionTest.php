<?php

namespace Tests\Feature;

use App\Models\MarketingAsset;
use App\Models\MarketingProduction;
use App\Models\MarketingProductionPlan;
use App\Models\MarketingProductionBudget;
use App\Models\MarketingVoiceAsset;
use App\Models\MarketingRevision;
use App\Models\User;
use App\Services\Marketing\MarketingWorkflow;
use App\Services\Marketing\MarketingVoiceLibrary;
use App\Services\Marketing\MarketingMediaTools;
use App\Services\Marketing\MarketingProductionFailure;
use App\Services\Marketing\MarketingProductionProcessor;
use App\Services\Marketing\MarketingVideoRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class MarketingProductionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Queue::fake();
        Storage::fake('local');
        config(['marketing.production.audio_cap_usd' => '1.00']);
    }

    private function plan(bool $approve = true, bool $narration = true): MarketingProductionPlan
    {
        $u = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        $this->actingAs($u);
        $p = app(MarketingWorkflow::class)->create($u, ['source_type' => 'theme', 'theme' => 'Our actual product introduction']);
        $path = UploadedFile::fake()->image('product.png', 100, 100)->store('marketing/'.$p->id, 'local');
        $a = MarketingAsset::create(['project_id' => $p->id, 'name' => 'Actual product', 'path' => $path, 'mime' => 'image/png', 'size' => Storage::disk('local')->size($path), 'authorized' => true]);
        $brief = array_replace($p->brief, ['source_confirmed' => true, 'facts_confirmed' => true, 'goal_confirmed' => true, 'audience_confirmed' => true, 'language' => 'English', 'market' => 'Malaysia', 'platforms' => ['Instagram']]);
        $concept = ['title' => 'Our product', 'ratio' => '9:16'];
        $p->update(['brief' => $brief, 'concepts' => [$concept], 'selected_concept' => 0]);
        $r = MarketingRevision::create(['project_id' => $p->id, 'actor_id' => $u->id, 'number' => 1, 'document' => ['summary' => 'A factual introduction', 'duration' => 5, 'cta' => 'Learn more', 'changes' => [], 'gaps' => [], 'scenes' => [['visual' => 'Our real image', 'voiceover' => $narration ? 'Our product.' : '', 'subtitle' => 'Our product', 'duration' => 5, 'material' => 'Real product photo', 'asset_ids' => [$a->id]]]], 'context' => ['brief' => $brief, 'snapshot' => $p->snapshot, 'concept' => $concept], 'confirmed_by' => $u->id, 'confirmed_at' => now()]);
        $p->update(['current_revision_id' => $r->id, 'confirmed_revision_id' => $r->id, 'status' => 'prepared', 'stage' => 'confirmed']);
        $this->post('/admin/marketing/projects/'.$p->id.'/production', ['version' => $p->version, 'revision_id' => $r->id, 'request_id' => (string) Str::uuid()])->assertRedirect();
        $plan = MarketingProductionPlan::where('project_id', $p->id)->firstOrFail();
        if ($approve) {
            $this->post('/admin/marketing/projects/'.$p->id.'/production/'.$plan->id.'/confirm', ['version' => $p->version, 'budget_confirmation' => 1])->assertRedirect();
        }

        return $plan->fresh();
    }

    private function start(MarketingProductionPlan $plan)
    {
        return $this->post('/admin/marketing/projects/'.$plan->project_id.'/production/'.$plan->id.'/start', ['version' => $plan->project_version]);
    }

    public function test_only_separately_approved_current_plan_can_start(): void
    {
        $plan = $this->plan(false);
        $this->start($plan)->assertStatus(422);
        $this->assertDatabaseCount('marketing_productions', 0);
        Queue::assertNothingPushed();
        Http::assertNothingSent();
    }

    public function test_duplicate_start_creates_one_durable_task_on_the_isolated_queue(): void
    {
        $plan = $this->plan();
        $this->start($plan)->assertRedirect();
        $this->start($plan)->assertRedirect();
        $this->assertDatabaseCount('marketing_productions', 1);
        $task = MarketingProduction::firstOrFail();
        $this->assertSame('queued', $task->status);
        $this->assertSame($plan->id, $task->plan_id);
        $this->assertSame(0, $task->new_reserved_microusd);
        Queue::assertPushedOn('marketing-production', \App\Jobs\ProcessMarketingProduction::class);
        Queue::assertPushed(\App\Jobs\ProcessMarketingProduction::class, 1);
        Http::assertNothingSent();
    }

    public function test_start_rechecks_expiry_and_modified_materials(): void
    {
        $plan = $this->plan();
        $this->travel(25)->hours();
        $this->start($plan)->assertStatus(409);
        $this->travelBack();
        $a = MarketingAsset::where('project_id', $plan->project_id)->firstOrFail();
        Storage::disk('local')->delete($a->path);
        $this->start($plan)->assertStatus(409);
        Queue::assertNothingPushed();
    }

    public function test_foreign_users_cannot_start_or_preview_a_production(): void
    {
        $plan = $this->plan();
        $this->start($plan)->assertRedirect();
        $task = MarketingProduction::firstOrFail();
        $url = '/admin/marketing/projects/'.$plan->project_id.'/productions/'.$task->id.'/preview';
        $this->get($url)->assertNotFound();
        $this->actingAs(User::factory()->create(['is_admin' => true, 'is_super_admin' => true]));
        $this->start($plan)->assertNotFound();
        $this->get($url)->assertNotFound();
        $this->assertDatabaseCount('marketing_productions', 1);
    }

    private function voiceTask(): array
    {
        $plan = $this->plan();
        $this->start($plan)->assertRedirect();
        $task = MarketingProduction::where('plan_id', $plan->id)->firstOrFail();
        $task->update(['status' => 'preparing']);
        config(['marketing.production.live_audio_enabled' => true, 'marketing.production.elevenlabs_key' => 'local-test-key', 'marketing.production.test_budget' => 'test-phase', 'marketing.production.live_audio_project' => $plan->project_id]);
        MarketingProductionBudget::firstOrCreate(['id' => 'test-phase'], ['ceiling_microusd' => 5000000, 'reserved_microusd' => 1993000, 'note' => 'Prior real probe allowance held']);
        $this->mock(MarketingMediaTools::class)->shouldReceive('audioDuration')->andReturn(1.0);

        return [$task, $plan];
    }

    private function speechResponse(string $text): array
    {
        $chars = mb_str_split($text);

        return ['audio_base64' => base64_encode('ID3-test-audio-bytes'), 'alignment' => ['characters' => $chars, 'character_start_times_seconds' => array_map(fn ($i) => $i * 0.03, array_keys($chars)), 'character_end_times_seconds' => array_map(fn ($i) => ($i + 1) * 0.03, array_keys($chars))]];
    }

    public function test_timestamped_voice_is_saved_and_exact_project_cache_prevents_another_paid_post(): void
    {
        [$task, $plan] = $this->voiceTask();
        Http::fake(['api.elevenlabs.io/*' => Http::response($this->speechResponse($plan->manifest['audio']['text']), 200, ['character-cost' => '2.5'])]);
        $library = app(MarketingVoiceLibrary::class);
        $voice = $library->obtain($task, $plan);
        $again = $library->obtain($task->fresh(), $plan);
        $this->assertSame($voice->id, $again->id);
        $this->assertSame('ready', $voice->status);
        $this->assertNull($voice->usage['usd_cost']);
        $this->assertSame(2.5, $voice->usage['character_cost']);
        $this->assertSame(2993000, MarketingProductionBudget::findOrFail('test-phase')->reserved_microusd);
        Storage::disk('local')->assertExists($voice->audio_path);
        Http::assertSentCount(1);
        Http::assertSent(fn ($r) => $r->hasHeader('xi-api-key', 'local-test-key') && $r['text'] === $plan->manifest['audio']['text'] && $r['model_id'] === 'eleven_flash_v2_5');
    }

    public function test_unknown_provider_outcome_is_held_and_never_automatically_resubmitted(): void
    {
        [$task, $plan] = $this->voiceTask();
        Http::fake(['api.elevenlabs.io/*' => Http::response([], 503)]);
        for ($i = 0; $i < 2; $i++) {
            try {
                app(MarketingVoiceLibrary::class)->obtain($task->fresh(), $plan);
                $this->fail('Unknown provider result must block further generation.');
            } catch (MarketingProductionFailure $e) {
                $this->assertSame('unknown', $e->state);
            }
        }
        $this->assertSame('unknown', MarketingVoiceAsset::firstOrFail()->status);
        $this->assertSame(2993000, MarketingProductionBudget::findOrFail('test-phase')->reserved_microusd);
        Http::assertSentCount(1);
    }

    public function test_test_budget_exhaustion_prevents_a_provider_submission(): void
    {
        [$task, $plan] = $this->voiceTask();
        MarketingProductionBudget::whereKey('test-phase')->update(['reserved_microusd' => 4500000]);
        try {
            app(MarketingVoiceLibrary::class)->obtain($task, $plan);
            $this->fail('Insufficient reservation budget must stop generation.');
        } catch (MarketingProductionFailure $e) {
            $this->assertSame('test_budget_exhausted', $e->reason);
        }
        Http::assertNothingSent();
        $this->assertDatabaseCount('marketing_voice_assets', 0);
    }

    public function test_malformed_paid_alignment_is_not_presented_as_success_or_retried(): void
    {
        [$task, $plan] = $this->voiceTask();
        $bad = $this->speechResponse('Wrong script.');
        Http::fake(['api.elevenlabs.io/*' => Http::response($bad, 200)]);
        try {
            app(MarketingVoiceLibrary::class)->obtain($task, $plan);
            $this->fail('Speech must match the approved original text.');
        } catch (MarketingProductionFailure $e) {
            $this->assertSame('voice_result_invalid', $e->reason);
        }
        $this->assertSame('unknown', MarketingVoiceAsset::firstOrFail()->status);
        Http::assertSentCount(1);
    }

    public function test_disabled_live_audio_cannot_submit_or_reserve(): void
    {
        [$task, $plan] = $this->voiceTask();
        config(['marketing.production.live_audio_enabled' => false]);
        try {
            app(MarketingVoiceLibrary::class)->obtain($task, $plan);
            $this->fail('Live generation is disabled.');
        } catch (MarketingProductionFailure $e) {
            $this->assertSame('live_audio_disabled', $e->reason);
        }
        $this->assertSame(1993000, MarketingProductionBudget::findOrFail('test-phase')->reserved_microusd);
        Http::assertNothingSent();
    }

    public function test_worker_revalidates_queued_input_before_any_paid_call(): void
    {
        $plan = $this->plan();
        $this->start($plan)->assertRedirect();
        $task = MarketingProduction::firstOrFail();
        $this->travel(25)->hours();
        app(MarketingProductionProcessor::class)->process($task->id);
        $this->assertSame('blocked', $task->fresh()->status);
        $this->assertSame('approval_no_longer_valid', $task->fresh()->error_code);
        Http::assertNothingSent();
    }

    public function test_worker_uses_validated_cached_voice_and_duplicate_delivery_does_not_render_again(): void
    {
        [$task, $plan] = $this->voiceTask();
        $response = $this->speechResponse($plan->manifest['audio']['text']);
        $voice = app(MarketingVoiceLibrary::class)->import($plan, base64_decode($response['audio_base64']), $response['alignment'], ['character_cost' => 2.5]);
        $task->update(['status' => 'queued']);
        $renderer = $this->mock(MarketingVideoRenderer::class);
        $renderer->shouldReceive('assertReady')->once();
        $renderer->shouldReceive('render')->once()->andReturn(['duration_seconds' => 5, 'width' => 720, 'height' => 1280, 'frames' => 120, 'sha256' => str_repeat('a', 64)]);
        app(MarketingProductionProcessor::class)->process($task->id);
        app(MarketingProductionProcessor::class)->process($task->id);
        $this->assertSame('completed', $task->fresh()->status);
        $this->assertSame($voice->id, $task->fresh()->voice_asset_id);
        $this->assertSame(0, $task->fresh()->new_reserved_microusd);
        $this->assertSame('verified_local_probe', $task->fresh()->result['voice_source']);
        Http::assertNothingSent();
    }

    public function test_render_failure_preserves_saved_voice_and_does_not_repeat_speech(): void
    {
        [$task, $plan] = $this->voiceTask();
        $response = $this->speechResponse($plan->manifest['audio']['text']);
        $voice = app(MarketingVoiceLibrary::class)->import($plan, base64_decode($response['audio_base64']), $response['alignment'], []);
        $task->update(['status' => 'queued']);
        $renderer = $this->mock(MarketingVideoRenderer::class);
        $renderer->shouldReceive('assertReady')->once();
        $renderer->shouldReceive('render')->once()->andThrow(new MarketingProductionFailure('render_failed'));
        app(MarketingProductionProcessor::class)->process($task->id);
        $this->assertSame('failed', $task->fresh()->status);
        $this->assertSame('ready', $voice->fresh()->status);
        Storage::disk('local')->assertExists($voice->audio_path);
        $this->assertNull($task->fresh()->output_path);
        Http::assertNothingSent();
    }

    public function test_completed_preview_is_private_and_supports_http_range(): void
    {
        $plan = $this->plan();
        $this->start($plan)->assertRedirect();
        $task = MarketingProduction::firstOrFail();
        $path = 'marketing-productions/'.$task->id.'/output.mp4';
        Storage::disk('local')->put($path, 'A-local-test-video-payload');
        $task->update(['status' => 'completed', 'output_path' => $path]);
        $url = '/admin/marketing/projects/'.$plan->project_id.'/productions/'.$task->id.'/preview';
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'video/mp4')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->withHeader('Range', 'bytes=0-4')->get($url)->assertStatus(206)->assertHeader('Content-Range', 'bytes 0-4/26');
    }

    public function test_an_explicit_resume_after_setup_block_can_requeue_but_unknown_paid_work_cannot(): void
    {
        $plan = $this->plan();
        $this->start($plan)->assertRedirect();
        $task = MarketingProduction::firstOrFail();
        $task->update(['status' => 'blocked', 'error_code' => 'live_audio_disabled']);
        $this->start($plan)->assertRedirect();
        $this->assertSame('queued', $task->fresh()->status);
        $task->update(['status' => 'unknown', 'new_reserved_microusd' => 1000000]);
        $this->start($plan)->assertRedirect();
        $this->assertSame('unknown', $task->fresh()->status);
        Queue::assertPushed(\App\Jobs\ProcessMarketingProduction::class, 2);
        Http::assertNothingSent();
    }

    public function test_an_explicit_render_retry_requires_the_already_saved_voice_and_reuses_it(): void
    {
        [$task, $plan] = $this->voiceTask();
        $r = $this->speechResponse($plan->manifest['audio']['text']);
        $voice = app(MarketingVoiceLibrary::class)->import($plan, base64_decode($r['audio_base64']), $r['alignment'], []);
        $task->update(['status' => 'failed', 'error_code' => 'media_processing_failed', 'voice_asset_id' => $voice->id]);
        $this->start($plan)->assertRedirect();
        $this->assertSame('queued', $task->fresh()->status);
        $this->assertSame($voice->id, $task->fresh()->voice_asset_id);
        Queue::assertPushed(\App\Jobs\ProcessMarketingProduction::class, 2);
        Http::assertNothingSent();
    }

    public function test_no_narration_preview_does_not_claim_a_reused_or_paid_voice(): void
    {
        $plan = $this->plan(true, false);
        $this->start($plan)->assertRedirect();
        $task = MarketingProduction::firstOrFail();
        $task->update(['status' => 'completed', 'result' => ['duration_seconds' => 5, 'width' => 720, 'height' => 1280, 'voice_reused' => true, 'voice_source' => null]]);
        $this->get('/admin/marketing/projects/'.$plan->project_id.'/production')->assertOk()->assertSee('No voiceover was requested')->assertDontSee('Previously generated, matching voiceover was reused.');
    }

    public function test_live_calls_are_limited_to_the_operator_selected_test_project(): void
    {
        [$task, $plan] = $this->voiceTask();
        config(['marketing.production.live_audio_project' => (string) Str::uuid()]);
        try {
            app(MarketingVoiceLibrary::class)->obtain($task, $plan);
            $this->fail('Other projects must not consume the live test allowance.');
        } catch (MarketingProductionFailure $e) {
            $this->assertSame('live_audio_disabled', $e->reason);
        }
        Http::assertNothingSent();
        $this->assertDatabaseCount('marketing_voice_assets', 0);
    }

    public function test_queued_work_does_not_execute_after_owner_administrator_access_is_revoked(): void
    {
        $plan = $this->plan();
        $this->start($plan)->assertRedirect();
        $task = MarketingProduction::firstOrFail();
        User::whereKey($task->owner_id)->update(['is_super_admin' => false]);
        app(MarketingProductionProcessor::class)->process($task->id);
        $this->assertSame('blocked', $task->fresh()->status);
        $this->assertSame('approval_no_longer_valid', $task->fresh()->error_code);
        Http::assertNothingSent();
    }

    private function completedDelivery(): array
    {
        $plan = $this->plan();
        $this->start($plan)->assertRedirect();
        $task = MarketingProduction::where('plan_id', $plan->id)->firstOrFail();
        $path = 'marketing-productions/'.$task->id.'/output.mp4';
        Storage::disk('local')->put($path, 'Existing completed movie');
        $task->update(['status'=>'completed','output_path'=>$path,'output_sha256'=>hash('sha256','Existing completed movie'),'result'=>['duration_seconds'=>31.9,'width'=>720,'height'=>1280,'voice_source'=>null,'publication_authorized'=>false]]);
        $url = '/admin/marketing/projects/'.$plan->project_id.'/productions/'.$task->id;
        return [$plan,$task,$url,['output_sha256'=>$task->output_sha256,'final_confirmation'=>1]];
    }

    public function test_video_change_saves_new_draft_and_impact_without_overwriting_approved_movie(): void
    {
        [$plan,$task,$url,$data]=$this->completedDelivery();
        $this->post($url.'/approve',$data)->assertRedirect();
        $p=\App\Models\MarketingProject::findOrFail($plan->project_id);
        $old=$p->currentRevision;
        $draft=$old->document;$draft['scenes'][0]['duration']+=2;$draft['duration']+=2;$draft['changes']=['Hold the ending two seconds longer'];
        $writer=$this->mock(\App\Services\Marketing\MarketingWriter::class);$writer->shouldReceive('propose')->once()->andReturn($draft);$writer->shouldReceive('ready')->andReturn(true);
        $input=['version'=>$p->version,'request_id'=>(string)Str::uuid(),'feedback'=>'Hold the ending two seconds longer, keep everything else unchanged.','text_confirmation'=>1];
        $this->post($url.'/changes',$input)->assertRedirect();
        $this->post($url.'/changes',$input)->assertRedirect();
        $p->refresh();$new=$p->currentRevision;
        $this->assertNotSame($old->id,$new->id);
        $this->assertNull($new->confirmed_at);
        $this->assertSame($old->id,$new->parent_id);
        $this->assertSame($task->id,$new->context['production_change']['production_id']);
        $this->assertSame([1],$new->context['production_change']['impact']['changed_scenes']);
        $this->assertFalse($new->context['production_change']['impact']['voice_changed']);
        $this->assertSame($old->document,$old->fresh()->document);
        $this->get($url.'/download')->assertOk();
        $this->assertDatabaseCount('marketing_production_approvals',1);
        $this->assertDatabaseCount('marketing_productions',1);
        $this->get('/admin/marketing/projects/'.$p->id.'/production')->assertOk()->assertSee('Video change draft')->assertSee('Confirm the revised storyboard');
        Http::assertNothingSent();
    }

    public function test_video_change_requires_separate_text_confirmation_and_current_owned_source(): void
    {
        [$plan,$task,$url,$data]=$this->completedDelivery();
        $p=\App\Models\MarketingProject::findOrFail($plan->project_id);
        $input=['version'=>$p->version,'request_id'=>(string)Str::uuid(),'feedback'=>'Hold the ending longer'];
        $this->post($url.'/changes',$input)->assertSessionHasErrors('text_confirmation');
        $input['text_confirmation']=1;
        $this->post($url.'/changes',array_replace($input,['version'=>$p->version+1]))->assertStatus(409);
        $other=$this->plan();
        $this->post($url.'/changes',$input)->assertNotFound();
        $this->assertDatabaseCount('marketing_ai_runs',0);
        Http::assertNothingSent();
    }

    public function test_video_change_brand_restore_creates_an_unconfirmed_revision_without_ai_or_overwriting_history(): void
    {
        [$plan,$task,$url,$data]=$this->completedDelivery();$p=\App\Models\MarketingProject::findOrFail($plan->project_id);$old=$p->currentRevision;
        $ctx=$old->context;$ctx['presentation']=['brand'=>['name'=>'Original','descriptor'=>'Business']];$old->update(['context'=>$ctx]);
        $ctx['presentation']['brand']['name']='Unrequested';$ctx['production_change']=['production_id'=>$task->id,'revision_id'=>$old->id,'impact'=>['changed_scenes'=>[],'voice_changed'=>false,'materials_changed'=>false,'planned_seconds_before'=>5,'planned_seconds_after'=>5]];
        $bad=MarketingRevision::create(['project_id'=>$p->id,'actor_id'=>$p->owner_id,'parent_id'=>$old->id,'number'=>2,'document'=>$old->document,'context'=>$ctx,'feedback'=>'Hold ending longer']);$p->update(['current_revision_id'=>$bad->id,'status'=>'needs_confirmation']);
        $this->post('/admin/marketing/projects/'.$p->id.'/production/restore-brand',['version'=>$p->version,'revision_id'=>$bad->id,'restore_confirmation'=>1])->assertRedirect();
        $fixed=$p->fresh()->currentRevision;$this->assertSame(3,$fixed->number);$this->assertNull($fixed->confirmed_at);$this->assertSame('Original',$fixed->context['presentation']['brand']['name']);$this->assertSame('Unrequested',$bad->fresh()->context['presentation']['brand']['name']);$this->assertSame($bad->id,$fixed->parent_id);
        $this->assertDatabaseCount('marketing_ai_runs',0);Http::assertNothingSent();
    }

    public function test_final_approval_is_independent_idempotent_and_download_is_private(): void
    {
        [$plan,$task,$url,$data]=$this->completedDelivery();
        $this->get($url.'/download')->assertForbidden();
        $this->post($url.'/approve',['output_sha256'=>$task->output_sha256])->assertSessionHasErrors('final_confirmation');
        $this->post($url.'/approve',$data)->assertRedirect();
        $this->post($url.'/approve',$data)->assertRedirect();
        $this->assertDatabaseCount('marketing_production_approvals',1);
        $this->assertDatabaseHas('marketing_production_approvals',['production_id'=>$task->id,'revision_id'=>$plan->revision_id,'output_sha256'=>$task->output_sha256,'approved_by'=>$task->owner_id]);
        $this->get($url.'/download')->assertOk()->assertDownload('marketing-video-'.$task->id.'.mp4')->assertHeader('X-Content-Type-Options','nosniff');
        $this->get('/admin/marketing/projects/'.$plan->project_id.'/production')->assertOk()->assertSee('Final video approved')->assertSee('Download approved video');
        $this->assertFalse($task->fresh()->result['publication_authorized']);
        Http::assertNothingSent();
        Queue::assertPushed(\App\Jobs\ProcessMarketingProduction::class,1);
    }

    public function test_final_delivery_denies_foreign_owner_and_mismatched_project_task(): void
    {
        [$plan,$task,$url,$data]=$this->completedDelivery();
        $this->post($url.'/approve',$data)->assertRedirect();
        $other=$this->plan();
        $this->post($url.'/approve',$data)->assertNotFound();
        $this->get($url.'/download')->assertNotFound();
        $wrong='/admin/marketing/projects/'.$other->project_id.'/productions/'.$task->id;
        $this->post($wrong.'/approve',$data)->assertNotFound();
        $this->get($wrong.'/download')->assertNotFound();
        $this->assertDatabaseCount('marketing_production_approvals',1);
        Http::assertNothingSent();
    }

    public function test_final_approval_rejects_stale_revision_hash_and_unfinished_output(): void
    {
        [$plan,$task,$url,$data]=$this->completedDelivery();
        $this->post($url.'/approve',array_replace($data,['output_sha256'=>str_repeat('a',64)]))->assertStatus(409);
        $task->update(['status'=>'rendering']);
        $this->post($url.'/approve',$data)->assertStatus(409);
        $task->update(['status'=>'completed']);
        \App\Models\MarketingProject::whereKey($plan->project_id)->increment('version');
        $this->post($url.'/approve',$data)->assertStatus(409);
        $this->assertDatabaseCount('marketing_production_approvals',0);
        Http::assertNothingSent();
    }

    public function test_approved_history_remains_downloadable_but_changed_or_missing_file_is_blocked(): void
    {
        [$plan,$task,$url,$data]=$this->completedDelivery();
        $this->post($url.'/approve',$data)->assertRedirect();
        \App\Models\MarketingProject::whereKey($plan->project_id)->increment('version');
        $this->get($url.'/download')->assertOk();
        Storage::disk('local')->put($task->output_path,'Changed movie');
        $this->get($url.'/download')->assertStatus(409);
        Storage::disk('local')->delete($task->output_path);
        $this->get($url.'/download')->assertNotFound();
        $this->assertDatabaseCount('marketing_production_approvals',1);
        Http::assertNothingSent();
    }

    public function test_final_approval_does_not_transfer_to_another_output_or_revoked_administrator(): void
    {
        [$plan,$task,$url,$data]=$this->completedDelivery();
        $this->post($url.'/approve',$data)->assertRedirect();
        $newPlan=$plan->replicate();
        $newPlan->request_id=(string) Str::uuid();
        $newPlan->save();
        $newTask=$task->replicate();
        $newTask->plan_id=$newPlan->id;
        $newTask->save();
        $newUrl='/admin/marketing/projects/'.$plan->project_id.'/productions/'.$newTask->id;
        $this->get($newUrl.'/download')->assertForbidden();
        $this->assertDatabaseCount('marketing_production_approvals',1);
        User::whereKey($task->owner_id)->update(['is_super_admin'=>false]);
        $this->actingAs(User::findOrFail($task->owner_id));
        $this->get($url.'/download')->assertForbidden();
        $this->post($url.'/approve',$data)->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_final_approval_rejects_changed_bytes_and_redirected_storage_path(): void
    {
        [$plan,$task,$url,$data]=$this->completedDelivery();
        Storage::disk('local')->put($task->output_path,'Modified before approval');
        $this->post($url.'/approve',$data)->assertStatus(409);
        $task->update(['output_path'=>'marketing-voices/other-output.mp4']);
        $this->post($url.'/approve',$data)->assertStatus(409);
        $this->assertDatabaseCount('marketing_production_approvals',0);
        Http::assertNothingSent();
    }

    public function test_matching_voice_from_another_project_is_not_reused(): void
    {
        [$first, $planA] = $this->voiceTask();
        $r = $this->speechResponse($planA->manifest['audio']['text']);
        app(MarketingVoiceLibrary::class)->import($planA, base64_decode($r['audio_base64']), $r['alignment'], []);
        $planB = $this->plan();
        $this->start($planB)->assertRedirect();
        $task = MarketingProduction::where('plan_id', $planB->id)->firstOrFail();
        $task->update(['status' => 'preparing']);
        config(['marketing.production.live_audio_enabled' => false]);
        try {
            app(MarketingVoiceLibrary::class)->obtain($task, $planB);
            $this->fail('Another project must not reuse private voice assets.');
        } catch (MarketingProductionFailure $e) {
            $this->assertSame('live_audio_disabled', $e->reason);
        }
        $this->assertNull($task->fresh()->voice_asset_id);
        $this->assertDatabaseCount('marketing_voice_assets', 1);
        Http::assertNothingSent();
    }
}
