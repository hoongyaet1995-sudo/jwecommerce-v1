<?php
namespace Tests\Feature\Cms;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Domain\Cms\{PageCrudService,PageRuntimePayloadFactory,PageDocumentContract,StarterPricingReference};
use App\Models\PageDocument;
final class StarterPricingReferenceTest extends TestCase {
 use RefreshDatabase;
 public function test_manual_price_is_generic_canonical_and_needs_no_source_page():void {
  $s=['id'=>'faq','type'=>'faq_accordion','title'=>'Services','faqPreset'=>'corporate-starter-faq','isVisible'=>true,'planContentSource'=>'manual','planName'=>'Consultation','planAmount'=>150.5,'planCurrency'=>'SGD','planPeriod'=>'/ session','planTerms'=>'Appointment required.'];
  $d=['title'=>'Services','slug'=>'generic-services','template'=>'standard','sections'=>[$s]];
  $this->assertSame($d,app(PageDocumentContract::class)->validate($d));$page=app(PageCrudService::class)->create($d);
  $fact=app(PageRuntimePayloadFactory::class)->forDraft($page)['runtime']['sections'][0]['starterPricing'];
  $this->assertSame(150.5,$fact['price']);$this->assertSame('SGD',$fact['currency']);$this->assertSame('/ session',$fact['billingLabel']);
  $s['planAmount']=0;$this->assertSame(0,app(StarterPricingReference::class)->resolve($s,null)['price']);
  $s['planPricingMode']='contact';$s['planPriceLabel']='Request an estimate';$this->assertSame('Request an estimate',app(StarterPricingReference::class)->resolve($s,null)['priceLabel']);
  $d['sections'][0]['planAmount']=-1;$this->expectException(\Illuminate\Validation\ValidationException::class);app(PageDocumentContract::class)->validate($d);
 }
 public function test_preview_reads_live_source_updates_but_public_never_reads_drafts():void {
  $source=['title'=>'Plans','slug'=>'source-plans','template'=>'standard','sections'=>[
   ['id'=>'plans','type'=>'pricing_table','pricingPreset'=>'saas-packages','pricingCurrency'=>'MYR','title'=>'Plans','isVisible'=>true,'items'=>[['id'=>'starter','title'=>'Starter','price'=>299,'billingLabel'=>'/ month']]],
   ['id'=>'terms','type'=>'feature_cards','title'=>'Terms','content'=>'Original terms.','isVisible'=>true],
  ]];
  $page=app(PageCrudService::class)->create($source);
  $s=['id'=>'faq','type'=>'faq_accordion','title'=>'Start','subtitle'=>'FAQ','faqPreset'=>'corporate-starter-faq','isVisible'=>true,'pricingSourcePageId'=>(string)$page->id,'pricingSourceSectionId'=>'plans','pricingSourceItemId'=>'starter','pricingSourceTermsSectionId'=>'terms','buttonText'=>'Compare','buttonLink'=>'https://example.com/#plans','items'=>[]];
  $d=['title'=>'Feature','slug'=>'feature','template'=>'standard','sections'=>[$s]];$consumer=app(PageCrudService::class)->create($d);
  $factory=app(PageRuntimePayloadFactory::class);$payload=$factory->forDraft($consumer);
  $this->assertSame(299,$payload['runtime']['sections'][0]['starterPricing']['price']);$this->assertSame($d,$payload['page']['document']);
  $service=app(StarterPricingReference::class);$this->assertSame('unavailable',$service->forDocument($d,false)[0]['starterPricing']['status']);
  $published=$page->draftDocument->replicate();$published->stage=PageDocument::STAGE_PUBLISHED;$published->save();$page->update(['status'=>'published']);
  $source['sections'][0]['items'][0]['price']=321;$source['sections'][1]['content']='Updated terms.';$page->draftDocument->update(['document'=>$source]);
  $this->assertSame(321,$factory->forDraft($consumer)['runtime']['sections'][0]['starterPricing']['price']);
  $this->assertSame('Updated terms.',$factory->forDraft($consumer)['runtime']['sections'][0]['starterPricing']['terms']);
  $this->assertSame(299,$service->forDocument($d,false)[0]['starterPricing']['price']);
  $d['sections'][0]['pricingSourceItemId']='missing';$this->assertSame('unavailable',$service->forDocument($d,true)[0]['starterPricing']['status']);
  unset($d['sections'][0]['pricingSourceItemId']);$d['sections'][0]['type']='hero_banner';$this->expectException(\Illuminate\Validation\ValidationException::class);app(PageDocumentContract::class)->validate($d);
 }
}
