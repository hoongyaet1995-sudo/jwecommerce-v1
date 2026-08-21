<?php

namespace Tests\Feature\Admin;

use App\Models\Coupon;
use App\Models\StoreSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommerceConfigurationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_persist_coupon_shipping_and_simulated_payment_configuration(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $coupon = $this->actingAs($admin)->postJson('/admin/api/coupons', [
            'code' => 'studio10', 'name' => 'Studio Welcome', 'description' => 'Ten percent off.', 'discountType' => 'percentage',
            'value' => 10, 'minimumSpend' => 50, 'usageLimit' => 100, 'perCustomerLimit' => 1, 'isActive' => true,
        ])->assertCreated()->assertJsonPath('data.code', 'STUDIO10')->json('data');
        $this->assertDatabaseHas('coupons', ['id' => $coupon['id'], 'code' => 'STUDIO10', 'min_spend' => 5000]);

        $shipping = [['id'=>'rate-standard','name'=>'Standard Delivery','zoneRegion'=>'all','logisticType'=>'all','calculationMode'=>'per_order','rateType'=>'flat','tiers'=>[],'flatRateCost'=>12.5,'freeShippingThreshold'=>200,'estimatedDays'=>'2-4 days','isActive'=>true,'createdAt'=>now()->toIso8601String(),'updatedAt'=>now()->toIso8601String()]];
        $payments = [['id'=>'paypal','name'=>'PayPal','category'=>'card','description'=>'Simulated PayPal','logo'=>'','isEnabled'=>true,'isSandbox'=>true,'sortOrder'=>1,'credentials'=>['clientSecret'=>'must-not-persist'],'supportedCurrencies'=>['USD'],'transactionFee'=>['type'=>'fixed','feePayer'=>'merchant'],'limits'=>[],'features'=>[],'webhookUrl'=>'']];
        $this->actingAs($admin)->putJson('/admin/api/commerce-settings', ['shippingRates'=>$shipping,'paymentGateways'=>$payments,'globalPayment'=>['defaultGateway'=>'paypal']])->assertOk()
            ->assertJsonPath('data.shippingRates.0.flatRateCost', 12.5)->assertJsonMissing(['clientSecret'=>'must-not-persist']);
        $this->assertSame([], data_get(StoreSetting::query()->where('key', 'payment_gateways')->firstOrFail()->value, 'data.0.credentials'));
    }

    public function test_commerce_configuration_writes_require_admin_authentication(): void
    {
        $this->postJson('/admin/api/coupons', [])->assertUnauthorized();
        $this->putJson('/admin/api/commerce-settings', [])->assertUnauthorized();
    }
}
