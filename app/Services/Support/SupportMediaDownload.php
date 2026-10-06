<?php
namespace App\Services\Support;
use Illuminate\Support\Facades\Http;

class SupportMediaDownload
{
    public function host(string $url): string
    {
        $p=parse_url($url);$host=strtolower($p['host']??'');
        if(($p['scheme']??'')!=='https' || isset($p['user']) || isset($p['pass']) || isset($p['port']) && $p['port']!==443
            || !preg_match('/(^|\.)(fbcdn\.net|fbsbx\.com|facebook\.com)$/D',$host)) throw new \RuntimeException('Untrusted media destination.');
        return $host;
    }
    public function download(string $url, ?string $token=null): string
    {
        $host=$this->host($url);
        $ips=gethostbynamel($host)?:[];
        if(!$ips)throw new \RuntimeException('Media destination unavailable.');
        foreach($ips as $ip) if(!filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE)) throw new \RuntimeException('Untrusted media address.');
        $request=Http::connectTimeout(5)->timeout(20)->withoutRedirecting()->withOptions(['stream'=>true,'curl'=>[CURLOPT_RESOLVE=>[$host.':443:'.$ips[0]],CURLOPT_PROXY=>'']]);
        if($token)$request=$request->withToken($token);
        try{$response=$request->get($url);}catch(\Throwable){throw new \RuntimeException('Media request unavailable.');}
        if(!$response->successful() || (int)$response->header('Content-Length')>SupportAttachmentService::MAX_BYTES)throw new \RuntimeException('Media response unavailable.');
        $stream=$response->toPsrResponse()->getBody();$bytes='';
        try {while(!$stream->eof()) {$bytes.=$stream->read(65536);if(strlen($bytes)>SupportAttachmentService::MAX_BYTES)throw new \RuntimeException('Media exceeds size limit.');}}finally{$stream->close();}
        return $bytes;
    }
}
