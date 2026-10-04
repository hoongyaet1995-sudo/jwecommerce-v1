<?php

namespace App\Services\Social;

use App\Models\ManagedSocialAccount;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FacebookAccountService
{
    public function merchant(): string { return (string)config('services.facebook.merchant_key','jw-ecommerce'); }
    public function configured(): bool
    {
        return filled(config('services.facebook.client_id')) && filled(config('services.facebook.client_secret'))
            && str_starts_with((string)config('services.facebook.redirect'),'https://');
    }
    public function authorizeUrl(string $state): string
    {
        return 'https://www.facebook.com/'.$this->version().'/dialog/oauth?'.http_build_query([
            'client_id'=>config('services.facebook.client_id'),'redirect_uri'=>config('services.facebook.redirect'),
            'state'=>$state,'response_type'=>'code','scope'=>'pages_show_list,business_management,pages_read_engagement','return_scopes'=>'true',
            'auth_type'=>'rerequest',
        ]);
    }
    private function version(): string {return (string)config('services.facebook.graph_version','v25.0');}
    private function graph(string $path,array $query=[],?string $token=null): array
    {
        try {
            $client=Http::connectTimeout(5)->timeout(12)->acceptJson();
            if($token!==null){$client=$client->withToken($token);$query['appsecret_proof']=hash_hmac('sha256',$token,(string)config('services.facebook.client_secret'));}
            $response=$client->get('https://graph.facebook.com/'.$this->version().'/'.$path,$query);
            $body=$response->json();
            if(!$response->successful() || !is_array($body) || isset($body['error'])){
                $code=(int)($body['error']['code']??0);
                $reason=$code===190?'authorization_expired':((in_array($code,[10,200],true)||($code===100&&str_contains((string)($body['error']['message']??''),'pages_read_engagement')))?'permission_required':'provider_unavailable');
                throw new RuntimeException($reason);
            }
            return $body;
        } catch (RuntimeException $e) {
            if(in_array($e->getMessage(),['authorization_expired','permission_required','provider_unavailable'],true))throw $e;
            throw new RuntimeException('provider_unavailable');
        } catch (\Throwable $e) {throw new RuntimeException('provider_unavailable');}
    }
    public function exchange(string $code): string
    {
        $result=$this->graph('oauth/access_token',['client_id'=>config('services.facebook.client_id'),
            'client_secret'=>config('services.facebook.client_secret'),'redirect_uri'=>config('services.facebook.redirect'),'code'=>$code]);
        $token=$result['access_token']??null;
        if(!is_string($token)||$token==='')throw new RuntimeException('provider_unavailable');
        return $token;
    }
    private function collection(string $path,string $token,array $query=[]): array
    {
        $items=[];$after=null;
        for($page=0;$page<10;$page++){
            $body=$this->graph($path,$query+['limit'=>100]+($after?['after'=>$after]:[]),$token);
            if(!is_array($body['data']??null))throw new RuntimeException('provider_unavailable');
            $items=array_merge($items,$body['data']);
            if(empty($body['paging']['next']))return $items;
            $next=$body['paging']['cursors']['after']??null;
            if(!is_string($next)||$next===$after)throw new RuntimeException('provider_unavailable');
            $after=$next;
        }
        throw new RuntimeException('too_many_pages');
    }
    public function permissions(string $token): array
    {
        return array_values(array_map(fn($p)=>$p['permission'],array_filter($this->collection('me/permissions',$token),
            fn($p)=>is_array($p)&&($p['status']??null)==='granted'&&is_string($p['permission']??null))));
    }
    public function pages(string $token): array
    {
        return array_values(array_filter($this->collection('me/accounts',$token,['fields'=>'id,name,access_token,tasks']),
            fn($p)=>is_array($p)&&is_string($p['id']??null)&&is_string($p['name']??null)&&is_string($p['access_token']??null)));
    }
    public function check(ManagedSocialAccount $account): array
    {
        if($account->state==='disconnected')return $this->summary($account,'This Page is disconnected from this store.');
        $state='unknown';$reason=null;
        try {
            if($account->app_id!==(string)config('services.facebook.client_id'))throw new RuntimeException('authorization_expired');
            $body=$this->graph($account->provider_account_id,['fields'=>'id,name'],$account->access_token);
            if(($body['id']??null)!==$account->provider_account_id)throw new RuntimeException('authorization_expired');
            $state='connected';$account->name=mb_substr((string)($body['name']??$account->name),0,255);$account->checked_at=now();
        } catch(\Illuminate\Contracts\Encryption\DecryptException $e){$state='authorization_expired';}
        catch(RuntimeException $e){$state=in_array($e->getMessage(),['authorization_expired','permission_required'],true)?$e->getMessage():'unknown';}
        $reason=match($state){'permission_required'=>'Page read permission is missing. Reconnect and grant Page access.','authorization_expired'=>'Authorization is no longer valid. Reconnect this Page.','unknown'=>'Meta could not verify this Page. Try again later.',default=>null};
        $updated=ManagedSocialAccount::whereKey($account->id)->where('state','!=','disconnected')
            ->where('access_token',$account->getRawOriginal('access_token'))
            ->update(['state'=>$state,'name'=>$account->name,'checked_at'=>now(),'updated_at'=>now()]);
        if(!$updated){$fresh=ManagedSocialAccount::find($account->id);return $fresh?$this->summary($fresh,$fresh->state==='disconnected'?'This Page is disconnected from this store.':null):$this->summary($account,'Connection changed during verification. Refresh the list.');}
        $account->state=$state;$account->checked_at=now();
        return $this->summary($account,$reason);
    }
    public function summary(ManagedSocialAccount $account,?string $reason=null): array
    {
        return ['id'=>(string)$account->id,'pageId'=>$account->provider_account_id,'name'=>$account->name,
            'state'=>$account->state,'checkReason'=>$reason,'checkedAt'=>$account->checked_at?->toIso8601String(),'permissions'=>$account->permissions,
            'messagingReady'=>false];
    }
}
