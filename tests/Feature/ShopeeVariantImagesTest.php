<?php
namespace Tests\Feature;
use App\Models\{Product,User,MarketplaceStoreToken};
use App\Services\Marketplace\{MarketplacePublishingSource,ShopeePublishingMasterData,ShopeeVariantImages};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB,Http,Storage};
use Tests\TestCase;

class ShopeeVariantImagesTest extends TestCase
{
    use RefreshDatabase;
    public function test_owned_variant_images_bind_to_options_and_changed_bytes_cannot_reuse_receipt(): void
    {
        config(['marketplace.publish_operations_enabled'=>true,'marketplace.basic_wms_enabled'=>false,
            'marketplace.shopee.enabled'=>true,'marketplace.shopee.environment'=>'sandbox',
            'marketplace.shopee.applications.sandbox'=>['partner_id'=>'123','partner_key'=>'fixture'], 'marketplace.shopee.partner_id'=>'123','marketplace.shopee.partner_key'=>'fixture']);
        $actor=User::factory()->create(['is_admin'=>true]);
        $p=Product::create(['title'=>'Variant images','handle'=>'variant-images','base_price'=>100,
            'options'=>[['name'=>'Color','values'=>['Red','Blue']]]]);
        Storage::fake('public');
        $path='cms/media/9285db80-1111-4111-8111-123456789012.png';
        Storage::disk('public')->put($path,base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a9Z8AAAAASUVORK5CYII='));
        foreach(['Red','Blue'] as $color) $p->variants()->create(['sku'=>$color,'title'=>$color,'price'=>100,'stock'=>10,'enabled'=>true,'options'=>['Color'=>$color],'image_url'=>'/old-unused.png','option_image'=>'/storage/'.$path]);
        $store=DB::table('marketplace_stores')->insertGetId(['platform'=>'shopee','shop_name'=>'Test','external_shop_id'=>'456','region'=>'MY','environment'=>'sandbox','status'=>'connected']);
        app(\App\Services\Marketplace\MarketplaceStoreAccess::class)->setProductPublishing($store,true,$actor->id,'Variant image publishing test');
        MarketplaceStoreToken::create(['store_id'=>$store,'application_id'=>'123','access_token'=>'fixture-token','access_expires_at'=>now()->addHour()]);
        Http::preventStrayRequests();$uploads=0;
        Http::fake(function($request) use (&$uploads){
            if(str_contains($request->url(),'get_shop_info')) return Http::response(['error'=>'','shop_id'=>456,'shop_name'=>'Test','region'=>'MY']);
            if(str_contains($request->url(),'upload_image')) { $uploads++;return Http::response(['error'=>'','response'=>['image_info'=>['image_id'=>'owned-variant-image']]]);}
            return Http::response([],500);
        });
        $source=app(MarketplacePublishingSource::class)->read($p->id);
        $images=app(ShopeeVariantImages::class);
        $choices=['Red'=>$source['variants'][0]['id']];
        $images->prepare($store,$source,$actor->id,$choices);
        $images->prepare($store,$source,$actor->id);
        $this->assertSame(1,$uploads);
        $bound=$images->bind($store,$source,ShopeePublishingMasterData::bind($source,['jw_variant_image_choices'=>$choices]));
        foreach($bound['jw_variations']['tier_variation'][0]['option_list'] as $option) $this->assertSame(['image_id'=>'owned-variant-image'],$option['image']);
        $this->assertSame(20,(int)$p->variants()->sum('stock'));
        Storage::disk('public')->put($path,'changed bytes');
        $this->expectException(\DomainException::class);
        $images->bind($store,$source,ShopeePublishingMasterData::bind($source,['jw_variant_image_choices'=>$choices]));
    }
}
