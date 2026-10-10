<?php

namespace Tests\Feature\Cms;

use App\Domain\Cms\PageDocumentContract;
use App\Domain\Cms\RuntimeAdapterRegistry;
use App\Models\Coupon;
use App\Services\PromotionHighlightsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class PromotionHighlightsTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_live_vouchers_are_projected_and_checkout_fields_remain_authoritative(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 9)->setTime(12, 0));
        $active = $this->coupon('ACTIVE', ['discount_type'=>'fixed_amount','value'=>2500,'min_spend'=>10000]);
        $this->coupon('SCHEDULED', ['starts_at'=>now()->addDay()]);
        $this->coupon('EXPIRED', ['ends_at'=>now()->subDay()]);
        $this->coupon('INACTIVE', ['is_active'=>false]);
        $this->coupon('EXHAUSTED', ['usage_limit'=>1,'usage_count'=>1]);
        $offers = app(PromotionHighlightsService::class)->offers($this->section());
        $this->assertCount(1, $offers);
        $this->assertSame((string)$active->id, $offers[0]['id']);
        $this->assertSame('RM 25.00 off', $offers[0]['benefit']);
        $this->assertSame('RM 100.00', $offers[0]['minimumSpend']);
        $this->assertArrayNotHasKey('usage_count', $offers[0]);
        $active->update(['is_active'=>false]);
        $this->assertSame([], app(PromotionHighlightsService::class)->offers($this->section()));
    }

    public function test_registered_section_roundtrips_images_and_rejects_wrong_owner_and_locked_geometry(): void
    {
        $section=$this->section();
        $section['imageSlots']=[['target'=>'item:offer:imageUrl','imagePosition'=>'center-right','imageOverlay'=>true,'imageOverlayOpacity'=>20]];
        $doc=['title'=>'Home','slug'=>'home','template'=>'standard','sections'=>[$section]];
        $this->assertSame($doc, app(PageDocumentContract::class)->validate($doc));
        $this->coupon('LIVE');
        $facts=app(RuntimeAdapterRegistry::class)->factsFor($doc);
        $this->assertCount(1,$facts['sections'][0]['promotions']['offers']);
        $section['imageSlots'][0]['imageRatio']='1:1';
        try {app(PageDocumentContract::class)->validate([...$doc,'sections'=>[$section]]);$this->fail('Expected locked geometry rejection');}
        catch(ValidationException $e){$this->assertArrayHasKey('sections.0.imageSlots.0.imageRatio',$e->errors());}
        $section=$this->section();$section['type']='rich_text';
        $this->expectException(ValidationException::class);
        app(PageDocumentContract::class)->validate([...$doc,'sections'=>[$section]]);
    }

    private function section(): array
    {
        return ['id'=>'promotions','type'=>'promotion_highlights','title'=>'A little more','isVisible'=>true,'promotionSource'=>'automatic','promotionLayout'=>'side','showOfferBadges'=>true,'containerMode'=>'full-bleed','motionPreset'=>'subtle-lift','items'=>[['id'=>'offer','title'=>'','sourceId'=>'','imageUrl'=>'/storage/cms/media/28ab0cb3-b015-4596-bd2e-9f0028bf94e9.png','buttonText'=>'Explore','linkUrl'=>'/shop']]];
    }

    public function test_saved_offer_cards_are_bounded(): void
    {
        $section=$this->section();
        $section['items']=array_map(fn($i)=>['id'=>'slot-'.$i,'title'=>''],range(1,7));
        $this->expectException(ValidationException::class);
        app(PageDocumentContract::class)->validate(['title'=>'Home','slug'=>'home','template'=>'standard','sections'=>[$section]]);
    }

    private function coupon(string $code,array $attributes=[]): Coupon
    {
        return Coupon::query()->create(['code'=>$code,'name'=>$code,'description'=>'Approved promotion','discount_type'=>'percentage','value'=>20,'is_active'=>true,'usage_count'=>0,...$attributes]);
    }
}
