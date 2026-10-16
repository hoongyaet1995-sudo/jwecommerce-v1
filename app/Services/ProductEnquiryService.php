<?php
namespace App\Services;
use App\Models\{Product,ProductVariant,ProductEnquiry};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductEnquiryService
{
    public function owner(Request $request): string {
        if (!$request->session()->has('enquiry_owner')) $request->session()->put('enquiry_owner',Str::random(64));
        return hash('sha256',$request->session()->get('enquiry_owner'));
    }
    public function canView(Request $request,ProductEnquiry $enquiry): bool {
        return hash_equals($enquiry->owner_hash,$this->owner($request)) || ($enquiry->customer_user_id && (int)$request->user('customer')?->id===(int)$enquiry->customer_user_id);
    }
    public function submit(Request $request,array $data): ProductEnquiry {
        abort_unless(config('product_enquiries.enabled'),503,'Enquiries are not accepting new requests at the moment.');
        $owner=$this->owner($request);
        $fingerprint=hash('sha256',json_encode([$data['product_id'],$data['variant_id']??null,trim($data['name']),strtolower(trim($data['email'])),trim($data['phone']??''),trim($data['requirements'])]));
        return DB::transaction(function()use($request,$data,$owner,$fingerprint){
            $product=Product::query()->lockForUpdate()->find($data['product_id']);
            $existing=ProductEnquiry::where('owner_hash',$owner)->where('request_key',$data['request_key'])->first();
            if($existing){
                if(!hash_equals($existing->payload_hash,$fingerprint)) throw ValidationException::withMessages(['request_key'=>'This request was already submitted with different details. Please reopen the form.']);
                return $existing;
            }
            if(!$product || $product->status!=='active' || $product->purchase_mode!=='quote') throw ValidationException::withMessages(['product_id'=>'This item is not currently available for enquiries.']);
            $variant=null;
            if(!empty($data['variant_id'])) $variant=ProductVariant::where('product_id',$product->id)->whereKey($data['variant_id'])->where('enabled',true)->lockForUpdate()->first();
            if((!empty($data['variant_id'])&&!$variant)||(!$variant&&$product->variants()->where('enabled',true)->exists())) throw ValidationException::withMessages(['variant_id'=>'Please choose an available option for this item.']);
            $id=(string)Str::uuid();
            $enquiry=ProductEnquiry::create([
                'id'=>$id,'reference'=>'ENQ-'.now()->format('Ymd').'-'.strtoupper(Str::random(10)),
                'owner_hash'=>$owner,'request_key'=>$data['request_key'],'payload_hash'=>$fingerprint,'customer_user_id'=>$request->user('customer')?->id,
                'product_id'=>$product->id,'product_variant_id'=>$variant?->id,'product_title'=>$product->title,
                'product_snapshot'=>['title'=>$product->title,'handle'=>$product->handle,'variant'=>$variant?->title,'sku'=>$variant?->sku,'options'=>$variant?->options??[], 'image'=>$variant?->image_url?:$product->images()->orderBy('position')->value('path'),'source'=>route('storefront.products.show',$product->handle,false)],
                'name'=>trim($data['name']),'email'=>strtolower(trim($data['email'])),'phone'=>filled($data['phone']??null)?trim($data['phone']):null,'requirements'=>trim($data['requirements']),
            ]);
            $enquiry->events()->create(['type'=>'created']);
            return $enquiry;
        },3);
    }
    public function change(ProductEnquiry $enquiry,array $data,int $actorId): ProductEnquiry {
        return DB::transaction(function()use($enquiry,$data,$actorId){
            $row=ProductEnquiry::lockForUpdate()->findOrFail($enquiry->id);
            abort_unless($row->version===$data['version'],409,'This enquiry changed. Reload it before saving again.');
            $next=['status'=>$data['status'],'assigned_to'=>$data['assigned_to']??null,'outcome'=>$data['status']==='closed'?$data['outcome']:null];
            $changes=[];foreach($next as $key=>$value) if($row->$key!=$value) $changes[$key]=['from'=>$row->$key,'to'=>$value];
            if($changes){$row->fill($next);$row->version++;$row->save();$row->events()->create(['type'=>'updated','actor_id'=>$actorId,'changes'=>$changes]);}
            return $row;
        });
    }
    public function addNote(ProductEnquiry $enquiry,string $content,int $actorId): ProductEnquiry {
        return DB::transaction(function()use($enquiry,$content,$actorId){
            $row=ProductEnquiry::lockForUpdate()->findOrFail($enquiry->id);
            $row->events()->create(['type'=>'note','content'=>trim($content),'actor_id'=>$actorId]);$row->version++;$row->save();return $row;
        });
    }
}
