<?php
namespace Tests\Feature\Cms;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Domain\Cms\{PageCrudService,PageDraftService,PageRuntimePayloadFactory,PageDocumentContract};
use Illuminate\Validation\ValidationException;
final class IllustratedRightGeometryTest extends TestCase
{
 use RefreshDatabase;
 private function document():array{return ['title'=>'Keep page geometry','slug'=>'cta-right-geometry','template'=>'landing','containerWidth'=>'full','sections'=>[['id'=>'closing','type'=>'cta_strip','ctaPreset'=>'illustrated-panel-right','title'=>'Original heading','isVisible'=>true,'containerMode'=>'page','mediaUrl'=>'/cms/connected/assets/brand/business.webp','buttonText'=>'Request a demo','buttonLink'=>'/contact','imageSlots'=>[['target'=>'mediaUrl','mediaAlt'=>'Existing alt']]]]];}
 public function test_right_artwork_geometry_edits_save_refresh_and_reach_the_preview():void {
  $this->assertSame('sqlite',config('database.default'));$doc=$this->document();$page=app(PageCrudService::class)->create($doc);
  $next=$doc;$next['sections'][0]['imageSlots'][0]+=['imageRatio'=>'1:1','imageWidth'=>'75','imageShape'=>'rounded'];app(PageDraftService::class)->save($page,$next,1);
  $this->assertSame($next,$page->fresh()->draftDocument->document);$payload=app(PageRuntimePayloadFactory::class)->forDraft($page->fresh());$this->assertSame($next,$payload['page']['document']);
  $reset=$next;$reset['sections'][0]['imageSlots'][0]=['target'=>'mediaUrl','mediaAlt'=>'Existing alt'];app(PageDraftService::class)->save($page,$reset,2);$this->assertSame($doc,$page->fresh()->draftDocument->document);
 }
 public function test_other_layout_owned_geometry_remains_locked():void {
  $doc=$this->document();$doc['sections'][0]['ctaPreset']='image-banner';$doc['sections'][0]['imageSlots'][0]['imageWidth']='75';$this->expectException(ValidationException::class);app(PageDocumentContract::class)->validate($doc);
 }
}
