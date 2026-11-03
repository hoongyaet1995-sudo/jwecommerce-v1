<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\{AddonDeal,Product};
use Illuminate\Http\{Request,JsonResponse};
use Illuminate\Validation\{Rule,ValidationException};
class AddonDealController extends Controller {
 public function index():JsonResponse{
  $deals=AddonDeal::latest('id')->get();
  $ids=$deals->flatMap(fn($d)=>array_merge($d->product_ids,array_column($d->addons,'product_id')))->unique();
  $products=Product::with('images')->whereIn('id',$ids)->get()->keyBy('id');
  $identity=fn($id)=>['id'=>$id,'name'=>$products->get($id)?->title??'Unavailable product','image'=>$products->get($id)?->images->sortBy('position')->first()?->path];
  return response()->json(['data'=>$deals->map(fn($d)=>[...$d->toArray(),'selected_products'=>collect($d->product_ids)->map($identity)->all(),'product_names'=>collect($d->product_ids)->map(fn($id)=>$identity($id)['name'])->all(),'addons'=>collect($d->addons)->map(fn($a)=>[...$a,...$identity($a['product_id'])])->all()])]);
 }
 public function store(Request $r):JsonResponse{return $this->save($r,new AddonDeal,201);}
 public function update(Request $r,AddonDeal $deal):JsonResponse{return $this->save($r,$deal,200);}
 private function save(Request $r,AddonDeal $deal,int $status):JsonResponse {
 $exists=Rule::exists('products','id')->where(fn($q)=>$q->where('status','active')->where('purchase_mode','buy'));
 $d=$r->validate(['enabled'=>'sometimes|boolean','name'=>'required|string|max:255','description'=>'nullable|string|max:5000','per_order_cap'=>'required|integer|min:1|max:1000','product_ids'=>'required|array|min:1|max:10000','product_ids.*'=>['integer','distinct',$exists],'addons'=>'required|array|min:1|max:10000','addons.*'=>'required|array:product_id,price','addons.*.product_id'=>['required','integer','distinct',$exists],'addons.*.price'=>'required|integer|min:1|max:100000000','cover_image'=>'nullable|string|max:2048','image_settings'=>'nullable|array:mobile,focus,focusY,alt,hidden','image_settings.mobile'=>'nullable|string|max:2048','image_settings.focus'=>'nullable|in:left,center,right','image_settings.focusY'=>'nullable|in:top,middle,bottom','image_settings.alt'=>'nullable|string|max:500','image_settings.hidden'=>'boolean','starts_at'=>'nullable|date','ends_at'=>['nullable','date',...($r->filled('starts_at')?['after_or_equal:starts_at']:[])]]);
 if(array_intersect($d['product_ids'],array_column($d['addons'],'product_id')))throw ValidationException::withMessages(['addons'=>'Qualifying products and add-ons must be different.']);
 $deal->fill($d)->save();return response()->json(['data'=>$deal->fresh()],$status);
 }
}
