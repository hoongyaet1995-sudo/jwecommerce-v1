<?php
namespace App\Services\Marketplace;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\{DB,Crypt,Schema};

class VariantOwnershipTransfer
{
    public function run(int $listingId,int $destination,int $actor,?string $token=null,?string $reason=null): array
    {
        return DB::transaction(function() use($listingId,$destination,$actor,$token,$reason){
            $listing=DB::table('marketplace_listing_variants')->find($listingId);abort_unless($listing,404);
            DB::table('marketplace_stores')->where('id',$listing->store_id)->lockForUpdate()->first();
            $identity=DB::table('marketplace_variant_imports')->where('listing_variant_id',$listingId)->lockForUpdate()->first();
            abort_unless($identity,409,'This listing has no imported variant to move. Use the normal import action.');
            $variant=ProductVariant::whereKey($identity->product_variant_id)->lockForUpdate()->firstOrFail();
            $parents=Product::whereIn('id',[$variant->product_id,$destination])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $from=$parents->get($variant->product_id);$to=$parents->get($destination);
            abort_unless($to && $from && $from->id!==$to->id,409,'Choose a different existing destination product.');
            abort_unless($from->status==='draft' && $to->status==='draft',409,'Only draft products can use this ownership transfer.');
            abort_unless((int)$variant->stock===0,409,'This variant has stock. Reconcile its stock before changing ownership.');
            if(Schema::hasColumn('order_items','variant_id')) abort_if(DB::table('order_items')->where('variant_id',$variant->id)->lockForUpdate()->first(),409,'This variant has order history and cannot be moved through this draft import flow.');
            abort_if(DB::table('marketplace_variant_mappings')->where('listing_variant_id',$listingId)->exists(),409,'Unlink this listing before reviewing ownership transfer.');
            foreach(['order_items'=>'product_variant_id','stock_allocations'=>'product_variant_id','inventory_balances'=>'product_variant_id','inventory_movements'=>'product_variant_id','marketplace_order_items'=>'product_variant_id'] as $table=>$column){
                if(Schema::hasColumn($table,$column)) abort_if(DB::table($table)->where($column,$variant->id)->lockForUpdate()->first(),409,'This variant has order or inventory records and cannot be moved through this draft import flow.');
            }
            abort_if(DB::table('marketplace_variant_mappings')->where('product_variant_id',$variant->id)->exists(),409,'Unlink the existing platform mapping before reviewing this move.');
            abort_if(DB::table('marketplace_product_imports')->where('product_id',$from->id)->exists(),409,'This product has a whole-product import association; this transfer requires a separate reconciliation.');
            $snapshot=[$listingId,$variant->id,$from->id,$to->id,$actor,(string)$variant->updated_at,(string)$to->updated_at];
            if($token===null) return ['token'=>Crypt::encryptString(json_encode(['snapshot'=>$snapshot,'expires'=>time()+600])), 'sku'=>$variant->sku,'fromTitle'=>$from->title,'fromId'=>$from->id,'toTitle'=>$to->title,'toId'=>$to->id];
            try{$review=json_decode(Crypt::decryptString($token),true,512,JSON_THROW_ON_ERROR);}catch(\Throwable){abort(409,'Review the ownership transfer again.');}
            abort_unless(($review['snapshot']??null)===$snapshot && ($review['expires']??0)>=time(),409,'The reviewed product changed or the review expired. Review again.');
            abort_unless(is_string($reason) && mb_strlen(trim($reason))>=3 && mb_strlen($reason)<=500,422,'Enter a reason for the ownership transfer.');
            $variant->update(['product_id'=>$to->id]);
            app(MarketplaceFoundationService::class)->map($listingId,$variant->id,$to->id,$actor);
            foreach([$from,$to] as $parent){$parent->update(['has_variants'=>$parent->variants()->count()>1]);}
            DB::table('marketplace_variant_transfers')->insert(['listing_variant_id'=>$listingId,'product_variant_id'=>$variant->id,'from_product_id'=>$from->id,'to_product_id'=>$to->id,'actor_id'=>$actor,'reason'=>trim($reason),'created_at'=>now()]);
            return ['variantId'=>$variant->id,'productId'=>$to->id];
        });
    }
}
