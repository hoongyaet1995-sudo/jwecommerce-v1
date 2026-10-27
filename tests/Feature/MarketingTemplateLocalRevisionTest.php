<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use App\Models\{User,Product,ProductImage,MarketingProject,MarketingTemplateRun};
class MarketingTemplateLocalRevisionTest extends TestCase{
 use RefreshDatabase;
 public function test_confirmed_display_copy_edit_creates_local_version_and_reuses_saved_voice():void{
  Queue::fake();$u=User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]);$this->actingAs($u);$product=Product::create(['title'=>'Any catalog item','handle'=>'local-edit-item','base_price'=>1,'status'=>'active']);ProductImage::create(['product_id'=>$product->id,'path'=>'/images/themes/food/tea.png','position'=>0]);
  $p=MarketingProject::create(['owner_id'=>$u->id,'product_id'=>$product->id,'source_type'=>'product','name'=>'Local edit','snapshot'=>[],'brief'=>[],'preferences'=>['language'=>'English'],'version'=>1,'template_id'=>'product_showcase_15s']);
  $input=['project_version'=>1,'packet'=>['language'=>'en'],'asset_index'=>[],'preferences'=>['narration'=>true,'subtitles'=>true,'formats'=>['landscape'],'music'=>true]];$voice=['path'=>'saved.mp3','sha256'=>str_repeat('a',64)];
  $old=MarketingTemplateRun::create(['project_id'=>$p->id,'owner_id'=>$u->id,'request_id'=>(string)Str::uuid(),'input_digest'=>str_repeat('b',64),'input'=>$input,'draft'=>['copy'=>['hook'=>'Original']],'status'=>'completed','result'=>['voice'=>$voice]]);
  $this->mock(\App\Services\Marketing\MarketingTemplateContract::class,fn($m)=>$m->shouldReceive('call')->with('validate',\Mockery::any())->once()->andReturn(['status'=>'awaiting_semantic_check']));$this->mock(\App\Services\Marketing\MarketingProductTemplateRenderer::class,fn($m)=>$m->shouldReceive('validateDraft')->once());
  $data=['version'=>1,'run_id'=>$old->id,'request_id'=>(string)Str::uuid(),'copy'=>['hook'=>'A complete new hook'],'confirmed'=>true];$url='/admin/marketing/templates/projects/'.$p->id.'/local-copy';
  $this->postJson($url,$data)->assertOk()->assertJsonPath('run.status','queued_production');$this->postJson($url,$data)->assertOk();
  $new=MarketingTemplateRun::where('id','!=',$old->id)->firstOrFail();$this->assertSame($voice,$new->result['voice']);$this->assertSame('Original',$old->fresh()->draft['copy']['hook']);$this->assertSame('A complete new hook',$new->draft['copy']['hook']);$this->assertSame('merchant_confirmed_display_copy',$new->checks['mode']);$this->assertDatabaseCount('marketing_template_calls',0);Queue::assertPushed(\App\Jobs\MarketingTemplateJob::class,1);
 }
}
