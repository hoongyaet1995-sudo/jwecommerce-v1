<?php
namespace Tests\Concerns;
use App\Models\{MarketingProject,MarketingRevision,MarketingProductionPlan,User};
use App\Services\Marketing\{MarketingWorkflow,MarketingToolPlanning};
use Illuminate\Support\Facades\{Http,Queue,Storage};
use Illuminate\Support\Str;
trait MarketingDynamicFixtures
{
    protected function dynamicFixture():array
    {
        Http::preventStrayRequests();Queue::fake();Storage::fake('local');
        $u=User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]);$this->actingAs($u);
        $p=app(MarketingWorkflow::class)->create($u,['source_type'=>'theme','theme'=>'Confirmed JTG factual introduction']);
        $brief=array_replace($p->brief,['source_confirmed'=>true,'facts_confirmed'=>true,'goal_confirmed'=>true,'audience_confirmed'=>true,'language'=>'English','market'=>'Malaysia','platforms'=>['Facebook']]);$c=['title'=>'JTG uses','ratio'=>'9:16'];
        $p->update(['brief'=>$brief,'concepts'=>[$c],'selected_concept'=>0]);config(['marketing.planning.enabled'=>true,'marketing.planning.project'=>$p->id,'marketing.planning.execution_enabled'=>true]);
        $doc=['summary'=>'Verified introduction','duration'=>15,'cta'=>$brief['cta'],'changes'=>[],'gaps'=>[],'scenes'=>array_map(fn($i)=>['visual'=>'Internal production direction '.$i,'voiceover'=>'','subtitle'=>'Confirmed use '.$i,'duration'=>5,'material'=>'text-only original graphic','asset_ids'=>[]],[1,2,3])];
        $context=['brief'=>$brief,'snapshot'=>$p->snapshot,'concept'=>$c,'production_recipe'=>MarketingToolPlanning::RECIPE,'presentation'=>['brand'=>['name'=>'JTG','descriptor'=>'Container & Logistics']]];
        $service=app(MarketingToolPlanning::class);$proposal=$service->fromSaved($p,$doc);$context['production_strategy']=['proposal'=>$proposal,'review'=>$service->review($p,$doc,$proposal)];
        $r=MarketingRevision::create(['project_id'=>$p->id,'actor_id'=>$u->id,'number'=>1,'document'=>$doc,'context'=>$context,'confirmed_at'=>now(),'confirmed_by'=>$u->id]);$p->update(['current_revision_id'=>$r->id,'confirmed_revision_id'=>$r->id,'status'=>'prepared','stage'=>'confirmed']);
        return [$p->fresh(),$r];
    }
    protected function dynamicStart($p,$r):array
    {
        $url='/admin/marketing/projects/'.$p->id.'/production';$this->post($url,['version'=>$p->version,'revision_id'=>$r->id,'request_id'=>(string)Str::uuid()])->assertRedirect();$plan=MarketingProductionPlan::sole();
        $this->assertSame(0,$plan->manifest['budget']['cap_microusd']);$this->post($url.'/'.$plan->id.'/confirm',['version'=>$p->version,'budget_confirmation'=>1])->assertRedirect();
        $this->post($url.'/'.$plan->id.'/start',['version'=>$p->version])->assertRedirect();return [$plan,\App\Models\MarketingProduction::sole()];
    }
    protected function fakeDynamicRenderer(bool $failFirst=false):void
    {
        $renderer=$this->mock(\App\Services\Marketing\MarketingVideoRenderer::class);$renderer->shouldReceive('assertReady')->andReturnNull();$calls=0;
        $renderer->shouldReceive('render')->times($failFirst?2:1)->andReturnUsing(function($t,$plan,$directory)use(&$calls,$failFirst){$calls++;if($failFirst&&$calls===1)throw new \App\Services\Marketing\MarketingProductionFailure('render_failed');$bytes=str_repeat('verified local render fixture',100);file_put_contents($directory.'/output.mp4',$bytes);return ['sha256'=>hash('sha256',$bytes),'frames'=>360,'duration_seconds'=>15,'width'=>720,'height'=>1280,'scene_timings'=>[['frames'=>120],['frames'=>120],['frames'=>120]]];});
    }
}
