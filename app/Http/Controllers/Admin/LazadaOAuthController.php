<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Marketplace\LazadaAuthorizationService;
use App\Services\Marketplace\LazadaAuthorizationUrl;
use App\Services\Marketplace\OAuthStateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class LazadaOAuthController extends Controller
{
    public function start(Request $request, OAuthStateService $states)
    {
        $request->session()->forget('marketplace.oauth_result');
        $data=$request->validate(['reconnectStoreId'=>'nullable|integer|exists:marketplace_stores,id']);
        $id=isset($data['reconnectStoreId'])?(int)$data['reconnectStoreId']:null;
        if ($id) {
            $store=DB::table('marketplace_stores')->find($id);
            abort_unless($store->platform==='lazada' && $store->environment==='live' && $store->region==='MY'
                && $store->external_shop_id!==null,409,'Store cannot be reauthorized with Lazada.');
        }
        try {
            if (config('marketplace.lazada.enabled')!==true || !is_string(config('marketplace.lazada.app_secret'))
                || trim(config('marketplace.lazada.app_secret'))==='') throw new RuntimeException('Unavailable.');
            $build=fn($state)=>LazadaAuthorizationUrl::build((string)config('marketplace.lazada.app_key'),
                (string)config('marketplace.lazada.callback_url'),config('app.url'),$state);
            $build(str_repeat('0',64));
            $state=$states->issue($request->user()->id,'lazada',config('app.url'),$id);
            return response()->json(['url'=>$build($state)])->header('Cache-Control','no-store');
        } catch (RuntimeException) {
            return response()->json(['message'=>'Lazada authorization is not available. Contact the system administrator.'],409);
        }
    }

    public function callback(Request $request, OAuthStateService $states, LazadaAuthorizationService $service)
    {
        $state=$request->query('state');
        $result='failed';
        $id=null;
        if (is_string($state)) {
            if ($request->has('error') || !$request->filled('code')) {
                $result=$states->consume($state,$request->user()->id,'lazada',config('app.url'))?'cancelled':'failed';
            } elseif (is_string($request->query('code'))) {
                try {
                    $id=$service->complete($state,$request->user()->id,$request->query('code'));
                    $result='success';
                } catch (RuntimeException) { $result='failed'; }
            }
        }
        $request->session()->put('marketplace.oauth_result',['status'=>$result,'storeId'=>$id,
            'actorId'=>$request->user()->id,'expiresAt'=>now()->addMinutes(10)->timestamp]);
        return redirect('/admin/marketplace/auth?connection='.$result)->header('Cache-Control','no-store')
            ->header('Referrer-Policy','no-referrer');
    }
}
