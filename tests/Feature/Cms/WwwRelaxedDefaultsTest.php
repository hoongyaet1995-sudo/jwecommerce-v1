<?php
namespace Tests\Feature\Cms;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Domain\Cms\{WwwRelaxedDefaults,PageCrudService,PagePublicationService,PageDraftService,HomepageSelection};
use App\Models\{Page,PageDocument};
final class WwwRelaxedDefaultsTest extends TestCase
{
 use RefreshDatabase;
 private function plan():array {
  $this->assertSame('sqlite',config('database.default'));
  $service=app(PageCrudService::class);$service->create(['title'=>'Outside scope','slug'=>'outside-scope','template'=>'landing','sections'=>[]]);
  $counts=[3,10,3,3,8,8,9,8,8,9,6,7,7,7,11,9,7];
  $style=json_decode(file_get_contents(base_path('resources/cms/releases/20261115-connected-starters.json')),true)['pages'][0]['document']['style'];
  foreach(range(2,18) as $i){$sections=[];foreach(range(1,$counts[$i-2]) as $n)$sections[]=['id'=>'page-'.$i.'-section-'.$n,'type'=>'rich_text','title'=>'Original title','content'=>'Keep copy and layout','isVisible'=>true,'motionPreset'=>in_array($i,[2,4,5],true)||$i===8&&$n===1?'none':'subtle-lift'];
   $doc=['title'=>'Page '.$i,'slug'=>'page-'.$i,'template'=>'landing','sections'=>$sections];if(!in_array($i,[3,6,7],true))$doc['style']=$style;
   $p=$service->create($doc);$this->assertSame($i,$p->id);app(PagePublicationService::class)->publish($p,1);
   if(in_array($i,[3,14],true)){$doc['title']='Unpublished employee draft '.$i;app(PageDraftService::class)->save($p,$doc,1);}
  }
  app(HomepageSelection::class)->select(Page::findOrFail(11),1);
  $rows=PageDocument::whereIn('page_id',range(2,18))->orderBy('id')->get()->map(function($d){$next=$d->document;if(isset($next['style']))$next['style']['motionPace']='relaxed';return ['id'=>$d->id,'pageId'=>$d->page_id,'stage'=>$d->stage,'version'=>$d->lock_version,'hash'=>$d->content_hash,'afterHash'=>\App\Domain\Cms\PageDocumentHasher::hash($next)];})->all();
  return ['release'=>'www-relaxed-default-20261115','home'=>'11','pageIds'=>range(2,18),'documents'=>$rows,'publishedSectionCount'=>123,'publishedStaticCount'=>10];
 }
 public function test_patches_only_default_pace_in_both_stages_and_is_idempotent():void {
  $plan=$this->plan();$before=PageDocument::all()->keyBy('id')->map(fn($d)=>$d->document)->all();$result=app(WwwRelaxedDefaults::class)->run($plan);$this->assertSame('updated',$result['status']);$this->assertSame(28,$result['changed']);$this->assertSame(10,$result['staticSections']);
  foreach(PageDocument::all() as $d){$expected=$before[$d->id];if($d->page_id!==1&&isset($expected['style']))$expected['style']['motionPace']='relaxed';$this->assertSame($expected,$d->document);}
  $this->assertSame('Unpublished employee draft 14',Page::findOrFail(14)->draftDocument->document['title']);$this->assertSame('Page 14',Page::findOrFail(14)->publishedDocument->document['title']);
  $this->assertSame('11',app(HomepageSelection::class)->id());$versions=PageDocument::pluck('lock_version','id')->all();$this->assertSame('already-updated',app(WwwRelaxedDefaults::class)->run($plan)['status']);$this->assertSame($versions,PageDocument::pluck('lock_version','id')->all());
 }
 public function test_stale_document_aborts_atomically():void {
  $plan=$this->plan();$d=Page::findOrFail(14)->draftDocument;$d->increment('lock_version');$before=PageDocument::pluck('content_hash','id')->all();
  try{app(WwwRelaxedDefaults::class)->run($plan);$this->fail('Stale plan accepted');}catch(\Illuminate\Validation\ValidationException $e){$this->assertNotEmpty($e->errors());}$this->assertSame($before,PageDocument::pluck('content_hash','id')->all());
 }
 public function test_rejects_wrong_scope_before_writes():void {
  $plan=$this->plan();$plan['pageIds'][]=1;$before=PageDocument::pluck('content_hash','id')->all();
  try{app(WwwRelaxedDefaults::class)->run($plan);$this->fail('Extra page accepted');}catch(\Illuminate\Validation\ValidationException $e){$this->assertNotEmpty($e->errors());}$this->assertSame($before,PageDocument::pluck('content_hash','id')->all());
 }
}
