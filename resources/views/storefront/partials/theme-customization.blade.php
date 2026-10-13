@php
    $customizer=app(\App\Services\WebsiteThemeCustomization::class);
    $theme=request()->attributes->get('websiteRenderedTheme')??app(\App\Services\WebsiteDesignService::class)->surface();
    $style=$customizer->style($theme);
    $font=$style['font']??'default';
    $pairs=['editorial'=>['"Lora", Georgia, serif','"Inter", Arial, sans-serif'],'modern'=>['"DM Sans", Arial, sans-serif','"Inter", Arial, sans-serif'],'classic'=>['Georgia, serif','Arial, sans-serif']];
    if ($theme==='beauty') $pairs['editorial']=['BeautyLora, Georgia, serif','BeautyInter, Arial, sans-serif'];
    $main=($style['colorMode']??'theme')!=='theme'?($style['main']??null):null;
    $sub=($style['colorMode']??'theme')!=='theme'?($style['sub']??null):null;
    $roots='.technology-drawer,.te-theme main,.te-dialog,.te-cart,[data-tech-quick-buy],.fd-theme main,.fd-cart,.food-theme main,.food-theme .drawer,[data-food-quick-buy],.hd-theme main,.hd-dialog,.hd-cart,.bs-theme main,.bs-dialog,.bs-cart,.home-deco-theme main,.beauty-theme main,.home-deco-theme .drawer,.beauty-theme .drawer,[data-home-deco-quick-buy],[data-beauty-quick-buy],.website-theme-content';
@endphp
@if($style && $theme==='default')
<script>document.querySelectorAll('main').forEach(n=>n.classList.add('website-theme-content'));</script>
@endif
<style>.theme-account-media{width:100%;grid-column:1/-1;height:180px;overflow:hidden}.theme-account-media img{width:100%;height:180px;object-fit:cover}.hd-auth:not(:has(.hd-auth-scene)),.bs-auth:not(:has(.bs-auth-scene)){display:block;min-height:0}.hd-auth:not(:has(.hd-auth-scene)) .hd-auth-panel,.bs-auth:not(:has(.bs-auth-scene)) .bs-auth-panel{margin:auto}</style>
@if($style)
<link rel="stylesheet" href="{{ asset('fonts/sample/fonts.css') }}">
<style data-theme-customization>
@if(isset($pairs[$font]))
:is({!! $roots !!}),:is({!! $roots !!}) :is(p,a,button,input,select,textarea,label,small,li,td,th){font-family:{!! $pairs[$font][1] !!}!important}
:is({!! $roots !!}) :is(h1,h2,h3,h4,h1 a,h2 a,h3 a,h4 a){font-family:{!! $pairs[$font][0] !!}!important}
@endif
@if($main)
:is({!! $roots !!}){--fd-olive:{{ $main }};--hd-olive:{{ $main }};--bs-olive:{{ $main }};--te-olive:{{ $main }};--theme-main:{{ $main }};--theme-main-ink:{{ $customizer->foreground($main) }}}
:is(.te-primary,.fd-primary,.hd-primary,.bs-primary),.website-theme-content button[type="submit"],:is({!! $roots !!}) :is(.drawerBottom .apply,[data-cart-checkout-link],button[data-add-to-cart-button]){background:{{ $main }}!important;border-color:{{ $main }}!important;color:{{ $customizer->foreground($main) }}!important}
:is(.te-outline,.fd-outline,.hd-outline,.bs-outline){border-color:{{ $main }}!important}
:is(.food-theme,.home-deco-theme,.beauty-theme) .details{color:{{ $customizer->foreground($main)==='#000000'?'#292923':$main }}!important;border-color:{{ $main }}!important}
:is({!! $roots !!}) :is(input,select){accent-color:{{ $main }}}
@endif
@if($sub)
:is({!! $roots !!}){--theme-sub:{{ $sub }};--theme-sub-ink:{{ $customizer->foreground($sub) }}}
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
 sizes.forEach(([n,size])=>{if(!originals.has(n))originals.set(n,n.style.fontSize);n.style.setProperty('font-size',`${Math.max(11,size*factor)}px`,@js(in_array($theme,['beauty','tech'],true)?'important':''))});
}
let timer;const schedule=()=>{clearTimeout(timer);timer=setTimeout(apply,80)};
apply();window.addEventListener('resize',schedule);new MutationObserver(schedule).observe(document.getElementById('cart-drawer')||document.body,{childList:true,subtree:true});
})();
</script>
@endif
@endif
