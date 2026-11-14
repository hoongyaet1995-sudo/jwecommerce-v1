<?php
namespace Tests\Feature\Cms;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Domain\Cms\{PageCrudService,PageDraftService,PageRuntimePayloadFactory,PageDocumentContract,PageDocumentJsonCodec};
final class ConnectedFamiliesAcceptanceTest extends TestCase {
 use RefreshDatabase;
 public function test_all_registered_and_connected_documents_edit_save_refresh_and_runtime_retention():void {
  $this->assertSame('sqlite',config('database.default'),'Use isolated test database only.');
  $codec=app(PageDocumentJsonCodec::class);$report=[];
  $register=function($value) use (&$register):void {if(is_array($value)||is_object($value)){foreach($value as $child)$register($child);return;}if(!is_string($value)||!\App\Domain\Cms\MediaUrlPolicy::isManaged($value))return;\App\Models\CmsMediaAsset::firstOrCreate(['url'=>$value],['id'=>pathinfo($value,PATHINFO_FILENAME),'disk'=>'public','path'=>substr($value,strlen('/storage/')),'mime_type'=>'image/png','size'=>1,'checksum'=>hash('sha256',$value)]);};

  foreach(['registered-documents.json','new-page-documents.json'] as $fixture){$raw=json_decode(file_get_contents(base_path('output/jw-cms-integration-20261114/'.$fixture)),false,flags:JSON_THROW_ON_ERROR);foreach($raw as $index=>$data){$register($data);$document=$codec->decodeDocument(json_encode($data,JSON_THROW_ON_ERROR));$page=app(PageCrudService::class)->create($document);$saved=$page->fresh()->draftDocument->document;$this->assertEquals($document,$saved,$fixture.':'.$index.' create');$saved['sections'][0]['title']='Edited '.$index.' '.$saved['sections'][0]['title'];app(PageDraftService::class)->save($page,$saved,1);$refreshed=$page->fresh()->draftDocument;$this->assertEquals($saved,$refreshed->document,$fixture.':'.$index.' refresh');$this->assertSame(2,$refreshed->lock_version);$payload=app(PageRuntimePayloadFactory::class)->forDraft($page->fresh());$this->assertEquals($saved,$payload['page']['document'],$fixture.':'.$index.' public-renderer payload');$report[]=['fixture'=>$fixture,'index'=>$index,'pageId'=>$page->id,'status'=>'pass'];}}
  $this->assertCount(169,$report);file_put_contents(base_path('output/jw-cms-integration-20261114/database-acceptance.json'),json_encode($report,JSON_PRETTY_PRINT));
 }
 public function test_actual_inspector_edits_save_and_refresh_without_losing_page_siblings():void {
  $this->assertSame('sqlite',config('database.default'));$codec=app(PageDocumentJsonCodec::class);$raw=json_decode(file_get_contents(base_path('output/jw-cms-integration-20261114/mounted-edited-canonical-documents.json')),false,flags:JSON_THROW_ON_ERROR);$register=function($value) use (&$register){if(is_array($value)||is_object($value)){foreach($value as $child)$register($child);return;}if(!is_string($value)||!\App\Domain\Cms\MediaUrlPolicy::isManaged($value))return;\App\Models\CmsMediaAsset::firstOrCreate(['url'=>$value],['id'=>pathinfo($value,PATHINFO_FILENAME),'disk'=>'public','path'=>substr($value,strlen('/storage/')),'mime_type'=>'image/png','size'=>1,'checksum'=>hash('sha256',$value)]);};
  foreach($raw as $index=>$data){$register($data);$doc=$codec->decodeDocument(json_encode($data,JSON_THROW_ON_ERROR));$page=app(PageCrudService::class)->create($doc);app(PageDraftService::class)->save($page,$doc,1);$fresh=$page->fresh();$this->assertEquals($doc,$fresh->draftDocument->document,'Mounted edit '.$index);$this->assertEquals($doc,app(PageRuntimePayloadFactory::class)->forDraft($fresh)['page']['document']);}$this->assertCount(223,$raw);
 }
 public function test_matrix_unknown_cells_and_bad_title_ranges_are_rejected():void {
  $doc=app(PageDocumentJsonCodec::class)->decodeDocument(file_get_contents(base_path('output/jw-cms-integration-20261114/pricing-canonical-fixture.json')));$doc['sections'][0]['pricingMatrix']['groups'][0]['rows'][0]['cells']['orphan']=['type'=>'text','value'=>'bad'];try{app(PageDocumentContract::class)->validate($doc);$this->fail('Orphan cell accepted');}catch(\Illuminate\Validation\ValidationException $e){$this->assertNotEmpty($e->errors());}
 }
}
