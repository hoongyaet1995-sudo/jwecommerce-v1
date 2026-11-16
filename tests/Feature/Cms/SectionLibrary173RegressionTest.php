<?php
namespace Tests\Feature\Cms;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Domain\Cms\{PageCrudService,PageDraftService,PageRuntimePayloadFactory,PageDocumentJsonCodec};
use App\Models\CmsMediaAsset;
use Illuminate\Support\Str;

final class SectionLibrary173RegressionTest extends TestCase {
 use RefreshDatabase;
 public function test_all_173_starters_save_refresh_and_retain_frontend_payload_without_changing_existing_content():void {
  $this->assertSame('sqlite',config('database.default'));
  $this->assertSame(':memory:',config('database.connections.sqlite.database'));
  $codec=app(PageDocumentJsonCodec::class);
  $old=app(PageCrudService::class)->create(['title'=>'Keep old page','slug'=>'keep-audit173','template'=>'landing','description'=>'','sections'=>[['id'=>'keep','type'=>'rich_text','title'=>'Keep heading','content'=>'Keep copy','isVisible'=>true]]]);
  $hash=$old->fresh()->draftDocument->content_hash;
  $register=function($value)use(&$register){if(is_array($value)||is_object($value)){foreach($value as $v)$register($v);return;}if(is_string($value)&&\App\Domain\Cms\MediaUrlPolicy::isManaged($value))CmsMediaAsset::firstOrCreate(['url'=>$value],['id'=>(string)Str::uuid(),'disk'=>'public','path'=>substr($value,strlen('/storage/')),'mime_type'=>'image/png','size'=>1,'checksum'=>hash('sha256',$value)]);};
  $rows=json_decode(file_get_contents(base_path('tests/Fixtures/Cms/section-library-173.v1.json')),false,flags:JSON_THROW_ON_ERROR);
  $this->assertCount(173,$rows);$report=[];
  foreach($rows as $index=>$row){
   $register($row);$doc=$codec->decodeDocument(json_encode($row,JSON_THROW_ON_ERROR));
   $page=app(PageCrudService::class)->create($doc);$this->assertEquals($doc,$page->fresh()->draftDocument->document,$doc['title'].' create');
   $doc['sections'][0]['title']='Saved '.$doc['sections'][0]['title'];
   app(PageDraftService::class)->save($page,$doc,1);
   $this->assertEquals($doc,$page->fresh()->draftDocument->document,$doc['title'].' refresh');
   $this->assertEquals($doc,app(PageRuntimePayloadFactory::class)->forDraft($page->fresh())['page']['document'],$doc['title'].' frontend payload');
   $report[]=['card'=>$index+1,'name'=>$row->title,'status'=>'pass'];
  }
  $this->assertSame($hash,$old->fresh()->draftDocument->content_hash);
  if(!is_dir(base_path('output/jw-cms-integration-20261114/section-test-audit-20261116')))mkdir(base_path('output/jw-cms-integration-20261114/section-test-audit-20261116'),0777,true);
  file_put_contents(base_path('output/jw-cms-integration-20261114/section-test-audit-20261116/backend-173-report.json'),json_encode($report,JSON_PRETTY_PRINT));
 }
}
