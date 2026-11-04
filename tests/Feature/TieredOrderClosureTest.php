<?php
namespace Tests\Feature;

use App\Models\{Product, TieredDiscount, StoreSetting, Order, User, ReturnRequest};
use App\Services\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TieredOrderClosureTest extends TestCase
{
    use RefreshDatabase;

    private function checkout(int $quantity = 3): Order
    {
        config(['inventory.current_stock_only' => true]);
        \Illuminate\Support\Facades\DB::table('inventory_configuration')->where('id',1)->update(['operating_mode'=>'independent_stores']);
        $product = Product::create(['title'=>'Closure item','handle'=>'closure-item','base_price'=>15900,'status'=>'active','purchase_mode'=>'buy']);
        $variant = $product->variants()->create(['sku'=>'CLOSURE','title'=>'One','price'=>15900,'stock'=>10,'enabled'=>true]);
        app(\App\Services\Inventory\CatalogStockService::class)->setPhysical($variant->id,10,null,'closure-opening',User::factory()->create(['is_admin'=>true])->id);
        $offer = TieredDiscount::create(['name'=>'Tier closure','scope'=>'all','product_ids'=>[],'discount_type'=>'fixed','tiers'=>[['spend'=>40000,'discount'=>27800]],'enabled'=>true]);
        StoreSetting::updateOrCreate(['key'=>'shipping_rates'],['value'=>['data'=>[['id'=>'standard','name'=>'Standard','zoneRegion'=>'all','logisticType'=>'all','calculationMode'=>'per_order','rateType'=>'flat','flatRateCost'=>0,'isActive'=>true]]]]);
        StoreSetting::updateOrCreate(['key'=>'bank_transfer'],['value'=>['data'=>['isEnabled'=>true,'bankAccounts'=>[['id'=>'test','bankName'=>'Test','accountNumber'=>'123','isPrimary'=>true,'isActive'=>true]]]]]);
        return app(CheckoutService::class)->checkout('closure@example.test',collect([['variant'=>$variant,'quantity'=>$quantity]]),null,
            ['first_name'=>'Test','last_name'=>'Buyer','line_1'=>'Test road','city'=>'Kuala Lumpur','postal_code'=>'50000','country'=>'MY'],'bank_transfer');
    }

    public function test_current_stock_checkout_snapshot_and_cancel_are_historical_and_idempotent(): void
    {
        $order=$this->checkout(); $item=$order->items()->sole();
        $this->assertSame(27800,(int)$order->discount_amount);
        $this->assertSame(19900,$order->promotion_snapshot['lines'][(string)$item->product_variant_id]['net']);
        $this->assertSame(7,app(\App\Services\Inventory\WebsiteInventoryAvailability::class)->quantity($item->productVariant));
        $saved=$order->promotion_snapshot;
        TieredDiscount::query()->update(['enabled'=>false]);
        $item->productVariant->update(['price'=>99900]);
        app(CheckoutService::class)->transition($order,'cancelled');
        app(CheckoutService::class)->transition($order->fresh(),'cancelled');
        $this->assertSame($saved,$order->fresh()->promotion_snapshot);
        $this->assertSame(10,app(\App\Services\Inventory\WebsiteInventoryAvailability::class)->quantity($item->productVariant->fresh()));
    }

    public function test_snapshot_cannot_be_overwritten(): void
    {
        $order=$this->checkout();
        $this->expectException(\LogicException::class);
        $order->update(['promotion_snapshot'=>['discount'=>0]]);
    }

    public function test_insufficient_current_stock_leaves_no_order_or_stock_debit(): void
    {
        try { $this->checkout(11); $this->fail('Oversold opening stock.'); }
        catch (\RuntimeException $e) { $this->assertSame('Requested quantity is unavailable.',$e->getMessage()); }
        $this->assertSame(0,Order::count());
        $variant=\App\Models\ProductVariant::where('sku','CLOSURE')->firstOrFail();
        $this->assertSame(10,app(\App\Services\Inventory\WebsiteInventoryAvailability::class)->quantity($variant));
    }

    public function test_multi_line_discount_and_tax_preserve_every_minor_unit(): void
    {
        $amounts=app(\App\Services\Promotions\OrderPromotionAmounts::class);
        $snapshot=$amounts->snapshot(['promotion_snapshot'=>['type'=>'coupon'], 'discount'=>101,'tax'=>17],collect([
            ['variant'=>(object)['id'=>1,'price'=>100], 'quantity'=>3],
            ['variant'=>(object)['id'=>2,'price'=>200], 'quantity'=>1],
        ]));
        $this->assertSame(101,array_sum(array_column($snapshot['lines'],'discount')));
        $this->assertSame(399,array_sum(array_column($snapshot['lines'],'net')));
        $this->assertSame(17,array_sum(array_column($snapshot['lines'],'tax')));
        $order=new Order(['promotion_snapshot'=>$snapshot]);
        $item=new \App\Models\OrderItem(['product_variant_id'=>1]);
        $parts=0;
        for($i=0;$i<3;$i++) $parts+=$amounts->itemAmount($order,$item,1,$i);
        $this->assertSame($snapshot['lines'][1]['net']+$snapshot['lines'][1]['tax'],$parts);
    }

    public function test_return_rejects_gross_price_and_accepts_net_share_with_tax(): void
    {
        $order=$this->checkout(); $item=$order->items()->sole();
        $order->payments()->update(['status'=>'paid']);
        $r=ReturnRequest::create(['order_id'=>$order->id,'order_item_id'=>$item->id,'customer_id'=>$order->customer_id,'status'=>'received','resolution'=>'refund','quantity'=>1,'received_quantity'=>1,'reason'=>'Test','eligible_until'=>now()->addDays(14)]);
        $actor=User::factory()->create(['is_admin'=>true]);
        $service=app(\App\Services\Returns\ReturnRequestService::class);
        $data=['external_refund_reference'=>'LOCAL ONLY','refund_note'=>'No provider call'];
        try { $service->transition($r,'resolve_refund',$actor,$data+['refund_amount'=>159]); $this->fail('Gross price bypassed discounted item ceiling.'); }
        catch (\Illuminate\Validation\ValidationException $e) { $this->assertArrayHasKey('refund_amount',$e->errors()); }
        $cap=app(\App\Services\Promotions\OrderPromotionAmounts::class)->itemAmount($order,$item,1);
        $service->transition($r,'resolve_refund',$actor,$data+['refund_amount'=>$cap/100]);
        $this->assertDatabaseHas('order_refunds',['order_id'=>$order->id,'amount'=>$cap]);
    }

    public function test_partial_admin_refund_cannot_exceed_discounted_unit_and_remainder_is_exact(): void
    {
        $order=$this->checkout(); $item=$order->items()->sole();
        $order->payments()->update(['status'=>'paid']);
        $actor=User::factory()->create(['is_admin'=>true]);
        $service=app(\App\Services\Admin\OrderOperationsService::class);
        try { $service->refund($order,15900,'Test',$actor->id,[$item->id=>1]); $this->fail('Gross price bypassed net ceiling.'); }
        catch (\Illuminate\Validation\ValidationException $e) { $this->assertArrayHasKey('amount',$e->errors()); }
        $amounts=app(\App\Services\Promotions\OrderPromotionAmounts::class);
        $first=$amounts->itemAmount($order,$item,1);
        $rest=$amounts->itemAmount($order,$item,2,1);
        $this->assertSame((int)$order->total,$first+$rest);
        $service->refund($order,$first,'Test first',$actor->id,[$item->id=>1]);
        $service->refund($order->fresh(),$rest,'Test rest',$actor->id,[$item->id=>2]);
        $this->assertSame((int)$order->total,(int)$order->refunds()->sum('amount'));
        $this->assertSame(10,app(\App\Services\Inventory\WebsiteInventoryAvailability::class)->quantity($item->productVariant));
    }
}
