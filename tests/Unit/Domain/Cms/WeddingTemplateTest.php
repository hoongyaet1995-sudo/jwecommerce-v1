<?php
namespace Tests\Unit\Domain\Cms;
use App\Domain\Cms\PageDocumentContract;
use Tests\TestCase;
final class WeddingTemplateTest extends TestCase {
 public function test_portfolio_template_survives_canonical_validation_without_commerce():void {
  $s=json_decode(file_get_contents(resource_path('admin/src/cms/weddingTemplate.json')),true,512,JSON_THROW_ON_ERROR);
  $doc=['title'=>'Wedding','slug'=>'wedding','template'=>'standard','style'=>$s['style'],'sections'=>$s['sections']];
  $contract=app(PageDocumentContract::class);$this->assertCount(9,$doc['sections']);$this->assertSame($doc,$contract->validate($doc));
  foreach($doc['sections'] as $section)$this->assertNotContains($section['type'],['product_listing','product_grid','pricing_table']);
  foreach([['media_gallery','galleryPreset','editorial-triptych'],['media_gallery','galleryPreset','portfolio-cards'],['feature_cards','featureCardsPreset','numbered-process'],['media_text_split','layoutPreset','editorial-journal-rows']] as [$type,$key,$value]){
   $doc['sections']=[['id'=>'sparse','type'=>$type,'title'=>'Editable','isVisible'=>true,$key=>$value,'items'=>[]]];$this->assertSame($doc,$contract->validate($doc));
  }
 }
}
