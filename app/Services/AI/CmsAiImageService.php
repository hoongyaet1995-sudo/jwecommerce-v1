<?php
namespace App\Services\AI;
use App\Models\{AiConnection,CmsMediaAsset};
use App\Services\ManagedMediaIngest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CmsAiImageService
{
    public function __construct(private CmsAiImageClient $client,private ManagedMediaIngest $media){}
    public function generate(array $document,array $input,int $actor,array $brief):array
    {
        $index=array_search($input['sectionId'],array_column($document['sections'],'id'),true);
        if($index===false||!in_array($input['sectionId'],array_column($brief['document']['sections'],'id'),true)||($brief['target']&&$brief['target']!==$input['sectionId']))$this->invalid('Select a section belonging to the confirmed Brief.');
        $section=$document['sections'][$index];$target=$input['imageTarget'];
        // Only editorial photographs. Product/SKU photos, logos and testimonials remain locked.
        $itemIndex=null;
        if($target==='mediaUrl'){
            if(!in_array($section['type'],['hero_banner','media_text_split','split_hero'],true)||($section['mediaType']??'image')==='video')$this->invalid('This media slot is not an editable photograph.');
        }elseif(preg_match('/^item:([^:]+):imageUrl$/',$target,$m)&&in_array($section['type'],['media_gallery','hero_slider','shop_navigation'],true)){
            $itemIndex=array_search($m[1],array_column($section['items']??[],'id'),true);
            if($itemIndex===false||!empty($section['items'][$itemIndex]['videoUrl']))$this->invalid('Select an existing photograph slot.');
        }else $this->invalid('Image generation is unavailable for this field.');
        $c=AiConnection::where('provider','openai')->where('enabled',true)->where('builder_enabled',true)->first();
        $models=array_column(array_filter($c?->models??[],fn($m)=>$m['image']??false),'id');
        if(!$c||!$c->verified_at||!$c->image_model||!in_array($input['imageModel'],$models,true))$this->invalid('Enable and verify this image model for CMS in AI Connect.');
        $reference=null;
        if(!empty($input['referenceUrl'])){
            $asset=CmsMediaAsset::where('url',$input['referenceUrl'])->where('disk','public')->first();
            if(!$asset||!in_array($asset->mime_type,['image/png','image/jpeg','image/webp'],true)||$asset->size>10*1024*1024)$this->invalid('Choose an image from the managed media library.');
            $reference='data:'.$asset->mime_type.';base64,'.base64_encode(Storage::disk('public')->get($asset->path));
        }
        $key='cms-ai-image:'.$actor.':'.$input['requestId'];
        $signature=hash('sha256',json_encode(array_intersect_key($input,array_flip(['sectionId','imageTarget','imagePrompt','imageModel','quality','ratio','referenceUrl','token']))));
        $saved=Cache::get($key);
        if($saved&&$saved['signature']!==$signature)$this->invalid('This image request has changed. Start a new request.');
        if(!$saved){
            $lock=Cache::lock('cms-ai-image-lock:'.$actor.':'.$input['token'],210);
            if(!$lock->get())$this->invalid('Another image is still generating. Wait for it to finish.');
            try{
                $saved=Cache::get($key);
                if(!$saved){
                    @set_time_limit(210);
                    $result=$this->client->generate($c->api_key,$input['imageModel'],$input['imagePrompt'],$input['quality'],$input['ratio'],$reference);
                    $temporary=tempnam(sys_get_temp_dir(),'cms-ai-');
                    try{
                        file_put_contents($temporary,$result['bytes']);
                        $asset=$this->media->ingest(new UploadedFile($temporary,'ai-illustrative-scene.png','image/png',null,true),$actor);
                    }finally{if(is_file($temporary))unlink($temporary);}
                    $saved=['signature'=>$signature,'url'=>$asset->url,'usage'=>$result['usage']];Cache::put($key,$saved,now()->addHours(2));
                }
            }finally{$lock->release();}
        }
        if($itemIndex===null)$document['sections'][$index]['mediaUrl']=$saved['url'];
        else $document['sections'][$index]['items'][$itemIndex]['imageUrl']=$saved['url'];
        return ['document'=>$document,'url'=>$saved['url'],'usage'=>$saved['usage']];
    }
    private function invalid(string $message):never{throw ValidationException::withMessages(['image'=>$message]);}
}
