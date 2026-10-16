<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogisticsCommercePresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tracking_does_not_invent_payment_or_delivery_events_from_status_label(): void
    {
        $payload = ['number'=>'JW-TEST','currency'=>'MYR','currencyExchangeRate'=>1,'statusLabel'=>'Delivered',
            'timeline'=>[['title'=>'Order received','description'=>'Created','timestamp'=>null,'location'=>null]],
            'items'=>[],'address'=>null,'tracking'=>['number'=>null,'carrier'=>null],
            'totals'=>['subtotal'=>0,'shipping'=>0,'total'=>0]];
        $html = view('storefront.themes.logistics-tracking-result',['trackingPayload'=>$payload])->render();
        $this->assertStringContainsString('JW-TEST',$html);
        $this->assertStringContainsString('Order received',$html);
        $this->assertStringNotContainsString('data-stage-completed="payment"',$html);
        $this->assertStringNotContainsString('data-stage-completed="delivered"',$html);
        $payload['timeline'][]=['title'=>'Payment confirmed','description'=>'Paid','timestamp'=>null,'location'=>null];
        $html=view('storefront.themes.logistics-tracking-result',['trackingPayload'=>$payload])->render();
        $this->assertStringContainsString('data-stage-completed="payment"',$html);
    }

    public function test_empty_cart_and_checkout_use_shared_operational_forms(): void
    {
        $this->withoutVite();
        view()->share('errors',new \Illuminate\Support\ViewErrorBag());
        $data=['items'=>collect(),'coupon'=>null,'summary'=>['subtotal'=>0,'discount'=>0,'shipping'=>0,'tax'=>0,'total'=>0,'tax_quote'=>['status'=>'not_configured']],
            'checkoutEmail'=>'','savedAddresses'=>[],'hostedReturn'=>null,'selectedDelivery'=>'standard',
            'deliveryOptions'=>[],'paymentMethods'=>[],'paymentMethodModels'=>[],'shippingEstimateAvailable'=>false];
        $cart=view('storefront.themes.logistics-cart',$data)->render();
        $this->assertStringContainsString('Your cart is empty',$cart);
        $checkout=view('storefront.themes.logistics-checkout',$data)->render();
        $this->assertStringContainsString('data-checkout-shipping-quote-url',$checkout);
        $this->assertStringContainsString('name="first_name"',$checkout);
        $this->assertStringContainsString('data-checkout-submit',$checkout);
        $drawer=view('storefront.themes.logistics-cart-drawer',$data)->render();
        $this->assertStringContainsString('data-cart-drawer-close',$drawer);
        $tracking=view('storefront.themes.logistics-tracking',['step'=>'lookup','trackingPayload'=>null])->render();
        $this->assertStringContainsString(route('order-tracking.request'),$tracking);
        $order=new \App\Models\Order(['number'=>'JW-PENDING','status'=>'pending_payment','currency'=>'MYR','currency_exchange_rate'=>1,'subtotal'=>0,'shipping_amount'=>0,'total'=>0]);
        $order->setRelation('payments',collect([new \App\Models\OrderPayment(['status'=>'pending','gateway'=>'bank_transfer'])]))->setRelation('addresses',collect())->setRelation('items',collect());
        $success=view('storefront.themes.logistics-success',['order'=>$order,'paymentProof'=>null])->render();
        $this->assertStringContainsString('Payment confirmation is still pending.',$success);
        $this->assertStringNotContainsString('>Paid<',$success);
        $quote=app(\App\Services\WebsiteThemeDemoData::class)->quotationCheckout(['demoProducts'=>collect([new \App\Models\Product(['title'=>'Container'])])]);
        $quotation=view('storefront.themes.logistics-checkout',$quote)->render();
        $this->assertStringContainsString('DEMO-QT-100328',$quotation);
        $this->assertStringContainsString(route('quotations.checkout.store',$quote['version']),$quotation);
    }
}
