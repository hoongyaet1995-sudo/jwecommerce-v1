<?php

namespace App\Services\Social;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class SocialHubOAuth
{
    public function installation(): object
    {
        if(!Schema::hasTable('social_hub_settings'))throw new RuntimeException('hub_not_configured');
        $s=app(SocialHubInstallation::class)->settings();
        if(!$s||!$s->enabled)throw new RuntimeException('hub_not_configured');
        if($s->hub_url!=='https://social.jwaistudio.online' && !(app()->environment('local')&&$s->hub_url==='http://127.0.0.1:8512'))throw new RuntimeException('hub_not_configured');
        return $s;
    }
    public function fingerprint(): string
    {
        $s=$this->installation();
        return hash('sha256',json_encode([$s->hub_url,$s->client_id,$s->key_id,$s->registered_domain,$s->encrypted_secret]));
    }
    public function call(string $action,array $data): array
    {
        if(!in_array($action,['start','redeem','select'],true))throw new RuntimeException('hub_unavailable');
        $s=$this->installation();$path='/api/v1/oauth/facebook/'.$action;
        try{
            $secret=Crypt::decryptString($s->encrypted_secret);
            $body=json_encode(['registered_domain'=>$s->registered_domain]+$data,JSON_THROW_ON_ERROR);
            $h=SocialProtocol::headers($s->client_id,$s->key_id,$secret,'client-to-hub','POST',$path,$body);
            $r=Http::connectTimeout(5)->timeout(15)->withoutRedirecting()->withHeaders($h+['Accept'=>'application/json'])
                ->withBody($body,'application/json')->post($s->hub_url.$path);
        }catch(\Throwable){throw new RuntimeException('hub_unavailable');}
        if(!$r->successful())throw new RuntimeException(match($r->status()){
            401,403=>'hub_not_permitted',404,409=>'invalid_state',422=>'page_not_authorized',default=>'hub_unavailable',
        });
        $expected=SocialProtocol::responseSignature($h,$secret,'client-to-hub',$r->status(),$r->body());
        if(!hash_equals($expected,(string)$r->header('X-Social-Response-Signature'))||!is_array($r->json()))throw new RuntimeException('hub_unavailable');
        $d=$r->json();
        if(($d['app_id']??null)!==(string)config('services.facebook.client_id'))throw new RuntimeException('not_configured');
        if(isset($data['transaction_id'])&&($d['transaction_id']??null)!==$data['transaction_id'])throw new RuntimeException('hub_unavailable');
        return $d;
    }
    public function start(string $state): array
    {
        $d=$this->call('start',['client_state'=>$state]);$base=$this->installation()->hub_url;
        $url=$d['launch_url']??null;$id=$d['transaction_id']??null;
        if(!is_string($url)||!is_string($id)||!\Illuminate\Support\Str::isUuid($id)
            ||!preg_match('~^'.preg_quote($base,'~').'/oauth/facebook/launch\?ticket=[a-f0-9]{64}$~D',$url))throw new RuntimeException('hub_unavailable');
        return $d;
    }
}
