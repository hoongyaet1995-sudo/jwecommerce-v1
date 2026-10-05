<?php

namespace App\Services\Social;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class SocialHubOAuth
{
    public function permitted(string $platform): array
    {
        try {
            $s=$this->installation();$secret=Crypt::decryptString($s->encrypted_secret);
            $path='/api/v1/installations/capabilities';
            $h=SocialProtocol::headers($s->client_id,$s->key_id,$secret,'client-to-hub','GET',$path,'');
            $r=Http::connectTimeout(5)->timeout(10)->withoutRedirecting()->withHeaders($h+['Accept'=>'application/json'])->get($s->hub_url.$path);
            if(!$r->successful())return ['oauthPermitted'=>false,'oauthReason'=>$r->status()===403?'Social Hub has paused authorization for this website.':'Social Hub could not verify authorization.'];
            $expected=SocialProtocol::responseSignature($h,$secret,'client-to-hub',$r->status(),$r->body());
            if(!hash_equals($expected,(string)$r->header('X-Social-Response-Signature'))||$r->json('client_id')!==$s->client_id)throw new RuntimeException('invalid_receipt');
            $ok=in_array($platform,$r->json('enabled_channels',[]),true);
            return ['oauthPermitted'=>$ok,'oauthReason'=>$ok?null:'Social Hub has not permitted Instagram OAuth for this website.'];
        }catch(\Throwable){return ['oauthPermitted'=>false,'oauthReason'=>'Social Hub is unavailable or this website installation is not configured.'];}
    }
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
    public function call(string $action,array $data,string $platform="facebook"): array
    {
        if(!in_array($action,['start','redeem','select'],true))throw new RuntimeException('hub_unavailable');
        if(!in_array($platform,['facebook','instagram'],true))throw new RuntimeException('hub_unavailable');
        $s=$this->installation();$path='/api/v1/oauth/'.$platform.'/'.$action;
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
    public function start(string $state,string $platform="facebook"): array
    {
        $d=$this->call('start',['client_state'=>$state],$platform);$base=$this->installation()->hub_url;
        $url=$d['launch_url']??null;$id=$d['transaction_id']??null;
        if(!is_string($url)||!is_string($id)||!\Illuminate\Support\Str::isUuid($id)
            ||!preg_match('~^'.preg_quote($base,'~').'/oauth/'.preg_quote($platform,'~').'/launch\?ticket=[a-f0-9]{64}$~D',$url))throw new RuntimeException('hub_unavailable');
        return $d;
    }
}
