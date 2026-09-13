<?php

namespace Tests\Feature;

use App\Services\Marketplace\LazadaClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LazadaImageMigrationTest extends TestCase
{
    public function test_migration_signs_request_and_returns_only_confirmed_platform_url(): void
    {
        config(['marketplace.publish_operations_enabled'=>true,'marketplace.lazada.enabled'=>true,
            'marketplace.lazada.app_key'=>'123','marketplace.lazada.app_secret'=>'fixture']);
        Http::fake(['*'=>Http::response(['code'=>'0','data'=>['image'=>['url'=>'https://my-live-02.slatic.net/p/test.jpg']]])]);
        $this->assertSame('https://my-live-02.slatic.net/p/test.jpg',app(LazadaClient::class)->migrateImage('token','https://ecom.jwaistudio.online/images/catalog/test.jpg'));
        Http::assertSent(function ($request) {
            $params=$request->data();$sign=$params['sign'];unset($params['sign']);ksort($params,SORT_STRING);
            $base='/image/migrate';foreach($params as $key=>$value)$base.=$key.$value;
            return $request->url()==='https://api.lazada.com.my/rest/image/migrate'
                && $sign===strtoupper(hash_hmac('sha256',$base,'fixture'))
                && json_decode($params['payload'],true)===['Request'=>['Image'=>['Url'=>'https://ecom.jwaistudio.online/images/catalog/test.jpg']]];
        });
    }

    public function test_disabled_migration_sends_nothing(): void
    {
        config(['marketplace.publish_operations_enabled'=>false]);
        Http::fake();
        try {app(LazadaClient::class)->migrateImage('token','https://example.com/image.jpg');$this->fail('Disabled write allowed');}
        catch (\RuntimeException $error) {$this->assertStringContainsString('disabled',$error->getMessage());}
        Http::assertNothingSent();
    }

    public function test_unconfirmed_or_foreign_image_url_is_never_accepted(): void
    {
        config(['marketplace.publish_operations_enabled'=>true,'marketplace.lazada.enabled'=>true,
            'marketplace.lazada.app_key'=>'123','marketplace.lazada.app_secret'=>'fixture']);
        foreach (['https://slatic.net.evil.test/p/a.jpg','https://user:pass@my-live-02.slatic.net/p/a.jpg','http://my-live-02.slatic.net/p/a.jpg',''] as $url) {
            Http::fake(['*'=>Http::response(['code'=>'0','data'=>['image'=>['url'=>$url]]])]);
            try {app(LazadaClient::class)->migrateImage('token','https://example.com/image.jpg');$this->fail('Invalid result accepted');}
            catch (\RuntimeException $error) {$this->assertStringContainsString('image',$error->getMessage());}
        }
    }
}
