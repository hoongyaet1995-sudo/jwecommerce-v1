(()=>{
 const header=document.querySelector('[data-mega-nav]');
 if(!header||header.dataset.headerMode==='normal')return;
 const main=header.querySelector('[data-header-main]');
 if(!main)return;
 const logo=header.querySelector('[data-header-logo]');
 const normalLogo=logo?.getAttribute('src');
 // The Blade shell can also run without Alpine on React CMS routes.
 if(!window.Alpine){
  const bindPanel=(label,expression)=>{
   const button=header.querySelector(`[aria-label="${label}"]`);
   const panel=[...header.querySelectorAll('[x-show]')].find(node=>node.getAttribute('x-show')===expression);
   if(!button||!panel)return;
   let open=false;
   const set=value=>{open=value;panel.removeAttribute('x-cloak');panel.style.display=open?'':'none';button.setAttribute('aria-expanded',String(open));if(open)panel.querySelector('input')?.focus();};
   set(false);button.addEventListener('click',()=>set(!open));
   panel.querySelectorAll('button').forEach(close=>{if(expression==='searchOpen')close.addEventListener('click',()=>set(false));});
   document.addEventListener('keydown',event=>{if(event.key==='Escape')set(false);});
   document.addEventListener('click',event=>{if(open&&!panel.contains(event.target)&&!button.contains(event.target))set(false);});
  };
  bindPanel('Search Catalog','searchOpen');bindPanel('User Account Menu','userDropdownOpen');bindPanel('Toggle Mobile Navigation Drawer','mobileMenuOpen');
  header.querySelectorAll('[data-mobile-menu-toggle]').forEach(button=>{const panel=header.querySelector(`[data-mobile-menu-panel="${CSS.escape(button.dataset.mobileMenuToggle)}"]`);if(!panel)return;panel.removeAttribute('x-cloak');panel.style.display='none';button.setAttribute('aria-expanded','false');button.addEventListener('click',()=>{const open=button.getAttribute('aria-expanded')!=='true';panel.style.display=open?'':'none';button.setAttribute('aria-expanded',String(open));});});
  header.querySelectorAll('[data-mobile-menu-url]').forEach(button=>{if(button.dataset.mobileMenuUrl)button.addEventListener('click',()=>window.location.assign(button.dataset.mobileMenuUrl));});
  const menus=[...header.querySelectorAll('[data-header-menu]')];
  const closeMenus=()=>menus.forEach(item=>{const panel=header.querySelector(`[data-header-menu-panel="${CSS.escape(item.dataset.headerMenu)}"]`);if(panel){panel.style.display='none';item.querySelector('a').setAttribute('aria-expanded','false');}});
  menus.forEach(item=>{const panel=header.querySelector(`[data-header-menu-panel="${CSS.escape(item.dataset.headerMenu)}"]`);if(!panel)return;const button=item.querySelector('a');panel.removeAttribute('x-cloak');panel.style.display='none';button.setAttribute('aria-expanded','false');const open=()=>{closeMenus();panel.style.display='';button.setAttribute('aria-expanded','true');};item.addEventListener('mouseenter',open);item.addEventListener('focusin',open);button.addEventListener('click',event=>{event.preventDefault();open();});});
  header.addEventListener('mouseleave',closeMenus);header.addEventListener('focusout',event=>{if(!header.contains(event.relatedTarget))closeMenus();});document.addEventListener('keydown',event=>{if(event.key==='Escape')closeMenus();});
 }
 const measure=()=>document.documentElement.style.setProperty('--cms-header-height',main.getBoundingClientRect().height+'px');
 const update=()=>{
  const open=header.querySelector('[aria-expanded="true"]')!==null;
  header.dataset.headerSolid=window.scrollY>20||open?'true':'false';
  if(logo&&normalLogo){const overlay=window.innerWidth>=1024&&header.dataset.headerSolid==='false';const source=overlay&&logo.dataset.overlayLogo?logo.dataset.overlayLogo:normalLogo;if(logo.getAttribute('src')!==source)logo.setAttribute('src',source);}
 };
 new ResizeObserver(measure).observe(main);measure();update();
 window.addEventListener('scroll',update,{passive:true});
 window.addEventListener('resize',update,{passive:true});
 new MutationObserver(update).observe(header,{subtree:true,attributes:true,attributeFilter:['aria-expanded','aria-hidden']});
})();
