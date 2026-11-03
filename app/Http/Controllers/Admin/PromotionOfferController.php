<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\{PromotionOffer,Product,Category};
use Illuminate\Http\{Request,JsonResponse};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
class PromotionOfferController extends Controller {
 public function index():JsonResponse{return response()->json(['data'=>PromotionOffer::with('products.images')->latest('id')->get()->map(fn($o)=>$this->data($o))]);}
 public function products(Request $r):JsonResponse {
  $data=$r->validate(['search'=>'nullable|string|max:100','category'=>'nullable|integer','page'=>'nullable|integer|min:1','ids'=>'nullable|array|max:10000','ids.*'=>'integer']);
  $q=Product::query()->where('status','active')->where('purchase_mode','buy');
  if($search=trim($data['search']??''))$q->where(fn($q)=>$q->where('title','like','%'.$search.'%')->orWhereHas('variants',fn($v)=>$v->where('sku','like','%'.$search.'%')));
  if(!empty($data['category']))$q->where('category_id',$data['category']);
  if(array_key_exists('ids',$data))$q->whereIn('id',$data['ids']);
  $page=$q->with(['images','category:id,name'])->orderBy('title')->orderBy('id')->paginate(20);
  return response()->json(['data'=>$page->getCollection()->map(fn($p)=>['id'=>$p->id,'name'=>$p->title,'category'=>$p->category?->name,'image'=>$p->images->sortBy('position')->first()?->path,'price'=>$p->base_price]),'total'=>$page->total(),'last_page'=>$page->lastPage(),'categories'=>Category::orderBy('name')->get(['id','name'])]);
 }
 public function store(Request $r):JsonResponse{return $this->save($r,new PromotionOffer,201);}
 public function update(Request $r,PromotionOffer $offer):JsonResponse{return $this->save($r,$offer,200);}
 private function save(Request $r,PromotionOffer $offer,int $status):JsonResponse {
  $data=$r->validate(['cover_image'=>'nullable|string|max:2048','image_settings'=>'nullable|array:mobile,focus,focusY,alt,hidden','image_settings.mobile'=>'nullable|string|max:2048','image_settings.focus'=>'nullable|in:left,center,right','image_settings.focusY'=>'nullable|in:top,middle,bottom','image_settings.alt'=>'nullable|string|max:500','image_settings.hidden'=>'boolean','name'=>'required|string|max:255','description'=>'nullable|string|max:5000','quantity'=>'required|integer|min:2|max:100','price'=>'required|integer|min:1|max:100000000','repeat'=>'required|boolean','enabled'=>'required|boolean','starts_at'=>'nullable|date','ends_at'=>['nullable','date',...($r->filled('starts_at')?['after_or_equal:starts_at']:[])],'product_ids'=>'required|array|min:1|max:10000','product_ids.*'=>['required','integer','distinct',Rule::exists('products','id')->where(fn($q)=>$q->where('status','active')->where('purchase_mode','buy'))]]);
  DB::transaction(function()use($offer,$data){if($offer->exists)PromotionOffer::whereKey($offer->id)->lockForUpdate()->firstOrFail();$ids=$data['product_ids'];unset($data['product_ids']);$offer->fill($data)->save();$offer->products()->sync($ids);});
  return response()->json(['data'=>$this->data($offer->fresh('products.images'))],$status);
 }
 private function data(PromotionOffer $o):array{return [...$o->only(['cover_image','image_settings','id','name','description','quantity','price','repeat','enabled','starts_at','ends_at']),'selected_products'=>$o->products->map(fn($p)=>['id'=>$p->id,'name'=>$p->title,'image'=>$p->images->sortBy('position')->first()?->path]),'product_ids'=>$o->products->pluck('id'),'product_names'=>$o->products->take(3)->pluck('title')];}
}
