import {applyPlaceholderMedia} from '../../cms/defaultPlaceholderMedia';
import {neutralMediaRoot} from '../../cms/sectionStarterDefaults';
/** Only bundled default placeholders may be adapted. Uploaded/user-selected URLs are never touched. */
export function observeNeutralStarterMedia(root:HTMLElement){
 if(typeof ResizeObserver==='undefined'||typeof MutationObserver==='undefined')return ()=>{};
 let scheduled=false,disposed=false;
 const refresh=()=>{if(disposed||scheduled)return;scheduled=true;requestAnimationFrame(()=>{
  scheduled=false;if(disposed)return;
  const eligible=[...root.querySelectorAll<HTMLImageElement>('img')].filter(img=>{
   const src=img.getAttribute('src')??'';
   const owned=src.startsWith(neutralMediaRoot)||src===img.dataset.neutralGenerated;
   if(!owned&&img.dataset.neutralGenerated){
    for(const dimension of ['width','height']){const value=img.dataset['neutralOriginal'+(dimension==='width'?'Width':'Height')];if(value)img.setAttribute(dimension,value);else img.removeAttribute(dimension);}
    for(const key of ['neutralSource','neutralGenerated','neutralOriginalWidth','neutralOriginalHeight','sampleMediaSource','samplePlaceholder','sampleRatio','sampleInitialRatio'])delete img.dataset[key];
   }
   return owned;
  });
  for(const img of eligible){
   if(!img.dataset.neutralSource){img.dataset.neutralSource=img.getAttribute('src')??'';img.dataset.neutralOriginalWidth=img.getAttribute('width')??'';img.dataset.neutralOriginalHeight=img.getAttribute('height')??'';}
   img.dataset.sampleMediaSource=img.dataset.neutralSource;
   // Reuse the reviewed slot-aware SVG generator without replacing authored data or image geometry.
   const holder={querySelectorAll:(selector:string)=>selector==='img'?[img]:root.querySelectorAll(selector)} as unknown as Element;
   applyPlaceholderMedia(holder,'neutral');
   img.dataset.neutralGenerated=img.getAttribute('src')??'';
  }
 })};
 const resize=new ResizeObserver(refresh);resize.observe(root);
 const mutations=new MutationObserver(refresh);mutations.observe(root,{childList:true,subtree:true,attributes:true,attributeFilter:['src','class','style']});
 root.addEventListener('load',refresh,true);refresh();
 return ()=>{disposed=true;resize.disconnect();mutations.disconnect();root.removeEventListener('load',refresh,true)};
}
