<?php
namespace Tests\Unit\Domain\Cms;
use App\Domain\Cms\PageDocumentContract;
use Tests\TestCase;
final class TechTemplateTest extends TestCase {
 public function test_tech_template_and_sparse_presets_survive_canonical_round_trip():void {
  $s=json_decode(file_get_contents(resource_path('admin/src/cms/techTemplate.json')),true,512,JSON_THROW_ON_ERROR);
  $doc=['title'=>'Tech','slug'=>'tech','template'=>'standard','style'=>$s['style'],'sections'=>$s['sections']];
  $contract=app(PageDocumentContract::class);$this->assertCount(9,$doc['sections']);$this->assertSame($doc,$contract->validate($doc));
  $doc['sections'][5]['items'][]=['id'=>'fourth-device','title'=>'Fourth','subtitle'=>'device'];
  $doc['sections'][5]['items'][]=['id'=>'extra-row','title'=>'Compatibility','comparisonCells'=>[['deviceId'=>'fourth-device','value'=>'Verified']]];
  $this->assertSame($doc,$contract->validate($doc));
  foreach([['media_text_split','layoutPreset','tech-feature'],['comparison_matrix','comparisonPreset','device-table'],['product_listing','listingPreset','tech-grid'],['product_listing','listingPreset','tech-tabs']] as [$type,$field,$preset]){$doc['sections']=[['id'=>'sparse','type'=>$type,'title'=>'Editable','isVisible'=>true,$field=>$preset,'items'=>[]]];$this->assertSame($doc,$contract->validate($doc));}
 }
}
