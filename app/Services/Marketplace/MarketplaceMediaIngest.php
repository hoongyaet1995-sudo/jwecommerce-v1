<?php
namespace App\Services\Marketplace;
use App\Models\CmsMediaAsset;
use App\Services\ManagedMediaIngest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB,Http,Storage};
use DomainException;
final class MarketplaceMediaIngest
{
 public function supports(string $url):bool {return in_array(parse_url($url,PHP_URL_HOST),config('marketplace_media.image_hosts',[]),true);}
 public function normalize(array $data):array {
  foreach(['productImage','variantImage'] as $key)abort_unless(!isset($data[$key])||is_string($data[$key]),422,'Invalid channel image URL.');
  if(isset($data['productImages'])) {
   abort_unless(is_array($data['productImages'])&&array_is_list($data['productImages']),422,'Invalid channel image list.');
   foreach($data['productImages'] as $url)abort_unless(is_string($url)&&\App\Domain\Cms\MediaUrlPolicy::isSafe($url),422,'Invalid channel image URL.');
  }

  foreach(['productImage','variantImage'] as $key)if(!empty($data[$key])&&$this->supports($data[$key]))$data[$key]=$this->ingest($data[$key]);
  if(isset($data['productImages']))$data['productImages']=array_map(fn($url)=>$this->supports($url)?$this->ingest($url):$url,$data['productImages']);
  return $data;
 }
 public function ingest(string $url):string {
  if(!$this->supports($url)||!\App\Domain\Cms\MediaUrlPolicy::isSafe($url))throw new DomainException('This image source is not supported for automatic import. Upload the image to JW media.');
  $hash=hash('sha256',$url);
  $record=DB::table('marketplace_media_sources')->where('source_hash',$hash)->first();
  $asset=$record?CmsMediaAsset::find($record->asset_id):null;
  if($asset&&Storage::disk($asset->disk)->exists($asset->path))return $asset->url;
  $host=parse_url($url,PHP_URL_HOST);$addresses=dns_get_record($host,DNS_A);$ip=$addresses[0]['ip']??null;
  if(!$ip||!filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE))throw new DomainException('Image host could not be resolved to a public address.');
  $file=tempnam(sys_get_temp_dir(),'jw-media-');
  try {
   $response=Http::connectTimeout(10)->timeout(30)->withoutRedirecting()->withOptions([
    'sink'=>$file,'curl'=>[CURLOPT_RESOLVE=>["$host:443:$ip"]],
    'on_headers'=>function($response){if((int)$response->getHeaderLine('Content-Length')>10485760)throw new DomainException('Image exceeds 10 MiB.');},
    'progress'=>function($total,$downloaded){if($downloaded>10485760)throw new DomainException('Image exceeds 10 MiB.');}
   ])->get($url);
   if(!$response->successful())throw new DomainException('The source image could not be downloaded. Retry or upload it to JW media.');
   // Fake HTTP transports do not stream to sink; production uses the bounded sink above.
   if(filesize($file)===0&&$response->body()!=='')file_put_contents($file,$response->body());
   if(filesize($file)>10485760||filesize($file)===0)throw new DomainException('Image must be between 1 byte and 10 MiB.');
   $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($file);
   $ext=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'][$mime]??null;
   if(!$ext)throw new DomainException('The source did not return a supported image.');
   $checksum=hash_file('sha256',$file);$asset=CmsMediaAsset::where('checksum',$checksum)->first();
   if(!$asset||!Storage::disk($asset->disk)->exists($asset->path))$asset=app(ManagedMediaIngest::class)->ingest(new UploadedFile($file,'marketplace.'.$ext,$mime,null,true),null);
   DB::table('marketplace_media_sources')->updateOrInsert(['source_hash'=>$hash],['source_url'=>$url,'asset_id'=>$asset->id,'created_at'=>now(),'updated_at'=>now()]);
   return $asset->url;
  } finally {if(is_file($file))unlink($file);}
 }
}
