<?php
namespace Tests\Feature;
use App\Services\Marketplace\LazadaListingPreflight;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LazadaListingPreflightTest extends TestCase
{
    public function test_required_size_chart_is_satisfied_by_the_reviewed_provider_image(): void
    {
        config(['marketplace.lazada.enabled'=>true,'marketplace.lazada.app_key'=>'123','marketplace.lazada.app_secret'=>'fixture']);
        Http::preventStrayRequests();
        Http::fake(function($request) {
            return str_contains($request->url(),'/category/tree/get')
                ? Http::response(['code'=>'0','data'=>[['category_id'=>2,'name'=>'Leaf','leaf'=>true]]])
                : Http::response(['code'=>'0','data'=>[
                    ['name'=>'size_chart','label'=>'Size Chart','attribute_type'=>'normal','is_mandatory'=>1,'input_type'=>'numeric'],
                ]]);
        });

        $payload=[
            'jw_size_chart'=>'https://my-live-02.slatic.net/p/size-chart.png',
            'Request'=>['Product'=>[
                'PrimaryCategory'=>'2',
                'Attributes'=>[],
                'Skus'=>['Sku'=>[['quantity'=>'0']]],
            ]],
        ];

        app(LazadaListingPreflight::class)->validate('token',$payload);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        unset($payload['jw_size_chart']);
        app(LazadaListingPreflight::class)->validate('token',$payload);
    }

    public function test_leaf_and_required_fields_are_checked_against_current_provider_schema(): void
    {
        config(['marketplace.lazada.enabled'=>true,'marketplace.lazada.app_key'=>'123','marketplace.lazada.app_secret'=>'fixture']);
        Http::preventStrayRequests();
        Http::fake(function($request) {
            return str_contains($request->url(),'/category/tree/get')
                ? Http::response(['code'=>'0','data'=>[['category_id'=>1,'name'=>'Root','leaf'=>false,'children'=>[['category_id'=>2,'name'=>'Leaf','leaf'=>true]]]]])
                : Http::response(['code'=>'0','data'=>[
                    ['name'=>'brand','attribute_type'=>'normal','is_mandatory'=>1,'input_type'=>'text'],
                    ['name'=>'color_family','attribute_type'=>'sku','is_mandatory'=>1,'input_type'=>'text'],
                    ['name'=>'quantity','attribute_type'=>'sku','is_mandatory'=>1,'input_type'=>'numeric'],
                ]]);
        });
        $payload=['Request'=>['Product'=>['PrimaryCategory'=>'2','Attributes'=>['brand'=>'Selected'],
            'Skus'=>['Sku'=>[['color_family'=>'Blue','quantity'=>'0'],['color_family'=>'Red','quantity'=>'0']]]]]];
        $preflight=app(LazadaListingPreflight::class);
        $preflight->validate('token',$payload);
        $bad=$payload;unset($bad['Request']['Product']['Skus']['Sku'][1]['color_family']);
        try {$preflight->validate('token',$bad);$this->fail('Missing SKU attribute accepted');}
        catch (\Illuminate\Validation\ValidationException $e) {$this->assertArrayHasKey('attributes',$e->errors());}
        $bad=$payload;$bad['Request']['Product']['PrimaryCategory']='1';
        try {$preflight->validate('token',$bad);$this->fail('Parent category accepted');}
        catch (\Illuminate\Validation\ValidationException $e) {$this->assertArrayHasKey('category',$e->errors());}
        Http::assertNotSent(fn($request)=>str_contains($request->url(),'/product/create'));
    }
}
