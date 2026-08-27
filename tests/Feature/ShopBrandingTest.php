<?php

namespace Tests\Feature;

use App\Mail\OrderTrackingCodeMail;
use App\Models\StoreSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopBrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_tracking_uses_the_persisted_shop_name_for_its_customer_facing_brand_copy(): void
    {
        StoreSetting::query()->create([
            'key' => 'shop_name',
            'value' => ['data' => 'JW Studio'],
        ]);

        $this->get('/order-tracking')
            ->assertOk()
            ->assertSee('<title>Track Your Order | JW Studio</title>', false)
            ->assertSee('Return to JW Studio Home')
            ->assertDontSee('Return to Atelier Home');
    }

    public function test_shared_storefront_shell_and_tracking_email_use_the_persisted_shop_name(): void
    {
        StoreSetting::query()->create([
            'key' => 'shop_name',
            'value' => ['data' => 'JW Studio'],
        ]);

        $this->get('/shop')
            ->assertOk()
            ->assertSee('>JW Studio</span>', false)
            ->assertSee('aria-label="JW Studio home"', false)
            ->assertSee('© 2026 JW Studio. All rights reserved.', false)
            ->assertDontSee('>ATELIER</span>', false)
            ->assertDontSee('ATELIER Design Studio Inc.', false);

        $mail = new OrderTrackingCodeMail('123456');

        $this->assertSame('Your JW Studio order tracking code', $mail->envelope()->subject);
        $this->assertStringContainsString('Your JW Studio order tracking verification code is:', $mail->render());
    }
}
