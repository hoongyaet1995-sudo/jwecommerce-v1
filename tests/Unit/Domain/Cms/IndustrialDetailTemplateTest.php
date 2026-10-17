<?php
namespace Tests\Unit\Domain\Cms;
use App\Domain\Cms\PageDocumentContract;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
class IndustrialDetailTemplateTest extends TestCase {
 public function test_detail_and_gallery_snapshots_round_trip_with_owned_fields():void {
  foreach(['logisticsSolutionTemplate','logisticsGalleryTemplate'] as $name){
   $s=json_decode(file_get_contents(base_path('resources/admin/src/cms/'.$name.'.json')),true,512,JSON_THROW_ON_ERROR);
   $d=['title'=>'Container details','slug'=>'container-detail','template'=>'standard','style'=>$s['style'],'sections'=>$s['sections']];
   try{$actual=app(PageDocumentContract::class)->validate($d);}catch(ValidationException $e){$this->fail(json_encode($e->errors()));}
   $this->assertSame($d,$actual);
  }
 }
 public function test_gallery_controls_cannot_be_saved_on_other_section_types():void {
  $this->expectException(ValidationException::class);
  app(PageDocumentContract::class)->validate(['title'=>'Wrong owner','slug'=>'wrong-owner','template'=>'standard','sections'=>[['id'=>'x','type'=>'rich_text','title'=>'Text','isVisible'=>true,'galleryFilters'=>true]]]);
 }
}
