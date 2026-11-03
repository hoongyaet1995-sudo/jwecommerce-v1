<?php
namespace App\Http\Controllers;
use App\Models\ProductVariant;
use App\Services\Promotions\AddonOfferService;
use App\Services\Inventory\WebsiteInventoryAvailability;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
class AddonCartController extends Controller {
 public function __invoke(Request $r,AddonOfferService $offers){
  $data=$r->validate(['offer_id'=>'required|integer','main_variant_id'=>'required|integer','addon_variant_id'=>'required|integer|different:main_variant_id','expected_price'=>'required|integer|min:1']);
  $deal=$offers->active()->firstWhere('id',$data['offer_id']);$main=ProductVariant::with('product')->findOrFail($data['main_variant_id']);$extra=ProductVariant::with('product')->findOrFail($data['addon_variant_id']);
  $price=$deal?collect($deal->addons)->firstWhere('product_id',$extra->product_id):null;
  if(!$deal||!in_array($main->product_id,$deal->product_ids)||!$price)throw ValidationException::withMessages(['offer'=>'This offer is no longer available.']);
  if((int)$price['price']!==(int)$data['expected_price']||$extra->price<=$price['price'])throw ValidationException::withMessages(['offer'=>'The offer price changed. Please review the current offer before adding.']);
  $cart=app(\App\Services\ProductPurchaseRules::class)->cartQuantities($r);$selections=$r->session()->get('addon_selections',[]);
  $mainIds=ProductVariant::whereIn('id',array_keys($cart))->whereIn('product_id',$deal->product_ids)->pluck('id')->all();
  $mainQty=array_sum(array_intersect_key($cart,array_flip($mainIds)));
  $selected=0;foreach(($selections[$deal->id]??[]) as $id=>$s)$selected+=min((int)($cart[$id]??0),(int)($s['quantity']??0));
  if($selected>=$deal->per_order_cap)throw ValidationException::withMessages(['offer'=>'You have reached the add-on limit for this offer.']);
  $addMain=$mainQty<=$selected;
  foreach([$main,$extra] as $v){app(\App\Services\ProductPurchaseRules::class)->assertPurchasable($v);$available=app(WebsiteInventoryAvailability::class)->quantity($v);$required=(int)($cart[$v->id]??0)+(($v->id===$extra->id||$addMain)?1:0);if(!$v->enabled||$available===null||$required>$available)throw ValidationException::withMessages(['offer'=>'An item is no longer available in the requested quantity.']);}
  if($addMain)$cart[$main->id]=(int)($cart[$main->id]??0)+1;
  $cart[$extra->id]=(int)($cart[$extra->id]??0)+1;
  $old=$selections[$deal->id][$extra->id]??[];
  $selections[$deal->id][$extra->id]=['quantity'=>min((int)($old['quantity']??0),(int)$cart[$extra->id]-1)+1,'price'=>$price['price']];
  $r->session()->put(['cart'=>$cart,'addon_selections'=>$selections]);
  return redirect()->route('cart.show');
 }
}
