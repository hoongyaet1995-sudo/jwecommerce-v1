<?php
namespace Tests\Feature;

use App\Models\{MarketingProject,MarketingResearchRun,Product};
use App\Services\Marketing\{MarketingWorkflow,MarketingCreativePlanning,MarketingResearchWorkflow,MarketingResearchSources};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Concerns\MarketingResearchFixtures;
use Tests\TestCase;

class MarketingOptionalResearchTest extends TestCase
{
    use RefreshDatabase,MarketingResearchFixtures;

    private function sample(int $sources=0):MarketingProject
    {
        $p=$this->researchProject('New desk lamp');
        $product=Product::create(['title'=>'New desk lamp','handle'=>'optional-research-lamp','status'=>'active','base_price'=>25,'description'=>'A nickel-plated desk lamp.']);
        $brief=$p->brief;
        $p=app(MarketingWorkflow::class)->create(\App\Models\User::findOrFail($p->owner_id),['source_type'=>'product','product_id'=>$product->id]);
        $p->update(['brief'=>$brief]);
        $p->refresh()->update(['planning_state'=>['status'=>'confirmed'],'preferences'=>['video_type'=>'product_showcase'],'brief'=>array_replace($p->brief,['source_confirmed'=>true,'facts_confirmed'=>true,'goal_confirmed'=>true,'audience_confirmed'=>true,'duration_seconds'=>15])]);
        config(['marketing.billing.enabled'=>true,'marketing.conversation.enabled'=>true]);
        if($sources){
            $run=app(MarketingResearchWorkflow::class)->start($p->id,$p->owner_id,$p->fresh()->version,(string)Str::uuid());
            $rows=app(MarketingResearchSources::class)->normalize(array_map(fn($i)=>['url'=>'https://youtube.com/watch?v=examplevid'.$i,'raw_content'=>"### Transcript\n[0:01] Introduce the use first.\n[0:06] Then show the detail.",'_role'=>'same_industry','_extraction_status'=>'content_available'],range(1,$sources)));
            $run->update(['status'=>'partial','sources'=>$rows]);$p->refresh()->update(['active_research_run_id'=>null]);
        }
        return $p->fresh();
    }
    private function body(MarketingProject $p,string $action,array $extra=[]):array{return $extra+['request_id'=>(string)Str::uuid(),'version'=>$p->fresh()->version,'action'=>$action];}
    private function providerOutput(array $data):array{return ['status'=>'completed','output'=>[['content'=>[['type'=>'output_text','text'=>json_encode($data)]]]]];}
    private function concepts(bool $reference=false):array{return array_map(fn($i)=>['title'=>'Lamp direction '.$i,'approach'=>['use_scenario','feature_detail','question_answer'][$i-1],'hook'=>'Distinct opening '.$i,'story'=>'Original lamp story with a different focus '.$i,'materials'=>'Planned lamp setting','duration'=>15,'ratio'=>'9:16','video_type'=>'product_showcase','visual_treatment'=>'The lamp appears in a fictional setting','recommended'=>$i===1,'reason'=>'Fits the product and user request','references'=>$reference?[['excerpt_key'=>'reference-1','adaptation'=>'Borrow the explain-then-show structure']]:[]],range(1,3));}
    private function draft(MarketingProject $p):array{return ['summary'=>'Selected original lamp story','duration'=>15,'cta'=>$p->brief['cta'],'changes'=>[],'requirements'=>[],'scenes'=>[['visual'=>'The lamp appears in a fictional desk setting','voiceover'=>'Meet this desk lamp.','subtitle'=>'Desk lamp','duration'=>15,'material'=>'Planned future AI image using product reference','asset_ids'=>[]]]];}

    public function test_new_product_without_research_can_generate_select_and_prepare_storyboard_once():void
    {
        $p=$this->sample();$p->update(['brief'=>array_replace($p->brief,['tone'=>'清新自然'])]);$before=$p->snapshot;$url='/admin/marketing/workspace/'.$p->id.'/creative';
        $director=MarketingDirectorContractTest::directorOutput();$director['shots'][0]['voiceover']=$this->draft($p)['scenes'][0]['voiceover'];$director['shots'][0]['subtitle']=$this->draft($p)['scenes'][0]['subtitle'];
        Http::fakeSequence()->push($this->providerOutput(['concepts'=>$this->concepts()]))->push($this->providerOutput($this->draft($p)))->push($this->providerOutput($director));
        $request=$this->body($p,'concepts',['feedback'=>'Imagine a playful setting around the lamp.']);
        $this->post($url,$request)->assertRedirect()->assertSessionHasNoErrors();
        $this->post($url,$request)->assertRedirect()->assertSessionHasNoErrors();
        $p->refresh();$this->assertCount(3,$p->concepts);$this->assertSame([],$p->concepts[0]['references']);Http::assertSentCount(1);
        Http::assertSent(fn($r)=>$r->method()==='POST'&&str_ends_with($r->url(),'/responses')&&str_contains($r['input'],'New desk lamp')&&str_contains($r['input'],'Imagine a playful setting')&&str_contains($r['input'],'柔和自然光')&&data_get($r->data(),'text.format.schema.properties.concepts.items.properties.references.maxItems')===0);
        $this->post($url,$this->body($p,'select',['index'=>0]))->assertRedirect()->assertSessionHasNoErrors();
        $p->refresh();$this->assertNotNull($p->current_revision_id);$this->assertSame($before,$p->snapshot);$this->assertNull($p->currentRevision->context['research']);
        Http::assertSentCount(3);$this->assertSame(0,\App\Models\MarketingProduction::count());$this->assertSame(0,\App\Models\MarketingStoryboardProviderCall::count());
        $this->get('/admin/marketing/workspace?project='.$p->id.'&screen=concepts')->assertOk()->assertSee('商品资料和用户要求')->assertDontSee('缺少足够的已保存研究参考');
    }
    public static function referenceCounts():array{return ['one_available'=>[1],'several_available_one_used'=>[3]];}
    #[\PHPUnit\Framework\Attributes\DataProvider('referenceCounts')]
    public function test_one_saved_reference_is_enough_to_borrow_with_honest_citation(int $available):void
    {
        $p=$this->sample($available);$research=app(MarketingCreativePlanning::class)->research($p);
        Http::fakeSequence()->push($this->providerOutput(['concepts'=>$this->concepts(true)]));
        $this->post('/admin/marketing/workspace/'.$p->id.'/creative',$this->body($p,'concepts'))->assertRedirect()->assertSessionHasNoErrors();
        $p->refresh();$this->assertSame($research['sources'][0]['id'],$p->concepts[0]['references'][0]['source_id']);$this->assertSame('text',$p->concepts[0]['references'][0]['basis']);$this->assertSame($research['digest'],$p->concepts[0]['_research']['digest']);Http::assertSentCount(1);
    }
    public function test_selected_story_survives_disappearance_of_optional_research():void
    {
        $p=$this->sample(2);$research=app(MarketingCreativePlanning::class)->research($p);$concepts=$this->concepts();
        foreach($concepts as &$c){$c['references']=[['source_id'=>$research['sources'][0]['id'],'excerpt'=>'Introduce the use first.','adaptation'=>'Borrow the structure','basis'=>'text']];$c['_research']=['digest'=>$research['digest']];}unset($c);
        $p->update(['concepts'=>$concepts,'selected_concept'=>0]);MarketingResearchRun::query()->delete();
        Http::fakeSequence()->push($this->providerOutput($this->draft($p)));
        $this->post('/admin/marketing/workspace/'.$p->id.'/creative',$this->body($p,'storyboard'))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($concepts,$p->fresh()->concepts);$this->assertNotNull($p->fresh()->current_revision_id);Http::assertSentCount(1);
    }
    public function test_reference_changes_do_not_force_regeneration_of_selected_story():void
    {
        $p=$this->sample(1);$research=app(MarketingCreativePlanning::class)->research($p);$concepts=$this->concepts();
        foreach($concepts as &$c)$c['_research']=['digest'=>$research['digest']];unset($c);
        $p->update(['concepts'=>$concepts,'selected_concept'=>0]);
        $run=MarketingResearchRun::where('project_id',$p->id)->firstOrFail();$sources=$run->sources;$sources[0]['bounded_excerpt'].=' A new optional note.';$run->update(['sources'=>$sources]);
        $this->assertNotSame($research['digest'],app(MarketingCreativePlanning::class)->research($p)['digest']);
        Http::fakeSequence()->push($this->providerOutput($this->draft($p)));
        $this->post('/admin/marketing/workspace/'.$p->id.'/creative',$this->body($p,'storyboard'))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($concepts,$p->fresh()->concepts);$this->assertNotNull($p->fresh()->current_revision_id);Http::assertSentCount(1);
    }
    public function test_invented_reference_is_rejected_without_search_or_replacing_saved_story():void
    {
        $p=$this->sample();$old=$this->concepts();$p->update(['concepts'=>$old,'selected_concept'=>0]);
        $reply=$this->concepts();$reply[0]['references']=[['excerpt_key'=>'invented-source','adaptation'=>'Unsupported citation']];
        Http::fakeSequence()->push($this->providerOutput(['concepts'=>$reply]));
        $this->post('/admin/marketing/workspace/'.$p->id.'/creative',$this->body($p,'concepts'))->assertSessionHasErrors();
        $this->assertSame($old,$p->fresh()->concepts);$this->assertSame(0,$p->fresh()->selected_concept);Http::assertSentCount(1);
    }
}
