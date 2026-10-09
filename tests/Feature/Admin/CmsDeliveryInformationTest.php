<?php

namespace Tests\Feature\Admin;

use App\Domain\Cms\RuntimeAdapterRegistry;
use App\Domain\Cms\PageDocumentContract;
use App\Models\StoreSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CmsDeliveryInformationTest extends TestCase
{
    use RefreshDatabase;

    public function test_delivery_facts_follow_enabled_settings_without_publishing_a_new_snapshot(): void
    {
        $document = ['title'=>'Delivery','slug'=>'delivery','template'=>'standard','sections'=>[['id'=>'delivery','type'=>'delivery_table','title'=>'Delivery','isVisible'=>true,'deliverySource'=>'settings','deliveryNote'=>'Final fees at checkout']]];
        $this->assertSame($document,app(PageDocumentContract::class)->validate($document));
        $setting = StoreSetting::updateOrCreate(['key'=>'shipping_rates'],['value'=>['data'=>[
            ['id'=>'a','name'=>'Standard','zoneRegion'=>'peninsular','isActive'=>true,'flatRateCost'=>5,'estimatedDays'=>'2–4 business days'],
            ['id'=>'b','name'=>'Hidden','zoneRegion'=>'international','isActive'=>false],
        ]]]);
        $facts=app(RuntimeAdapterRegistry::class)->factsFor($document);
        $this->assertCount(1,$facts['sections'][0]['delivery']['rows']);
        $this->assertSame('Calculated at checkout',$facts['sections'][0]['delivery']['rows'][0]['deliveryFee']);
        $this->assertSame('2–4 business days',$facts['sections'][0]['delivery']['rows'][0]['deliveryEstimate']);
        $setting->update(['value'=>['data'=>[['id'=>'c','name'=>'New option','zoneRegion'=>'sabah','isActive'=>true]]]]);
        $changed=app(RuntimeAdapterRegistry::class)->factsFor($document);
        $this->assertSame('Sabah — New option',$changed['sections'][0]['delivery']['rows'][0]['title']);
        $this->assertSame('Confirm at checkout',$changed['sections'][0]['delivery']['rows'][0]['deliveryEstimate']);
        $setting->delete();
        $this->assertSame([],app(RuntimeAdapterRegistry::class)->factsFor($document)['sections'][0]['delivery']['rows']);
    }

    public function test_manual_rows_and_new_shared_presets_round_trip_in_the_contract(): void
    {
        $document=['title'=>'Shipping','slug'=>'shipping','template'=>'standard','sections'=>[
            ['id'=>'hero','type'=>'media_text_split','title'=>'Hero','isVisible'=>true,'layoutPreset'=>'editorial-hero','leadText'=>'Introduction','secondaryButtonText'=>'Track','secondaryButtonLink'=>'/order-tracking'],
            ['id'=>'process','type'=>'media_text_split','title'=>'Process','isVisible'=>true,'layoutPreset'=>'story-with-steps','items'=>[['id'=>'step','title'=>'Packed','description'=>'With care']]],
            ['id'=>'table','type'=>'delivery_table','title'=>'Delivery','isVisible'=>true,'deliverySource'=>'manual','items'=>[['id'=>'row','title'=>'Region','deliveryEstimate'=>'To edit','deliveryFee'=>'At checkout']]],
            ['id'=>'faq','type'=>'faq_accordion','title'=>'FAQ','isVisible'=>true,'faqPreset'=>'minimal','faqInitiallyOpen'=>true],
            ['id'=>'help','type'=>'cta_strip','title'=>'Help','isVisible'=>true,'ctaPreset'=>'help-panel'],
        ]];
        $this->assertSame($document,app(PageDocumentContract::class)->validate($document));
    }
    public function test_chapter_delivery_reuses_live_projection_and_media_width_contract(): void
    {
        $document=['title'=>'Chapters','slug'=>'chapters','template'=>'standard','sections'=>[
            ['id'=>'cover','type'=>'media_text_split','title'=>'Cover','isVisible'=>true,'layoutPreset'=>'editorial-cover','containerMode'=>'page','mediaContainerMode'=>'full-bleed'],
            ['id'=>'process','type'=>'chapter_block','title'=>'Prepare','isVisible'=>true,'chapterPreset'=>'process'],
            ['id'=>'delivery','type'=>'chapter_block','title'=>'Delivery','isVisible'=>true,'chapterPreset'=>'delivery','deliverySource'=>'settings','mediaContainerMode'=>'page'],
            ['id'=>'actions','type'=>'chapter_block','title'=>'Tracking','isVisible'=>true,'chapterPreset'=>'actions','buttonText'=>'Track','buttonLink'=>'/order-tracking'],
        ]];
        $this->assertSame($document,app(PageDocumentContract::class)->validate($document));
        StoreSetting::updateOrCreate(['key'=>'shipping_rates'],['value'=>['data'=>[['id'=>'live','name'=>'Configured courier','zoneRegion'=>'sabah','isActive'=>true]]]]);
        $facts=app(RuntimeAdapterRegistry::class)->factsFor($document);
        $this->assertSame('Sabah — Configured courier',$facts['sections'][2]['delivery']['rows'][0]['title']);
        $this->assertArrayNotHasKey(1,$facts['sections']);
    }
}
