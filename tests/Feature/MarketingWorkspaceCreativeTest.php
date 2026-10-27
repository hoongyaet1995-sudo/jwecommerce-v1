<?php

namespace Tests\Feature;

use App\Models\AiConnection;
use App\Models\MarketingProduction;
use App\Models\MarketingProject;
use App\Models\MarketingResearchRun;
use App\Services\Marketing\MarketingCreativePlanning;
use App\Services\Marketing\MarketingResearchSources;
use App\Services\Marketing\MarketingResearchWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Concerns\MarketingResearchFixtures;
use Tests\TestCase;

class MarketingWorkspaceCreativeTest extends TestCase
{
    use MarketingResearchFixtures,RefreshDatabase;

    private function sample(): MarketingProject
    {
        $p = $this->researchProject('Business storage equipment');
        config(['marketing.conversation.enabled' => true, 'marketing.conversation.open_testing' => true]);
        AiConnection::updateOrCreate(['provider' => 'openai'], ['enabled' => true, 'verified_at' => now(), 'text_model' => 'gpt-5.6-luna', 'api_key' => 'fixture-only']);
        $r = app(MarketingResearchWorkflow::class)->start($p->id, $p->owner_id, $p->version, (string) Str::uuid());
        $sources = app(MarketingResearchSources::class)->normalize(array_map(fn ($i) => ['url' => 'https://youtube.com/watch?v=examplevid'.$i, 'raw_content' => "### Transcript\n[0:01] Explain the real use first.\n[0:06] Then introduce the product.", '_role' => $i === 3 ? 'cross_industry' : 'same_industry', '_extraction_status' => 'content_available'], [1, 2, 3]));
        $r->update(['status' => 'partial', 'sources' => $sources]);
        $p->refresh()->update(['active_research_run_id' => null, 'planning_state' => ['status' => 'confirmed'], 'preferences'=>['video_type'=>'presenter'], 'brief'=>array_replace($p->brief,['duration_seconds'=>15])]);

        return $p->fresh();
    }

    private function providerOutput($value): array
    {
        return ['status' => 'completed', 'output' => [['content' => [['type' => 'output_text', 'text' => json_encode($value)]]]]];
    }

    private function body($p, $action, $extra = []): array
    {
        return $extra + ['request_id' => (string) Str::uuid(), 'version' => $p->fresh()->version, 'action' => $action];
    }

    public function test_three_concepts_hook_selection_and_storyboard_reuse_research_without_other_tools(): void
    {
        $p = $this->sample();
        $s = app(MarketingCreativePlanning::class)->research($p);
        $concepts = array_map(fn ($i) => ['title' => 'Direction'.$i, 'approach' => ['use_scenario', 'feature_detail', 'question_answer'][$i - 1], 'hook' => 'Opening'.$i, 'story' => 'Different story'.$i, 'materials' => 'Existing product photos', 'duration' => 15, 'ratio' => '9:16', 'video_type'=>'presenter','visual_treatment'=>'A presenter explains the product to the viewer','recommended' => $i === 1, 'reason' => 'Fits saved facts', 'references' => [['excerpt_key' => 'reference-'.$i, 'adaptation' => 'Original adaptation']]], [1, 2, 3]);
        $draft = ['summary' => 'Selected story', 'duration' => 15, 'cta' => $p->brief['cta'], 'changes' => [], 'requirements' => [], 'scenes' => [['visual' => 'Show product', 'voiceover' => 'See the product.', 'subtitle' => 'Product', 'duration' => 15, 'material' => 'Existing product photo', 'asset_ids' => []]]];
        $director=MarketingDirectorContractTest::directorOutput();$director['shots'][0]['voiceover']=$draft['scenes'][0]['voiceover'];$director['shots'][0]['subtitle']=$draft['scenes'][0]['subtitle'];
        Http::fakeSequence()->push($this->providerOutput(['concepts' => $concepts]))->push($this->providerOutput(['hook' => 'A new opening']))->push($this->providerOutput($draft))->push($this->providerOutput($director))->push($this->providerOutput(['hook'=>'Another distinct opening']));
        $url = '/admin/marketing/workspace/'.$p->id.'/creative';
        $body = $this->body($p, 'concepts');
        $this->post($url, $body)->assertRedirect();
        $this->post($url, $body)->assertRedirect();
        Http::assertSentCount(1);
        Http::assertSent(fn($request)=>str_contains($request['input'],'VIDEO FORM POLICY')&&str_contains($request['input'],'Speaker-led'));
        Http::assertSent(fn($request)=>data_get($request->data(),'text.format.schema.properties.concepts.items.properties.duration.enum')===[15] && data_get($request->data(),'text.format.schema.properties.concepts.items.properties.video_type.enum')===['presenter'] && !str_contains($request['input'],'"duration":30'));
        $p->refresh();
        $this->assertCount(3, $p->concepts);
        $this->assertSame($s['digest'], $p->concepts[0]['_research']['digest']);
        $before = $p->concepts;
        $this->post($url, $this->body($p, 'hook', ['index' => 1]))->assertRedirect();
        $p->refresh();
        $this->assertSame('A new opening', $p->concepts[1]['hook']);
        unset($before[1]['hook']);
        $after = $p->concepts;
        unset($after[1]['hook']);
        $this->assertSame($before, $after);
        $this->post($url, $this->body($p, 'select', ['index' => 1]))->assertRedirect()->assertSessionHasNoErrors();
        $p->refresh();
        $this->assertSame(1, $p->selected_concept);
        $this->assertSame(1, count($p->currentRevision->document['scenes']));
        $this->assertNull($p->currentRevision->confirmed_at);
        $this->assertSame('pending-tool-routing',$p->currentRevision->context['production_recipe']);
        Http::assertSentCount(4);
        $this->post($url,$this->body($p,'confirm_storyboard'))->assertStatus(422);
        $p->refresh();$this->assertNull($p->confirmed_revision_id);$this->assertNotNull($p->current_revision_id);
        $this->post($url,$this->body($p,'hook',['index'=>1]))->assertRedirect();
        $p->refresh();$this->assertNull($p->current_revision_id);$this->assertNull($p->confirmed_revision_id);
        $this->post($url,['request_id'=>(string)Str::uuid(),'version'=>1,'action'=>'concepts'])->assertStatus(409);
        Http::assertSentCount(5);
        $this->assertSame(0, MarketingProduction::count());
        $this->get('/admin/marketing/workspace?project='.$p->id.'&screen=concepts')->assertOk()->assertSee('data-creative-action="hook"', false);
    }

    public function test_workspace_schema_locks_concept_and_storyboard_duration_to_confirmed_brief(): void
    {
        $service=app(\App\Services\Marketing\MarketingWorkspaceCreative::class);
        foreach ([15,20,30,60] as $duration) {
            $schema=$service->schema('concepts',[],['duration_seconds'=>$duration]);
            $this->assertSame([$duration],$schema['properties']['concepts']['items']['properties']['duration']['enum']);
            $schema=$service->schema('storyboard',[],['duration_seconds'=>$duration]);
            $this->assertSame([$duration],$schema['properties']['duration']['enum']);
        }
    }

    public function test_duplicate_refresh_preserves_current_set_and_calls_provider_only_once(): void
    {
        $p=$this->sample();$research=app(MarketingCreativePlanning::class)->research($p);
        $concepts=array_map(fn($i)=>['title'=>'Story'.$i,'approach'=>['use_scenario','feature_detail','question_answer'][$i-1],'hook'=>'Opening'.$i,'story'=>'Show real equipment storage in a warehouse for enterprise customers '.$i,'materials'=>'Catalog photos','duration'=>15,'ratio'=>'9:16','video_type'=>'presenter','visual_treatment'=>'A presenter explains the product to the viewer','recommended'=>$i===1,'reason'=>'Confirmed use','references'=>[['excerpt_key'=>'reference-'.$i,'adaptation'=>'Original method']]],range(1,3));
        $existing=$concepts;foreach($existing as &$c){$ref=$c['references'][0];$n=(int)substr($ref['excerpt_key'],-1)-1;$c['references']=[['source_id'=>$research['sources'][$n]['id'],'excerpt'=>mb_substr($research['sources'][$n]['bounded_excerpt'],0,200),'adaptation'=>$ref['adaptation'],'basis'=>'text']];}unset($c);
        $p->update(['concepts'=>$existing]);Http::fakeSequence()->push($this->providerOutput(['concepts'=>$concepts]));
        $this->post('/admin/marketing/workspace/'.$p->id.'/creative',$this->body($p,'concepts'))->assertSessionHasErrors();
        $this->assertSame($existing,$p->fresh()->concepts);Http::assertSentCount(1);
    }

    public function test_fixed_form_is_constrained_and_timecoded_storyboards_are_rejected_in_step_two(): void
    {
        $service=app(\App\Services\Marketing\MarketingWorkspaceCreative::class);
        $schema=$service->schema('concepts',[],['duration_seconds'=>15],['video_type'=>'presenter']);
        $this->assertSame(['presenter'],$schema['properties']['concepts']['items']['properties']['video_type']['enum']);
        $schema=$service->schema('concepts',[],[],['video_type'=>'auto']);
        $this->assertCount(6,$schema['properties']['concepts']['items']['properties']['video_type']['enum']);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->validateConcept(['video_type'=>'presenter','visual_treatment'=>'人物讲述用途','story'=>'0–5秒：展示产品','materials'=>'商品图'],['video_type'=>'presenter']);
    }

    public function test_wrong_fixed_form_is_rejected_but_auto_allows_alternative_form():void
    {
        $service=app(\App\Services\Marketing\MarketingWorkspaceCreative::class);
        $concept=['video_type'=>'product_showcase','visual_treatment'=>'Product detail demonstration','story'=>'Introduce a real use and explain the product','materials'=>'Product images'];
        $service->validateConcept($concept,['video_type'=>'auto']);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->validateConcept($concept,['video_type'=>'presenter']);
    }

    public function test_reference_schema_binds_each_source_to_an_exact_saved_excerpt(): void
    {
        $schema=app(\App\Services\Marketing\MarketingWorkspaceCreative::class)->schema('concepts',['sources'=>[['id'=>'source-a','bounded_excerpt'=>'Exact saved transcript.'],['id'=>'source-b','bounded_excerpt'=>'A different saved transcript.']]]);
        $choices=$schema['properties']['concepts']['items']['properties']['references']['items'];
        $this->assertSame(['reference-1','reference-2'],$choices['properties']['excerpt_key']['enum']);
        $options=app(\App\Services\Marketing\MarketingWorkspaceCreative::class)->referenceOptions(['sources'=>[['id'=>'source-a','bounded_excerpt'=>"Exact\n saved transcript."]]]);
        $this->assertSame("Exact\n saved transcript.",$options[0]['excerpt']);

    }

    public function test_unconfirmed_summary_still_blocks_actions_without_research(): void
    {
        $p = $this->sample();
        MarketingResearchRun::query()->delete();
        Http::fake();
        $url = '/admin/marketing/workspace/'.$p->id.'/creative';
        $p->refresh()->update(['planning_state' => ['status' => 'draft']]);
        $this->post($url, $this->body($p,'concepts'))->assertStatus(422);
        Http::assertNothingSent();
    }
}
