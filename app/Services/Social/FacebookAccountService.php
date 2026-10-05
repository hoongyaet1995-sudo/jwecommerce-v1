<?php

namespace App\Services\Social;

use App\Models\ManagedSocialAccount;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FacebookAccountService
{
    protected function subscription(ManagedSocialAccount $account): MessengerSubscriptionService {return app($account->platform==='instagram'?InstagramSubscriptionService::class:MessengerSubscriptionService::class);}
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
            'state'=>$state,'response_type'=>'code','scope'=>'pages_show_list,business_management,pages_read_engagement,pages_messaging,pages_manage_metadata','return_scopes'=>'true',
            'auth_type'=>'rerequest',
        ]);
    }
    private function version(): string {return (string)config('services.facebook.graph_version','v25.0');}
    protected function graph(string $path,array $query=[],?string $token=null): array
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
    public function invalidateInbox(ManagedSocialAccount $account): void
    {
        \App\Models\SupportConversation::where('channel',$account->platform)->where('merchant_key',$account->merchant_key)
            ->where('provider_page_id',$account->provider_account_id)->update(['updated_at'=>now()]);
    }
    public function check(ManagedSocialAccount $account,bool $retryMessaging=false): array
    {
        $previous=[$account->state,(bool)$account->inbound_enabled,$account->inbound_verified_at!==null,$account->messaging_error];
        if($account->state==='disconnected')return $this->summary($account,'This Page is disconnected from this store.');
        $state='unknown';$reason=null;
        try {
            if($account->app_id!==(string)config('services.facebook.client_id'))throw new RuntimeException('authorization_expired');
            $body=$this->graph($account->provider_account_id,['fields'=>'id,name'],$account->access_token);
            if(($body['id']??null)!==$account->provider_account_id)throw new RuntimeException('authorization_expired');
            if($account->inbound_enabled){
                $messagingError=null;
                try {$verified=$this->subscription($account)->verify($account); if(!$verified)$messagingError='Message subscription is not active. Retry the connection.';}
                catch(RuntimeException $e){$verified=null;$messagingError=$e->getMessage();}
                $values=['inbound_verified_at'=>$verified===true?now():null];
                if(array_key_exists('messaging_error',$account->getAttributes()))$values['messaging_error']=$messagingError;
                $account->messaging_error=$messagingError;
                if($verified!==null)$values['inbound_enabled']=$verified;
                ManagedSocialAccount::whereKey($account->id)->where('state','!=','disconnected')->where('access_token',$account->getRawOriginal('access_token'))->update($values);
                if($verified!==null)$account->inbound_enabled=$verified;
                $account->inbound_verified_at=$verified===true?now():null;
            }
            $state='connected';$account->name=mb_substr((string)($body['name']??$account->name),0,255);$account->checked_at=now();
        } catch(\Illuminate\Contracts\Encryption\DecryptException $e){$state='authorization_expired';}
        catch(RuntimeException $e){$state=in_array($e->getMessage(),['authorization_expired','permission_required'],true)?$e->getMessage():'unknown';}
        $reason=match($state){'permission_required'=>'Page read permission is missing. Reconnect and grant Page access.','authorization_expired'=>'Authorization is no longer valid. Reconnect this Page.','unknown'=>'Meta could not verify this Page. Try again later.',default=>null};
        $updated=ManagedSocialAccount::whereKey($account->id)->where('state','!=','disconnected')
            ->where('access_token',$account->getRawOriginal('access_token'))
            ->update(['state'=>$state,'name'=>$account->name,'checked_at'=>now(),'updated_at'=>now()]);
        if(!$updated){$fresh=ManagedSocialAccount::find($account->id);return $fresh?$this->summary($fresh,$fresh->state==='disconnected'?'This Page is disconnected from this store.':null):$this->summary($account,'Connection changed during verification. Refresh the list.');}
        $account->state=$state;$account->checked_at=now();
        // Only an explicit manager action may repair subscription state; listing stays read-only.
        if($retryMessaging && $state==='connected' && (!$account->inbound_enabled || !$account->inbound_verified_at)){
            $this->subscription($account)->activate($account->fresh());
            $account=$account->fresh();
        }
        if($previous!==[$account->state,(bool)$account->inbound_enabled,$account->inbound_verified_at!==null,$account->messaging_error])$this->invalidateInbox($account);
        return $this->summary($account,$reason);
    }
    public function summary(ManagedSocialAccount $account,?string $reason=null): array
    {
        return ['id'=>(string)$account->id,'pageId'=>$account->provider_account_id,'name'=>$account->name,
            'state'=>$account->state,'checkReason'=>$reason,'checkedAt'=>$account->checked_at?->toIso8601String(),'permissions'=>$account->permissions,
            'messagingReady'=>$account->state==='connected'&&(bool)$account->inbound_enabled&&$account->inbound_verified_at!==null&&config('services.'.$account->platform.'.inbound_routing_ready')===true&&in_array($account->platform==='instagram'?'instagram_manage_messages':'pages_messaging',$account->permissions??[],true),'messagingError'=>$account->messaging_error,'inboundEnabled'=>(bool)$account->inbound_enabled&&$account->state!=='disconnected',
            'inboundRoutingReady'=>config('services.'.$account->platform.'.inbound_routing_ready')===true,
            'inboundVerifiedAt'=>$account->inbound_verified_at?->toIso8601String(),'lastInboundAt'=>$account->last_inbound_at?->toIso8601String()];
    }
}
