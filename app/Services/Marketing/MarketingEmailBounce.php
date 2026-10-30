<?php
namespace App\Services\Marketing;

use App\Models\StoreSetting;
use Illuminate\Support\Facades\{Crypt, DB};
use Illuminate\Support\Str;

final class MarketingEmailBounce
{
    public function messageId(object $delivery, string $from): string
    {
        $domain=strtolower(substr(strrchr($from,'@')?:'@invalid.test',1));
        $signature=hash_hmac('sha256','email-bounce|'.$delivery->merchant_key.'|'.$delivery->id.'|'.$delivery->recipient_hash,config('app.key'));
        return 'jw.'.$delivery->id.'.'.$signature.'@'.$domain;
    }

    private function key(): string
    {
        $settings=app(MarketingEmailSettings::class);
        return 'marketing_bounce:'.hash('sha256',app(MarketingPosts::class)->merchant().'|'.$settings->quotaKey($settings->raw()+['host'=>'','username'=>'']));
    }

    public function status(): array
    {
        $s=StoreSetting::where('key',$this->key())->first()?->value??[];
        return array_intersect_key($s,array_flip(['cursor','uidvalidity','last_checked','error','matched','scanned','has_more','skipped_large']));
    }

    /** Only feed raw messages fetched via the authenticated read-only mailbox adapter. */
    public function ingest(string $raw): int
    {
        $merchant=app(MarketingPosts::class)->merchant(); $count=0;
        foreach (app(MarketingEmailDsn::class)->parse($raw) as $dsn) {
            if(!preg_match('/^jw\.([a-f0-9-]{36})\.([a-f0-9]{64})@[^@\s]+$/D',$dsn['message_id'],$m))continue;
            $r=DB::table('marketing_email_deliveries')->where('id',$m[1])->where('merchant_key',$merchant)->first();
            if(!$r||!in_array($r->state,['accepted','sending','unknown','delivered','delayed','bounced','complained'],true))continue;
            $snapshot=json_decode($r->snapshot,true);
            if(empty($snapshot['message_id'])||!hash_equals($snapshot['message_id'],$dsn['message_id']))continue;
            // Revalidate signature, not just UUID or recipient. Unknown old emails stay unchanged.
            $expected=$this->messageId($r,'sender@'.substr(strrchr($dsn['message_id'],'@'),1));
            if(!hash_equals($expected,$dsn['message_id']))continue;
            if(!hash_equals(strtolower(trim(Crypt::decryptString($r->recipient))),$dsn['recipient']))continue;
            $event=hash('sha256',$dsn['message_id'].'|'.$dsn['recipient'].'|'.$dsn['kind']);
            if(app(MarketingEmailFeedback::class)->recordVerified($merchant,$r->id,'hostinger_imap',$event,$dsn['kind']))$count++;
        }
        return $count;
    }

    public function check(int $version): array
    {
        abort_unless(config('marketing.email_bounce_enabled',false),409,'Bounce checks are not enabled yet. Your messages are retained.');
        $settings=app(MarketingEmailSettings::class); $s=$settings->raw();
        abort_unless($version>0&&($s['version']??0)===$version&&($s['host']??'')==='smtp.hostinger.com',409,'Save the Hostinger connection before checking bounces.');
        $key=$this->key(); $lease=(string)Str::uuid();
        $checkpoint=DB::transaction(function()use($key,$lease){
            StoreSetting::firstOrCreate(['key'=>$key],['value'=>[]]);
            $row=StoreSetting::where('key',$key)->lockForUpdate()->first();$v=$row->value??[];
            abort_if(($v['lease_until']??0)>time(),409,'A bounce check is already running.');
            $v['lease']=$lease;$v['lease_until']=time()+90;$row->update(['value'=>$v]);return $v;
        });
        $result=null;$error=null;
        try {
            $transport=$settings->transport($s);
            $result=app(MarketingEmailImap::class)->scan(['host'=>'imap.hostinger.com','port'=>993,'username'=>$transport['username'],'password'=>$transport['password']],(int)($checkpoint['uidvalidity']??0),(int)($checkpoint['cursor']??0));
            abort_unless($settings->version()===$version,409,'The saved connection changed.');
            $matched=0;
            foreach($result['messages'] as $raw)$matched+=$this->ingest($raw);
            unset($result['messages']);$result['matched']=$matched;
        } catch (\Throwable $e) { $error=in_array($e->getMessage(),['mailbox_connection_failed','mailbox_read_failed'],true)?$e->getMessage():'connection_failed';$result=null; }
        return DB::transaction(function()use($key,$lease,$result,$error){
            $row=StoreSetting::where('key',$key)->lockForUpdate()->first();$v=$row->value??[];
            abort_unless(($v['lease']??null)===$lease,409,'The bounce check changed. Check again.');
            if($result)$v=array_replace($v,$result);
            $v['error']=$error;$v['last_checked']=now()->toIso8601String();unset($v['lease'],$v['lease_until']);
            $row->update(['value'=>$v]);return $this->status();
        });
    }
}
