<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** One purchase contract for every storefront theme and transaction entry. */
class ProductPurchaseRules
{
    public function isBuy(Product $product): bool
    {
        return $product->purchase_mode === 'buy';
    }

    public function showsPrice(Product $product): bool
    {
        return $this->isBuy($product) || ($product->purchase_mode === 'quote' && in_array($product->price_display, ['exact', 'from'], true));
    }

    public function price(Product $product, ?int $amount): string
    {
        if (! $this->showsPrice($product)) return '';
        return ($product->purchase_mode === 'quote' && $product->price_display === 'from' ? 'From ' : '').app(\App\Support\StorefrontCurrencyContext::class)->format($amount ?? $product->base_price);
    }

    public function assertPurchasable(ProductVariant $variant): void
    {
        $product = $variant->product;
        if (! $product || ! $this->isBuy($product) || $product->status !== 'active') {
            throw ValidationException::withMessages(['variant_id' => 'This item is no longer available for online purchase.']);
        }
    }

    public function cartQuantities(Request $request): array
    {
        $cart = $request->session()->get('cart', []);
        if (! $cart) return [];
        $valid = ProductVariant::query()->whereKey(array_keys($cart))->whereHas('product', fn ($q) => $q->where('status', 'active')->where('purchase_mode', 'buy'))->pluck('id')->all();
        $kept = array_intersect_key($cart, array_flip($valid));
        if (count($kept) !== count($cart)) {
            $request->session()->put('cart', $kept);
            $request->session()->flash('cart_purchase_notice', 'Some items are no longer available for online purchase and were removed from your cart.');
        }
        $gifts=$request->session()->get('gift_selections',[]);
        if($gifts){
            $variants=ProductVariant::with('product')->whereIn('id',array_keys($kept))->get()->keyBy('id');
            $items=collect($kept)->map(fn($n,$id)=>['variant'=>$variants->get($id),'quantity'=>(int)$n])->filter(fn($i)=>$i['variant']);
            $coupon=\App\Models\Coupon::where('code',$request->session()->get('coupon_code'))->first();
            $summary=app(CartPricingService::class)->summarize($items,$coupon);
            $snapshot=$summary['promotion_snapshot']??[];$removed=false;
            foreach($gifts as $offerId=>&$selected){foreach($selected as $id=>&$n){
                $price=(int)($variants->get($id)?->price??0);
                $allowed=(($snapshot['type']??null)==='gift'&&($snapshot['offer_id']??null)==$offerId&&$price>0)?intdiv((int)($snapshot['allocations'][(string)$id]??0),$price):0;
                $old=min((int)$n,(int)($kept[$id]??0));$keep=min($old,$allowed);
                if($old>$keep){$kept[$id]-=$old-$keep;if($kept[$id]<=0)unset($kept[$id]);$removed=true;}
                $n=$keep;
            }unset($n);}unset($selected);
            $request->session()->put(['cart'=>$kept,'gift_selections'=>$gifts]);
            if($removed)$request->session()->flash('cart_purchase_notice','Your free gift selection was updated because this cart no longer qualifies for every selected gift. No removed gift is charged at its regular price.');
        }
        return $kept;
    }
}
