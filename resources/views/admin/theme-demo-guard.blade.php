@php
 $demoDialogItem=match($demoTheme??'food'){'universal'=>['id'=>-1,'title'=>'Everyday Bottle','variantTitle'=>'Stone / 500 ml','imageUrl'=>'/images/themes/universal/bottle.png'],'home_deco'=>['id'=>-1,'title'=>'Oak Ridge Coffee Table','variantTitle'=>'Natural oak / 90 cm','imageUrl'=>'/images/themes/home-deco/table.png'],'fashion'=>['id'=>-1,'title'=>'Urban Utility Jacket','variantTitle'=>'Charcoal / M','imageUrl'=>'/images/themes/fashion/jacket.png?v=fashion2'],'jewelry'=>['id'=>-1,'title'=>'Luna Pearl Pendant','variantTitle'=>'Gold tone / 40 cm','imageUrl'=>'/images/themes/jewelry/pendant.png'],'tech'=>['id'=>-1,'title'=>'Orbit Pro Wireless Headphones','variantTitle'=>'Graphite','imageUrl'=>'/images/themes/tech/headphones.png'],'beauty'=>['id'=>-1,'title'=>'Daily Serum','variantTitle'=>'30ml','imageUrl'=>'/images/themes/beauty/serum-front.png'],default=>['id'=>-1,'title'=>'Extra Virgin Olive Oil','variantTitle'=>'500 ml','imageUrl'=>'/images/themes/food/olive-oil-lifestyle.png']};
if(($demoTheme??'')==='logistics') $demoDialogItem=['id'=>-1,'title'=>'Container Accessories','variantTitle'=>'Sample accessory order','imageUrl'=>'/images/themes/logistics/containers.png'];
if(($demoTheme??'')==='wedding') $demoDialogItem=['id'=>-1,'title'=>'Linen wedding album','variantTitle'=>'Ivory / 25 × 25 cm','imageUrl'=>'/images/themes/wedding/album.png'];
$demoDialogSku=match($demoTheme??''){'logistics'=>'DEMO-ACCESSORY','fashion'=>'URBAN-CHARCOAL-M','jewelry'=>'LUNA-GOLD','tech'=>'DEMO-TECH-0',default=>'DEMO-OIL'};
$demoDialogAmount=match($demoTheme??''){'logistics'=>'200.00 MYR','jewelry'=>'189.00 MYR','tech'=>'899.00 MYR',default=>'68.00 MYR'};
@endphp
<script>
(()=>{
// This guard is emitted only by authenticated Admin demo routes.
window.__JW_THEME_DEMO__=true;
const demoScenario=@js($scenario);
window.addEventListener('load',()=>{if(demoScenario==='enquiry-modal')document.querySelector('[data-product-enquiry]')?.click();});
const demoTabs={'my-account':'dashboard','account-quotations':'quotations','account-orders':'purchase_history','account-vouchers':'vouchers','account-addresses':'addresses','account-profile':'profile','account-saved-items':'wishlist','review-dialog':'purchase_history','return-dialog':'purchase_history'};
if(demoTabs[demoScenario])location.hash=demoTabs[demoScenario];
const originalFetch=window.fetch.bind(window);
window.fetch=(input,options={})=>{
 const url=new URL(typeof input==='string'?input:input.url,location.href);
 const method=(options.method||(typeof input==='object'?input.method:'GET')||'GET').toUpperCase();
 if(method!=='GET'||url.pathname.includes('shipping-quote')||url.pathname==='/cart/drawer')return Promise.reject(new Error('Demo only. No store changes were made.'));
 return originalFetch(input,options);
};
document.addEventListener('submit',event=>{if(event.target.matches('[data-universal-filters],[data-wedding-filter-form],[data-logistics-filter-form]')&&event.target.method.toUpperCase()==='GET')return;event.preventDefault();event.stopImmediatePropagation();},true);
const originalFormSubmit=HTMLFormElement.prototype.submit;HTMLFormElement.prototype.submit=function(){if(this.matches('[data-universal-filters],[data-wedding-filter-form],[data-logistics-filter-form]')&&this.method.toUpperCase()==='GET')return originalFormSubmit.call(this);};
HTMLFormElement.prototype.requestSubmit=function(){};
const originalOpen=XMLHttpRequest.prototype.open;
XMLHttpRequest.prototype.open=function(method,...args){if(method.toUpperCase()!=='GET')throw new Error('Demo only. No store changes were made.');return originalOpen.call(this,method,...args);};
navigator.sendBeacon=()=>false;
document.addEventListener('click',event=>{const link=event.target.closest('a');if(link&&!link.getAttribute('href')?.startsWith('#')){event.preventDefault();if(!link.hasAttribute('data-cart-drawer-open')&&!link.hasAttribute('data-product-enquiry'))event.stopImmediatePropagation();}},true);
window.addEventListener('load',()=>{if(demoScenario==='quick-buy')setTimeout(()=>document.querySelector(@js(($demoTheme??'')==='logistics'?'[data-logistics-quick-view]':(($demoTheme??'')==='wedding'?'[data-wedding-quick-view]':'[data-quick-add-trigger]')))?.click(),0);});
document.addEventListener('alpine:initialized',()=>{
 const filter=document.querySelector('[aria-label="Open filters"]');
 if(demoScenario==='filter')filter?.click();

 const root=document.querySelector('[data-food-account],[data-home-deco-account],[data-beauty-account],[data-tech-account],[data-baby-account],[data-jewelry-account],[data-fashion-account],[data-universal-account],[data-wedding-account],[data-logistics-account]');
 if(root&&['review-dialog','return-dialog'].includes(demoScenario)){
  const state=Alpine.$data(root),item=@js($demoDialogItem);
  if(demoScenario==='review-dialog')state.openReview({number:'DEMO-JW100328'},item);
  else state.returnTarget={itemId:-1,orderNumber:'DEMO-JW100328',...item,sku:@js($demoDialogSku),quantity:1,amount:@js($demoDialogAmount)};
 }
});
})();
</script>
