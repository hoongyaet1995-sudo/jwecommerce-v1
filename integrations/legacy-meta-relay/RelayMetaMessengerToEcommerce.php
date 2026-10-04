<?php

// Install as app/Services/Social/RelayMetaMessengerToEcommerce.php in the retained JW project.
namespace App\Services\Social;

use App\Jobs\ForwardMetaMessengerToEcommerce;
use Illuminate\Http\Request;

class RelayMetaMessengerToEcommerce
{
    public function capture(Request $request): void
    {
        if(config('services.facebook.ecommerce_relay_enabled')!==true)return;
        $body=$request->getContent();abort_if(strlen($body)>1048576,413);
        $secret=(string)config('services.facebook.client_secret');$signature=$request->header('X-Hub-Signature-256');
        abort_unless($secret!==''&&is_string($signature)&&hash_equals('sha256='.hash_hmac('sha256',$body,$secret),$signature),403);
        $payload=json_decode($body,true);if(!is_array($payload)||($payload['object']??null)!=='page')return;
        $pages=array_map('strval',(array)config('services.facebook.ecommerce_relay_page_ids',[]));
        foreach((array)($payload['entry']??[]) as $entry){
            if(is_array($entry)&&in_array((string)($entry['id']??''),$pages,true)&&!empty($entry['messaging'])){
                ForwardMetaMessengerToEcommerce::dispatch($body,$signature);
                return;
            }
        }
    }
}
