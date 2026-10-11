<?php
namespace Tests\Unit\Domain\Cms;
use App\Domain\Cms\PageDocumentContract;
use Tests\TestCase;
final class JewelryTemplateTest extends TestCase {
 public function test_jewelry_template_and_sparse_artisan_survive_canonical_round_trip():void {
  $s=json_decode(file_get_contents(resource_path('admin/src/cms/jewelryTemplate.json')),true,512,JSON_THROW_ON_ERROR);$doc=['title'=>'Jewelry','slug'=>'jewelry','template'=>'standard','style'=>$s['style'],'sections'=>$s['sections']];$contract=app(PageDocumentContract::class);$this->assertCount(10,$doc['sections']);$this->assertSame($doc,$contract->validate($doc));
  $doc['sections']=[['id'=>'sparse','type'=>'media_text_split','title'=>'Editable','isVisible'=>true,'layoutPreset'=>'artisan-story','items'=>[]]];$this->assertSame($doc,$contract->validate($doc));
 }
}
