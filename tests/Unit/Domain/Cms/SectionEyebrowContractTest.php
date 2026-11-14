<?php
namespace Tests\Unit\Domain\Cms;
use App\Domain\Cms\PageDocumentContract;
use Tests\TestCase;
class SectionEyebrowContractTest extends TestCase
{
 public function test_canonical_eyebrow_and_explicit_empty_values_round_trip_without_overwriting_legacy_text():void
 {
  $document=['title'=>'Eyebrow QA','slug'=>'eyebrow-qa','template'=>'standard','sections'=>[
   ['id'=>'faq','type'=>'faq_accordion','faqPreset'=>'pricing-card-faq','title'=>'Plan','subtitle'=>'Keep FAQ heading','eyebrow'=>'','isVisible'=>true],
   ['id'=>'slide','type'=>'hero_slider','title'=>'Slides','isVisible'=>true,'items'=>[['id'=>'one','title'=>'Slide','eyebrow'=>'New label','subtitle'=>'Legacy label']]],
  ]];
  $this->assertSame($document,app(PageDocumentContract::class)->validate($document));
 }
}
