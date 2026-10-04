<?php

namespace App\Http\Controllers;

use App\Services\Social\MessengerInboundService;
use Illuminate\Http\Request;

class MessengerWebhookController extends Controller
{
    public function verify(Request $request)
    {
        $expected=(string)config('services.facebook.webhook_verify_token');
        $token=$request->query('hub_verify_token');$challenge=$request->query('hub_challenge');
        abort_unless($expected!==''&&$request->query('hub_mode')==='subscribe'&&is_string($token)
            &&hash_equals($expected,$token)&&is_string($challenge)&&strlen($challenge)<=512,403);
        return response($challenge)->header('Content-Type','text/plain')->header('Cache-Control','no-store');
    }
    public function handle(Request $request,MessengerInboundService $inbound)
    {
        $body=$request->getContent();abort_if(strlen($body)>1048576,413);
        $secret=(string)config('services.facebook.client_secret');
        $signature=$request->header('X-Hub-Signature-256');
        abort_unless($secret!==''&&is_string($signature)&&hash_equals('sha256='.hash_hmac('sha256',$body,$secret),$signature),403);
        $payload=json_decode($body,true);
        abort_unless(is_array($payload),400);
        return response()->json(['processed'=>$inbound->receive($payload)])->header('Cache-Control','no-store');
    }
}
