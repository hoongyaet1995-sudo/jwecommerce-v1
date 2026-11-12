<?php
namespace Tests\Unit\Domain\Cms;
use Tests\TestCase;
use App\Domain\Cms\StarterPricingReference;
final class AddonPricingReferenceTest extends TestCase {
 public function test_addon_reference_preserves_authoritative_charge_and_terms():void {
  $source=['sections'=>[['id'=>'addons','type'=>'feature_cards','featureCardsPreset'=>'saas-addons','isVisible'=>true,'title'=>'Optional add-ons','content'=>'Confirm limits first.','items'=>[['id'=>'studio','title'=>'Content studio','description'=>"+RM200 / month\nCampaign tools and conversion reporting."]]]]];
  $ref=['planContentSource'=>'reference','pricingSourceSectionId'=>'addons','pricingSourceItemId'=>'studio','pricingSourceTermsSectionId'=>'addons'];
  $fact=app(StarterPricingReference::class)->resolve($ref,$source);$this->assertSame('+RM200 / month',$fact['priceLabel']);$this->assertSame('Campaign tools and conversion reporting.',$fact['description']);$this->assertSame('Confirm limits first.',$fact['terms']);
  $source['sections'][0]['isVisible']=false;$this->assertSame('unavailable',app(StarterPricingReference::class)->resolve($ref,$source)['status']);
 }
}
