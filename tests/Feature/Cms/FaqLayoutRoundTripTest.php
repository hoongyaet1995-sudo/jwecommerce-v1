<?php
namespace Tests\Feature\Cms;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Domain\Cms\{PageCrudService,PageDraftService,PageRuntimePayloadFactory,PageDocumentJsonCodec};
final class FaqLayoutRoundTripTest extends TestCase {
 use RefreshDatabase;
 public function test_all_faq_layout_transitions_save_and_reload_without_data_loss():void {
  $this->assertSame('sqlite',config('database.default'));
  $docs=[];
  foreach(['faq-composition-1','faq-composition-2'] as $diagram)foreach(['framed-faq','pricing-card-faq','pricing-band-faq','corporate-starter-faq','saas-split','default','industrial-split','minimal'] as $preset){
   foreach([false,true] as $native){
    $raw=json_decode(file_get_contents(base_path('tests/Fixtures/Cms/page-document-all-sections.v1.json')),false,flags:JSON_THROW_ON_ERROR);
    $raw->title='FAQ layout retention';$raw->slug='faq-retention-'.count($docs);
    $baseFaq=current(array_filter($raw->sections,fn($s)=>$s->type==='faq_accordion'));
    $section=array_replace((array)$baseFaq,['id'=>'faq-section','type'=>'faq_accordion','title'=>'Saved FAQ','items'=>[['id'=>'faq-item','title'=>'Saved question','description'=>'Saved answer','layoutGroup'=>'c0','layoutVariant'=>0]],'faqPreset'=>$preset,'planName'=>'Saved plan','planAmount'=>299,'planCurrency'=>'MYR','planDescription'=>'Saved plan detail','faqInitiallyOpen'=>true,'connectedLayoutMemory'=>['faq-list'=>$diagram]]);
    if($native){$section['connectedLayout']='faq-list';$section['connectedDiagram']=$diagram;}
    $raw->sections=[(object)$section];$docs[]=$raw;
   }
  }
  foreach($docs as $raw){
   $doc=app(PageDocumentJsonCodec::class)->decodeDocument(json_encode($raw,JSON_THROW_ON_ERROR));
   $page=app(PageCrudService::class)->create($doc);
   app(PageDraftService::class)->save($page,$doc,1);
   $this->assertEquals($doc,$page->fresh()->draftDocument->document);
   $this->assertEquals($doc,app(PageRuntimePayloadFactory::class)->forDraft($page->fresh())['page']['document']);
  }
 }
}
