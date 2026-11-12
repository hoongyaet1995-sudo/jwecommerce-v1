/* A compact mobile launcher yields while it would obscure another action. */
(()=>{
 function init(){
  const launcher=document.querySelector('#sf-support-launcher'),root=document.querySelector('#storefront-support-widget');
  if(!launcher||!root)return;
  let queued=false;
  function update(){
   queued=false;
   if(!matchMedia('(max-width:600px)').matches||launcher.hidden||document.activeElement===launcher){launcher.classList.toggle('yields-to-action',false);return;}
   const box=launcher.getBoundingClientRect();
   const blocked=Array.from(document.querySelectorAll('a[href],button,input,select,textarea,summary,[role="button"]')).some(el=>{
    if(root.contains(el))return false;
    const r=el.getBoundingClientRect();
    if(!r.width||!r.height||r.bottom<box.top-8||r.top>box.bottom+8||r.right<box.left-8||r.left>box.right+8)return false;
    const css=getComputedStyle(el);return css.visibility!=='hidden'&&css.display!=='none';
   });
   launcher.classList.toggle('yields-to-action',blocked);
  }
  function schedule(){if(queued)return;queued=true;requestAnimationFrame(update);}
  document.addEventListener('scroll',schedule,{capture:true,passive:true});
  window.addEventListener('resize',schedule,{passive:true});
  window.visualViewport?.addEventListener('resize',schedule,{passive:true});
  new MutationObserver(schedule).observe(document.body,{childList:true,subtree:true});
  document.addEventListener('load',schedule,true);
  schedule();
 }
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init,{once:true});else init();
})();
