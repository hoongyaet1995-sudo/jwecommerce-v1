<?php
namespace App\Services\Marketing;

use App\Models\MarketingChannelConnection;
use App\Services\Social\SocialHubOAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Crypt,DB};
use Illuminate\Support\Str;

final class ExternalChannelConnections
{
    public static function supports(string $p): bool { return in_array($p,['tiktok','youtube'],true); }
    public function appId(string $p): string { return (string) config('services.'.$p.'.client_id'); }
    public function scope(string $p): string { return $p==='tiktok'?'user.info.basic':'https://www.googleapis.com/auth/youtube.readonly'; }
    private function merchant(): string { return app(MarketingChannels::class)->merchant(); }
    private function result(string $p,string $result) { return redirect('/admin/marketing/channels/'.$p.'?'.http_build_query(['result'=>$result]))->header('Cache-Control','no-store')->header('Referrer-Policy','no-referrer'); }
    public function authorize(Request $r,string $p)
    {
        $r->session()->forget([$p.'_pending',$p.'_oauth']);
        try {
            $hub=app(SocialHubOAuth::class);$state=Str::random(64);$purpose=$r->input('purpose','identity');$grant=$purpose==='publish'?$hub->start($state,$p,'publish'):$hub->start($state,$p);
            $r->session()->put($p.'_oauth',['state'=>$state,'actor'=>$r->user()->id,'merchant'=>$this->merchant(),'app'=>$this->appId($p),'installation'=>$hub->fingerprint(),'transaction'=>$grant['transaction_id'],'purpose'=>$purpose,'expires'=>now()->addMinutes(10)->timestamp]);
            return redirect()->away($grant['launch_url'])->header('Cache-Control','no-store')->header('Referrer-Policy','no-referrer');
        } catch (\Throwable) { $r->session()->forget($p.'_marketing_oauth');return $this->result($p,'hub_unavailable'); }
    }
    public function hubReturn(Request $r,string $p)
    {
        $s=$r->session()->pull($p.'_oauth');$hub=app(SocialHubOAuth::class);
        try {
            if (!app(MarketingChannels::class)->ready($r,$p)['ready'] || !is_array($s) || !is_string($r->query('state')) || !hash_equals($s['state'],$r->query('state')) || $s['actor']!==$r->user()->id || $s['merchant']!==$this->merchant() || $s['app']!==$this->appId($p) || $s['installation']!==$hub->fingerprint() || $s['transaction']!==$r->query('transaction') || $s['expires']<now()->timestamp || !is_string($r->query('handoff')) || !preg_match('/^[a-f0-9]{64}$/D',$r->query('handoff'))) throw new \RuntimeException('invalid_state');
            $receipt=$hub->call('redeem',['transaction_id'=>$s['transaction'],'handoff'=>$r->query('handoff')],$p);
            if (($receipt['result']??null)!=='select') {
                $r->session()->forget($p.'_marketing_oauth');
                return $this->result($p,in_array($receipt['result']??'', ['cancelled','no_pages','permission_required','authorization_expired'],true)?$receipt['result']:'provider_error');
            }
            $pages=$receipt['pages']??[];
            if (!is_array($pages) || !$pages || count($pages)>100) throw new \RuntimeException('invalid_state');
            foreach ($pages as $a) if (!is_array($a) || !is_string($a['id']??null) || !preg_match('/^[A-Za-z0-9_-]{1,100}$/D',$a['id']) || !is_string($a['name']??null)) throw new \RuntimeException('invalid_state');
            $r->session()->put($p.'_pending',Crypt::encryptString(json_encode($s+['pages'=>array_map(fn($a)=>['id'=>$a['id'],'name'=>$a['name']],$pages)],JSON_THROW_ON_ERROR)));
            return $this->result($p,'select');
        } catch (\Throwable) { $r->session()->forget([$p.'_pending',$p.'_marketing_oauth']);return $this->result($p,'invalid_state'); }
    }
    public function pending(Request $r,string $p): ?array
    {
        try {
            $intent=$r->session()->get($p.'_marketing_oauth');$raw=$r->session()->get($p.'_pending');$s=$raw?json_decode(Crypt::decryptString($raw),true,32,JSON_THROW_ON_ERROR):null;
            if (is_array($intent) && ($intent['actor']??null)===$r->user()->id && ($intent['platform']??null)===$p && ($intent['expires']??0)>=now()->timestamp && is_array($s) && $s['actor']===$r->user()->id && $s['merchant']===$this->merchant() && $s['app']===$this->appId($p) && $s['installation']===app(SocialHubOAuth::class)->fingerprint() && $s['expires']>=now()->timestamp) return $s;
        } catch (\Throwable) {}
        return null;
    }
    public function select(Request $r,string $p,string $id): void
    {
        $s=$this->pending($r,$p);abort_unless($s,409,'The account selection expired. Reconnect to continue.');
        abort_unless(collect($s['pages'])->contains('id',$id),422,'Choose an account returned by this authorization.');
        try { $receipt=app(SocialHubOAuth::class)->call('select',['transaction_id'=>$s['transaction'],'page_id'=>$id],$p); }
        catch (\Throwable) { $r->session()->forget([$p.'_pending',$p.'_marketing_oauth']);abort(502,'Authorization could not be saved. Reconnect to continue. Existing accounts are retained.'); }
        $r->session()->forget([$p.'_pending',$p.'_marketing_oauth']);
        $pages=$receipt['pages']??[];$a=count($pages)===1?$pages[0]:null;$scopes=$receipt['permissions']??[];
        abort_unless(is_array($a) && ($a['id']??null)===$id && is_string($a['name']??null) && is_string($a['access_token']??null) && $a['access_token']!=='' && is_array($scopes) && in_array($this->scope($p),$scopes,true) && count(array_filter($scopes,'is_string'))===count($scopes) && $this->validDate($a['expires_at']??null),502,'The authorization receipt is incomplete. Reconnect to continue.');
        if(($s['purpose']??'identity')==='publish')abort_unless(in_array($p==='tiktok'?'video.publish':'https://www.googleapis.com/auth/youtube.upload',$scopes,true),422,'Publishing permission was not granted. Existing accounts are retained.');
        DB::transaction(function() use($a,$scopes,$p,$id,$r) {
            $keys=['merchant_key'=>$this->merchant(),'platform'=>$p,'provider_account_id'=>$id];
            $row=MarketingChannelConnection::where($keys)->lockForUpdate()->first()??new MarketingChannelConnection($keys);
            $sameApp=$row->app_id===$this->appId($p);
            $refresh=is_string($a['refresh_token']??null)&&$a['refresh_token']!==''?$a['refresh_token']:($sameApp?$row->refresh_token:null);
            $row->fill(['app_id'=>$this->appId($p),'name'=>mb_substr($a['name'],0,255),'access_token'=>$a['access_token'],'refresh_token'=>$refresh,'expires_at'=>$a['expires_at'],'refresh_expires_at'=>$this->validDate($a['refresh_expires_at']??null)?$a['refresh_expires_at']:($sameApp?$row->refresh_expires_at:null),'permissions'=>array_values(array_unique($scopes)),'tasks'=>[],'authorized_by'=>$r->user()->id,'state'=>'connected','checked_at'=>now(),'check_code'=>null,'version'=>$row->exists?$row->version+1:1])->save();
        });
    }
    private function validDate(mixed $date): bool { try { return is_string($date)&&\Carbon\Carbon::parse($date)->year>=2020; } catch (\Throwable) {return false;} }
    public function refreshForPublishing(MarketingChannelConnection $account):void {
        abort_unless($account->state==='connected'&&$account->refresh_token&&(!$account->refresh_expires_at||$account->refresh_expires_at->isFuture()),422,'Reconnect this account to continue.');
        $version=$account->version;$this->check($account,true);$account->refresh();abort_unless($account->state==='connected'&&$account->version===$version&&(!$account->expires_at||$account->expires_at->isFuture()),422,'Account authorization changed. Review the connection before publishing.');
    }
    public function check(MarketingChannelConnection $account,bool $credentialOnly=false): void
    {
        DB::transaction(function() use($account,$credentialOnly) {
            $a=MarketingChannelConnection::whereKey($account->id)->lockForUpdate()->firstOrFail();
            if ($a->state==='disconnected') return;
            $before=[$a->state,$a->app_id,$a->provider_account_id,$a->permissions];$code='provider_unavailable';
            try {
                if ($a->app_id!==$this->appId($a->platform)) throw new \RuntimeException('authorization_expired');
                $d=app(SocialHubOAuth::class)->call('check',['account_id'=>$a->provider_account_id,'access_token'=>$a->access_token,'refresh_token'=>$a->refresh_token,'permissions'=>$a->permissions??[],'expires_at'=>$a->expires_at?->toIso8601String(),'refresh_expires_at'=>$a->refresh_expires_at?->toIso8601String()],$a->platform);
                $grant=$d['grant']??null;
                if (is_array($grant) && is_string($grant['access_token']??null) && $grant['access_token']!=='' && $this->validDate($grant['expires_at']??null)) {
                    $a->access_token=$grant['access_token'];$a->expires_at=$grant['expires_at'];
                    if (is_string($grant['refresh_token']??null)&&$grant['refresh_token']!=='') $a->refresh_token=$grant['refresh_token'];
                    if ($this->validDate($grant['refresh_expires_at']??null)) $a->refresh_expires_at=$grant['refresh_expires_at'];
                    if (is_array($grant['permissions']??null) && count(array_filter($grant['permissions'],'is_string'))===count($grant['permissions'])) $a->permissions=$grant['permissions'];
                }
                $code=$d['result']??'provider_unavailable';
                if ($code==='connected') {
                    if (($d['account']['id']??null)!==$a->provider_account_id) throw new \RuntimeException('identity_mismatch');
                    $a->name=mb_substr((string)($d['account']['name']??$a->name),0,255);$code=null;
                }
            } catch (\Throwable $e) { $code=$e->getMessage()==='authorization_expired'?'authorization_expired':($e->getMessage()==='identity_mismatch'?'identity_mismatch':'provider_unavailable'); }
            if ($code!==null && !in_array($code,['authorization_expired','permission_required','identity_mismatch'],true)) $code='provider_unavailable';
            $a->state=$code===null?'connected':(in_array($code,['authorization_expired','permission_required'],true)?$code:'unknown');
            $a->check_code=$code;$a->checked_at=now();if(!$credentialOnly||$code!==null||$before!==[$a->state,$a->app_id,$a->provider_account_id,$a->permissions])$a->version++;$a->save();
        },1);
    }
}
