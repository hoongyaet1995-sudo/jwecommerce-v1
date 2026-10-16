(() => {
 'use strict';
 const root=document.querySelector('[data-wedding-listing]');if(!root)return;
 const rows=root.querySelector('[data-wedding-rows]'),pager=root.querySelector('[data-wedding-pagination]');if(!rows||!pager)return;
 const button=pager.querySelector('[data-wedding-load-more]'),status=pager.querySelector('[data-wedding-load-status]'),sentinel=pager.querySelector('[data-wedding-sentinel]');
 let next=pager.dataset.nextUrl,busy=false,failed=false;const seen=new Set(),ids=new Set([...rows.querySelectorAll('[data-product-id]')].map(n=>n.dataset.productId));
 if(!next||!button)return;button.hidden=false;const links=pager.querySelector('[data-wedding-pagination-links]');if(links)links.hidden=true;
 const announce=(message)=>{if(status)status.textContent=message;};
 async function load(){
  if(busy||!next||seen.has(next))return;busy=true;failed=false;button.disabled=true;button.textContent='Loading…';announce('Loading more collections…');const url=next;
  try{
   const response=await fetch(url,{headers:{'X-Requested-With':'XMLHttpRequest','Accept':'text/html'},credentials:'same-origin'});if(!response.ok)throw new Error('response');
   const doc=new DOMParser().parseFromString(await response.text(),'text/html'),incoming=doc.querySelector('[data-wedding-rows]'),incomingPager=doc.querySelector('[data-wedding-pagination]');if(!incoming||!incomingPager)throw new Error('markup');
   for(const row of incoming.querySelectorAll('[data-product-id]')){if(!ids.has(row.dataset.productId)){ids.add(row.dataset.productId);rows.append(document.importNode(row,true));}}
   seen.add(url);next=incomingPager.dataset.nextUrl||'';pager.dataset.nextUrl=next;
   announce(next?`${ids.size} of ${pager.dataset.total} collections shown.`:`You’ve viewed all ${pager.dataset.total} collections.`);button.hidden=!next;button.textContent='Load more';
   document.dispatchEvent(new CustomEvent('wedding:collections-appended',{detail:{container:rows}}));
  }catch(error){failed=true;announce('More collections could not be loaded. Please try again.');button.textContent='Retry loading';button.hidden=false;if(links)links.hidden=false;}
  finally{busy=false;button.disabled=false;if(!failed&&next&&'IntersectionObserver'in window){requestAnimationFrame(()=>{if(sentinel.getBoundingClientRect().top<=window.innerHeight+300)load();});}}
 }
 button.addEventListener('click',load);
 if('IntersectionObserver'in window){const observer=new IntersectionObserver(entries=>{if(entries.some(e=>e.isIntersecting)&&!failed)load();},{rootMargin:'300px 0px'});observer.observe(sentinel);}
})();
