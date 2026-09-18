<?php
namespace Tests\Feature;

use App\Services\Marketplace\MarketplaceListingMetadataCache;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MarketplaceListingMetadataCacheTest extends TestCase
{
    public function test_review_cache_is_scoped_refreshable_and_expires(): void
    {
        Cache::flush();
        $store=(object)['id'=>1,'platform'=>'shopee','external_shop_id'=>'123','environment'=>'sandbox','region'=>'MY','updated_at'=>'one'];
        $service=new MarketplaceListingMetadataCache;
        $calls=0;$fetch=function()use(&$calls){return ['revision'=>++$calls];};
        $this->assertSame(['revision'=>1],$service->read($store,'brands',[10],$fetch));
        $this->assertSame(['revision'=>1],$service->read($store,'brands',[10],$fetch));
        $this->assertSame(['revision'=>2],$service->read($store,'brands',[11],$fetch));
        $this->assertSame(['revision'=>3],$service->read($store,'brands',[10],$fetch,true));
        $store->external_shop_id='456';
        $this->assertSame(['revision'=>4],$service->read($store,'brands',[10],$fetch));
        $this->travel(16)->minutes();
        $this->assertSame(['revision'=>5],$service->read($store,'brands',[10],$fetch));
        $this->travelBack();
    }
    public function test_failed_fetch_is_not_cached_as_empty_success(): void
    {
        Cache::flush();
        $store=(object)['id'=>1,'platform'=>'shopee','external_shop_id'=>'123','environment'=>'sandbox','region'=>'MY','updated_at'=>'one'];
        $service=new MarketplaceListingMetadataCache;
        try {$service->read($store,'categories',[],fn()=>throw new \RuntimeException('unavailable'));}
        catch (\RuntimeException) {}
        $this->assertSame([1],$service->read($store,'categories',[],fn()=>[1]));
    }

    public function test_lazada_metadata_uses_the_same_fifteen_minute_cache_but_never_shares_shopee_values(): void
    {
        Cache::flush();
        $service=new MarketplaceListingMetadataCache;
        $shopee=(object)['id'=>1,'platform'=>'shopee','external_shop_id'=>'123','environment'=>'sandbox','region'=>'MY','updated_at'=>'one'];
        $lazada=(object)['id'=>3,'platform'=>'lazada','external_shop_id'=>'300927968304','environment'=>'live','region'=>'MY','updated_at'=>'one'];
        $calls=0;
        $fetchLazada=function() use (&$calls) { return ['platform'=>'lazada','call'=>++$calls]; };
        $this->assertSame(['platform'=>'shopee'],$service->read($shopee,'categories',[],fn()=>['platform'=>'shopee']));
        $this->assertSame(['platform'=>'lazada','call'=>1],$service->read($lazada,'categories',[],$fetchLazada));
        $this->assertSame(['platform'=>'lazada','call'=>1],$service->read($lazada,'categories',[],$fetchLazada));
        $this->assertSame(1,$calls);
    }
}
