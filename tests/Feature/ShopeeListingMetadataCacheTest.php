<?php
namespace Tests\Feature;

use App\Services\Marketplace\ShopeeListingMetadataCache;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ShopeeListingMetadataCacheTest extends TestCase
{
    public function test_review_cache_is_scoped_refreshable_and_expires(): void
    {
        Cache::flush();
        $store=(object)['id'=>1,'external_shop_id'=>'123','environment'=>'sandbox','region'=>'MY','updated_at'=>'one'];
        $service=new ShopeeListingMetadataCache;
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
        $store=(object)['id'=>1,'external_shop_id'=>'123','environment'=>'sandbox','region'=>'MY','updated_at'=>'one'];
        $service=new ShopeeListingMetadataCache;
        try {$service->read($store,'categories',[],fn()=>throw new \RuntimeException('unavailable'));}
        catch (\RuntimeException) {}
        $this->assertSame([1],$service->read($store,'categories',[],fn()=>[1]));
    }
}
