<?php
namespace Tests\Feature\Cms;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Domain\Cms\{PageCrudService,PageDraftService,PageRuntimePayloadFactory,PageDocumentJsonCodec,PagePublicationService};
final class SharedSectionLibraryAcceptanceTest extends TestCase {
 use RefreshDatabase;
 public function test_every_new_layout_creates_edits_saves_refreshes_and_publishes_without_changing_an_existing_page():void {
  $this->assertSame('sqlite',config('database.default'));
  $codec=app(PageDocumentJsonCodec::class);
  $existing=app(PageCrudService::class)->create(['title'=>'Preserve existing','slug'=>'preserve-library-test','description'=>'','template'=>'landing','sections'=>[['id'=>'existing','type'=>'rich_text','title'=>'Original','content'=>'Original copy','isVisible'=>true]]]);
  $before=$existing->fresh()->draftDocument->content_hash;
  $docs=json_decode(file_get_contents(base_path('tests/Fixtures/Cms/shared-layouts-19.v1.json')),false,flags:JSON_THROW_ON_ERROR);
  $this->assertCount(19,$docs);
  foreach($docs as $raw){
   $doc=$codec->decodeDocument(json_encode($raw,JSON_THROW_ON_ERROR));
   $page=app(PageCrudService::class)->create($doc);$this->assertEquals($doc,$page->fresh()->draftDocument->document);
   $doc['description']='Edited and saved';app(PageDraftService::class)->save($page,$doc,1);
   $this->assertEquals($doc,$page->fresh()->draftDocument->document);
   app(PagePublicationService::class)->publish($page,2);
   $payload=app(PageRuntimePayloadFactory::class)->forOfficialPublishedSlug($doc['slug']);
   $this->assertEquals($doc,$payload['page']['document']);
  }
  $this->assertSame($before,$existing->fresh()->draftDocument->content_hash);
 }
}
