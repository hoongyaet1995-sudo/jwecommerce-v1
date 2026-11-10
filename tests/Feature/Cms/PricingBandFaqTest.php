<?php
namespace Tests\Feature\Cms;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Domain\Cms\{PageCrudService,PageRuntimePayloadFactory,PageDocumentContract,StarterPricingReference};
use App\Models\PageDocument;
final class PricingBandFaqTest extends TestCase {
 use RefreshDatabase;
 public function test_manual_description_is_faq_owned_and_retained_on_layout_switch():void {
  $s=['id'=>'faq','type'=>'faq_accordion','title'=>'Services','content'=>'Section intro','faqPreset'=>'pricing-band-faq','isVisible'=>true,'planContentSource'=>'manual','planName'=>'Consultation','planAmount'=>150,'planCurrency'=>'SGD','planPeriod'=>'/ session','planTerms'=>'Appointment required.','planDescription'=>'A tailored consultation.'];$d=['title'=>'Services','slug'=>'band-services','template'=>'standard','sections'=>[$s]];
  $c=app(PageDocumentContract::class);$this->assertSame($d,$c->validate($d));$page=app(PageCrudService::class)->create($d);$fact=app(PageRuntimePayloadFactory::class)->forDraft($page)['runtime']['sections'][0]['starterPricing'];$this->assertSame('A tailored consultation.',$fact['description']);$this->assertSame(150,$fact['price']);$d['sections'][0]['faqPreset']='minimal';$this->assertSame($d,$c->validate($d));$d['sections'][0]['type']='hero_banner';unset($d['sections'][0]['faqPreset']);$this->expectException(\Illuminate\Validation\ValidationException::class);$c->validate($d);
 }
 public function test_band_source_description_follows_draft_and_published_isolation():void {
  $source=['title'=>'Plans','slug'=>'band-plans','template'=>'standard','sections'=>[['id'=>'plans','type'=>'pricing_table','pricingPreset'=>'saas-packages','pricingCurrency'=>'MYR','title'=>'Plans','isVisible'=>true,'items'=>[['id'=>'starter','title'=>'Starter','price'=>299,'billingLabel'=>'/ month','description'=>'Website & ecommerce + customer support']]],['id'=>'terms','type'=>'feature_cards','title'=>'Terms','content'=>'Usage allowances and provider limits are confirmed before activation.','isVisible'=>true]]];
  $page=app(PageCrudService::class)->create($source);$s=['id'=>'faq','type'=>'faq_accordion','title'=>'Start','faqPreset'=>'pricing-band-faq','isVisible'=>true,'pricingSourcePageId'=>(string)$page->id,'pricingSourceSectionId'=>'plans','pricingSourceItemId'=>'starter','pricingSourceTermsSectionId'=>'terms'];$d=['title'=>'Feature','slug'=>'band-feature','template'=>'standard','sections'=>[$s]];$consumer=app(PageCrudService::class)->create($d);$factory=app(PageRuntimePayloadFactory::class);$service=app(StarterPricingReference::class);
  $this->assertSame('Website & ecommerce + customer support',$factory->forDraft($consumer)['runtime']['sections'][0]['starterPricing']['description']);$this->assertSame('unavailable',$service->forDocument($d,false)[0]['starterPricing']['status']);
  $published=$page->draftDocument->replicate();$published->stage=PageDocument::STAGE_PUBLISHED;$published->save();$page->update(['status'=>'published']);$source['sections'][0]['items'][0]['description']='Updated source description';$page->draftDocument->update(['document'=>$source]);$this->assertSame('Updated source description',$factory->forDraft($consumer)['runtime']['sections'][0]['starterPricing']['description']);$fact=$service->forDocument($d,false)[0]['starterPricing'];$this->assertSame('Website & ecommerce + customer support',$fact['description']);$this->assertSame(299,$fact['price']);$this->assertSame('/ month',$fact['billingLabel']);$this->assertSame('Usage allowances and provider limits are confirmed before activation.',$fact['terms']);
 }
}
