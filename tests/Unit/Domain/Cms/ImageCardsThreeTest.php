<?php
namespace Tests\Unit\Domain\Cms;
use Tests\TestCase;
use App\Domain\Cms\PageDocumentContract;
use Illuminate\Validation\ValidationException;
final class ImageCardsThreeTest extends TestCase {
 public function test_bounded_preset_round_trip_and_legacy_layout_data_retention():void{
  $s=['id'=>'cards','type'=>'media_gallery','galleryPreset'=>'image-cards-three','isVisible'=>true,'title'=>'Three highlights','content'=>'Editable intro','items'=>array_map(fn($n)=>['id'=>'c'.$n,'title'=>'Card '.$n,'description'=>'Description','imageUrl'=>''],[1,2,3,4])];
  $d=['title'=>'A page','slug'=>'image-cards','template'=>'standard','containerWidth'=>'site','sections'=>[$s]];$c=app(PageDocumentContract::class);$this->assertSame($d,$c->validate($d));$d['sections'][0]['galleryPreset']='default';$this->assertSame($d,$c->validate($d));$d['sections'][0]['galleryPreset']='image-cards-three';$this->assertCount(4,$c->validate($d)['sections'][0]['items']);$d['sections'][0]['items']=[];$this->assertSame($d,$c->validate($d));
 }
 public function test_gallery_preset_cannot_be_saved_on_a_hero():void{
  $this->expectException(ValidationException::class);app(PageDocumentContract::class)->validate(['title'=>'A page','slug'=>'wrong-owner','template'=>'standard','sections'=>[['id'=>'hero','type'=>'hero_banner','galleryPreset'=>'image-cards-three','title'=>'Wrong owner','isVisible'=>true]]]);
 }
}
