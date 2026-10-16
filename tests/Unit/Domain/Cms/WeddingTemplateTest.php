<?php
namespace Tests\Unit\Domain\Cms;
use App\Domain\Cms\PageDocumentContract;
use Tests\TestCase;
final class WeddingTemplateTest extends TestCase {
 public function test_editorial_quote_requires_quote_content_while_default_testimonial_still_requires_title():void {
  $s=json_decode(file_get_contents(resource_path('admin/src/cms/weddingTemplate.json')),true,512,JSON_THROW_ON_ERROR);
  $doc=['title'=>'Wedding','slug'=>'wedding','template'=>'standard','sections'=>[$s['sections'][7]]];
  $doc['sections'][0]['items']=[];
  try{app(PageDocumentContract::class)->assertPublishable($doc,[]);$this->fail('An empty testimonial cannot publish.');}
  catch(\Illuminate\Validation\ValidationException $e){$this->assertArrayHasKey('sections.0.items',$e->errors());}
  $doc['sections'][0]=$s['sections'][7];
  $doc['sections'][0]['items'][0]['description']=' ';
  try{app(PageDocumentContract::class)->assertPublishable($doc,[]);$this->fail('A blank quote cannot publish.');}
  catch(\Illuminate\Validation\ValidationException $e){$this->assertArrayHasKey('sections.0.items.0.description',$e->errors());}
  $doc['sections'][0]=$s['sections'][7];
  unset($doc['sections'][0]['testimonialPreset']);
  try{app(PageDocumentContract::class)->assertPublishable($doc,[]);$this->fail('Default testimonial still needs its heading.');}
  catch(\Illuminate\Validation\ValidationException $e){$this->assertArrayHasKey('sections.0.title',$e->errors());}
 }
 public function test_bundled_asset_allowlist_does_not_allow_arbitrary_local_paths():void {
  foreach(['hero','account','album','prints'] as $name)$this->assertTrue(\App\Domain\Cms\MediaUrlPolicy::isSafe('/images/themes/wedding/'.$name.'.png'));
  foreach(['/images/themes/wedding/../hero.png','/images/themes/wedding/%2e%2e/hero.png','/images/themes/wedding/unknown.png','//images/themes/wedding/hero.png','/images/themes/wedding/hero.png?file=secret','/images/themes/wedding/hero.png#fragment'] as $url)$this->assertFalse(\App\Domain\Cms\MediaUrlPolicy::isSafe($url));
 }
 public function test_portfolio_template_survives_canonical_validation_without_commerce():void {
  $s=json_decode(file_get_contents(resource_path('admin/src/cms/weddingTemplate.json')),true,512,JSON_THROW_ON_ERROR);
  $doc=['title'=>'Wedding','slug'=>'wedding','template'=>'standard','style'=>$s['style'],'sections'=>$s['sections']];
  $contract=app(PageDocumentContract::class);$this->assertCount(9,$doc['sections']);$this->assertSame($doc,$contract->validate($doc));
  foreach($doc['sections'] as $section)$this->assertNotContains($section['type'],['product_listing','product_grid','pricing_table']);
  foreach([['hero_banner','heroBannerPreset','editorial-gradient'],['testimonials_slider','testimonialPreset','editorial-quote'],['media_gallery','galleryPreset','photo-marquee'],['media_gallery','galleryPreset','editorial-triptych'],['media_gallery','galleryPreset','portfolio-cards'],['feature_cards','featureCardsPreset','numbered-process'],['media_text_split','layoutPreset','editorial-journal-rows']] as [$type,$key,$value]){
   $doc['sections']=[['id'=>'sparse','type'=>$type,'title'=>'Editable','isVisible'=>true,$key=>$value,'items'=>[]]];$this->assertSame($doc,$contract->validate($doc));
  }
 }
}
