<?php

namespace App\Services\Marketing;

use Illuminate\Support\Facades\{Http,Storage};
use App\Models\MarketingProduction;

class MarketingEvolinkMedia
{
    protected function addresses(string $host): array {return gethostbynamel($host)?:[];}

    public function downloadOptions(string $url): array
    {
        $p=parse_url($url);$host=$p['host']??'';
        if(($p['scheme']??'')!=='https'||!$host||isset($p['user'])||isset($p['pass'])||isset($p['port'])&&$p['port']!==443)throw new MarketingProductionFailure('native_result_url_invalid','unknown');
        $ips=$this->addresses($host);if(!$ips)throw new MarketingProductionFailure('native_result_url_unavailable','unknown');
        foreach($ips as $ip){
            if(!filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4|FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE)||preg_match('/^(?:100\.(?:6[4-9]|[7-9]\d|1[01]\d|12[0-7])\.|192\.0\.(?:0|2)\.|198\.(?:18|19|51)\.|203\.0\.113\.|22[4-9]\.|23\d\.)/',$ip))throw new MarketingProductionFailure('native_result_url_invalid','unknown');
        }
        return ['allow_redirects'=>false,'stream'=>true,'curl'=>[CURLOPT_RESOLVE=>[$host.':443:'.$ips[0]],CURLOPT_PROXY=>'']];
    }

    public function store(MarketingProduction $task,string $url): array {return $this->storeProfile($task,$url,false);}
    public function storeShot(MarketingProduction $task,string $url):array {return $this->storeProfile($task,$url,true);}
    private function storeProfile(MarketingProduction $task,string $url,bool $shot):array
    {
        $options=$this->downloadOptions($url);$disk=Storage::disk('local');$base='marketing-productions/'.$task->id;$disk->makeDirectory($base);
        $temporary=$disk->path($base.'/native-'.bin2hex(random_bytes(6)).'.mp4');$stream=null;$handle=null;
        try{
            // Never send the API credential to a result host. No redirect or proxy/DNS rebinding.
            $response=Http::connectTimeout(8)->timeout(90)->withOptions($options)->get($url);
            if(!$response->successful()||(int)$response->header('Content-Length')>40000000)throw new \RuntimeException;
            $stream=$response->toPsrResponse()->getBody();$handle=fopen($temporary,'xb');if(!$handle)throw new \RuntimeException;
            $size=0;while(!$stream->eof()){$bytes=$stream->read(65536);$size+=strlen($bytes);if($size>40000000||fwrite($handle,$bytes)!==strlen($bytes))throw new \RuntimeException;}
            fclose($handle);$handle=null;$stream->close();$stream=null;
            if($size<1000)throw new \RuntimeException;
            $tools=app(MarketingMediaTools::class);$probe=$tools->probe($temporary);$video=collect($probe['streams']??[])->where('codec_type','video');$audio=collect($probe['streams']??[])->where('codec_type','audio');$v=$video->first();$a=$audio->first();$duration=(float)($probe['format']['duration']??0);
            if($video->count()!==1||($shot?$audio->count()!==0:$audio->count()!==1)||($v['codec_name']??'')!=='h264'||(!$shot&&($a['codec_name']??'')!=='aac')||($v['width']??0)!==720||($v['height']??0)!==1280||$duration<($shot?4.9:14)||$duration>($shot?5.2:16)||(int)($v['nb_read_frames']??0)<1)throw new \RuntimeException;
            $args=[(string)config('marketing.production.ffmpeg_binary'),'-v','error','-xerror','-i',$temporary,'-map','0:v:0'];if(!$shot)$args=array_merge($args,['-map','0:a:0','-c:a','pcm_s16le']);$tools->run(array_merge($args,['-c:v','rawvideo','-f','null','-']),60,true);
            $target=$disk->path($base.($shot?'/generated-shot.mp4':'/output.mp4'));if($shot&&is_file($target)){if(!hash_equals(hash_file('sha256',$target),hash_file('sha256',$temporary)))throw new \RuntimeException;unlink($temporary);}elseif(!rename($temporary,$target))throw new \RuntimeException;
            if($shot)return ['path'=>$base.'/generated-shot.mp4','mime'=>'video/mp4','width'=>720,'height'=>1280,'duration_seconds'=>$duration,'has_audio'=>false,'sha256'=>hash_file('sha256',$target),'bytes'=>$size];
            return ['duration_seconds'=>$duration,'width'=>720,'height'=>1280,'frames'=>(int)$v['nb_read_frames'],'video_codec'=>'h264','audio_codec'=>'aac','sha256'=>hash_file('sha256',$target),'bytes'=>$size,'voice_source'=>'evolink_native_audio','voice_reused'=>false,'composition'=>'provider_native_no_remotion','publication_authorized'=>false];
        }catch(\Throwable){throw new MarketingProductionFailure('native_media_unverified','unknown');}
        finally{if(is_resource($handle))fclose($handle);if($stream)$stream->close();if(is_file($temporary))unlink($temporary);}
    }
}
