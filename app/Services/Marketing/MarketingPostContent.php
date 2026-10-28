<?php
namespace App\Services\Marketing;
use App\Models\ProductImage;
use Illuminate\Validation\ValidationException;
/** Ordered content contract. Legacy single-asset records remain readable. */
class MarketingPostContent {
 public function keys(array $d,?string $target=null):array {return array_values($target!==null&&array_key_exists($target,$d['media_copies']??[])?$d['media_copies'][$target]:($d['assets']??(!empty($d['asset'])?[$d['asset']]:[])));}
 private function invalid(string $m):never{throw ValidationException::withMessages(['media'=>$m]);}
 public function path(int $user,string $key):string {$s=app(MarketingPosts::class);$a=$s->asset($user,$key);if(!$a)$this->invalid('Selected media is unavailable. Choose an existing file again.');if($a['kind']==='video'||str_starts_with($key,'upload:'))return $s->videoFile($user,$key);$path=$s->imagePath(ProductImage::findOrFail(substr($key,6))->path);if(!$path)$this->invalid('The selected image is unavailable.');return $path;}
 public function normalize(int $user,array $d,array $old=[]):array {
  $keys=$this->keys($d);$sets=array_intersect_key($d['media_copies']??[],array_flip($d['targets']??[]));
  foreach($old['outcomes']??[] as $t=>$o)if(($o['status']??'')==='Published')$sets[$t]=$this->keys($old,$t);
  foreach([$keys,...array_values($sets)] as $set){if(!is_array($set)||count($set)>10||count(array_unique($set))!==count($set))$this->invalid('Choose up to 10 different media files.');foreach($set as $key)if(!is_string($key)||!app(MarketingPosts::class)->asset($user,$key))$this->invalid('Selected media is unavailable.');}
  return ['asset'=>$keys[0]??null,'assets'=>$keys,'media_copies'=>$sets,'format'=>$d['format']??($d['type']==='Reel'?'video':(count($keys)>1?'carousel':'image'))];
 }
 public function validate(int $user,array $d,array $known):void {
  foreach($d['targets'] as $t){if(($d['outcomes'][$t]['status']??'')==='Published')continue;if(!isset($known[$t]))$this->invalid('Choose accounts connected to this business.');$platform=$known[$t]['platform'];$keys=$this->keys($d,$t);$kinds=array_map(fn($k)=>app(MarketingPosts::class)->asset($user,$k)['kind']??'unavailable',$keys);
   if(count($keys)>10||count(array_unique($keys))!==count($keys))$this->invalid('Choose up to 10 different images.');
   if((($d['format']??($d['type']==='Reel'?'video':'image'))==='video')!==($d['type']==='Reel'))$this->invalid('Review the selected content format.');if(($d['format']??'')==='image'&&count($keys)>1)$this->invalid('Choose Multi-image post for multiple images.');
   if($d['type']==='Reel'){if(count($keys)!==1||$kinds!==['video'])$this->invalid('Choose one video for Video / Reel.');}
   else{if(array_diff($kinds,['image']))$this->invalid('Image posts cannot contain a video.');if($platform==='youtube')$this->invalid('YouTube requires a video.');if(!$keys&&$platform!=='facebook')$this->invalid(ucfirst($platform).' requires an image.');if(($d['format']??'')==='carousel'&&count($keys)<2)$this->invalid('Choose 2–10 images for each account in a multi-image post.');}
   if($platform==='instagram'&&$d['type']==='Post'){ $ratio=null;foreach($keys as $key){$info=@getimagesize($this->path($user,$key));if(!$info)$this->invalid('Choose a valid image.');$r=$info[0]/$info[1];if($r<.8||$r>1.91)$this->invalid('Instagram images need an aspect ratio between 4:5 and 1.91:1. Originals are retained.');if($ratio!==null&&abs($ratio-$r)>.01)$this->invalid('Instagram carousel images need matching aspect ratios. Originals are not cropped automatically.');$ratio=$r;}}
   $copy=$d['copies'][$t]??$d['copy'];$limit=['facebook'=>63206,'instagram'=>2200,'tiktok'=>$d['type']==='Reel'?2200:4000,'youtube'=>5000][$platform]??10000;if(strlen(mb_convert_encoding($copy,'UTF-16LE','UTF-8'))/2>$limit)$this->invalid(ucfirst($platform).': shorten the caption to '.$limit.' characters.');
  }
 }
 public function snapshot(int $user,array $d):array { $sets=['shared'=>$this->keys($d)];foreach($d['targets']??[] as $t)$sets[$t]=$this->keys($d,$t);$files=[];$kind=null;foreach($sets as $set)foreach($set as $key)if(!isset($files[$key])){$a=app(MarketingPosts::class)->asset($user,$key);if(!$a)$this->invalid('Selected media is unavailable.');$kind??=$a['kind'];if($a['kind']!==$kind)$this->invalid('Do not mix images and videos in one post.');$files[$key]=hash_file('sha256',$this->path($user,$key));}return ['kind'=>$kind??'image','sha256'=>hash('sha256',json_encode([$sets,$files],JSON_THROW_ON_ERROR)),'sets'=>$sets,'files'=>$files]; }
}
