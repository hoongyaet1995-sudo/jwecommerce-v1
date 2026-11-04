<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\ProductVariant;
use App\Services\Promotions\FreeGiftOfferService;
use App\Services\ProductPurchaseRules;
use App\Services\Inventory\WebsiteInventoryAvailability;
use Illuminate\Validation\ValidationException;
class FreeGiftCartController extends Controller {
 public function __invoke(Request $r,FreeGiftOfferService $offers){
  $d=$r->validate(['offer_id'=>'required|integer','main_variant_id'=>'required|integer','gift_variant_id'=>'required|integer|different:main_variant_id']);
  $deal=$offers->active()->firstWhere('id',$d['offer_id']);$main=ProductVariant::with('product')->findOrFail($d['main_variant_id']);$gift=ProductVariant::with('product')->findOrFail($d['gift_variant_id']);
  if(!$deal||!in_array($main->product_id,$deal->product_ids)||!in_array($gift->product_id,array_column($deal->addons,'product_id')))throw ValidationException::withMessages(['offer'=>'This gift is no longer available.']);
  $cart=app(ProductPurchaseRules::class)->cartQuantities($r);$selected=$r->session()->get('gift_selections',[]);
  $mainIds=ProductVariant::whereIn('id',array_keys($cart))->whereIn('product_id',$deal->product_ids)->pluck('id')->all();$mainQty=array_sum(array_intersect_key($cart,array_flip($mainIds)));
  $used=array_sum($selected[$deal->id]??[]);$needed=max(0,(int)$deal->buy_quantity-$mainQty);
  $allow=min($deal->per_order_cap,intdiv($mainQty+$needed,max(1,$deal->buy_quantity))*$deal->gift_quantity);
  if($used>=$allow)throw ValidationException::withMessages(['offer'=>'The gift limit has been reached.']);
  foreach([$main,$gift] as $v){app(ProductPurchaseRules::class)->assertPurchasable($v);$n=(int)($cart[$v->id]??0)+($v->id===$gift->id?1:$needed);if(!$v->enabled||$n>(app(WebsiteInventoryAvailability::class)->quantity($v)??0))throw ValidationException::withMessages(['offer'=>'An item is no longer available in the requested quantity.']);}
  if($needed)$cart[$main->id]=(int)($cart[$main->id]??0)+$needed;
  $cart[$gift->id]=(int)($cart[$gift->id]??0)+1;$selected[$deal->id][$gift->id]=(int)($selected[$deal->id][$gift->id]??0)+1;
  $r->session()->put(['cart'=>$cart,'gift_selections'=>$selected]);return redirect()->route('cart.show');
 }
}
