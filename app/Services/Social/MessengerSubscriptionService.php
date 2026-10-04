<?php

namespace App\Services\Social;

use App\Models\ManagedSocialAccount;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MessengerSubscriptionService
{
    private function request(ManagedSocialAccount $account,string $method,array $data=[]): array
    {
        try {
            $token=$account->access_token;
            $client=Http::withToken($token)->acceptJson()->connectTimeout(5)->timeout(12);
            $data['appsecret_proof']=hash_hmac('sha256',$token,(string)config('services.facebook.client_secret'));
            $url='https://graph.facebook.com/'.config('services.facebook.graph_version','v25.0').'/'.$account->provider_account_id.'/subscribed_apps';
            $response=$method==='POST'?$client->asForm()->post($url,$data):$client->get($url,$data);
            $body=$response->json();
            if(!$response->successful()||!is_array($body)||isset($body['error']))throw new RuntimeException('Meta could not verify the Page subscription. Review authorization and try again.');
            return $body;
        } catch(\Throwable $e){throw new RuntimeException('Meta could not verify the Page subscription. Review authorization and try again.');}
    }
    private function fields(ManagedSocialAccount $account): array
    {
        $body=$this->request($account,'GET',['limit'=>100]);
        if(!is_array($body['data']??null)||!empty($body['paging']['next']))throw new RuntimeException('The subscription list could not be fully verified.');
        foreach($body['data'] as $app){
            if(is_array($app)&&(string)($app['id']??'')===(string)config('services.facebook.client_id')){
                $fields=$app['subscribed_fields']??[];
                if(!is_array($fields)||count(array_filter($fields,'is_string'))!==count($fields))throw new RuntimeException('The subscription fields could not be verified.');
                return $fields;
            }
        }
        return [];
    }
    public function enable(ManagedSocialAccount $account): void
    {
        abort_unless(config('services.facebook.inbound_routing_ready')===true,409,'Messenger webhook routing is not ready. Ask your administrator to finish setup.');
        abort_unless($account->state==='connected'&&$account->app_id===(string)config('services.facebook.client_id'),422,'Reconnect and verify this Page first.');
        abort_unless(count(array_intersect(['pages_messaging','pages_manage_metadata'],$account->permissions??[]))===2,422,'Review Facebook authorization and grant messaging and Page webhook permissions.');
        $fields=$this->fields($account);
        if(!in_array('messages',$fields,true)){
            $body=$this->request($account,'POST',['subscribed_fields'=>implode(',',array_unique([...$fields,'messages']))]);
            if(($body['success']??null)!==true)throw new RuntimeException('Meta did not confirm the subscription.');
        }
        if(!in_array('messages',$this->fields($account),true))throw new RuntimeException('Meta has not enabled the messages subscription.');
        $updated=ManagedSocialAccount::whereKey($account->id)->where('state','connected')
            ->where('access_token',$account->getRawOriginal('access_token'))
            ->update(['inbound_enabled'=>true,'inbound_verified_at'=>now(),'updated_at'=>now()]);
        abort_unless($updated,409,'The Page connection changed. Refresh and try again.');
    }
    public function verify(ManagedSocialAccount $account): bool
    {
        return config('services.facebook.inbound_routing_ready')===true&&in_array('messages',$this->fields($account),true);
    }
}
