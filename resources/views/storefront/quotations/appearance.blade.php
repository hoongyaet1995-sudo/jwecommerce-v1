@php
 $profile=app(\App\Services\WebsiteThemeCustomization::class)->style($theme,'checkout');
 $pairs=['editorial'=>['Lora,Georgia,serif','Inter,Arial,sans-serif'],'modern'=>['"DM Sans",Arial,sans-serif','Inter,Arial,sans-serif'],'classic'=>['Georgia,serif','Arial,sans-serif'],'street'=>['FashionAnton,Impact,sans-serif','FashionInter,Arial,sans-serif'],'universal'=>['QuoteHeading,Arial,sans-serif','QuoteBody,Arial,sans-serif']];
 $font=$profile['font']??'default';
 if($font==='default')$font=match($theme){'fashion'=>'street','jewelry'=>'editorial',default=>'universal'};
 $pair=$pairs[$font]??$pairs['universal'];
 $factor=match($profile['size']??'medium'){'small'=>.9,'big'=>1.12,default=>1};
 $sub=($profile['colorMode']??'theme')!=='theme'?($profile['sub']??$main):$main;
@endphp
<style>#customer-view.quotation-live{--quote-sub:{{ $sub }};font-family:{!! $pair[1] !!}}#customer-view.quotation-live :is(p,a,button,input,select,textarea,label,small,span,strong,dd,dt){font-family:{!! $pair[1] !!}}#customer-view.quotation-live :is(h1,h2,h3,.document-brand,.document-grand-total strong){font-family:{!! $pair[0] !!}}#customer-view.quotation-live h1{font-size:{{ 34*$factor }}px}#customer-view.quotation-live .document-parties{background:color-mix(in srgb,var(--quote-sub) 5%,white)}@media(max-width:700px){#customer-view.quotation-live h1{font-size:{{ 28*$factor }}px}}</style>

@if($theme==='logistics')@include('storefront.themes.logistics-quotation-style')@endif
