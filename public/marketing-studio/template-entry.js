(() => {
  'use strict';
  const form=document.getElementById('ws-source-form');
  if(!form)return;
  const type=document.getElementById('ws-video-type'),source=document.getElementById('ws-source-kind'),button=form.querySelector('[type="submit"]');
  const eligible=()=>type?.value==='product_showcase'&&source?.value==='product';
  function update(){if(button)button.innerHTML=(eligible()?'下一步：选择模板':'下一步：制作偏好')+' <i class="bi bi-arrow-right"></i>';}
  function enter(e){if(!eligible())return;e.preventDefault();e.stopImmediatePropagation();const id=document.getElementById('ws-product')?.value;location.assign('/admin/marketing/templates'+(id?'?product_id='+encodeURIComponent(id):''));}
  form.addEventListener('submit',enter,true);
  button?.addEventListener('click',enter,true);
  form.addEventListener('click',()=>queueMicrotask(update));
  const search=document.getElementById('ws-product-search'),select=document.getElementById('ws-product'),hint=document.getElementById('ws-product-search-status');let timer,sequence=0;
  search?.addEventListener('keydown',e=>{if(e.key==='Enter')e.preventDefault();});
  search?.addEventListener('input',()=>{clearTimeout(timer);const current=++sequence;timer=setTimeout(async()=>{hint.textContent='正在搜索商品…';try{const response=await fetch('/admin/marketing/templates/products?limit=20&q='+encodeURIComponent(search.value),{credentials:'same-origin',headers:{Accept:'application/json'}});if(!response.ok)throw Error('search');const data=await response.json();if(current!==sequence)return;const selected=select.selectedOptions[0],id=select.value;select.replaceChildren(new Option('选择目录中的商品／服务',''));if(id&&!data.data.some(p=>String(p.id)===id))select.add(new Option(selected.text,id,true,true));for(const p of data.data)select.add(new Option(p.name,p.id,false,String(p.id)===id));hint.textContent=data.data.length?(data.meta.has_more?'显示前20件，请细化关键词继续查找':'已找到 '+data.data.length+' 件商品'):'没有相符商品，请调整关键词';}catch{if(current===sequence)hint.textContent='商品搜索暂不可用，请稍后重试。';}},250);});
  update();
})();
