(()=>{const header=document.querySelector('[data-shell-header]');if(!header)return;const main=header.querySelector('[data-header-main]');const measure=()=>document.documentElement.style.setProperty('--cms-header-height',header.getBoundingClientRect().height+'px');const update=()=>{header.dataset.headerSolid=String(window.innerWidth<1024||window.scrollY>16||!!header.querySelector('details[open]')||header.querySelector('[aria-expanded=true]')!==null);const logo=header.querySelector('[data-shell-brand-logo]');if(logo){const source=header.dataset.headerSolid==='false'&&header.dataset.headerMode!=='normal'?logo.dataset.overlayLogo:logo.dataset.solidLogo;if(source&&logo.getAttribute('src')!==source)logo.setAttribute('src',source)}};for(const kind of ['menu','search']){const button=header.querySelector(`[data-shell-${kind}-toggle]`),panel=header.querySelector(`[data-shell-${kind}-panel]`);if(button&&panel)button.addEventListener('click',()=>{panel.hidden=!panel.hidden;button.setAttribute('aria-expanded',String(!panel.hidden));if(!panel.hidden)panel.querySelector('input')?.focus();update()})}new ResizeObserver(measure).observe(header);new MutationObserver(update).observe(header,{subtree:true,attributes:true,attributeFilter:['open','aria-expanded']});window.addEventListener('scroll',update,{passive:true});window.addEventListener('resize',update,{passive:true});document.addEventListener('keydown',e=>{if(e.key==='Escape'){header.querySelectorAll('details[open]').forEach(d=>d.open=false);header.querySelectorAll('[data-shell-menu-panel],[data-shell-search-panel]').forEach(p=>p.hidden=true);header.querySelectorAll('[aria-expanded]').forEach(b=>b.setAttribute('aria-expanded','false'));update()}});measure();update()})();
(()=>{
 const header=document.querySelector('[data-shell-header]');if(!header)return;
 const menus=[...header.querySelectorAll('.jw-shell-desktop .jw-shell-dropdown')];
 // A compact header changes document height. Separate thresholds prevent
 // browser scroll anchoring from repeatedly crossing one shared boundary.
 const scrollState=()=>{
  const compact=header.dataset.headerScrolled==='true';
  const next=compact?window.scrollY>8:window.scrollY>64;
  if(header.dataset.headerScrolled!==String(next))header.dataset.headerScrolled=String(next);
 };
 const place=()=>menus.filter(d=>d.open).forEach(d=>{const p=d.querySelector('.jw-shell-submenu');p.style.translate='';const r=p.getBoundingClientRect();const shift=r.left<16?16-r.left:r.right>window.innerWidth-16?window.innerWidth-16-r.right:0;if(shift)p.style.translate=shift+'px 0'});
 menus.forEach(d=>d.addEventListener('toggle',()=>{if(d.open){menus.forEach(other=>{if(other!==d)other.open=false});place()}}));
 document.addEventListener('click',e=>{if(!e.target.closest('.jw-shell-dropdown'))menus.forEach(d=>d.open=false)});
 // relatedTarget is the intended destination; activeElement can briefly be body
 // while a mouse press transfers focus from summary to a submenu link.
 header.addEventListener('focusout',event=>{const next=event.relatedTarget;menus.forEach(d=>{if(!(next instanceof Node)||!d.contains(next))d.open=false})});
 window.addEventListener('scroll',scrollState,{passive:true});window.addEventListener('resize',place,{passive:true});scrollState();
})();
