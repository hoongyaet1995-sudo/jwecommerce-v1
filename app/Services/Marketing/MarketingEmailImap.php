<?php
namespace App\Services\Marketing;

use Webklex\PHPIMAP\ClientManager;

/** No mutation methods are called: EXAMINE + UID FETCH BODY.PEEK only. */
class MarketingEmailImap
{
    public function scan(array $credentials, int $validity, int $cursor): array
    {
        abort_unless(($credentials['host']??'')==='imap.hostinger.com'&&($credentials['port']??0)===993,409);
        $addresses=array_merge(gethostbynamel($credentials['host'])?:[],array_column(dns_get_record($credentials['host'],DNS_AAAA)?:[],'ipv6'));
        if (!$addresses) throw new \RuntimeException('Mailbox unavailable');
        foreach ($addresses as $ip) if(!filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE))throw new \RuntimeException('Mailbox unavailable');
        $client=(new ClientManager(['options'=>['debug'=>false]]))->make([
            'host'=>$credentials['host'],'port'=>993,'encryption'=>'ssl','validate_cert'=>true,
            'username'=>$credentials['username'],'password'=>$credentials['password'],
            'protocol'=>'imap','timeout'=>5,'authentication'=>null,
        ]);
        try {
            $client->connect();
            $connection=$client->getConnection();
            return $this->readWindow($connection,$validity,$cursor);
        } finally { $client->disconnect(); }
    }

    /** Public for a protocol fake: all network I/O remains in scan(). */
    public function readWindow(object $connection, int $validity, int $cursor): array
    {
        $meta=$connection->examineFolder('INBOX')->validatedData();
        $current=(int)($meta['uidvalidity']??0); $next=(int)($meta['uidnext']??0);
        if($current<1||$next<1)throw new \RuntimeException('Mailbox metadata unavailable');
        if($current!==$validity)$cursor=0;
        if($cursor>=$next)throw new \RuntimeException('Mailbox checkpoint changed');
        $end=min($next-1,$cursor+50); $messages=[]; $scanned=0; $large=0; $started=microtime(true);
        $last=$cursor;
        // Bound UID ranges rather than searching an entire mailbox into memory.
        $sizes=$end>$cursor?$connection->fetch(['UID','RFC822.SIZE'],$cursor+1,$end)->validatedData():[];
        ksort($sizes,SORT_NUMERIC);
        foreach ($sizes as $uid=>$attributes) {
            $uid=(int)$uid;
            if($uid<=$cursor||$uid>$end)throw new \RuntimeException('Mailbox UID mismatch');
            if(microtime(true)-$started>12)break;
            $scanned++; $size=(int)($attributes['RFC822.SIZE']??0);
            if($size<=0||$size>1048576){$large++;$last=$uid;continue;}
            $head=$connection->fetch(['UID','BODY.PEEK[HEADER]<0.65536>'],$uid)->validatedData();
            $header=$head['BODY[HEADER]<0>']??$head['BODY[HEADER]']??'';
            if (!is_string($header))throw new \RuntimeException('Mailbox header unavailable');
            if(preg_match('/^Content-Type:\s*multipart\/report\b/im',$header)) {
                $body=$connection->fetch(['UID','BODY.PEEK[]<0.1048577>'],$uid)->validatedData();
                $raw=$body['BODY[]<0>']??$body['BODY[]']??null;
                if(!is_string($raw))throw new \RuntimeException('Mailbox body unavailable');
                if(strlen($raw)<=1048576)$messages[]=$raw; else $large++;
            }
            $last=$uid;
        }
        if($scanned===count($sizes))$last=$end;
        return ['uidvalidity'=>$current,'cursor'=>$last,'has_more'=>$last<$next-1,'scanned'=>$scanned,'skipped_large'=>$large,'messages'=>$messages];
    }
}
