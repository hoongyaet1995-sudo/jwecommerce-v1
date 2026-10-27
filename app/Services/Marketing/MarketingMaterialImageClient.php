<?php
namespace App\Services\Marketing;
use App\Models\{AiConnection,MarketingProject};
use Illuminate\Support\Facades\Http;
use App\Services\AI\ProviderFailure;
/** GPT Image requests only; credentials and model always come from AI Connect. */
class MarketingMaterialImageClient
{
 public function connection():AiConnection{
  $c=AiConnection::where('provider','openai')->where('enabled',true)->first();
  abort_unless($c&&$c->verified_at&&$c->api_key&&$c->image_model==='gpt-image-2'&&collect($c->models??[])->contains(fn($m)=>($m['id']??'')===$c->image_model&&($m['image']??false)),422,'请在AI Connect启用并验证GPT Image 2图片模型。');return $c;
 }
 public function configuration():array{$c=$this->connection();return ['provider'=>$c->provider,'model'=>$c->image_model,'connection_version'=>(int)$c->version,'quality'=>'medium','billing_mode'=>'standard','pricing_version'=>MarketingImagePricing::VERSION];}
 public function size(string $ratio):string{return match($ratio){'9:16'=>'864x1536','16:9'=>'1536x864','1:1'=>'1024x1024','4:3'=>'1344x1008','3:4'=>'1008x1344','2:3'=>'1024x1536','3:2'=>'1536x1024',default=>abort(422,'图片比例暂不支持。')};}
 public function generate(string $prompt,string $size,array $paths):array{
  $c=$this->connection();abort_if(count($paths)>16||mb_strlen($prompt)>25000,422,'图片输入超过模型限制。');
  $payload=['model'=>$c->image_model,'prompt'=>$prompt,'quality'=>'medium','size'=>$size,'n'=>1,'output_format'=>'png'];
  foreach($paths as $path){$info=@getimagesize($path);abort_unless($info&&filesize($path)<=10485760&&in_array($info['mime'],['image/png','image/jpeg','image/webp']),422,'参考文件已失效。');$payload['images'][]=['image_url'=>'data:'.$info['mime'].';base64,'.base64_encode(file_get_contents($path))];}
  try{$r=Http::withToken($c->api_key)->acceptJson()->connectTimeout(8)->timeout(240)->withoutRedirecting()->post('https://api.openai.com/v1/images/'.($paths?'edits':'generations'),$payload);}catch(\Throwable){throw new ProviderFailure('timeout');}
  if(!$r->successful()){
   $code=$r->json('error.code');
   if(in_array($code,['insufficient_quota','billing_hard_limit_reached'])||$r->status()===402)throw new ProviderFailure('balance');
   if(in_array($r->status(),[401,403]))throw new ProviderFailure('authentication');
   if($r->status()===429)throw new ProviderFailure('quota');
   if($r->status()>=500)throw new ProviderFailure('unavailable');
   throw new ProviderFailure('schema');
  }
  $data=$r->json();$b64=$data['data'][0]['b64_json']??null;abort_unless(is_string($b64)&&strlen($b64)<=15000000,409,'图片响应不完整，用量需核对；不会重试。');
  $bytes=base64_decode($b64,true);$info=$bytes===false?false:@getimagesizefromstring($bytes);abort_unless($info&&$info['mime']==='image/png'&&strlen($bytes)<=10485760,409,'生成图片验证失败，用量需核对；不会重试。');
  [$w,$h]=array_map('intval',explode('x',$size));abort_unless($info[0]===$w&&$info[1]===$h,409,'生成尺寸不符合镜头要求；保留用量待核对，不会重试。');
  $usage=$data['usage']??[];$text=$usage['input_tokens_details']['text_tokens']??null;$image=$usage['input_tokens_details']['image_tokens']??null;$out=$usage['output_tokens']??null;
  $micro=app(MarketingImagePricing::class)->cost($usage);$cost=$micro===null?null:$micro/1000000;
  return ['bytes'=>$bytes,'usage'=>$usage,'actual_usd'=>$cost,'request_id'=>$r->header('x-request-id')];
 }
}
