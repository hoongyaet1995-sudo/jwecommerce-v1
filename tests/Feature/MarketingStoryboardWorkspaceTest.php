<?php

namespace Tests\Feature;

use App\Models\MarketingRevision;
use App\Models\MarketingSceneRevision;
use App\Services\Marketing\MarketingStoryboardChanges;
use App\Services\Marketing\MarketingStoryboardContract;
use App\Services\Marketing\MarketingStoryboardHandoff;
use App\Services\Marketing\MarketingStoryboardSemanticClient;
use App\Services\Marketing\MarketingStoryboardTiming;
use App\Services\Marketing\MarketingStoryboardUpload;
use App\Services\Marketing\MarketingStoryboardWorkspace;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\MarketingResearchFixtures;
use Tests\TestCase;

class MarketingStoryboardWorkspaceTest extends TestCase
{
    use MarketingResearchFixtures,RefreshDatabase;

    private function sample()
    {
        $p = $this->researchProject();
        $r = MarketingRevision::create(['project_id' => $p->id, 'number' => 1, 'actor_id' => $p->owner_id, 'document' => ['scenes' => array_fill(0, 3, ['visual' => 'Show product', 'voiceover' => 'Confirmed information', 'subtitle' => 'Detail', 'duration' => 5])], 'context' => []]);
        $p->update(['current_revision_id' => $r->id]);

        return $p->fresh();
    }

    public function test_read_is_pure_and_initialization_is_idempotent()
    {
        $p = $this->sample();
        $s = app(MarketingStoryboardWorkspace::class);
        $this->assertNull($s->snapshot($p->id, $p->owner_id)['story_id']);
        $id = (string) Str::uuid();
        $a = $s->initialize($p->id, $p->owner_id, $id, $p->version);
        $b = $s->initialize($p->id, $p->owner_id, $id, $p->version);
        $this->assertSame($a, $b);
        $this->assertSame('auto',$a['profile']['voice']['mode']);
        $this->assertCount(3, $a['scenes']);
        $this->assertSame(15, $a['timing']['content_seconds']);
        $this->assertSame($p->current_revision_id, $p->fresh()->current_revision_id);
    }

    public function test_manual_save_rebinds_inherited_frame_state_to_saved_director_without_ai(): void
    {
        $p=$this->sample();$w=app(MarketingStoryboardWorkspace::class);$a=$w->initialize($p->id,$p->owner_id,(string)Str::uuid(),$p->version);$scene=$a['scenes'][0];
        $r=MarketingSceneRevision::findOrFail($scene['revision_id']);$c=$r->content;$c['director']['start_state']='Saved canonical opening';$c['frames']['start_frame']['state']='Stale framing description';$c['frames']['start_frame']['instruction']='Open: Stale framing description';$new=MarketingSceneRevision::create(['scene_id'=>$r->scene_id,'parent_id'=>$r->id,'content'=>$c,'signature'=>$r->signature]);\App\Models\MarketingScene::findOrFail($r->scene_id)->update(['current_revision_id'=>$new->id]);
        $input=array_intersect_key($scene,array_flip(['seconds','message','visual','voiceover','subtitle']));
        $out=$w->apply($p->id,$p->owner_id,(string)Str::uuid(),$a['story_version'],['type'=>'edit','mode'=>'manual','refine_manual'=>false,'scene_id'=>$scene['scene_id'],'content'=>$input,'update_scope'=>['director'=>true,'images'=>false]]);
        $this->assertSame('Saved canonical opening',$out['scenes'][0]['frames']['start_frame']['state']);$this->assertSame('Open: Saved canonical opening',$out['scenes'][0]['frames']['start_frame']['instruction']);$this->assertSame($c['director'],$out['scenes'][0]['director']);Http::assertNothingSent();
    }

    public function test_insert_replace_and_restore_preserve_identity_and_history()
    {
        $p = $this->sample();
        $s = app(MarketingStoryboardWorkspace::class);
        $a = $s->initialize($p->id, $p->owner_id, (string) Str::uuid(), $p->version);
        $old = array_column($a['scenes'], 'scene_id');
        $data = ['seconds' => 5, 'message' => 'New detail', 'visual' => 'Show detail', 'voiceover' => '', 'subtitle' => ''];
        $b = $s->apply($p->id, $p->owner_id, (string) Str::uuid(), 1, ['type' => 'insert', 'after_id' => $old[0], 'content' => $data]);
        $this->assertSame([$old[0], $old[1], $old[2]], array_values(array_intersect(array_column($b['scenes'], 'scene_id'), $old)));
        $this->assertSame(20, $b['timing']['content_seconds']);
        $c = $s->apply($p->id, $p->owner_id, (string) Str::uuid(), 2, ['type' => 'replace', 'scene_id' => $old[0], 'contents' => [$data, $data, $data]]);
        $this->assertCount(6, $c['scenes']);
        $this->assertSame(30, $c['timing']['content_seconds']);
        $this->assertGreaterThanOrEqual(7, MarketingSceneRevision::count());
        $this->assertSame($old[1], $c['scenes'][4]['scene_id']);
    }

    public function test_stale_version_rejected()
    {
        $p = $this->sample();
        $s = app(MarketingStoryboardWorkspace::class);
        $s->initialize($p->id, $p->owner_id, (string) Str::uuid(), $p->version);
        $this->expectException(HttpException::class);
        $s->apply($p->id, $p->owner_id, (string) Str::uuid(), 0, ['type' => 'remove', 'scene_id' => 'bad']);
    }

    public function test_owner_payload_conflict_and_scene_limit()
    {
        $p = $this->sample();
        $s = app(MarketingStoryboardWorkspace::class);
        $s->initialize($p->id, $p->owner_id, (string) Str::uuid(), $p->version);
        $snap = $s->snapshot($p->id, $p->owner_id);
        $request = (string) Str::uuid();
        $command = ['type' => 'edit', 'scene_id' => $snap['scenes'][0]['scene_id'], 'content' => ['seconds' => 5, 'message' => 'New', 'visual' => 'Updated', 'voiceover' => '', 'subtitle' => '']];
        $s->apply($p->id, $p->owner_id, $request, 1, $command);
        try {
            $s->apply($p->id, $p->owner_id, $request, 1, $command + ['extra' => true]);
            $this->fail('payload conflict');
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
        }try {
            $s->snapshot($p->id, $p->owner_id + 999);
            $this->fail('owner');
        } catch (ModelNotFoundException) {
            $this->assertTrue(true);
        }
    }

    public function test_timing_does_not_duplicate_outro_and_reports_voice_length()
    {
        $t = app(MarketingStoryboardTiming::class);
        $r = $t->calculate([['scene_id' => 'a', 'seconds' => 5, 'voiceover' => str_repeat('word ', 100)]], ['outro_seconds' => 3]);
        $this->assertSame(8, $r['output_seconds']);
        $this->assertTrue($r['requires_attention']);
    }

    public function test_manual_upload_and_edit_do_not_call_semantic_provider()
    {
        $p = $this->sample();
        $s = app(MarketingStoryboardWorkspace::class);
        $a = $s->initialize($p->id, $p->owner_id, (string) Str::uuid(), $p->version);
        $c = app(MarketingStoryboardChanges::class);
        $r = $c->change($p->id, $p->owner_id, (string) Str::uuid(), 1, ['type' => 'insert', 'mode' => 'manual', 'content' => ['seconds' => 5, 'message' => 'Detail', 'visual' => 'Photo', 'voiceover' => '', 'subtitle' => '']]);
        $this->assertCount(4, $r['snapshot']['scenes']);
        Http::assertNothingSent();
    }

    public function test_semantic_draft_cannot_add_unsupported_spoken_facts()
    {
        $c = app(MarketingStoryboardSemanticClient::class);
        $this->expectException(ValidationException::class);
        $c->validateDraft(['scenes' => [['seconds' => 5, 'message' => 'A', 'visual' => 'Space view', 'voiceover' => 'Guaranteed free delivery', 'subtitle' => '', 'fact_refs' => ['fake']]]], ['approved' => 'Product']);
    }

    public function test_confirmation_rejects_pending_and_generation_disabled()
    {
        $p = $this->sample();
        $s = app(MarketingStoryboardWorkspace::class);
        $s->initialize($p->id, $p->owner_id, (string) Str::uuid(), $p->version);
        $this->expectException(HttpException::class);
        app(MarketingStoryboardHandoff::class)->confirm($p->id, $p->owner_id, (string) Str::uuid(), 1);
    }

    public function test_http_get_is_readonly_and_manual_insert_persists()
    {
        $p = $this->sample();
        $url = '/admin/marketing/workspace/'.$p->id.'/storyboard';
        $this->getJson($url)->assertOk()->assertJsonPath('snapshot.story_version', 0);
        $this->postJson($url.'/initialize', ['request_id' => (string) Str::uuid(), 'version' => $p->version])->assertOk()->assertJsonPath('snapshot.story_version', 1);
        $this->postJson($url.'/changes', ['request_id' => (string) Str::uuid(), 'version' => 1, 'command' => ['type' => 'insert', 'mode' => 'manual', 'content' => ['seconds' => 5, 'message' => 'Service detail', 'visual' => 'Service process', 'voiceover' => '', 'subtitle' => '']]])->assertOk()->assertJsonCount(4, 'snapshot.scenes')->assertJsonPath('snapshot.timing.content_seconds', 20);
        Http::assertNothingSent();
    }

    public function test_semantic_fixture_splits_without_sample_keywords_and_facts_remain_whole()
    {
        $p = $this->sample();
        $s = app(MarketingStoryboardWorkspace::class);
        $a = $s->initialize($p->id, $p->owner_id, (string) Str::uuid(), $p->version);
        app()->instance('marketing.storyboard.semantic_transport', fn ($args) => ['scenes' => array_fill(0, 3, ['seconds' => 5, 'message' => 'Creative transition', 'visual' => 'A purely creative scene', 'voiceover' => '', 'subtitle' => '', 'fact_refs' => []])]);
        $r = app(MarketingStoryboardChanges::class)->change($p->id, $p->owner_id, (string) Str::uuid(), 1, ['type' => 'edit', 'mode' => 'ai', 'scene_id' => $a['scenes'][0]['scene_id'], 'rewrite' => true, 'intent' => 'Create an opening sequence']);
        $this->assertCount(5, $r['snapshot']['scenes']);
        $this->assertSame(25, $r['snapshot']['timing']['content_seconds']);
        $this->assertSame($a['scenes'][1]['scene_id'], $r['snapshot']['scenes'][3]['scene_id']);
    }

    public function test_ai_duplicate_request_reuses_analysis_and_old_result()
    {
        $p = $this->sample();
        $w = app(MarketingStoryboardWorkspace::class);
        $a = $w->initialize($p->id, $p->owner_id, (string) Str::uuid(), $p->version);
        $calls = 0;
        app()->instance('marketing.storyboard.semantic_transport', function ($args) use (&$calls) {
            $calls++;

            return ['scenes' => [['seconds' => 5, 'message' => 'New intent', 'visual' => 'Creative scene', 'voiceover' => '', 'subtitle' => '', 'fact_refs' => []]]];
        });
        $service = app(MarketingStoryboardChanges::class);
        $request = (string) Str::uuid();
        $cmd = ['type' => 'edit', 'mode' => 'ai', 'scene_id' => $a['scenes'][0]['scene_id'], 'intent' => 'An original opening'];
        $first = $service->change($p->id, $p->owner_id, $request, 1, $cmd);
        $second = $service->change($p->id, $p->owner_id, $request, 1, $cmd);
        $this->assertSame($first['snapshot'], $second['snapshot']);
        $this->assertSame(1, $calls);
    }

    public function test_profile_change_invalidates_presenter_images_but_voice_does_not()
    {
        $p = $this->sample();
        $w = app(MarketingStoryboardWorkspace::class);
        $a = $w->initialize($p->id, $p->owner_id, (string) Str::uuid(), $p->version);
        $scene = $a['scenes'][0];
        $c = app(MarketingStoryboardContract::class);
        $this->assertSame($c->signature($scene, ['voice' => ['id' => 'a']]), $c->signature($scene, ['voice' => ['id' => 'b']]));
        $this->assertNotSame($c->signature(array_replace($scene,['presenter_required' => true]), ['presenter' => ['id' => 'a']]), $c->signature(array_replace($scene,['presenter_required' => true]), ['presenter' => ['id' => 'b']]));
    }

    public function test_upload_manual_ready_handoff_and_history_remain_frozen()
    {
        $p = $this->sample();
        $w = app(MarketingStoryboardWorkspace::class);
        $a = $w->initialize($p->id, $p->owner_id, (string) Str::uuid(), $p->version);
        $ref = app(MarketingStoryboardUpload::class)->store($p->id, $p->owner_id, UploadedFile::fake()->image('product.png', 90, 160), true);
        $v = 1;
        foreach ($a['scenes'] as $row) {
            $after = $w->apply($p->id, $p->owner_id, (string) Str::uuid(), $v++, ['type' => 'edit', 'scene_id' => $row['scene_id'], 'content' => ['seconds' => 5, 'message' => 'Uploaded evidence', 'visual' => 'Use declared original photo', 'voiceover' => '', 'subtitle' => ''], 'asset_id' => $ref['asset_id']]);
        }$this->assertTrue($after['readiness']);
        $h = app(MarketingStoryboardHandoff::class);
        $confirmed = $h->confirm($p->id, $p->owner_id, (string) Str::uuid(), $after['story_version']);
        $this->assertSame('storyboard-production-v1', $confirmed['last_confirmation']['snapshot']['contract']);
        $frozen = $confirmed['last_confirmation'];
        $plan=app(\App\Services\Marketing\MarketingProductionPreparation::class)->prepareStoryboard($p->id,$p->owner_id,(string)Str::uuid(),$after['story_version']);$this->assertSame('storyboard-production-v1',$plan->manifest['recipe']);$this->assertSame(15,$plan->manifest['timing']['content_seconds']);$this->assertCount(3,$plan->manifest['clips']);$this->assertSame('blocked',$plan->status);\Illuminate\Support\Facades\Http::assertNothingSent();
        $w->apply($p->id, $p->owner_id, (string) Str::uuid(), $after['story_version'], ['type' => 'edit', 'scene_id' => $after['scenes'][0]['scene_id'], 'content' => ['seconds' => 5, 'message' => 'Changed', 'visual' => 'A new visual', 'voiceover' => '', 'subtitle' => '']]);
        $this->assertNotEmpty($frozen['snapshot']['scene_map'][0]['asset']);
        $this->assertNull($w->snapshot($p->id,$p->owner_id)['last_confirmation']);
    }

    public function test_revision_is_immutable()
    {
        $p = $this->sample();
        $a = app(MarketingStoryboardWorkspace::class)->initialize($p->id,$p->owner_id,(string) Str::uuid(),$p->version);
        $r = MarketingSceneRevision::findOrFail($a['scenes'][0]['revision_id']);
        $this->expectException(\LogicException::class);
        $r->update(['content' => ['visual' => 'tamper']]);
    }
    public function test_removal_restore_and_cap_roll_back_without_mutating_history():void {
        $p=$this->sample();$w=app(MarketingStoryboardWorkspace::class);$a=$w->initialize($p->id,$p->owner_id,(string)Str::uuid(),$p->version);$id=$a['scenes'][1]['scene_id'];$b=$w->apply($p->id,$p->owner_id,(string)Str::uuid(),1,['type'=>'remove','scene_id'=>$id]);$this->assertCount(2,$b['scenes']);$c=$w->apply($p->id,$p->owner_id,(string)Str::uuid(),2,['type'=>'restore','scene_id'=>$id,'index'=>1]);$this->assertSame(array_column($a['scenes'],'scene_id'),array_column($c['scenes'],'scene_id'));
        $before=MarketingSceneRevision::count();try{$w->apply($p->id,$p->owner_id,(string)Str::uuid(),3,['type'=>'replace','scene_id'=>$id,'contents'=>array_fill(0,12,['seconds'=>5,'message'=>'Extra','visual'=>'Scene','voiceover'=>'','subtitle'=>''])]);$this->fail('over limit');}catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){$this->assertSame(422,$e->getStatusCode());}$this->assertSame($before,MarketingSceneRevision::count());$this->assertSame(3,$w->snapshot($p->id,$p->owner_id)['story_version']);
    }
    public function test_wrong_owner_http_and_unconfigured_voice_do_not_generate():void { $p=$this->sample();$other=\App\Models\User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]);$this->actingAs($other)->getJson('/admin/marketing/workspace/'.$p->id.'/storyboard')->assertNotFound();$r=app(\App\Services\Marketing\MarketingCreativeProfiles::class)->resolveProduction(['voice'=>['mode'=>'preset','id'=>'unverified']],[]);$this->assertNotEmpty($r['blockers']);\Illuminate\Support\Facades\Http::assertNothingSent(); }
    public function test_review_source_change_blocks_confirmation():void {
        $p=$this->sample();$w=app(MarketingStoryboardWorkspace::class);$w->initialize($p->id,$p->owner_id,(string)Str::uuid(),$p->version);$p->update(['brief'=>array_replace($p->brief,['facts'=>'Different approved facts'])]);$snapshot=$w->snapshot($p->id,$p->owner_id);$this->assertFalse($snapshot['source_fresh']);
    }
    public function test_step3_handoff_keeps_voiceover_for_later_formal_production():void {
        $p=$this->sample();$w=app(MarketingStoryboardWorkspace::class);$s=$w->initialize($p->id,$p->owner_id,(string)Str::uuid(),$p->version);$ref=app(\App\Services\Marketing\MarketingStoryboardUpload::class)->store($p->id,$p->owner_id,\Illuminate\Http\UploadedFile::fake()->image('speech.png',90,160),true);$v=1;foreach($s['scenes'] as $row)$s=$w->apply($p->id,$p->owner_id,(string)Str::uuid(),$v++,['type'=>'edit','scene_id'=>$row['scene_id'],'content'=>['seconds'=>5,'message'=>'Confirmed information','visual'=>'Photo','voiceover'=>'A complete spoken sentence','subtitle'=>''],'asset_id'=>$ref['asset_id']]);$out=app(\App\Services\Marketing\MarketingStoryboardHandoff::class)->confirm($p->id,$p->owner_id,(string)Str::uuid(),$s['story_version']);$this->assertSame('A complete spoken sentence',$out['scenes'][0]['voiceover']);$this->assertTrue($out['readiness']);
    }
    public function test_review_semantic_intent_contains_target_and_neighbors():void {
        $p=$this->sample();$w=app(MarketingStoryboardWorkspace::class);$s=$w->initialize($p->id,$p->owner_id,(string)Str::uuid(),$p->version);$seen=[];app()->instance('marketing.storyboard.semantic_transport',function($args)use(&$seen){$seen=$args;return ['scenes'=>[['seconds'=>5,'message'=>'Changed','visual'=>'New scene','voiceover'=>'','subtitle'=>'','fact_refs'=>[]]]];});app(\App\Services\Marketing\MarketingStoryboardChanges::class)->change($p->id,$p->owner_id,(string)Str::uuid(),1,['type'=>'edit','mode'=>'ai','scene_id'=>$s['scenes'][1]['scene_id'],'intent'=>'More direct']);$this->assertSame($s['scenes'][1]['scene_id'],$seen['intent']['scene_id']);
    }
    public function test_review_same_upload_reuses_immutable_asset():void {
        $p=$this->sample();$u=app(\App\Services\Marketing\MarketingStoryboardUpload::class);$f=\Illuminate\Http\UploadedFile::fake()->image('same.png',90,160);$one=$u->store($p->id,$p->owner_id,$f,true);$two=$u->store($p->id,$p->owner_id,$f,true);$this->assertSame($one['asset_id'],$two['asset_id']);
    }
    public function test_legacy_first_frame_mapping_survives_middle_insert():void {
        $p=$this->sample();$outputs=[];$ids=[];for($i=1;$i<=3;$i++){$ref=app(\App\Services\Marketing\MarketingStoryboardUpload::class)->store($p->id,$p->owner_id,\Illuminate\Http\UploadedFile::fake()->image('old'.$i.'.png',90+$i,160),true);$ids[]=$ref['asset_id'];$outputs[]=['id'=>'old-'.$i,'scene_number'=>$i,'role'=>'start_frame','status'=>'approved','asset_id'=>$ref['asset_id']];}$doc=$p->currentRevision->document;$doc['material_package']=['outputs'=>$outputs];$p->currentRevision->update(['document'=>$doc]);$p->refresh();$w=app(MarketingStoryboardWorkspace::class);$a=$w->initialize($p->id,$p->owner_id,(string)Str::uuid(),$p->version);$this->assertSame($ids,array_column(array_column($a['scenes'],'previous_asset'),'asset_id'));$b=$w->apply($p->id,$p->owner_id,(string)Str::uuid(),1,['type'=>'insert','after_id'=>$a['scenes'][0]['scene_id'],'content'=>['seconds'=>5,'message'=>'New','visual'=>'New','voiceover'=>'','subtitle'=>'']]);$this->assertSame($ids[1],$b['scenes'][2]['previous_asset']['asset_id']);
    }
    public function test_add_description_keeps_requested_duration_neighbors_and_existing_scenes(): void
    {
        $p=$this->sample();$w=app(MarketingStoryboardWorkspace::class);$before=$w->initialize($p->id,$p->owner_id,(string)Str::uuid(),$p->version);$ids=array_column($before['scenes'],'scene_id');
        app()->instance('marketing.storyboard.semantic_transport',function($args){$this->assertSame(8,$args['intent']['requested_seconds']);return ['scenes'=>[['seconds'=>8,'message'=>'Service detail','visual'=>'Show the service process','voiceover'=>'','subtitle'=>'','fact_refs'=>[]]]];});
        $out=app(MarketingStoryboardChanges::class)->change($p->id,$p->owner_id,(string)Str::uuid(),1,['type'=>'insert','mode'=>'ai','after_id'=>$ids[0],'intent'=>'Show service details','requested_seconds'=>8]);
        $this->assertSame(23,$out['snapshot']['timing']['content_seconds']);$this->assertSame($ids[0],$out['snapshot']['scenes'][0]['scene_id']);$this->assertSame($ids[1],$out['snapshot']['scenes'][2]['scene_id']);$this->assertSame($ids[2],$out['snapshot']['scenes'][3]['scene_id']);Http::assertNothingSent();
    }

}
