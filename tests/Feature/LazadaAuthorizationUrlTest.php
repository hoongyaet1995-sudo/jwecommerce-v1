<?php

namespace Tests\Feature;

use App\Services\Marketplace\LazadaAuthorizationUrl;
use RuntimeException;
use Tests\TestCase;

class LazadaAuthorizationUrlTest extends TestCase
{
    public function test_official_redirect_preserves_state_and_exact_deployment_callback(): void
    {
        $url = LazadaAuthorizationUrl::build('139209', 'https://ecom.example.test/admin/marketplace/oauth/lazada/callback',
            'https://ecom.example.test', str_repeat('a', 64));
        $this->assertSame('auth.lazada.com', parse_url($url, PHP_URL_HOST));
        $this->assertSame('/oauth/authorize', parse_url($url, PHP_URL_PATH));
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $this->assertSame(['response_type'=>'code', 'force_auth'=>'true',
            'redirect_uri'=>'https://ecom.example.test/admin/marketplace/oauth/lazada/callback',
            'client_id'=>'139209', 'state'=>str_repeat('a',64)], $query);
    }

    public function test_wrong_deployment_or_unsafe_callback_cannot_start_authorization(): void
    {
        foreach (['https://dashboard.example.test/admin/marketplace/oauth/lazada/callback',
            'http://ecom.example.test/admin/marketplace/oauth/lazada/callback',
            'https://ecom.example.test/admin/marketplace/oauth/lazada/callback?state=other',
            'https://user@ecom.example.test/admin/marketplace/oauth/lazada/callback',
            'https://ecom.example.test/another/callback'] as $callback) {
            try {
                LazadaAuthorizationUrl::build('139209',$callback,'https://ecom.example.test',str_repeat('a',64));
                $this->fail('Unsafe callback accepted: '.$callback);
            } catch (RuntimeException $error) {
                $this->assertSame('Lazada authorization configuration is invalid.', $error->getMessage());
            }
        }
    }

    public function test_missing_application_or_invalid_state_is_rejected(): void
    {
        foreach ([['',str_repeat('a',64)],['139209',''],['139209','not-a-state']] as [$key,$state]) {
            try {
                LazadaAuthorizationUrl::build($key,'https://ecom.example.test/admin/marketplace/oauth/lazada/callback','https://ecom.example.test',$state);
                $this->fail('Invalid authorization input accepted.');
            } catch (RuntimeException $error) {
                $this->assertNotEmpty($error->getMessage());
            }
        }
    }
}
