<?php
namespace Tests\Unit\Domain\Cms;
use Tests\TestCase;
use App\Domain\Cms\PageDocumentContract;
use Illuminate\Validation\ValidationException;
final class EditorialIllustratedCtaTest extends TestCase {
 public function test_round_trip_and_hidden_media_retention():void {
  $s=['id'=>'cta','type'=>'cta_strip','ctaPreset'=>'editorial-illustrated','isVisible'=>true,'title'=>'Start here','subtitle'=>'Let’s talk','content'=>'A short introduction.','mediaUrl'=>'https://images.unsplash.com/photo-1','buttonText'=>'Book now','buttonLink'=>'/contact','imageSlots'=>[['target'=>'mediaUrl','mediaAlt'=>'Authored alt']]];$d=['title'=>'A page','slug'=>'illustrated-cta','template'=>'standard','containerWidth'=>'site','sections'=>[$s]];$c=app(PageDocumentContract::class);$this->assertSame($d,$c->validate($d));$d['sections'][0]['ctaPreset']='default';$this->assertSame($d,$c->validate($d));$d['sections'][0]['ctaPreset']='editorial-illustrated';$this->assertSame($d,$c->validate($d));
 }
 public function test_wrong_preset_owner_is_rejected():void {$this->expectException(ValidationException::class);app(PageDocumentContract::class)->validate(['title'=>'A page','slug'=>'wrong-owner','template'=>'standard','sections'=>[['id'=>'hero','type'=>'hero_banner','ctaPreset'=>'editorial-illustrated','title'=>'Wrong','isVisible'=>true]]]);}
}
