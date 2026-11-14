<?php
namespace Tests\Feature\Cms;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Domain\Cms\{ConnectedStarterDrafts,PageCrudService,PageDocumentJsonCodec,PageDocumentHasher};
use App\Models\{Page,PageDocument,StoreSetting};
class ConnectedStarterDraftsTest extends TestCase {
 use RefreshDatabase;
 private function bundle():array{return json_decode(file_get_contents(base_path('resources/cms/releases/20261115-connected-starters.json')),true,flags:JSON_THROW_ON_ERROR);}
 private function existing():Page{$doc=['title'=>'Existing','slug'=>'existing-preserve','description'=>'','template'=>'landing','sections'=>[['id'=>'original','type'=>'rich_text','title'=>'Original title','content'=>'Original copy','isVisible'=>true]]];return app(PageCrudService::class)->create($doc);}
 public function test_creates_eight_independent_drafts_and_preserves_existing_data():void{
  $this->assertSame('sqlite',config('database.default'));$old=$this->existing();StoreSetting::create(['key'=>'storefront_homepage','value'=>['pageId'=>(string)$old->id]]);$hash=$old->draftDocument->content_hash;
  $receipt=app(ConnectedStarterDrafts::class)->install($this->bundle(),'Acceptance Company');
  $this->assertSame(8,$receipt['created']);$this->assertTrue($receipt['protectedUnchanged']);$this->assertCount(8,$receipt['pages']);$this->assertSame(9,Page::count());$this->assertSame($hash,$old->fresh()->draftDocument->content_hash);$this->assertSame((string)$old->id,StoreSetting::where('key','storefront_homepage')->value('value')['pageId']);
  $regions=0;foreach($receipt['pages'] as $row){$p=Page::findOrFail($row['id']);$this->assertSame('draft',$p->status);$this->assertNull($p->publishedDocument);$this->assertStringStartsWith('Acceptance Company',$p->draftDocument->document['title']);$regions+=count($p->draftDocument->document['sections']);}$this->assertSame(63,$regions);$this->assertSame(['created'],\Illuminate\Support\Facades\DB::table('page_revisions')->whereIn('page_id',array_column($receipt['pages'],'id'))->distinct()->pluck('kind')->all());
 }
 public function test_repeated_install_preserves_employee_edits_without_duplicates():void{
  $service=app(ConnectedStarterDrafts::class);$receipt=$service->install($this->bundle(),'Acceptance Company');$page=Page::findOrFail($receipt['pages'][0]['id']);$draft=$page->draftDocument;$doc=$draft->document;$doc['title']='Employee edited page';$draft->update(['document'=>$doc,'content_hash'=>PageDocumentHasher::hash($doc)]);
  $again=$service->install($this->bundle(),'Different brand',$receipt);$this->assertSame(0,$again['created']);$this->assertSame(8,Page::count());$this->assertSame('Employee edited page',$page->fresh()->draftDocument->document['title']);
 }
 public function test_reserved_slug_collision_aborts_without_overwriting():void{
  $old=$this->existing();$draft=$old->draftDocument;$doc=$draft->document;$doc['slug']='jw-connected-home';$draft->update(['slug'=>$doc['slug'],'document'=>$doc,'content_hash'=>PageDocumentHasher::hash($doc)]);
  try{app(ConnectedStarterDrafts::class)->install($this->bundle(),'Acceptance Company');$this->fail('Collision accepted');}catch(\Illuminate\Validation\ValidationException $e){$this->assertNotEmpty($e->errors());}$this->assertSame(1,Page::count());$this->assertSame('Original title',$old->fresh()->draftDocument->document['sections'][0]['title']);
 }
}
