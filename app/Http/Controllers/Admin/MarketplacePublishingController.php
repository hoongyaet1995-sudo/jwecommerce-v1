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

    public function source(int $product, \App\Services\Marketplace\MarketplacePublishingSource $source)
    {
        return response()->json($source->read($product))->header('Cache-Control','no-store, private');
    }

    public function stage(Request $request, int $store, PublishingTasks $tasks)
    {
        if (array_diff(array_keys($request->all()), ['product_id', 'request_key', 'source_revision', 'payload', '_token'])) {
            throw ValidationException::withMessages(['publishing'=>'Unsupported marketplace publishing fields.']);
        }
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
        $id=DB::transaction(function () use ($data,$request,$store,$tasks) {
            app(\App\Services\Marketplace\BasicPublishingDestination::class)->check($store,(int)$data['product_id']);
            $source=app(\App\Services\Marketplace\MarketplacePublishingSource::class)->read((int)$data['product_id']);
            abort_unless(hash_equals($source['revision'],$data['source_revision']),409,'The product changed after review. Review it again before publishing.');
            $payload=$data['payload'];
            if (DB::table('marketplace_stores')->where('id',$store)->value('platform')==='shopee') {
                if (config('marketplace.basic_wms_enabled')===true) {
                    $payload=\App\Services\Marketplace\ShopeePublishingMasterData::bind($source,$payload);
                    app(\App\Services\Marketplace\ShopeePublishingImages::class)->prepare($store,$source,(int)$request->user()->id);
                    $payload=app(\App\Services\Marketplace\ShopeePublishingImages::class)->bind($store,$source,$payload);
                }
                \App\Services\Marketplace\ShopeePublishingVariants::validate($payload,$source);
            }
            if (config('marketplace.basic_wms_enabled')===true && DB::table('marketplace_stores')->where('id',$store)->value('platform')==='lazada') {
                $payload=\App\Services\Marketplace\LazadaPublishingMasterData::bind($source,$payload);
                app(\App\Services\Marketplace\LazadaPublishingImages::class)->prepare($store,$source,(int)$request->user()->id);
                $payload=app(\App\Services\Marketplace\LazadaPublishingImages::class)->bind($store,$source,$payload);
                $payload=app(\App\Services\Marketplace\LazadaPublishingWarehouses::class)->bind($store,$payload);
            }
            return $tasks->create($store,(int)$data['product_id'],(int)$request->user()->id,$data['request_key'],$payload,$source);
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

    public function show(int $task)
    {
        $row=DB::table('marketplace_publishing_tasks')->select([
            'id','store_id','product_id','status','external_item_id','created_at','updated_at',
        ])->find($task);
        abort_unless($row,404);

        return response()->json([
            'id'=>(int)$row->id,
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
