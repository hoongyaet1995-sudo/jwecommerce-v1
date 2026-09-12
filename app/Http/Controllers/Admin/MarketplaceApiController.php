<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Marketplace\MarketplaceFoundationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MarketplaceApiController extends Controller
{
    private function storeData(object $store): array
    {
        return ['id'=>(string)$store->id,'platform'=>$store->platform,'shopName'=>$store->shop_name,'shopId'=>$store->external_shop_id ?? '',
            'region'=>$store->region,'environment'=>$store->environment,'status'=>$store->status,
            'appKey'=>'','appSecret'=>'','accessToken'=>'','refreshToken'=>'','tokenExpiresAt'=>'','lastSyncAt'=>'',
            'autoSyncStock'=>false,'autoPullOrders'=>false,'webhookRegistered'=>false];
    }

    public function index(): JsonResponse
    {
        $stores=DB::table('marketplace_stores')->orderBy('id')->get();
        $variants=DB::table('marketplace_listing_variants as l')
            ->leftJoin('marketplace_variant_mappings as m','m.listing_variant_id','=','l.id')
            ->leftJoin('product_variants as v','v.id','=','m.product_variant_id')
            ->leftJoin('products as p','p.id','=','v.product_id')
            ->select('l.*','v.id as jw_variant_id','v.product_id as jw_product_id','v.sku as jw_sku','v.title as jw_title','p.title as jw_product_title','m.updated_at as mapped_at')->orderBy('l.id')->get();
        $products=$variants->groupBy(fn($v)=>$v->store_id.':'.$v->external_product_id)->map(function($rows)use($stores){
            $first=$rows->first();$store=$stores->firstWhere('id',$first->store_id);$data=json_decode($first->observed_data,true)??[];
            return ['id'=>(string)$first->store_id.':'.$first->external_product_id,'connectionId'=>(string)$store->id,'platform'=>$store->platform,'shopName'=>$store->shop_name,
                'channelProductId'=>$first->external_product_id,'title'=>$data['productTitle']??$first->external_product_id,'imageUrl'=>$data['productImage']??'',
                'status'=>$data['status']??'unknown','updatedAt'=>$first->fetched_at,'categoryName'=>$data['categoryName']??'',
                'variants'=>$rows->map(function($v){$d=json_decode($v->observed_data,true)??[];return [
                    'id'=>(string)$v->id,'channelSkuId'=>$v->external_variant_id,'channelSellerSku'=>$d['sku']??'','title'=>$d['title']??$v->external_variant_id,
                    'price'=>$d['price']??0,'currency'=>$d['currency']??'','stock'=>$d['stock']??0,'imageUrl'=>$d['imageUrl']??'',
                    'mappingStatus'=>$v->jw_variant_id?'mapped':'unmapped','mappedJwProductId'=>$v->jw_product_id?(string)$v->jw_product_id:null,
                    'mappedJwVariantId'=>$v->jw_variant_id?(string)$v->jw_variant_id:null,'mappedJwSku'=>$v->jw_sku,'mappedJwProductName'=>$v->jw_product_title,'mappedJwVariantTitle'=>$v->jw_title,'mappedAt'=>$v->mapped_at,
                ];})->values()];
        })->values();
        return response()->json(['stores'=>DB::table('marketplace_stores')->orderBy('id')->get()->map(fn($s)=>$this->storeData($s)),
            'products'=>$products,
            'providerAvailable'=>false,'listingVariantCount'=>DB::table('marketplace_listing_variants')->count(),
            'mappingCount'=>DB::table('marketplace_variant_mappings')->count()]);
    }

    public function save(Request $request, ?int $store = null): JsonResponse
    {
        if ($store !== null) abort_unless(DB::table('marketplace_stores')->where('id',$store)->exists(),404);
        $data=$request->validate([
            'platform'=>['required',Rule::in(['shopee','lazada','tiktok','shopify'])],
            'shopName'=>'required|string|max:150','region'=>'required|string|max:10',
            'environment'=>['required',Rule::in(['sandbox','production'])],
            'status'=>'prohibited','appSecret'=>'prohibited','accessToken'=>'prohibited','refreshToken'=>'prohibited','appKey'=>'prohibited','shopId'=>'prohibited',
        ]);
        $id=DB::transaction(function()use($data,$store){
            $existing=$store ? DB::table('marketplace_stores')->where('id',$store)->lockForUpdate()->first() : null;
            if ($store) {
                abort_unless($existing,404);
                abort_unless($existing->status==='pending_auth',409,'Authorized store identity cannot be edited here.');
                abort_if(DB::table('marketplace_listing_variants')->where('store_id',$store)->exists() && ($existing->platform!==$data['platform'] || $existing->environment!==$data['environment']),409,'Store identity is already used by catalog records.');
            }
            $values=['platform'=>$data['platform'],'shop_name'=>$data['shopName'],'region'=>$data['region'],'environment'=>$data['environment'],'updated_at'=>now()];
            if($store){DB::table('marketplace_stores')->where('id',$store)->update($values);return $store;}
            return DB::table('marketplace_stores')->insertGetId($values+['status'=>'pending_auth','created_at'=>now()]);
        });
        return response()->json(['data'=>$this->storeData(DB::table('marketplace_stores')->find($id))],$store?200:201);
    }

    public function destroy(int $store): JsonResponse
    {
        DB::transaction(function()use($store){
            $row=DB::table('marketplace_stores')->where('id',$store)->lockForUpdate()->first();
            abort_unless($row,404);
            abort_unless($row->status==='pending_auth' && !DB::table('marketplace_listing_variants')->where('store_id',$store)->exists(),409,'Only unused, unauthorised store configurations can be removed.');
            DB::table('marketplace_stores')->where('id',$store)->delete();
        });
        return response()->json(null,204);
    }

    public function mapping(Request $request, int $listing, MarketplaceFoundationService $service): JsonResponse
    {
        $data=$request->validate(['productId'=>'required|integer','variantId'=>'required|integer','expectedVariantId'=>'sometimes|nullable|integer|min:1']);
        $expected=array_key_exists('expectedVariantId',$data)?['variant_id'=>$data['expectedVariantId']===null?null:(int)$data['expectedVariantId']]:null;
        $service->map($listing,(int)$data['variantId'],(int)$data['productId'],(int)$request->user()->id,$expected);
        return response()->json(['saved'=>true]);
    }

    public function unlink(Request $request, int $listing, MarketplaceFoundationService $service): JsonResponse
    {
        $service->map($listing,null,null,(int)$request->user()->id);
        return response()->json(['saved'=>true]);
    }

    public function unavailable(): JsonResponse
    {
        return response()->json(['code'=>'provider_not_available','message'=>'Marketplace Open API is not connected. No remote action was performed.'],409);
    }
}
