<?php
namespace Tests\Feature;
use App\Models\{MarketingRevision,MarketingStoryboardTask};
use App\Services\Marketing\{MarketingStoryboardWorkspace,MarketingPreparationBudget};

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Concerns\MarketingResearchFixtures;
use Tests\TestCase;

class MarketingStep3InteractionTest extends TestCase
{
    use MarketingResearchFixtures; protected function setUp():void {parent::setUp();$this->artisan('migrate:fresh')->run();}
    public function test_active_batch_allows_read_and_replay_but_blocks_new_mutation_until_complete():void
    {
        $p=$this->researchProject();$r=MarketingRevision::create(['project_id'=>$p->id,'number'=>1,'actor_id'=>$p->owner_id,'document'=>['scenes'=>[['visual'=>'Tea cup','voiceover'=>'','subtitle'=>'','duration'=>5]]],'context'=>[]]);$p->update(['current_revision_id'=>$r->id]);
        $service=app(MarketingStoryboardWorkspace::class);$request=(string)Str::uuid();$s=$service->initialize($p->id,$p->owner_id,$request,$p->version);
        $auth=app(MarketingPreparationBudget::class)->authorize($p->id,$p->owner_id,(string)Str::uuid(),$s['story_version'],[$s['scenes'][0]['revision_id']],100000);
        $task=MarketingStoryboardTask::create(['project_id'=>$p->id,'authorization_id'=>$auth['id'],'revision_id'=>$s['scenes'][0]['revision_id'],'signature'=>$s['scenes'][0]['image_signature'],'status'=>'queued','result'=>[]]);
        $this->assertSame(1,$service->snapshot($p->id,$p->owner_id)['preparation_progress']['active']);
        $this->assertSame($s,$service->initialize($p->id,$p->owner_id,$request,$p->version));
        try{$service->transact($p->id,$p->owner_id,(string)Str::uuid(),['fixture-mutation'],fn()=> $this->fail('Active batch allowed mutation'));$this->fail('Expected lock');}catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){$this->assertSame(409,$e->getStatusCode());}
        $task->update(['status'=>'completed']);$called=false;$service->transact($p->id,$p->owner_id,(string)Str::uuid(),['fixture-mutation'],function()use(&$called){$called=true;});$this->assertTrue($called);Http::assertNothingSent();
    }
    public function test_production_markup_uses_approved_local_component_without_demo_tools():void
    {
        $p=$this->researchProject();$p->update(['selected_concept'=>0,'concepts'=>[['title'=>'Tea story']]]);
        $this->get('/admin/marketing/workspace?project='.$p->id.'&screen=materials')->assertOk()->assertSee('storyboard-state.css')->assertSee('sb-phase-title')->assertSee('sb-image-quote-dialog')->assertDontSee('预览状态')->assertDontSee('演示金额');Http::assertNothingSent();
    }
    public function test_image_quote_never_executes_before_consent_and_confirmation_replay_executes_once():void
    {
        config(['marketing.billing.enabled'=>true]);$p=$this->researchProject();
        $r=MarketingRevision::create(['project_id'=>$p->id,'number'=>1,'actor_id'=>$p->owner_id,'document'=>['scenes'=>[['visual'=>'Tea cup','voiceover'=>'','subtitle'=>'','duration'=>5]]],'context'=>[]]);$p->update(['current_revision_id'=>$r->id]);
        $s=app(MarketingStoryboardWorkspace::class)->initialize($p->id,$p->owner_id,(string)Str::uuid(),$p->version);
        $calls=0;$this->mock(\App\Services\Marketing\MarketingStoryboardClosure::class)->shouldReceive('prepareMissing')->once()->andReturnUsing(function()use(&$calls,$s){$calls++;return ['snapshot'=>$s,'preparation'=>['tasks'=>[]]];});
        $quote=$this->postJson(route('marketing.workspace.storyboard.write',[$p->id,'billing-quote']),['request_id'=>(string)Str::uuid(),'version'=>$s['story_version'],'operation'=>'missing-images-prepare','payload'=>['preview_key'=>str_repeat('a',64),'consent'=>true]])->assertOk()->json('quote');
        $this->assertSame(0,$calls);$this->assertSame(0,MarketingStoryboardTask::count());
        $body=['request_id'=>(string)Str::uuid(),'version'=>$s['story_version'],'quote_id'=>$quote['id'],'consent'=>true];$url=route('marketing.workspace.storyboard.write',[$p->id,'billing-confirm']);
        $this->postJson($url,array_replace($body,['consent'=>false]))->assertStatus(422);$this->assertSame(0,$calls);
        $this->postJson($url,$body)->assertOk();$this->postJson($url,$body)->assertOk();$this->assertSame(1,$calls);
        $this->assertSame('accepted',\App\Models\MarketingGenerationQuote::find($quote['id'])->status);Http::assertNothingSent();
    }
    public function test_worker_denial_records_reason_and_does_not_call_or_retry():void
    {
        $p=$this->researchProject();$r=MarketingRevision::create(['project_id'=>$p->id,'number'=>1,'actor_id'=>$p->owner_id,'document'=>['scenes'=>[['visual'=>'Tea cup','duration'=>5]]],'context'=>[]]);$p->update(['current_revision_id'=>$r->id]);
        $s=app(MarketingStoryboardWorkspace::class)->initialize($p->id,$p->owner_id,(string)Str::uuid(),$p->version);
        $a=app(MarketingPreparationBudget::class)->authorize($p->id,$p->owner_id,(string)Str::uuid(),$s['story_version'],[$s['scenes'][0]['revision_id']],550000);
        $t=MarketingStoryboardTask::create(['project_id'=>$p->id,'authorization_id'=>$a['id'],'revision_id'=>$s['scenes'][0]['revision_id'],'signature'=>$s['scenes'][0]['image_signature'],'status'=>'queued','result'=>[]]);
        config(['marketing.billing.enabled'=>false,'marketing.storyboard.live_enabled'=>false,'marketing.storyboard.live_grant'=>[]]);
        app(\App\Services\Marketing\MarketingStoryboardPreparation::class)->process($t->id);
        $this->assertSame('blocked',$t->fresh()->status);
        $this->assertSame('worker_authorization_unavailable',$t->fresh()->result['block_code']??null);
        $this->assertNotEmpty($t->fresh()->result['message']??null);Http::assertNothingSent();
    }
    public function test_worker_restores_confirmed_quote_without_request_context_and_reserves_once():void
    {
        config(['marketing.billing.enabled'=>true]);$p=$this->researchProject();$r=MarketingRevision::create(['project_id'=>$p->id,'number'=>1,'actor_id'=>$p->owner_id,'document'=>['scenes'=>[['visual'=>'Tea cup','duration'=>5]]],'context'=>[]]);$p->update(['current_revision_id'=>$r->id]);
        $s=app(MarketingStoryboardWorkspace::class)->initialize($p->id,$p->owner_id,(string)Str::uuid(),$p->version);
        $qsvc=app(\App\Services\Marketing\MarketingGenerationQuotes::class);$q=$qsvc->quote($p->id,$p->owner_id,$s['story_version'],'missing-images-prepare',[]);$q=$qsvc->claim($p->id,$p->owner_id,$q->id,true)['quote'];
        config(['marketing.billing.execution_quote_id'=>$q->id]);$qsvc->activate($q);
        $a=app(MarketingPreparationBudget::class)->authorize($p->id,$p->owner_id,(string)Str::uuid(),$s['story_version'],[$s['scenes'][0]['revision_id']],550000);$qsvc->finish($q,['tasks'=>[]]);
        config(['marketing.billing.execution_quote_id'=>null,'marketing.storyboard.live_grant'=>[],'marketing.storyboard.live_enabled'=>false]);
        $this->assertTrue($qsvc->activateAuthorization($a['id']));$provider=app(\App\Services\Marketing\MarketingStoryboardProvider::class);
        $this->assertTrue($provider->allowed('image',$p->id));$this->assertTrue($provider->allowed('qa',$p->id));$this->assertFalse($provider->allowed('video',$p->id));
        $sent=0;$payload=['revision_id'=>$s['scenes'][0]['revision_id'],'fixture'=>'worker-boundary'];$transport=function()use(&$sent){$sent++;return ['actual_microusd'=>10000,'fixture'=>'done'];};
        $provider->call('image',$p->id,$a['id'],$payload,$transport);$provider->call('image',$p->id,$a['id'],$payload,$transport);
        $this->assertSame(1,$sent);$this->assertSame(1,\App\Models\MarketingStoryboardProviderCall::where('project_id',$p->id)->count());Http::assertNothingSent();
    }    public function test_progress_tasks_remain_json_list_when_completed_scene_is_filtered():void
    {
        $p=$this->researchProject();$r=MarketingRevision::create(['project_id'=>$p->id,'number'=>1,'actor_id'=>$p->owner_id,'document'=>['scenes'=>[['visual'=>'Tea garden','duration'=>5],['visual'=>'Tea cup','duration'=>5]]],'context'=>[]]);$p->update(['current_revision_id'=>$r->id]);
        $svc=app(MarketingStoryboardWorkspace::class);$s=$svc->initialize($p->id,$p->owner_id,(string)Str::uuid(),$p->version);
        $a=app(MarketingPreparationBudget::class)->authorize($p->id,$p->owner_id,(string)Str::uuid(),$s['story_version'],array_column($s['scenes'],'revision_id'),1100000);
        foreach($s['scenes'] as $n=>$scene)MarketingStoryboardTask::create(['id'=>'00000000-0000-4000-8000-'.str_pad((string)($n+1),12,'0',STR_PAD_LEFT),'project_id'=>$p->id,'authorization_id'=>$a['id'],'revision_id'=>$scene['revision_id'],'signature'=>$scene['image_signature'],'status'=>'requesting','result'=>[]]);
        $first=$s['scenes'][0]['scene_id'];$this->partialMock(\App\Services\Marketing\MarketingStoryboardPackage::class)->shouldReceive('ready')->andReturnUsing(fn($scene)=>$scene['scene_id']===$first);
        $tasks=$svc->snapshot($p->id,$p->owner_id)['preparation_progress']['tasks'];$this->assertCount(1,$tasks);$this->assertTrue(array_is_list($tasks));Http::assertNothingSent();
    }}
