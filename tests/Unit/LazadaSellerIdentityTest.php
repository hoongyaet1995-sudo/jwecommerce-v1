<?php

namespace Tests\Unit;

use App\Services\Marketplace\LazadaSellerIdentity;
use PHPUnit\Framework\TestCase;

class LazadaSellerIdentityTest extends TestCase
{
    public function test_active_production_seller_is_verified_by_its_registered_seller_id_not_a_test_short_code(): void
    {
        LazadaSellerIdentity::assertCurrent(
            (object) ['external_shop_id' => '300927968304'],
            ['seller_id' => '300927968304', 'short_code' => 'LIVE-MY-SELLER', 'status' => 'ACTIVE'],
            'before product publishing',
        );
        $this->addToAssertionCount(1);
    }

    public function test_another_or_inactive_seller_is_rejected(): void
    {
        foreach ([
            ['seller_id' => '300163725140', 'short_code' => 'MY4NA1T7CK', 'status' => 'ACTIVE'],
            ['seller_id' => '300927968304', 'short_code' => 'LIVE-MY-SELLER', 'status' => 'INACTIVE'],
        ] as $identity) {
            try {
                LazadaSellerIdentity::assertCurrent((object) ['external_shop_id' => '300927968304'], $identity, 'before product publishing');
                $this->fail('An invalid seller identity was accepted.');
            } catch (\RuntimeException $error) {
                $this->assertStringContainsString('identity changed', $error->getMessage());
            }
        }
    }
}
