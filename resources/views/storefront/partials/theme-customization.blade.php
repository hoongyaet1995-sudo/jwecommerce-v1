@php
    $customizer=app(\App\Services\WebsiteThemeCustomization::class);
    $theme=request()->attributes->get('websiteRenderedTheme')??app(\App\Services\WebsiteDesignService::class)->surface();
    $style=$customizer->style($theme);
    $font=$style['font']??'default';
    if($theme==='baby') $style=array_replace(['font'=>'default','size'=>'medium','colorMode'=>'theme'],$style);
    $pairs=['editorial'=>['"Lora", Georgia, serif','"Inter", Arial, sans-serif'],'modern'=>['"DM Sans", Arial, sans-serif','"Inter", Arial, sans-serif'],'classic'=>['Georgia, serif','Arial, sans-serif']];
    if($theme==='baby') $pairs['default']=['BabyDM, Arial, sans-serif','BabyDM, Arial, sans-serif'];
    if ($theme==='beauty') $pairs['editorial']=['BeautyLora, Georgia, serif','BeautyInter, Arial, sans-serif'];
    $main=($style['colorMode']??'theme')!=='theme'?($style['main']??null):null;
    $sub=($style['colorMode']??'theme')!=='theme'?($style['sub']??null):null;
    $pairs['street']=['FashionAnton, Impact, sans-serif','FashionInter, Arial, sans-serif'];
    $pairs['universal']=['UniversalHeading, Arial, sans-serif','UniversalBody, Arial, sans-serif'];
    if($theme==='universal') $pairs['default']=$pairs['universal'];
    if($theme==='logistics') $pairs['default']=$pairs['logistics']=['"Barlow Semi Condensed", Arial, sans-serif','"Inter", Arial, sans-serif'];
    if($theme==='fashion') $pairs['default']=$pairs['street'];
    if($theme==='jewelry') $pairs['default']=$pairs['editorial']=['JewelryLora, Georgia, serif','JewelryInter, Arial, sans-serif'];
    if($theme==='wedding') $pairs['default']=$pairs['wedding']=['"Cormorant Garamond", Georgia, serif','"WeddingInter", Arial, sans-serif'];
    $roots='.jg-theme main,.jg-dialog,.jewelry-drawer,[data-jewelry-quick-buy],.bk-theme main,.bk-dialog,.baby-drawer,[data-baby-quick-buy],.technology-drawer,.te-theme main,.te-dialog,.te-cart,[data-tech-quick-buy],.fd-theme main,.fd-cart,.food-theme main,.food-theme .drawer,[data-food-quick-buy],.hd-theme main,.hd-dialog,.hd-cart,.bs-theme main,.bs-dialog,.bs-cart,.home-deco-theme main,.beauty-theme main,.home-deco-theme .drawer,.beauty-theme .drawer,[data-home-deco-quick-buy],[data-beauty-quick-buy],.website-theme-content';
    if($theme==='universal') $roots='.uv-theme,.uv-dialog,.universal-drawer,[data-universal-quick-buy]';
    if($theme==='fashion') $roots='.fs-theme main,.fs-dialog,.fashion-drawer,[data-fashion-quick-buy]';
    if($customizer->page()==='checkout') $roots.=',.fd-checkout,.hd-checkout,.bs-checkout';
    if($theme==='jewelry') $roots='.jg-theme main,.jg-dialog,.jewelry-drawer,[data-jewelry-quick-buy]';
    if($theme==='logistics') $roots='.lg-theme,.lg-dialog,.logistics-drawer,[data-logistics-quick-buy],.eq-ui';
    if($theme==='wedding') $roots='.wd-theme,.wd-dialog,.wedding-drawer,[data-wedding-quick-buy],.eq-ui';
@endphp
@if($style && $theme==='default')
<script>document.querySelectorAll('main').forEach(n=>n.classList.add('website-theme-content'));</script>
@endif
<style>.theme-account-media{width:100%;grid-column:1/-1;height:180px;overflow:hidden}.theme-account-media img{width:100%;height:180px;object-fit:cover}.hd-auth:not(:has(.hd-auth-scene)),.bs-auth:not(:has(.bs-auth-scene)){display:block;min-height:0}.hd-auth:not(:has(.hd-auth-scene)) .hd-auth-panel,.bs-auth:not(:has(.bs-auth-scene)) .bs-auth-panel{margin:auto}</style>
@if($style)
<link rel="stylesheet" href="{{ asset('fonts/sample/fonts.css') }}">
<style data-theme-customization>
@if(isset($pairs[$font]))
@if($theme==='fashion') :is({!! $roots !!}){--fs-action-font:{!! $pairs[$font][0] !!}} @endif
@if($theme==='logistics') :is({!! $roots !!}){--lg-heading-font:{!! $pairs[$font][0] !!};--lg-body-font:{!! $pairs[$font][1] !!};--website-theme-heading-font:{!! $pairs[$font][0] !!}} @endif
:is({!! $roots !!}),:is({!! $roots !!}) :is(p,a,button,input,select,textarea,label,small,li,td,th){font-family:{!! $pairs[$font][1] !!}!important}
:is({!! $roots !!}) :is(h1,h2,h3,h4,h1 a,h2 a,h3 a,h4 a){font-family:{!! $pairs[$font][0] !!}!important}
@endif
@if($main)
@if($theme==='logistics') :is({!! $roots !!}){--lg-main:{{ $main }};--website-theme-main:{{ $main }}} @endif
:is({!! $roots !!}){--fd-olive:{{ $main }};--hd-olive:{{ $main }};--bs-olive:{{ $main }};--te-olive:{{ $main }};--bk-olive:{{ $main }};--jg-olive:{{ $main }};--uv-olive:{{ $main }};--fs-olive:{{ $main }};--theme-main:{{ $main }};--theme-main-ink:{{ $customizer->foreground($main) }}}
:is(.lg-primary,.wd-primary,.fs-primary,.jg-primary,.bk-primary,.te-primary,.fd-primary,.hd-primary,.bs-primary),.website-theme-content button[type="submit"],:is({!! $roots !!}) :is(.drawerBottom .apply,[data-cart-checkout-link],button[data-add-to-cart-button],[data-checkout-submit]){background:{{ $main }}!important;border-color:{{ $main }}!important;color:{{ $customizer->foreground($main) }}!important}
:is(.lg-outline,.lg-small-outline,.wd-outline,.fs-outline,.jg-outline,.bk-outline,.te-outline,.fd-outline,.hd-outline,.bs-outline){border-color:{{ $main }}!important}
:is(.food-theme,.home-deco-theme,.beauty-theme) .details{color:{{ $customizer->foreground($main)==='#000000'?'#292923':$main }}!important;border-color:{{ $main }}!important}
:is({!! $roots !!}) :is(input,select){accent-color:{{ $main }}}
@endif
@if($sub)
@if($theme==='logistics') :is({!! $roots !!}){--lg-sub:{{ $sub }};--website-theme-sub:{{ $sub }}} @endif
:is({!! $roots !!}){--theme-sub:{{ $sub }};--jg-sub:{{ $sub }};--uv-sub:{{ $sub }};--fs-sub:{{ $sub }};--theme-sub-ink:{{ $customizer->foreground($sub) }}}
:is({!! $roots !!}) :is(.fd-notice,.hd-notice,.bs-notice,[aria-pressed="true"]){background:{{ $sub }};color:{{ $customizer->foreground($sub) }}}
@endif
.theme-account-media{width:100%;grid-column:1/-1;height:180px;overflow:hidden}.theme-account-media img{width:100%;height:180px;object-fit:cover}
.hd-auth:not(:has(.hd-auth-scene)),.bs-auth:not(:has(.bs-auth-scene)){display:block;min-height:0}
.hd-auth:not(:has(.hd-auth-scene)) .hd-auth-panel,.bs-auth:not(:has(.bs-auth-scene)) .bs-auth-panel{margin:auto}
</style>
@if(($style['size']??'medium')!=='medium')
<script>
(()=>{
const roots={!! json_encode($roots) !!},factor={{ ($style['size']??'medium')==='small'?.9:1.12 }};
const originals=new Map();
function apply(){
 originals.forEach((value,node)=>{if(node.isConnected)node.style.fontSize=value;else originals.delete(node)});
 const nodes=[...document.querySelectorAll(roots)].flatMap(root=>[root,...root.querySelectorAll('h1,h2,h3,h4,p,a,button,label,input,select,textarea,small,span,li,td,th')]).filter(n=>!n.closest('[data-header-mode],footer')&&n.textContent.trim());
 const sizes=nodes.map(n=>[n,parseFloat(getComputedStyle(n).fontSize)]);
 sizes.forEach(([n,size])=>{if(!originals.has(n))originals.set(n,n.style.fontSize);n.style.setProperty('font-size',`${Math.max(11,size*factor)}px`,@js(in_array($theme,['beauty','tech','baby','jewelry','wedding','fashion','universal','logistics'],true)?'important':''))});
}
let timer;const schedule=()=>{clearTimeout(timer);timer=setTimeout(apply,80)};
apply();window.addEventListener('resize',schedule);new MutationObserver(schedule).observe(document.getElementById('cart-drawer')||document.body,{childList:true,subtree:true});
})();
</script>
@endif
@endif
