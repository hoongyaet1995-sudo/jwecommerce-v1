/** Observe rendered preview failures without changing the editable document. */
export function observeAiPreviewQuality(root:HTMLElement|null,report:(messages:string[])=>void):()=>void {
 let previous='';
 const inspect=()=>{
  const surface=root?.querySelector<HTMLElement>('.cms-render-surface');if(!surface)return;
  const messages:string[]=[];
  if(surface.scrollWidth>surface.clientWidth+8)messages.push('Preview content extends beyond its available width. Review mobile and desktop layout.');
  let overflow=0;
  for(const e of surface.querySelectorAll<HTMLElement>('[data-cms-text]')){
   const css=getComputedStyle(e);if(css.textOverflow==='ellipsis'||Number.parseInt(css.webkitLineClamp)>0)continue;
   if(e.clientWidth>0&&e.scrollWidth>e.clientWidth+4)overflow++;
  }
  if(overflow)messages.push(`${overflow} text region(s) may overflow. Inspect the page preview.`);
  const broken=[...surface.querySelectorAll<HTMLImageElement>('img')].filter(i=>i.getAttribute('src')&&i.complete&&i.naturalWidth===0).length;
  if(broken)messages.push(`${broken} image(s) failed to load in this preview.`);
  const next=JSON.stringify(messages);if(next!==previous){previous=next;report(messages);}
 };
 const timer=setTimeout(inspect,400);root?.addEventListener('load',inspect,true);root?.addEventListener('error',inspect,true);window.addEventListener('resize',inspect);
 return()=>{clearTimeout(timer);root?.removeEventListener('load',inspect,true);root?.removeEventListener('error',inspect,true);window.removeEventListener('resize',inspect);};
}
