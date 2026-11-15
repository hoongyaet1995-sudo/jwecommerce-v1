<?php
namespace Tests\Feature\Cms;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Domain\Cms\{WwwWidthDefaults,PageCrudService,PagePublicationService,PageDraftService,PageDocumentHasher,HomepageSelection};
use App\Models\{Page,PageDocument};
final class WwwWidthDefaultsTest extends TestCase
{
 use RefreshDatabase;
 private function plan():array {
  $this->assertSame('sqlite',config('database.default'));
  foreach(range(1,18) as $i){$doc=['title'=>'Page '.$i,'slug'=>'page-'.$i,'template'=>'landing','containerWidth'=>$i>=11?'full':'wide','sections'=>[['id'=>'section-'.$i,'type'=>'rich_text','isVisible'=>true,'title'=>'Preserve layout','containerMode'=>$i===14?'full-bleed':'page']]];$p=app(PageCrudService::class)->create($doc);app(PagePublicationService::class)->publish($p,1);if($i===14){$doc['title']='Unpublished employee changes';app(PageDraftService::class)->save($p,$doc,1);}}
  app(HomepageSelection::class)->select(Page::findOrFail(11),1);
  return ['release'=>'www-width-default-20261115','home'=>'11','pageIds'=>range(11,18),'documents'=>PageDocument::whereIn('page_id',range(11,18))->orderBy('id')->get()->map(function($d){$next=$d->document;$next['containerWidth']='site';return ['id'=>$d->id,'pageId'=>$d->page_id,'stage'=>$d->stage,'version'=>$d->lock_version,'hash'=>$d->content_hash,'afterHash'=>PageDocumentHasher::hash($next)];})->all()];
 }
 public function test_changes_only_width_in_each_stage_preserves_special_layouts_and_is_idempotent():void {
  $plan=$this->plan();$before=PageDocument::all()->keyBy('id')->map(fn($d)=>$d->document)->all();$service=app(WwwWidthDefaults::class);$this->assertSame(16,$service->run($plan)['changed']);
  foreach(PageDocument::all() as $d){$expected=$before[$d->id];if($d->page_id>=11)$expected['containerWidth']='site';$this->assertSame($expected,$d->document);}
  $this->assertSame('Unpublished employee changes',Page::find(14)->draftDocument->document['title']);$this->assertSame('Page 14',Page::find(14)->publishedDocument->document['title']);$this->assertSame('11',app(HomepageSelection::class)->id());
  $versions=PageDocument::pluck('lock_version','id')->all();$this->assertSame(0,$service->run($plan)['changed']);$this->assertSame($versions,PageDocument::pluck('lock_version','id')->all());
 }
 public function test_stale_plan_and_extra_scope_are_rejected_without_writes():void {
  $plan=$this->plan();Page::find(14)->draftDocument->increment('lock_version');$before=PageDocument::pluck('content_hash','id')->all();
  try{app(WwwWidthDefaults::class)->run($plan);$this->fail('Stale plan accepted');}catch(\Illuminate\Validation\ValidationException $e){$this->assertNotEmpty($e->errors());}
  $this->assertSame($before,PageDocument::pluck('content_hash','id')->all());$plan['pageIds'][]=1;
  try{app(WwwWidthDefaults::class)->run($plan);$this->fail('Extra page accepted');}catch(\Illuminate\Validation\ValidationException $e){$this->assertNotEmpty($e->errors());}
  $this->assertSame($before,PageDocument::pluck('content_hash','id')->all());
 }
}
