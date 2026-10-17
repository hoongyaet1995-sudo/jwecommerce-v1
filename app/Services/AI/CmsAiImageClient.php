<?php
namespace App\Services\AI;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CmsAiImageClient
{
    public function generate(string $key,string $model,string $prompt,string $quality,string $ratio,?string $reference=null): array
    {
        if(!preg_match('/^gpt-image-(?:2(?:-|$)|2\.5-)/',$model)||!in_array($quality,['medium','high'],true)||!in_array($ratio,['16:9','4:3','1:1'],true)||strlen($prompt)>6000)throw new ProviderFailure('endpoint');
        $payload=['model'=>$model,'prompt'=>$prompt,'n'=>1,'quality'=>$quality,'size'=>match($ratio){'16:9'=>'1536x864','4:3'=>'1344x1008',default=>'1024x1024'},'output_format'=>'png'];
        if($reference)$payload['images']=[['image_url'=>$reference]];
        try{
            $response=Http::acceptJson()->withToken($key)->connectTimeout(8)->timeout(180)->withOptions(['allow_redirects'=>false])->post('https://api.openai.com/v1/images/'.($reference?'edits':'generations'),$payload);
            if(!$response->successful())throw new ProviderFailure(match($response->status()){401,403=>'authentication',402,429=>'quota',default=>'unavailable'});
            $data=$response->json();$encoded=$data['data'][0]['b64_json']??null;
            if(!is_string($encoded)||strlen($encoded)>15_000_000)throw new ProviderFailure('response');
            $bytes=base64_decode($encoded,true);$info=$bytes!==false?@getimagesizefromstring($bytes):false;
            if(!$info||($info['mime']??'')!=='image/png'||strlen($bytes)>10*1024*1024)throw new ProviderFailure('response');
            $usage=array_intersect_key($data['usage']??[],array_flip(['input_tokens','output_tokens','total_tokens']));
            Log::info('cms_ai_image_usage',['model'=>$model,'usage'=>$usage]);
            return ['bytes'=>$bytes,'usage'=>$usage];
        }catch(ProviderFailure $e){throw $e;}catch(\Throwable){throw new ProviderFailure('unavailable');}
    }
}
