<?php
namespace App\Services\AI;
use App\Models\{AiConnection,Category,Page,Product,StoreSetting};
final class CmsAiStoreContext
{
    public function metadata(): array
    {
        $c=AiConnection::where('enabled',true)->where('builder_enabled',true)->first();
        $ready=$c&&$c->verified_at&&$c->text_model;
        $models=array_values(array_column(array_filter($c?->models??[],fn($m)=>($m['image']??false)&&preg_match('/^gpt-image-(?:2(?:-|$)|2\.5-)/',$m['id']??'')),'id'));
        return ['brand'=>app(\App\Services\CmsThemePalette::class)->current(),'business'=>app(\App\Support\ShopBrand::class)->name(),'productCount'=>Product::where('status','active')->count(),'categoryCount'=>Category::where('is_active',true)->count(),'faqCount'=>count($this->faqs()),'textReady'=>(bool)$ready,'imageReady'=>(bool)($ready&&$c->provider==='openai'&&$c->image_model&&in_array($c->image_model,$models,true)),'textModel'=>$c?->text_model,'imageModel'=>$c?->image_model,'imageModels'=>$models];
    }
    public function facts(?string $sourcePage=null): array
    {
        $profile=data_get(StoreSetting::where('key','shop_profile')->value('value'),'data',[]);
        $products=Product::where('status','active')->orderBy('id')->limit(30)->get(['id','title','handle','subtitle'])->map(fn($p)=>['id'=>(string)$p->id,'title'=>$p->title,'summary'=>mb_substr(strip_tags($p->subtitle??''),0,300),'url'=>'/products/'.$p->handle])->all();
        $categories=Category::where('is_active',true)->orderBy('id')->limit(20)->get(['id','name','slug'])->map(fn($c)=>['id'=>(string)$c->id,'name'=>$c->name,'url'=>'/categories/'.$c->slug])->all();
        $image='';$images=[];
        if($sourcePage){$source=Page::find($sourcePage)?->draftDocument?->document;foreach($source['sections']??[] as $s)if(!empty($s['mediaUrl'])&&($s['mediaType']??'image')!=='video'){$images[]=$s['mediaUrl'];}foreach($source['sections']??[] as $s)foreach($s['items']??[] as $item)if(!empty($item['imageUrl']))$images[]=$item['imageUrl'];$images=array_slice(array_values(array_unique($images)),0,6);$image=$images[0]??'';}
        return ['brand'=>app(\App\Services\CmsThemePalette::class)->current(),'business'=>app(\App\Support\ShopBrand::class)->name(),'tagline'=>mb_substr(strip_tags($profile['tagline']??''),0,500),'products'=>$products,'categories'=>$categories,'faqs'=>$this->faqs(),'existingImage'=>$image,'existingImages'=>$images,'supportUrl'=>'/#storefront-support-widget','catalogUrl'=>'/shop'];
    }
    private function faqs(): array
    {
        $faq=data_get(StoreSetting::where('key','support_faq')->value('value'),'faq',[]);
        if(!($faq['isEnabled']??false))return [];
        return array_slice(array_values(array_map(fn($q)=>['question'=>mb_substr(strip_tags($q['question']??''),0,400),'answer'=>mb_substr(strip_tags($q['answer']??''),0,900)],array_filter($faq['items']??[],fn($q)=>$q['isActive']??false))),0,20);
    }
}
