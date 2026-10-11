<?php
namespace Tests\Unit\Domain\Cms;
use App\Domain\Cms\PageDocumentContract;
use Tests\TestCase;
final class KidsTemplateTest extends TestCase {
 public function test_kids_template_and_sparse_presets_survive_canonical_round_trip():void {
  $s=json_decode(file_get_contents(resource_path('admin/src/cms/kidsTemplate.json')),true,512,JSON_THROW_ON_ERROR);$doc=['title'=>'Kids','slug'=>'kids','template'=>'standard','style'=>$s['style'],'sections'=>$s['sections']];$contract=app(PageDocumentContract::class);$this->assertCount(9,$doc['sections']);$this->assertSame($doc,$contract->validate($doc));
  foreach([['shop_navigation','shopNavigationPreset','age-cards'],['media_text_split','layoutPreset','kids-discovery'],['product_listing','listingPreset','kids-grid'],['product_listing','listingPreset','kids-tabs']] as [$type,$field,$preset]){$doc['sections']=[['id'=>'sparse','type'=>$type,'title'=>'Editable','isVisible'=>true,$field=>$preset,'items'=>[]]];$this->assertSame($doc,$contract->validate($doc));}
 }
}
