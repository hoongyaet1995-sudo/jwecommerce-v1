<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\TieredDiscount;
use Illuminate\Http\{Request,JsonResponse};
use Illuminate\Validation\Rule;
class TieredDiscountController extends Controller {
 public function index():JsonResponse{return response()->json(['data'=>TieredDiscount::latest('id')->get()]);}
 public function store(Request $r):JsonResponse{return $this->save($r,new TieredDiscount,201);}
 public function update(Request $r,TieredDiscount $deal):JsonResponse{return $this->save($r,$deal,200);}
 private function save(Request $r,TieredDiscount $deal,int $status):JsonResponse {
 $d=$r->validate(['name'=>'required|string|max:255','description'=>'nullable|string|max:5000','enabled'=>'required|boolean','scope'=>'required|in:all,selected','discount_type'=>'required|in:fixed,percentage','product_ids'=>'present|array|max:10000','product_ids.*'=>['integer','distinct',Rule::exists('products','id')->where(fn($q)=>$q->where('status','active')->where('purchase_mode','buy'))],'tiers'=>'required|array|min:1|max:20','tiers.*'=>'required|array:spend,discount','tiers.*.spend'=>'required|integer|min:1|max:100000000|distinct','tiers.*.discount'=>'required|integer|min:1|max:100000000','starts_at'=>'nullable|date','ends_at'=>['nullable','date',...($r->filled('starts_at')?['after_or_equal:starts_at']:[])]]);
 if($d['scope']==='selected'&&!count($d['product_ids']))throw \Illuminate\Validation\ValidationException::withMessages(['product_ids'=>'Choose at least one product.']);
 usort($d['tiers'],fn($a,$b)=>$a['spend']<=>$b['spend']);$last=0;
 foreach($d['tiers'] as $t){if($t['discount']<=$last||($d['discount_type']==='percentage'&&$t['discount']>100)||($d['discount_type']==='fixed'&&$t['discount']>$t['spend']))throw \Illuminate\Validation\ValidationException::withMessages(['tiers'=>'Use increasing savings, up to 100% or the threshold amount.']);$last=$t['discount'];}
 $deal->fill($d)->save();return response()->json(['data'=>$deal->fresh()],$status);
 }
}
