<?php
namespace Tests\Feature;

use App\Models\{Product, ProductVariant, Order, User};
use App\Services\{CheckoutService, StorefrontVariantData};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class ProductPurchaseFrontendTest extends TestCase
{
    use RefreshDatabase;

    private function variant(string $mode='buy'): ProductVariant
    {
        $product=Product::create(['title'=>'Purchase mode item','handle'=>'mode-item-'.Product::count(),'base_price'=>876543,'status'=>$mode==='buy'?'active':'draft','purchase_mode'=>$mode,'price_display'=>'hide']);
        return $product->variants()->create(['sku'=>'MODE-'.Product::count(),'title'=>'One size','price'=>876543,'stock'=>20,'enabled'=>true]);
    }

    public function test_non_buy_variant_is_rejected_before_cart_mutation(): void
    {
        foreach(['quote','display'] as $mode) {
            $v=$this->variant($mode);
            $this->post('/cart/items',['variant_id'=>$v->id,'quantity'=>1])->assertSessionHasErrors('variant_id')->assertSessionMissing('cart');
            $this->patch('/cart/items/'.$v->id,['quantity'=>1])->assertSessionHasErrors('variant_id')->assertSessionMissing('cart');
        }
    }

    public function test_stale_cart_removes_non_buy_items_and_reports_the_change(): void
    {
        $v=$this->variant('quote');
        $this->withSession(['cart'=>[$v->id=>2]])->get('/cart')->assertOk()->assertSessionHas('cart',[])->assertSee('no longer available for online purchase');
    }

    public function test_direct_checkout_rechecks_mode_before_creating_any_order(): void
    {
        $v=$this->variant('display');
        try {app(CheckoutService::class)->checkout('mode-test@example.test',collect([['variant'=>$v,'quantity'=>1]]));$this->fail('Checkout accepted display product');}
        catch(\Illuminate\Validation\ValidationException $error){$this->assertArrayHasKey('variant_id',$error->errors());}
        $this->assertSame(0,Order::count());
    }

    public function test_hidden_prices_are_absent_from_public_variant_payload(): void
    {
        $v=$this->variant('quote');
        $data=app(StorefrontVariantData::class)->payload(collect([$v]),collect(),$v->product)->first();
        $this->assertNull($data['price']);$this->assertNull($data['compareAt']);
        $this->assertFalse(app(StorefrontVariantData::class)->canAdd($v));
    }

    public function test_every_theme_renders_purchase_modes_without_business_writes(): void
    {
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        foreach(['default','home_deco','beauty','food','tech','baby','jewelry','fashion','universal'] as $theme) {
            foreach(['buy-exact','quote-exact','quote-from','quote-hide','display-hide'] as $configuration) {
                [$mode,$display]=explode('-', $configuration);
                $response=$this->get('/admin/website-design/preview?theme='.$theme.'&surface=pdp&purchaseMode='.$mode.'&priceDisplay='.$display)->assertOk();
                if ($mode === 'buy') $response->assertSee('data-pdp-add-to-cart',false);
                else $response->assertDontSee('data-pdp-add-to-cart',false)->assertDontSee('data-pdp-instant-buy',false);
                if($mode==='quote')$response->assertSee('data-product-enquiry',false);
                if ($display === 'hide') $response->assertDontSee('data-pdp-price',false);
                else $response->assertSee('data-pdp-price',false);
                if ($display === 'from') $response->assertSee('From ');
                $listing=$this->get('/admin/website-design/preview?theme='.$theme.'&surface=plp&purchaseMode='.$mode.'&priceDisplay='.$display)->assertOk();
                if ($mode !== 'buy') $this->assertDoesNotMatchRegularExpression('/<button[^>]*data-quick-add-trigger/', $listing->getContent(), $theme.' '.$mode);
            }
        }
        $this->assertSame(0,Product::count());
    }
    public function test_shared_quick_buy_component_never_mounts_for_non_buy_products(): void
    {
        foreach (['quote','display'] as $mode) {
            $variant=$this->variant($mode); $product=$variant->product;
            $html=Blade::render('<x-storefront.quick-add-variant-modal :product="$product" :variants="$variants" :images="$images" />', ['product'=>$product,'variants'=>collect([$variant]),'images'=>collect()]);
            $this->assertStringNotContainsString('data-quick-add-modal', $html);
            $this->assertStringNotContainsString('876543', $html);
        }
    }

    public function test_buy_mode_still_uses_authoritative_current_stock_and_rejects_drafts(): void
    {
        config(['inventory.current_stock_only'=>true]);
        $variant=$this->variant();
        $balance=app(\App\Services\Inventory\InventoryBalanceProvisioner::class)->provision($variant->id,'shared',null);
        \Illuminate\Support\Facades\DB::table('inventory_configuration')->where('id',1)->update(['operating_mode'=>'shared_pool']);
        \Illuminate\Support\Facades\DB::table('inventory_balances')->where('id',$balance['balance_id'])->update(['current_stock'=>3]);
        $this->post('/cart/items',['variant_id'=>$variant->id,'quantity'=>2])->assertRedirect(route('cart.show'))->assertSessionHas('cart.'.$variant->id,2);
        $this->patch('/cart/items/'.$variant->id,['quantity'=>4])->assertSessionHasErrors('quantity')->assertSessionHas('cart.'.$variant->id,2);
        $variant->product->update(['status'=>'draft']);
        $this->post('/cart/items',['variant_id'=>$variant->id,'quantity'=>1])->assertSessionHasErrors('variant_id');
    }

    public function test_saved_product_projection_redacts_hidden_prices(): void
    {
        $variant=$this->variant('quote');
        $user=User::factory()->create(['is_admin'=>false]);
        $customer=\App\Models\Customer::create(['user_id'=>$user->id,'email'=>$user->email]);
        $customer->wishlistItems()->create(['product_id'=>$variant->product_id]);
        $saved=app(\App\Domain\Accounts\OriginalAccountPayloadFactory::class)->for($customer)['wishlist'][0]['product'];
        $this->assertSame('quote',$saved['purchaseMode']);
        $this->assertSame('',$saved['priceLabel']);
        $this->assertNull($saved['basePrice']);
        $this->assertNull($saved['variants'][0]['price']);
    }

}
