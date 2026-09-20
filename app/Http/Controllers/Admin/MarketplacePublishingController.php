<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Marketplace\MarketplacePublishingDispatchService;
use App\Services\Marketplace\PublishingTasks;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarketplacePublishingController extends Controller
{
    public function prepareImage(Request $request, int $store, int $product, int $image, \App\Services\Marketplace\ShopeeProductImageService $images)
    {
        if (array_diff(array_keys($request->all()),['_token'])) {
            throw ValidationException::withMessages(['image'=>'Select a saved JW product image; arbitrary image fields are not accepted.']);
        }
        $processor=DB::table('marketplace_stores')->where('id',$store)->value('platform')==='lazada'
            ? app(\App\Services\Marketplace\LazadaProductImageService::class) : $images;
        $id=$processor->prepare($store,$product,$image,(int)$request->user()->id);
        $receipt=DB::table('marketplace_image_receipts')->find($id);
        return response()->json(['id'=>$id,'storeId'=>$store,'productId'=>$product,'imageId'=>$image,
            'status'=>'succeeded','providerIdentity'=>$receipt->provider_identity])->header('Cache-Control','no-store, private');
    }

    public function storeStatus(int $store, \App\Services\Marketplace\PublishingStoreStatus $status)
    {
        abort_unless(DB::table('marketplace_stores')->where('id',$store)->exists(),404);
        return response()->json(['products'=>(object)$status->read($store)])->header('Cache-Control','no-store, private');
    }

    public function source(int $product, \App\Services\Marketplace\MarketplacePublishingSource $source)
    {
        return response()->json($source->read($product))->header('Cache-Control','no-store, private');
    }

    public function stage(Request $request, int $store, PublishingTasks $tasks)
    {
        if (array_diff(array_keys($request->all()), ['product_id', 'request_key', 'source_revision', 'payload', '_token'])) {
            throw ValidationException::withMessages(['publishing'=>'Unsupported marketplace publishing fields.']);
        }
        $request->validate(['payload.jw_variant_image_choices'=>['sometimes','array'],'payload.jw_variant_image_choices.*'=>['required','integer','min:1'],
            'payload.jw_size_chart_image_id'=>['sometimes','integer','min:1'],
            'payload.jw_attribute_image_ids'=>['sometimes','array'],'payload.jw_attribute_image_ids.*'=>['required','integer','min:1']]);
        $data=$request->validate([
            'product_id'=>['required','integer','min:1'],
            'request_key'=>['required','uuid'],
            'source_revision'=>['required','string','regex:/^[a-f0-9]{64}$/D'],
            'payload'=>['required','array'],
        ]);
        if (!$request->user() || !DB::table('users')->where('id',$request->user()->id)->where('is_admin',true)->exists()) {
            abort(403);
        }
        try {
        app(\App\Services\Marketplace\BasicPublishingDestination::class)->check($store,(int)$data['product_id']);
        $source=app(\App\Services\Marketplace\MarketplacePublishingSource::class)->read((int)$data['product_id']);
        abort_unless(hash_equals($source['revision'],$data['source_revision']),409,'The product changed after review. Review it again before publishing.');
        $storeRow=DB::table('marketplace_stores')->find($store);
        abort_unless($storeRow,404);
        $payload=$data['payload'];
        if ($storeRow->platform==='lazada') {
            $allowed=['Request','jw_attribute_image_ids','jw_size_chart_image_id'];
            if (array_diff(array_keys($payload),$allowed)) throw ValidationException::withMessages(['publishing'=>'Unsupported Lazada publishing fields.']);
            $product=$payload['Request']['Product']??null;
            $category=$product['PrimaryCategory']??null;
            $payloadSkus=$product['Skus']['Sku']??null;
            if (!is_array($product)||!preg_match('/^[1-9][0-9]*$/D',(string)$category)||!is_array($payloadSkus)||!array_is_list($payloadSkus)) {
                throw ValidationException::withMessages(['publishing'=>'Review the Lazada category and variants.']);
            }
            $token=app(\App\Services\Marketplace\LazadaTokenManager::class)->accessToken($store);
            $schema=app(\App\Services\Marketplace\LazadaClient::class)->read('/category/attributes/get',$token,['primary_category_id'=>(string)$category],$store);
            $skuValues=[];
            $reserved=['SellerSku','price','quantity','package_weight','package_length','package_width','package_height','Images','MultiWarehouseInventories'];
            foreach ($payloadSkus as $sku) {
                $sellerSku=$sku['SellerSku']??null;
                if (!is_string($sellerSku)||isset($skuValues[$sellerSku])) throw ValidationException::withMessages(['publishing'=>'Review the Lazada variant identities.']);
                $skuValues[$sellerSku]=array_diff_key($sku,array_flip($reserved));
            }
            $imageBindings=$payload['jw_attribute_image_ids']??[];
            if (isset($payload['jw_size_chart_image_id'])) {
                $sizeNames=[];
                foreach ($schema as $attribute) if (($attribute['attribute_type']??null)==='normal'
                    && (preg_match('/size[ _-]*chart/i',(string)($attribute['name']??''))||preg_match('/size[ _-]*chart/i',(string)($attribute['label']??'')))) $sizeNames[]=$attribute['name'];
                if (count($sizeNames)!==1) throw ValidationException::withMessages(['size_chart'=>'The current category does not expose one verified Size Chart attribute.']);
                $sizeKey='lazada:product:'.$sizeNames[0];
                if (isset($imageBindings[$sizeKey]) && $imageBindings[$sizeKey]!==$payload['jw_size_chart_image_id']) {
                    throw ValidationException::withMessages(['size_chart'=>'Choose one current JW image for the Size Chart field.']);
                }
                $imageBindings[$sizeKey]=$payload['jw_size_chart_image_id'];
            }
            $reviewed=\App\Services\Marketplace\LazadaReviewedAttributes::bind($schema,$source,[
                'normal'=>$product['Attributes']??[],'skus'=>$skuValues,'imageBindings'=>$imageBindings,
            ]);
            $payload['Request']['Product']['Attributes']=$reviewed['normal'];
            foreach ($payload['Request']['Product']['Skus']['Sku'] as &$sku) {
                $sellerSku=$sku['SellerSku'];
                foreach (array_diff_key($sku,array_flip($reserved)) as $name=>$_) unset($sku[$name]);
                foreach ($reviewed['skus'][$sellerSku]??[] as $name=>$value) $sku[$name]=$value;
            }
            unset($sku,$payload['jw_size_chart_image_id']);
            if ($reviewed['jw_attribute_image_ids']) $payload['jw_attribute_image_ids']=$reviewed['jw_attribute_image_ids'];
            else unset($payload['jw_attribute_image_ids']);
        }
        if ($storeRow->platform==='shopee') {
            if (config('marketplace.basic_wms_enabled')===true) {
                $payload=\App\Services\Marketplace\ShopeePublishingMasterData::bind($source,$payload);
                app(\App\Services\Marketplace\ShopeePublishingImages::class)->prepare($store,$source,(int)$request->user()->id,$payload['jw_variant_image_choices']??[]);
                $payload=app(\App\Services\Marketplace\ShopeePublishingImages::class)->bind($store,$source,$payload);
            }
            \App\Services\Marketplace\ShopeePublishingVariants::validate($payload,$source);
        }
        if (config('marketplace.basic_wms_enabled')===true && $storeRow->platform==='lazada') {
            $payload=\App\Services\Marketplace\LazadaPublishingMasterData::bind($source,$payload);
            app(\App\Services\Marketplace\LazadaPublishingImages::class)->prepare($store,$source,(int)$request->user()->id);
            $payload=app(\App\Services\Marketplace\LazadaPublishingImages::class)->bind($store,$source,$payload);
            $payload=app(\App\Services\Marketplace\LazadaPublishingWarehouses::class)->bind($store,$payload);
        }
        $id=DB::transaction(function () use ($data,$request,$store,$tasks,$payload,$source,$storeRow) {
            app(\App\Services\Marketplace\BasicPublishingDestination::class)->check($store,(int)$data['product_id']);
            $current=app(\App\Services\Marketplace\MarketplacePublishingSource::class)->read((int)$data['product_id']);
            abort_unless(hash_equals($source['revision'],$current['revision'])
                && DB::table('marketplace_stores')->where('id',$store)->where('platform',$storeRow->platform)->where('status','connected')->exists(),
                409,'The product or store changed after review. Review it again before publishing.');
            return $tasks->create($store,(int)$data['product_id'],(int)$request->user()->id,$data['request_key'],$payload,$current);
        });
        } catch (\DomainException $error) {
            throw ValidationException::withMessages(['publishing'=>$error->getMessage()]);
        }
        return $this->show($id);
    }

    public function dispatch(Request $request, int $task, MarketplacePublishingDispatchService $dispatcher)
    {
        if (array_diff(array_keys($request->all()), ['_token'])) {
            throw ValidationException::withMessages(['publishing'=>'Marketplace publishing dispatch accepts no request fields.']);
        }
        try {
            return response()->json($dispatcher->dispatch($task))->header('Cache-Control','no-store, private');
        } catch (\App\Services\Marketplace\PublishingResultUncertain) {
            return response()->json(['code'=>'publishing_result_uncertain','taskId'=>$task,'retryAllowed'=>false,
                'message'=>'The platform result is not confirmed. Check this saved task and reconcile the store catalog before creating another listing.'],409)
                ->header('Cache-Control','no-store, private');
        } catch (\DomainException $error) {
            throw ValidationException::withMessages(['publishing'=>$error->getMessage()]);
        } catch (\RuntimeException) {
            return response()->json(['code'=>'publishing_dispatch_unavailable','taskId'=>$task,'retryAllowed'=>false,
                'message'=>'This task could not be dispatched. Check its saved status, current product review and store connection before continuing.'],409)
                ->header('Cache-Control','no-store, private');
        }
    }

    /** Read the seller catalog to resolve an ambiguous Lazada write; never sends a second create request. */
    public function reconcile(Request $request, int $task, \App\Services\Marketplace\LazadaPublishingReconciliation $reconciliation)
    {
        $owner=DB::table('marketplace_publishing_tasks')->where('id',$task)->value('actor_id');
        abort_unless($owner && (int)$owner===(int)$request->user()->id,404);
        try {
            return response()->json($reconciliation->reconcile($task))->header('Cache-Control','no-store, private');
        } catch (\RuntimeException $error) {
            return response()->json(['code'=>'publishing_reconciliation_unavailable','message'=>$error->getMessage()],409)
                ->header('Cache-Control','no-store, private');
        }
    }

    public function show(int $task)
    {
        $row=DB::table('marketplace_publishing_tasks')->select([
            'id','store_id','product_id','status','external_item_id','created_at','updated_at','source_snapshot',
        ])->find($task);
        abort_unless($row,404);

        $variants=json_decode($row->source_snapshot??'{}',true)['variants']??[];
        $linked=$row->external_item_id ? DB::table('marketplace_variant_mappings as m')
            ->join('marketplace_listing_variants as l','l.id','=','m.listing_variant_id')
            ->where('m.store_id',$row->store_id)->where('l.store_id',$row->store_id)
            ->where('l.external_product_id',$row->external_item_id)
            ->whereIn('m.product_variant_id',array_column($variants,'id'))->distinct()->count('m.product_variant_id') : 0;
        return response()->json([
            'id'=>(int)$row->id,
            'platform'=>DB::table('marketplace_stores')->where('id',$row->store_id)->value('platform'),
            'mappingComplete'=>count($variants)>0 && $linked===count($variants),
            'storeId'=>(int)$row->store_id,
            'productId'=>(int)$row->product_id,
            'status'=>$row->status,
            'externalItemId'=>$row->external_item_id,
            'createdAt'=>$row->created_at,
            'updatedAt'=>$row->updated_at,
        ])->header('Cache-Control','no-store, private');
    }

    public function recover(Request $request, int $store, string $key)
    {
        $id = DB::table('marketplace_publishing_tasks')->where('store_id', $store)
            ->where('request_key', $key)->where('actor_id', $request->user()->id)->value('id');
        abort_unless($id, 404);
        return $this->show((int) $id);
    }
}
