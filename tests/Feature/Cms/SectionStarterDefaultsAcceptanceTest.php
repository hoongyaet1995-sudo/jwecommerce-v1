<?php
namespace Tests\Feature\Cms;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Domain\Cms\{PageCrudService,PageDraftService,PageRuntimePayloadFactory,PageDocumentJsonCodec,MediaUrlPolicy};

final class SectionStarterDefaultsAcceptanceTest extends TestCase {
 use RefreshDatabase;
 public function test_new_defaults_and_employee_edits_round_trip_all_173_without_touching_old_pages():void {
  $this->assertSame('sqlite',config('database.default'));$this->assertSame(':memory:',config('database.connections.sqlite.database'));
  $codec=app(PageDocumentJsonCodec::class);$directory=base_path('tests/Fixtures/Cms/unified-section-defaults-v1');
  $reportDirectory=base_path('output/section-defaults-integration-20261116');if(!is_dir($reportDirectory))mkdir($reportDirectory,0777,true);
  $old=app(PageCrudService::class)->create(['title'=>'Existing employee page','slug'=>'keep-old-defaults','template'=>'landing','sections'=>[['id'=>'legacy','type'=>'rich_text','title'=>'Employee heading','content'=>'Employee copy','isVisible'=>true,'containerMode'=>'full-bleed','customColors'=>['titleColor'=>'#aabbcc']]]]);
  $before=$old->fresh()->draftDocument->content_hash;$reports=[];
  foreach(['documents-173.json','documents-edited-173.json'] as $file){
   $rows=json_decode(file_get_contents($directory.'/'.$file),false,flags:JSON_THROW_ON_ERROR);$this->assertCount(173,$rows);
   foreach($rows as $i=>$raw){
    try{
     $doc=$codec->decodeDocument(json_encode($raw,JSON_THROW_ON_ERROR));
     $doc['slug'].='-'.($file==='documents-173.json'?'default':'edited');
     $page=app(PageCrudService::class)->create($doc);
     $this->assertEquals($doc,$page->fresh()->draftDocument->document);
     $doc['description']='Saved and refreshed locally';app(PageDraftService::class)->save($page,$doc,1);
     $this->assertEquals($doc,$page->fresh()->draftDocument->document);
     $this->assertEquals($doc,app(PageRuntimePayloadFactory::class)->forDraft($page->fresh())['page']['document']);
     $reports[]=['card'=>$i+1,'fixture'=>$file,'status'=>'pass'];
    }catch(\Illuminate\Validation\ValidationException $e){$reports[]=['card'=>$i+1,'fixture'=>$file,'status'=>'fail','errors'=>$e->errors()];}
   }
  }
  file_put_contents($reportDirectory.'/backend-roundtrip-346.json',json_encode($reports,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
  $this->assertSame($before,$old->fresh()->draftDocument->content_hash);
  $this->assertSame([],array_values(array_filter($reports,fn($r)=>$r['status']!=='pass')));
  $this->assertFalse(MediaUrlPolicy::isSafe('/images/cms/defaults/neutral/../../untrusted.svg'));
  $this->assertFalse(MediaUrlPolicy::isSafe('/images/cms/defaults/neutral/photo-123456-center.svg'));
 }
}
