<?php
namespace Tests\Feature\Cms;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Domain\Cms\{PageCrudService,PageDraftService,PageRuntimePayloadFactory,PageDocumentJsonCodec};
final class TextToolbarRoundTripTest extends TestCase {
 use RefreshDatabase;
 public function test_existing_toolbar_fields_save_reload_and_reach_runtime_for_registered_starters():void {
  $this->assertSame('sqlite',config('database.default'));$this->assertSame(':memory:',config('database.connections.sqlite.database'));
  $rows=json_decode(file_get_contents(base_path('tests/Fixtures/Cms/section-library-173.v1.json')),false,flags:JSON_THROW_ON_ERROR);$this->assertCount(173,$rows);
  $codec=app(PageDocumentJsonCodec::class);
  $register=function($value)use(&$register){if(is_array($value)||is_object($value)){foreach($value as $v)$register($v);return;}if(is_string($value)&&\App\Domain\Cms\MediaUrlPolicy::isManaged($value))\App\Models\CmsMediaAsset::firstOrCreate(['url'=>$value],['id'=>(string)\Illuminate\Support\Str::uuid(),'disk'=>'public','path'=>substr($value,strlen('/storage/')),'mime_type'=>'image/png','size'=>1,'checksum'=>hash('sha256',$value)]);};
  foreach($rows as $index=>$raw){
   $register($raw);
   $doc=$codec->decodeDocument(json_encode($raw,JSON_THROW_ON_ERROR));
   $doc['slug']='toolbar-roundtrip-'.$index;
   $s=&$doc['sections'][0];$s['title']='Toolbar heading';$s['typography']=(array)($s['typography']??[]);$s['typography']['title']=['fontFamily'=>'inter','fontSize'=>46,'fontWeight'=>600,'color'=>'#7c3aed','colorOverride'=>true];$s['textAlignment']='right';
   $s['titleEmphasis']=[['start'=>8,'end'=>15]];unset($s);
   $page=app(PageCrudService::class)->create($doc);app(PageDraftService::class)->save($page,$doc,1);
   $this->assertEquals($doc,$page->fresh()->draftDocument->document,'starter '.$index);
   $this->assertEquals($doc,app(PageRuntimePayloadFactory::class)->forDraft($page->fresh())['page']['document'],'runtime '.$index);
  }
 }
}
