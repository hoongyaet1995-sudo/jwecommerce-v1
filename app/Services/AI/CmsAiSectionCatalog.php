<?php
namespace App\Services\AI;
use Illuminate\Support\Str;
final class CmsAiSectionCatalog
{
    public function seed(array $document,array $facts,string $direction=''): array
    {
        $common=['isVisible'=>true,'containerMode'=>'page','sectionBorder'=>'none','motionPreset'=>'subtle-lift','paddingY'=>'normal','backgroundColor'=>'custom','textColor'=>'dark','subtitle'=>'','content'=>''];
        $section=fn($type,$title,$data=[])=>array_merge($common,['id'=>(string)Str::uuid(),'type'=>$type,'title'=>$title],$data);
        $media=empty($facts['existingImage'])?[]:['mediaUrl'=>$facts['existingImage'],'mediaType'=>'image'];
        $item=fn($title)=>['id'=>(string)Str::uuid(),'title'=>$title,'description'=>''];
        $document['style']=array_merge($document['style']??[],['colorSource'=>'theme','palette'=>$facts['brand']['palette']??app(\App\Services\CmsThemePalette::class)->current()['palette']]);
        $document['sections']=[
            $section('hero_banner','Introduction',array_merge($media,['buttonText'=>'Discuss your requirements','buttonLink'=>$facts['supportUrl']])),
            $section('feature_cards','Services',['columnsCount'=>3,'items'=>[$item('Service one'),$item('Service two'),$item('Service three')]]),
            $section('media_text_split','Our approach',array_merge($media,['mediaPosition'=>'left','buttonText'=>'Contact our team','buttonLink'=>$facts['supportUrl']])),
            $section('rich_text','Our story'),
            $section('media_gallery','A closer look',['galleryPreset'=>'industrial-mosaic','items'=>array_map(fn($url)=>['id'=>(string)Str::uuid(),'imageUrl'=>$url,'title'=>'','description'=>''],$facts['existingImages']??[])]),
            $section('product_listing','Explore our range',['sourceType'=>'manual','productIds'=>array_column(array_slice($facts['products']??[],0,6),'id'),'productLimit'=>6]),
            $section('faq_accordion','A few things, answered',['items'=>array_map(fn($faq)=>['id'=>(string)Str::uuid(),'title'=>$faq['question'],'description'=>$faq['answer']],array_slice($facts['faqs']??[],0,4))]),
            $section('cta_strip','Start a conversation',['buttonText'=>'Discuss your requirements','buttonLink'=>$facts['supportUrl']]),
        ];
        if(empty($facts['existingImages']))$document['sections']=array_values(array_filter($document['sections'],fn($s)=>$s['type']!=='media_gallery'));
        $allowed=app(CmsAiCapabilities::class)->all();$document['sections']=array_values(array_filter($document['sections'],fn($s)=>$allowed[$s['type']]['newComposition']??false));
        if(empty($facts['products']))$document['sections']=array_values(array_filter($document['sections'],fn($s)=>$s['type']!=='product_listing'));
        if(empty($facts['faqs']))$document['sections']=array_values(array_filter($document['sections'],fn($s)=>$s['type']!=='faq_accordion'));
        $warm=str_contains($direction,'Warm & approachable');$clean=str_contains($direction,'Clean & minimal');
        foreach($document['sections'] as &$s){
            if($s['type']==='hero_banner')$s['heroBannerPreset']=$warm?'editorial-gradient':($clean?'editorial-gradient':'industrial-overlay');
            if($s['type']==='feature_cards')$s['featureCardsPreset']=$warm?'editorial-columns':($clean?'default':'industrial-cards');
            if($s['type']==='media_text_split')$s['layoutPreset']=$clean?'flush-split':'editorial-story';
            if($s['type']==='faq_accordion')$s['faqPreset']=$warm||$clean?'minimal':'industrial-split';
            $s['paddingY']=$clean?'normal':'spacious';
            if(!in_array($s['type'],['hero_banner'],true))$s['surface']=$s['type']==='cta_strip'?'contrast':'light';
        }
        return $document;
    }
}
