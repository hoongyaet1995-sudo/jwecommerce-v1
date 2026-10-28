<?php
namespace App\Services\Marketing;
use Illuminate\Support\Facades\{DB,Storage};
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;
class MarketingPostMedia {
 public function store(int $user,UploadedFile $file):string {
  abort_unless($file->isValid()&&$file->getSize()>24&&$file->getSize()<=64*1024*1024,422,'Choose an existing MP4 up to64MB.');$bytes=file_get_contents($file->getRealPath());$seconds=$this->duration($bytes);abort_unless($seconds>0&&$seconds<=600,422,'Choose a valid video of10minutes or less.');$id=(string)Str::uuid();$path='marketing/post-media/'.$user.'/'.$id.'.mp4';Storage::disk('local')->put($path,$bytes);DB::table('marketing_post_media')->insert(['id'=>$id,'user_id'=>$user,'title'=>mb_substr(pathinfo($file->getClientOriginalName(),PATHINFO_FILENAME),0,200)?:'Uploaded video','path'=>$path,'sha256'=>hash('sha256',$bytes),'bytes'=>strlen($bytes),'seconds'=>$seconds,'created_at'=>now(),'updated_at'=>now()]);return 'upload:'.$id;
 }
 public function duration(string $bytes):float {
  abort_unless(substr($bytes,4,4)==='ftyp',422,'Only MP4 video is supported.');$movie=null;$video=false;
  $walk=function(string $data,int $depth=0)use(&$walk,&$movie,&$video){if($depth>5)return;for($p=0,$n=strlen($data);$p+8<=$n;){$len=unpack('N',substr($data,$p,4))[1];$type=substr($data,$p+4,4);if($len<8||$p+$len>$n)break;$payload=substr($data,$p+8,$len-8);if(in_array($type,['moov','trak','mdia'],true))$walk($payload,$depth+1);if($type==='hdlr'&&substr($payload,8,4)==='vide')$video=true;if($type==='mvhd'){$v=ord($payload[0]??"\0");$scaleOffset=$v===1?20:12;$durationOffset=$v===1?24:16;if(strlen($payload)>=$durationOffset+($v===1?8:4)){$scale=unpack('N',substr($payload,$scaleOffset,4))[1];$ticks=$v===1?unpack('J',substr($payload,$durationOffset,8))[1]:unpack('N',substr($payload,$durationOffset,4))[1];if($scale>0)$movie=$ticks/$scale;}}$p+=$len;}};$walk($bytes);abort_unless($video&&is_numeric($movie)&&$movie>0,422,'Video duration could not be verified. The original file is retained.');return round($movie,3);
 }
}
