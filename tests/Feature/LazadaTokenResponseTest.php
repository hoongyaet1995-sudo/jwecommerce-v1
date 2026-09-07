<?php

namespace Tests\Feature;

use App\Services\Marketplace\LazadaTokenResponse;
use RuntimeException;
use Tests\TestCase;

class LazadaTokenResponseTest extends TestCase
{
    private function payload(): array
    {
        return ['code'=>'0','access_token'=>'fixture-access','refresh_token'=>'fixture-refresh',
            'expires_in'=>3600,'refresh_expires_in'=>86400,'country'=>'my','account_platform'=>'seller_center',
            'account'=>'fixture@example.test','country_user_info'=>[
                ['country'=>'my','seller_id'=>'2001','user_id'=>9999,'short_code'=>'MYTEST'],
            ]];
    }

    public function test_uses_provider_seller_identity_not_user_id_or_email(): void
    {
        $result=LazadaTokenResponse::validate($this->payload());
        $this->assertSame('2001',$result['seller_id']);
        $this->assertSame('my',$result['region']);
        $this->assertSame('fixture-access',$result['access_token']);
        $this->assertSame(3600,$result['expires_in']);
        $this->assertSame(86400,$result['refresh_expires_in']);
        $this->assertArrayNotHasKey('account',$result);
        $this->assertArrayNotHasKey('user_id',$result);
    }

    public function test_rejects_failed_incomplete_ambiguous_or_out_of_scope_authorization(): void
    {
        $cases=[['code'=>'InvalidCode'],['access_token'=>''],['refresh_token'=>null],['expires_in'=>0],
            ['expires_in'=>'3600'],['refresh_expires_in'=>-1],['country'=>'cb'],['country'=>'sg'],
            ['account_platform'=>'buyer'],['country_user_info'=>[]],
            ['country_user_info'=>[['country'=>'my','user_id'=>2001]]],
            ['country_user_info'=>[['country'=>'my','seller_id'=>0]]],
            ['country_user_info'=>[['country'=>'sg','seller_id'=>'2001']]],
            ['country_user_info'=>[['country'=>'my','seller_id'=>'2001'],['country'=>'my','seller_id'=>'2002']]]];
        foreach ($cases as $replacement) {
            try {
                LazadaTokenResponse::validate(array_replace($this->payload(),$replacement));
                $this->fail('Unsafe token response accepted.');
            } catch (RuntimeException $error) {
                $this->assertSame('Lazada authorization response is incomplete or unsupported.',$error->getMessage());
                $this->assertStringNotContainsString('fixture-access',$error->getMessage());
            }
        }
    }
}
