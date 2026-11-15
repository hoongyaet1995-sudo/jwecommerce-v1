<?php
namespace Tests\Feature\Cms;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use App\Domain\Cms\{ConnectedWwwPublication,ConnectedStarterDrafts,PageCrudService,PagePublicationService,HomepageSelection,PageRuntimePayloadFactory};
use App\Models\{Page,PageDocument,MenuItem};
final class ConnectedWwwPublicationTest extends TestCase {
 use RefreshDatabase;
 private function setupPlan():array{
  $this->assertSame('sqlite',config('database.default'));$plan=json_decode(file_get_contents(base_path('resources/cms/releases/20261115-www-connected-publication.json')),true,flags:JSON_THROW_ON_ERROR);
  for($i=0;$i<10;$i++){$p=app(PageCrudService::class)->create(['title'=>'Kept '.$i,'slug'=>'kept-'.$i,'description'=>'','template'=>'landing','sections'=>[['id'=>'old-'.$i,'type'=>'rich_text','title'=>'Original '.$i,'content'=>'Original copy','isVisible'=>true]]]);if($i===0){app(PagePublicationService::class)->publish($p,1);app(HomepageSelection::class)->select($p,1);$plan['expectedHome']=(string)$p->id;}}
  $bundle=json_decode(file_get_contents(base_path('resources/cms/releases/20261115-connected-starters.json')),true,flags:JSON_THROW_ON_ERROR);$receipt=app(ConnectedStarterDrafts::class)->install($bundle,'JW Studio');
  foreach($receipt['pages'] as $i=>$row){$plan['pages'][$i]['id']=$row['id'];$this->assertSame($plan['pages'][$i]['draftHash'],$row['hash']);} $plan['homePage']=$receipt['pages'][0]['id'];
  foreach($plan['expectedMenus'] as $menu)DB::table('menus')->insert(['id'=>$menu['id'],'key'=>$menu['key'],'name'=>$menu['name'],'created_at'=>now(),'updated_at'=>now()]);
  $items=array_merge(...array_column($plan['expectedMenus'],'items'));$inserted=[];while($items){foreach($items as $k=>$item){if($item['parent_id']!==null&&!isset($inserted[$item['parent_id']]))continue;DB::table('menu_items')->insert([...$item,'created_at'=>now(),'updated_at'=>now()]);$inserted[$item['id']]=true;unset($items[$k]);}}
  return $plan;
 }
 public function test_publishes_only_eight_switches_home_and_preserves_old_pages():void{
  $plan=$this->setupPlan();$old=PageDocument::where('page_id','<=',10)->orderBy('id')->pluck('content_hash','id')->all();$result=app(ConnectedWwwPublication::class)->run($plan);$this->assertSame('published',$result['status']);$this->assertTrue($result['oldPagesPreserved']);$this->assertSame((string)$plan['homePage'],app(HomepageSelection::class)->id());$this->assertSame(18,Page::count());$this->assertSame($old,PageDocument::where('page_id','<=',10)->orderBy('id')->pluck('content_hash','id')->all());
  foreach($plan['pages'] as $r){$p=Page::findOrFail($r['id']);$this->assertSame('published',$p->status);$this->assertSame($r['draftHash'],$p->publishedDocument->content_hash);$this->assertSame($r['draftHash'],$p->draftDocument->content_hash);$payload=app(PageRuntimePayloadFactory::class)->forOfficialPublishedSlug($r['draftSlug']);$this->assertSame('public',$payload['context']['mode']);foreach((array)$payload['links'] as $url)$this->assertStringNotContainsString('/admin/pages/',$url);}
  $this->assertSame(2,MenuItem::where('label','Home')->where('url','/')->count());$count=MenuItem::count();$this->assertSame('already-published',app(ConnectedWwwPublication::class)->run($plan)['status']);$this->assertSame($count,MenuItem::count());
 }
 public function test_changed_draft_aborts_without_partial_publication():void{
  $plan=$this->setupPlan();$p=Page::findOrFail($plan['pages'][3]['id']);$p->draftDocument->update(['lock_version'=>2]);try{app(ConnectedWwwPublication::class)->run($plan);$this->fail('Stale version accepted');}catch(\Illuminate\Validation\ValidationException $e){$this->assertNotEmpty($e->errors());}$this->assertSame($plan['expectedHome'],app(HomepageSelection::class)->id());$this->assertSame(0,Page::whereIn('id',array_column($plan['pages'],'id'))->where('status','published')->count());
 }
 public function test_changed_menu_aborts_and_preserves_employee_change():void{
  $plan=$this->setupPlan();MenuItem::findOrFail(63)->update(['label'=>'Employee contact label']);try{app(ConnectedWwwPublication::class)->run($plan);$this->fail('Stale menu accepted');}catch(\Illuminate\Validation\ValidationException $e){$this->assertNotEmpty($e->errors());}$this->assertSame('Employee contact label',MenuItem::findOrFail(63)->label);$this->assertSame($plan['expectedHome'],app(HomepageSelection::class)->id());
 }
 public function test_a_rendered_section_heading_is_still_required():void{
  $plan=$this->setupPlan();$p=Page::findOrFail($plan['homePage']);$d=$p->draftDocument;$doc=$d->document;$doc['sections'][0]['title']='';$hash=\App\Domain\Cms\PageDocumentHasher::hash($doc);$d->update(['document'=>$doc,'content_hash'=>$hash]);$plan['pages'][0]['draftHash']=$hash;
  try{app(ConnectedWwwPublication::class)->run($plan);$this->fail('Visible heading bypassed');}catch(\Illuminate\Validation\ValidationException $e){$this->assertArrayHasKey('sections.0.title',$e->errors());}$this->assertSame($plan['expectedHome'],app(HomepageSelection::class)->id());
 }
}
