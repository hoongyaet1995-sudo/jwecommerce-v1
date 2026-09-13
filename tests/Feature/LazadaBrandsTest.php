<?php
namespace Tests\Feature;

use App\Services\Marketplace\LazadaBrands;
use Illuminate\Support\Facades\{Cache,Http};
use Tests\TestCase;

class LazadaBrandsTest extends TestCase
{
    public function test_brand_pages_preserve_pagination_and_validate_selected_names_against_provider(): void
    {
        config(['marketplace.lazada.enabled'=>true,'marketplace.lazada.app_key'=>'123','marketplace.lazada.app_secret'=>'fixture']);
        Cache::flush(); Http::preventStrayRequests();
        Http::fake(['*'=>Http::sequence()
            ->push(['code'=>'0','data'=>['module'=>[['brand_id'=>5,'name'=>'ABBA']], 'total_record'=>201]])
            ->push(['code'=>'0','data'=>['module'=>[['brand_id'=>5,'name'=>'ABBA']], 'total_record'=>201]])
            ->push(['code'=>'0','data'=>['module'=>[['brand_id'=>6,'name'=>'Changed']], 'total_record'=>201]])]);
        $service=app(LazadaBrands::class);
        $page=$service->page('fixture-token',0);
        $this->assertSame(['ABBA'],$page['names']);
        $this->assertSame(1,$page['next_offset']);
        $service->validate('fixture-token','ABBA');
        $service->validate('fixture-token','No Brand');
        Http::assertSentCount(2);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->validate('fixture-token','ABBA');
    }
}
