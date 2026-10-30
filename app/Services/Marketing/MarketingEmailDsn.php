<?php
namespace App\Services\Marketing;

/** Strict, bounded RFC 3464 subset. Unsupported mail is ignored, never guessed. */
final class MarketingEmailDsn
{
    public function parse(string $raw): array
    {
        if (strlen($raw)>1048576 || str_contains($raw,"\0")) return [];
        [$headers,$body]=$this->split($raw);
        $h=$this->headers($headers); $type=$h['content-type']??'';
        if (!preg_match('~^multipart/report\s*;~i',$type) || !preg_match('~(?:^|;)\s*report-type\s*=\s*"?delivery-status"?(?:\s*;|\s*$)~i',$type)) return [];
        if (!preg_match('~(?:^|;)\s*boundary\s*=\s*(?:"([^"\r\n]{1,200})"|([^;\s]{1,200}))~i',$type,$m)) return [];
        $boundary=$m[1]!==''?$m[1]:($m[2]??'');
        $parts=preg_split('/(?:^|\n)--'.preg_quote($boundary,'/').'(?:--)?[ \t]*(?:\n|$)/',str_replace("\r\n","\n",$body));
        if (!$parts || count($parts)>30) return [];
        $reports=[]; $ids=[];
        foreach ($parts as $part) {
            [$ph,$pb]=$this->split(ltrim($part,"\r\n")); $p=$this->headers($ph);
            $ct=strtolower(trim(explode(';',$p['content-type']??'')[0]));
            $encoding=strtolower($p['content-transfer-encoding']??'7bit');
            if ($encoding==='base64') { $pb=base64_decode(preg_replace('/\s/','',$pb),true); if($pb===false)return []; }
            elseif ($encoding==='quoted-printable') $pb=quoted_printable_decode($pb);
            elseif (!in_array($encoding,['7bit','8bit','binary'],true)) continue;
            if ($ct==='message/delivery-status') {
                foreach (preg_split('/\n\s*\n/',str_replace("\r\n","\n",trim($pb))) as $block) {
                    $fields=$this->headers($block);
                    if (!preg_match('/^rfc822\s*;\s*([^\s<>]+)$/i',$fields['final-recipient']??'',$recipient)) continue;
                    $email=strtolower($recipient[1]);
                    if (!filter_var($email,FILTER_VALIDATE_EMAIL)) continue;
                    $action=strtolower($fields['action']??''); $status=$fields['status']??'';
                    $kind=($action==='failed'&&preg_match('/^5\.\d{1,3}\.\d{1,3}$/D',$status))?'hard_bounce':(($action==='delayed'&&preg_match('/^4\.\d{1,3}\.\d{1,3}$/D',$status))?'soft_bounce':null);
                    if ($kind) $reports[]=['recipient'=>$email,'kind'=>$kind];
                }
            } elseif (in_array($ct,['message/rfc822','text/rfc822-headers'],true)) {
                [$original]=$this->split($pb); $oh=$this->headers($original);
                if (preg_match('/^<([^<>\s]{1,250})>$/D',$oh['message-id']??'',$id)) $ids[]=$id[1];
            }
        }
        $ids=array_values(array_unique($ids));
        if (count($ids)!==1) return [];
        return array_map(fn($r)=>$r+['message_id'=>$ids[0]],$reports);
    }

    private function split(string $raw): array
    {
        $p=preg_split('/\r?\n\r?\n/',$raw,2);
        return [$p[0]??'', $p[1]??''];
    }

    private function headers(string $raw): array
    {
        if(strlen($raw)>65536)return [];
        $raw=preg_replace('/\r?\n[ \t]+/',' ',$raw); $out=[];
        foreach (preg_split('/\r?\n/',$raw) as $line) {
            if (!preg_match('/^([A-Za-z0-9-]+):[ \t]*(.*)$/D',$line,$m)) continue;
            $key=strtolower($m[1]); if(array_key_exists($key,$out))return []; // ambiguous headers
            $out[$key]=trim($m[2]);
        }
        return $out;
    }
}
