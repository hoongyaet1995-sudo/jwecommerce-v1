<?php
namespace Tests\Feature;

use App\Models\{Product, User};
use App\Services\WebsiteDesignService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FoodThemePackTest extends TestCase
{
    use RefreshDatabase;

    public function test_food_account_structures_use_canonical_customer_forms_and_independent_media(): void
    {
        app(WebsiteDesignService::class)->save(['theme'=>'food','overrides'=>[]]);
        $user=User::factory()->create(['is_admin'=>false]);
        $customer=\App\Models\Customer::create(['user_id'=>$user->id,'email'=>$user->email,'first_name'=>'Alex']);
        $customer->addresses()->create(['label'=>'Home','first_name'=>'Alex','last_name'=>'Sample','line_1'=>'Test address','city'=>'Test city','postal_code'=>'00000','country'=>'MY','is_default'=>true]);
        $this->actingAs($user,'customer')->get('/account')->assertOk()
            ->assertSee('fd-address-workspace',false)->assertSee('fd-profile-editor',false)
            ->assertSee('Your latest orders')->assertSee('/images/themes/food/account-vouchers.png',false)
            ->assertSee('/images/themes/food/account-addresses.png',false)
            ->assertSee('name="first_name"',false)->assertSee('name="is_default"',false);
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        $profile=['food'=>['media'=>['tracking'=>['desktop'=>'/images/custom-tracking.png']]]];
        $this->get('/admin/website-design/preview?theme=food&surface=tracking&customization='.urlencode(json_encode($profile)))
            ->assertOk()->assertSee('/images/custom-tracking.png',false)->assertSee('disabled',false);
    }

    public function test_food_surface_contract_and_empty_story_fallback(): void
    {
        $service=app(WebsiteDesignService::class);
        $service->save(['theme'=>'food','overrides'=>[]]);
        foreach (['login','register','forgot-password'] as $page) $this->get('/account/'.$page)->assertOk()->assertSee('data-food-auth', false);
        $product=Product::create(['title'=>'Catalog pantry item','handle'=>'food-contract','status'=>'active','base_price'=>4900]);
        $product->variants()->create(['sku'=>'FOOD-CONTRACT','title'=>'500ml','price'=>4900,'stock'=>0,'enabled'=>true]);
        $this->get('/shop')->assertOk()->assertSee('data-website-theme="food"',false);
        $this->get('/products/food-contract')->assertOk()->assertSee('data-food-pdp',false)->assertDontSee('data-commerce-product-zone="story"',false);
        $product->pdpContent()->create(['story'=>['heading'=>'Real product story','description'=>'Saved editorial copy.']]);
        $this->get('/products/food-contract')->assertOk()->assertSee('Real product story')->assertSee('data-commerce-product-zone="story"',false);
        $this->get('/cart')->assertOk()->assertSee('data-food-cart-page',false);
        $this->get('/cart/drawer')->assertOk()->assertSee('data-food-cart',false);
        $this->get('/order-tracking')->assertOk()->assertSee('data-food-tracking',false)->assertDontSee('Your items');
        $this->get('/order-tracking?step=verify')->assertOk()->assertSee('name="code"',false);
        $this->get('/order-tracking?step=verified_result')->assertOk()->assertDontSee('Your items');
        $this->withSession(['cart'=>[$product->variants->first()->id=>1]])->get('/checkout')->assertOk()->assertSee('data-food-checkout',false)->assertSee('data-checkout-shipping-quote-url',false);
        $service->save(['theme'=>'default','overrides'=>[]]);
        $this->get('/shop')->assertOk()->assertDontSee('data-website-theme="food"',false);
    }

    public function test_nonempty_food_checkout_composition(): void
    {
        app(WebsiteDesignService::class)->save(['theme'=>'food','overrides'=>[]]);
        \App\Models\StoreSetting::updateOrCreate(['key'=>'bank_transfer'], ['value'=>['data'=>['isEnabled'=>true,'allowProofUpload'=>true,'bankAccounts'=>[['id'=>'fixture-bank','isActive'=>true,'bankName'=>'Sample Bank','accountNumber'=>'TEST-ONLY','isPrimary'=>true]]]]]);
        \App\Models\StoreSetting::updateOrCreate(['key'=>'cod_config'], ['value'=>['data'=>['isEnabled'=>true,'allowedRegions'=>'peninsular_only']]]);
        $product=Product::create(['title'=>'Extra Virgin Olive Oil','handle'=>'food-visual-fixture','status'=>'active','base_price'=>4800]);
        $product->images()->create(['path'=>'/images/themes/food/olive-oil.png','position'=>0]);
        $variant=$product->variants()->create(['sku'=>'FOOD-VISUAL-FIXTURE','title'=>'250 ml','price'=>4800,'stock'=>0,'enabled'=>true]);
        $response=$this->withSession(['cart'=>[$variant->id=>2]])->get('/checkout');
        $response->assertOk()->assertSee('Contact information')->assertSee('Shipping address')
            ->assertSee('Shipping method')->assertSee('Payment method')->assertSee('fd-payment-methods',false);
        if (getenv('FOOD_VISUAL_EXPORT') === '1') {
            foreach (['checkout'=>$response, 'cart'=>$this->get('/cart')] as $surface=>$page) {
                $html=$page->getContent();
                $html=preg_replace('/data-checkout-shipping-quote-url="[^"]*"/', '', $html);
                $html=preg_replace('/data-delivery-quote-url="[^"]*"/', '', $html);
                $html=str_replace('</head>', '<script>document.addEventListener("submit",event=>event.preventDefault(),true);</script></head>', $html);
                $html=str_replace('<body>', '<body><div style="padding:10px;background:#515c3d;color:white;text-align:center">Isolated visual fixture - submissions disabled</div>', $html);
                file_put_contents(public_path('page-template-sample/food-'.$surface.'-acceptance.html'), $html);
            }
        }
    }

    public function test_success_uses_payment_state_instead_of_fulfillment_state(): void
    {
        app(WebsiteDesignService::class)->save(['theme'=>'food','overrides'=>[]]);
        $order=\App\Models\Order::create(['number'=>'FOOD-SUCCESS','email'=>'buyer@example.com','currency'=>'MYR','currency_exchange_rate'=>1,'status'=>'processing','subtotal'=>10000,'discount_amount'=>0,'shipping_amount'=>0,'tax_amount'=>0,'total'=>10000]);
        $sample=Product::create(['title'=>'Extra Virgin Olive Oil','handle'=>'food-success-fixture','status'=>'active','base_price'=>10000]);
        $sku=$sample->variants()->create(['sku'=>'FOOD-SUCCESS-FIXTURE','title'=>'500 ml','price'=>10000,'stock'=>0,'enabled'=>true]);
        $order->items()->create(['product_variant_id'=>$sku->id,'sku'=>$sku->sku,'title'=>$sample->title,'variant_title'=>$sku->title,'image_url'=>'/images/themes/food/olive-oil.png','unit_price'=>10000,'quantity'=>1,'line_total'=>10000]);
        $payment=$order->payments()->create(['gateway'=>'cod','provider'=>'cod','environment'=>'test','status'=>'pending','amount'=>10000,'currency'=>'MYR']);
        $this->withSession(['last_order_id'=>$order->id])->get('/order/success')->assertOk()->assertSee('Payment confirmation is still pending.')->assertDontSee('Payment has been recorded.');
        if (getenv('FOOD_VISUAL_EXPORT') === '1') $this->exportReadonlyVisual('pending',$this->get('/order/success')->getContent());
        $payment->update(['status'=>'paid']);
        $this->withSession(['last_order_id'=>$order->id])->get('/order/success')->assertOk()->assertSee('Payment has been recorded.');
        if (getenv('FOOD_VISUAL_EXPORT') === '1') $this->exportReadonlyVisual('success',$this->get('/order/success')->getContent());
    }

    public function test_food_media_slots_keep_account_banner_separate_from_auth_artwork(): void
    {
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        $profiles=['food'=>['media'=>[
            'account'=>['desktop'=>'/images/auth-artwork.png','alt'=>'Pantry artwork'],
            'accountHero'=>['desktop'=>'/images/account-banner.png','alt'=>'Account banner'],
        ]]];
        $this->get('/admin/website-design/preview?theme=food&surface=login&customization='.urlencode(json_encode($profiles)))
            ->assertOk()->assertSee('/images/auth-artwork.png',false)->assertDontSee('/images/account-banner.png',false)
            ->assertDontSee('A place to come home to.')->assertSee('Email address');
        $this->get('/admin/website-design/preview?theme=food&surface=account&customization='.urlencode(json_encode($profiles)))
            ->assertOk()->assertSee('/images/account-banner.png',false)->assertDontSee('/images/auth-artwork.png',false)
            ->assertSee('Welcome back, Sample.');
        $this->get('/admin/website-design/preview?theme=food&surface=account')->assertOk()->assertSee('/images/themes/food/hero.png',false);
        $hidden=['food'=>['media'=>['accountHero'=>['hidden'=>true]]]];
        $this->get('/admin/website-design/preview?theme=food&surface=account&customization='.urlencode(json_encode($hidden)))->assertOk()->assertDontSee('theme-account-media fd-account-banner',false);
    }

    public function test_food_draft_does_not_apply_and_account_writes_are_disabled(): void
    {
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        $this->get('/admin/website-design/preview?theme=food&surface=register')->assertOk()->assertSee('disabled',false)->assertSee('name="terms"',false);
        $this->get('/admin/website-design/preview?theme=food&surface=account')->assertOk()->assertSee('data-food-account',false)->assertSee('disabled',false);
        $this->assertSame('default',app(WebsiteDesignService::class)->resolve());
    }
    private function exportReadonlyVisual(string $surface,string $html): void
    {
        $html=str_replace('</head>', '<script>document.addEventListener("submit",event=>event.preventDefault(),true);</script></head>', $html);
        $html=str_replace('<body>', '<body><div style="padding:10px;background:#515c3d;color:white;text-align:center">Isolated visual fixture - submissions disabled</div>', $html);
        file_put_contents(public_path('page-template-sample/food-'.$surface.'-acceptance.html'), $html);
    }

}
