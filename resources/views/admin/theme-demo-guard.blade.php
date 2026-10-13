@php
 $demoDialogItem=match($demoTheme??'food'){'home_deco'=>['id'=>-1,'title'=>'Oak Ridge Coffee Table','variantTitle'=>'Natural oak / 90 cm','imageUrl'=>'/images/themes/home-deco/table.png'],'beauty'=>['id'=>-1,'title'=>'Daily Serum','variantTitle'=>'30ml','imageUrl'=>'/images/themes/beauty/serum-front.png'],default=>['id'=>-1,'title'=>'Extra Virgin Olive Oil','variantTitle'=>'500 ml','imageUrl'=>'/images/themes/food/olive-oil-lifestyle.png']};
@endphp
<script>
(()=>{
// This guard is emitted only by authenticated Admin demo routes.
window.__JW_THEME_DEMO__=true;
const demoScenario=@js($scenario);
const demoTabs={'my-account':'dashboard','account-orders':'purchase_history','account-vouchers':'vouchers','account-addresses':'addresses','account-profile':'profile','account-saved-items':'wishlist','review-dialog':'purchase_history','return-dialog':'purchase_history'};
if(demoTabs[demoScenario])location.hash=demoTabs[demoScenario];
const originalFetch=window.fetch.bind(window);
window.fetch=(input,options={})=>{
 const url=new URL(typeof input==='string'?input:input.url,location.href);
 const method=(options.method||(typeof input==='object'?input.method:'GET')||'GET').toUpperCase();
 if(method!=='GET'||url.pathname.includes('shipping-quote')||url.pathname==='/cart/drawer')return Promise.reject(new Error('Demo only. No store changes were made.'));
 return originalFetch(input,options);
};
document.addEventListener('submit',event=>{event.preventDefault();event.stopImmediatePropagation();},true);
HTMLFormElement.prototype.submit=function(){};
HTMLFormElement.prototype.requestSubmit=function(){};
const originalOpen=XMLHttpRequest.prototype.open;
XMLHttpRequest.prototype.open=function(method,...args){if(method.toUpperCase()!=='GET')throw new Error('Demo only. No store changes were made.');return originalOpen.call(this,method,...args);};
navigator.sendBeacon=()=>false;
document.addEventListener('click',event=>{const link=event.target.closest('a');if(link&&!link.getAttribute('href')?.startsWith('#')){event.preventDefault();if(!link.hasAttribute('data-cart-drawer-open'))event.stopImmediatePropagation();}},true);
window.addEventListener('load',()=>{if(demoScenario==='quick-buy')document.querySelector('[data-quick-add-trigger]')?.click();});
document.addEventListener('alpine:initialized',()=>{
 const filter=document.querySelector('[aria-label="Open filters"]');
 if(demoScenario==='filter')filter?.click();

 const root=document.querySelector('[data-food-account],[data-home-deco-account],[data-beauty-account]');
 if(root&&['review-dialog','return-dialog'].includes(demoScenario)){
  const state=Alpine.$data(root),item=@js($demoDialogItem);
  if(demoScenario==='review-dialog')state.openReview({number:'DEMO-JW100328'},item);
  else state.returnTarget={itemId:-1,orderNumber:'DEMO-JW100328',...item,sku:'DEMO-OIL',quantity:1,amount:'68.00 MYR'};
 }
});
})();
</script>
