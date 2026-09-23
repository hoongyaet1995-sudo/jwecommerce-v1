<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessMarketplaceHubOrderEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

final class MarketplaceHubEventController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless(config('marketplace.hub.event_endpoint_enabled') === true, 404);
        $body = $request->getContent();
        $timestamp = (string) $request->header('X-Hub-Timestamp');
        $nonce = (string) $request->header('X-Hub-Nonce');
        $signature = (string) $request->header('X-Hub-Signature');
        $secret = (string) config('marketplace.hub.client_secret');
        abort_unless($secret !== '' && hash_equals((string) config('marketplace.hub.client_id'), (string) $request->header('X-Hub-Client'))
            && ctype_digit($timestamp) && abs(now()->timestamp - (int) $timestamp) <= 300
            && preg_match('/^[a-zA-Z0-9_-]{12,100}$/D', $nonce)
            && preg_match('/^[a-f0-9]{64}$/D', $signature)
            && hash_equals(hash_hmac('sha256', "$timestamp.$nonce.$body", $secret), $signature), 401);
        abort_unless(Cache::add('hub-event:'.$nonce, true, 600), 409);
        $payload = json_decode($body, true);
        abort_unless(is_array($payload), 422);
        $data = Validator::make($payload, [
            'domain' => ['required', 'string'], 'operation' => ['required', 'in:deliver_event'],
            'eventId' => ['required', 'regex:/^[1-9][0-9]*$/D'],
            'clientStoreId' => ['required', 'regex:/^[1-9][0-9]*$/D'],
            'type' => ['required', 'in:ORDER_STATUS_CHANGED'],
            'externalOrderId' => ['required', 'regex:/^[1-9][0-9]*$/D'],
        ])->validate();
        abort_unless($data['domain'] === parse_url((string) config('app.url'), PHP_URL_HOST), 403);
        $store = DB::table('marketplace_stores')->where('id', $data['clientStoreId'])
            ->where('platform', 'lazada')->where('status', 'connected')->first();
        abort_unless($store, 404);
        $created = DB::table('marketplace_hub_event_inbox')->insertOrIgnore([
            'hub_event_id' => $data['eventId'], 'store_id' => $store->id,
            'event_type' => $data['type'], 'external_order_id' => $data['externalOrderId'],
            'status' => 'received', 'created_at' => now(), 'updated_at' => now(),
        ]);
        if ($created) ProcessMarketplaceHubOrderEvent::dispatch($data['eventId']);
        return response()->json(['status' => 'accepted', 'eventId' => $data['eventId']])
            ->header('Cache-Control', 'no-store');
    }
}
