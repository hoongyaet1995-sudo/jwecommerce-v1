<?php
namespace App\Services\Marketing;
/** Public result URLs only; never expose provider task/session URLs. */
class MarketingPostLink {
 public function url(string $platform,array $result,string $kind='video'):?string {
  if(($result['status']??null)!=='Published')return null;
  $url=$result['url']??null;
  if(is_string($url)&&$url!==''){
   $p=parse_url($url);$hosts=['facebook'=>['www.facebook.com','facebook.com'],'instagram'=>['www.instagram.com','instagram.com'],'tiktok'=>['www.tiktok.com','tiktok.com'],'youtube'=>['www.youtube.com','youtube.com','youtu.be']];
   if(($p['scheme']??'')!=='https'||!in_array($p['host']??'',$hosts[$platform]??[],true)||isset($p['user'])||isset($p['pass'])||isset($p['port'])||isset($p['fragment']))return null;
   if($platform==='instagram'&&preg_match('~^/(p|reel|tv)/[A-Za-z0-9_-]+/?$~D',$p['path']??'')&&!isset($p['query']))return $url;
   // Other platforms use the validated durable ID below.
  }
  $ref=(string)($result['ref']??'');
  if($platform==='youtube'&&preg_match('/^[A-Za-z0-9_-]{11}$/D',$ref))return 'https://www.youtube.com/watch?v='.$ref;
  if($platform==='facebook'&&preg_match('/^(\d+)_(\d+)$/D',$ref,$m))return 'https://www.facebook.com/'.$m[1].'/posts/'.$m[2];
  if($platform==='facebook'&&preg_match('/^\d+$/D',$ref))return 'https://www.facebook.com/photo.php?fbid='.$ref;
  if($platform==='tiktok'&&preg_match('/^\d{15,22}$/D',$ref))return 'https://www.tiktok.com/@_/'.($kind==='image'?'photo':'video').'/'.$ref;
  return null;
 }
}
