<?php

namespace App\Services\Social;

use App\Models\ManagedSocialAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WhatsAppAccountService
{
    public function configured():bool {return config('services.whatsapp.oauth_routing_ready')===true&&filled(config('services.whatsapp.app_id'))&&filled(config('services.whatsapp.app_secret'))&&filled(config('services.whatsapp.embedded_signup_config_id'));}
    public function merchant(): string {return (string)config('services.facebook.merchant_key','jw-ecommerce');}

    private function graph(string $path,string $token,array $query=[]): array
    {
        try {
            $response=Http::withToken($token)->acceptJson()->connectTimeout(5)->timeout(12)
                ->get('https://graph.facebook.com/'.config('services.whatsapp.graph_version','v25.0').'/'.$path,$query);
            $body=$response->json();
            if(!$response->successful()||!is_array($body)||isset($body['error']))throw new RuntimeException('provider_unavailable');
            return $body;
        } catch(\Throwable $e) {throw new RuntimeException('provider_unavailable');}
    }

    private function verify(array $values): array
    {
        foreach(['app_id','waba_id','phone_number_id','access_token'] as $key)if(!is_string($values[$key]??null)||$values[$key]==='')throw new RuntimeException('configuration_required');
        foreach(['app_id','waba_id','phone_number_id'] as $key)if(!ctype_digit($values[$key]))throw new RuntimeException('account_identity_mismatch');
        $app=(string)config('services.whatsapp.app_id');$secret=(string)config('services.whatsapp.app_secret');
        if($app===''||$secret==='')throw new RuntimeException('configuration_required');
        if($app!==$values['app_id'])throw new RuntimeException('account_identity_mismatch');
        $data=$this->graph('debug_token',$app.'|'.$secret,['input_token'=>$values['access_token']])['data']??[];
        if(($data['is_valid']??false)!==true||(string)($data['app_id']??'')!==$app
            ||(!empty($data['expires_at'])&&(int)$data['expires_at']<=now()->timestamp))throw new RuntimeException('authorization_expired');
        $scopes=$data['scopes']??[];
        if(!is_array($scopes)||array_diff(['whatsapp_business_management','whatsapp_business_messaging'],$scopes))throw new RuntimeException('permission_required');
        $after=null;
        for($page=0;$page<10;$page++) {
            $body=$this->graph($values['waba_id'].'/phone_numbers',$values['access_token'],['fields'=>'id,display_phone_number,verified_name','limit'=>100]+($after?['after'=>$after]:[]));
            foreach($body['data']??[] as $phone)if(is_array($phone)&&(string)($phone['id']??'')===$values['phone_number_id'])return $phone+['permissions'=>$scopes];
            if(empty($body['paging']['next']))break;
            $next=$body['paging']['cursors']['after']??null;
            if(!is_string($next)||$next===$after)throw new RuntimeException('provider_unavailable');
            $after=$next;
        }
        throw new RuntimeException('account_identity_mismatch');
    }

    public function importLegacy(array $values,User $actor): ManagedSocialAccount
    {
        if(!$actor->is_super_admin)throw new RuntimeException('manager_required');
        $phone=$this->verify($values);
        return DB::transaction(function()use($values,$actor,$phone){
            $accounts=ManagedSocialAccount::where('platform','whatsapp')->where('provider_account_id',$values['phone_number_id'])->lockForUpdate()->get();
            if($accounts->contains(fn($a)=>$a->merchant_key!==$this->merchant()||$a->app_id!==$values['app_id']||$a->waba_id!==$values['waba_id']))throw new RuntimeException('account_owner_conflict');
            $account=$accounts->first()??new ManagedSocialAccount;
            // Explicit server-owned whitelist; never mass-assign request data.
            $account->forceFill(['merchant_key'=>$this->merchant(),'platform'=>'whatsapp','app_id'=>$values['app_id'],
                'provider_account_id'=>$values['phone_number_id'],'waba_id'=>$values['waba_id'],
                'name'=>$phone['verified_name']??$values['phone_number_id'],'display_phone_number'=>$phone['display_phone_number']??null,
                'access_token'=>$values['access_token'],'permissions'=>$phone['permissions'],'tasks'=>[],
                'state'=>'connected','inbound_enabled'=>false,'inbound_verified_at'=>null,'messaging_error'=>null,'authorized_by'=>$actor->id,'checked_at'=>now(),'connection_method'=>'migrated_sender']);
            $account->save();return $account;
        });
    }

    public function summary(ManagedSocialAccount $account): array
    {
        return ['id'=>$account->id,'provider_account_id'=>$account->provider_account_id,'waba_id'=>$account->waba_id,
            'permissions'=>$account->permissions??[],'messaging_error'=>$account->messaging_error,'inbound_enabled'=>$account->inbound_enabled,'inbound_verified_at'=>$account->inbound_verified_at?->toIso8601String(),'inbound_routing_ready'=>config('services.whatsapp.inbound_routing_ready')===true,
            'name'=>$account->name,'display_phone_number'=>$account->display_phone_number,'state'=>$account->state,
            'connection_method'=>$account->connection_method,'messaging_ready'=>$account->state==='connected'&&$account->inbound_enabled&&$account->inbound_verified_at&&config('services.whatsapp.inbound_routing_ready')===true,'checked_at'=>$account->checked_at?->toIso8601String()];
    }

    public function list(): array
    {
        return ManagedSocialAccount::where('merchant_key',$this->merchant())->where('platform','whatsapp')->get()->map(fn($a)=>$this->summary($a))->all();
    }

    public function check(ManagedSocialAccount $account): array
    {
        if($account->platform!=='whatsapp'||$account->merchant_key!==$this->merchant())throw new RuntimeException('account_owner_conflict');
        if($account->state==='disconnected')return $this->summary($account);
        $phone=$this->verify(['app_id'=>$account->app_id,'waba_id'=>$account->waba_id,'phone_number_id'=>$account->provider_account_id,'access_token'=>$account->access_token]);
        $account->update(['checked_at'=>now(),'permissions'=>$phone['permissions'],'state'=>'connected']);
        return $this->summary($account);
    }
}
