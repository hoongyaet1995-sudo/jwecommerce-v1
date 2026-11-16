type RGBA=[number,number,number,number];
let colorContext:OffscreenCanvasRenderingContext2D|null=null;
function rgba(value:string):RGBA{
 colorContext??=new OffscreenCanvas(1,1).getContext('2d',{willReadFrequently:true});
 if(colorContext){colorContext.clearRect(0,0,1,1);colorContext.fillStyle=value;colorContext.fillRect(0,0,1,1);const c=colorContext.getImageData(0,0,1,1).data;return [c[0],c[1],c[2],c[3]/255];}
 const values=value.match(/[\d.]+/g)?.map(Number)??[];
 if(value.startsWith('color(srgb'))return [values[0]*255,values[1]*255,values[2]*255,values[3]??1];
 if(value==='transparent')return [0,0,0,0];
 return [values[0]??0,values[1]??0,values[2]??0,values[3]??1];
}
const over=(top:RGBA,bottom:RGBA):RGBA=>[0,1,2].map(i=>top[i]*top[3]+bottom[i]*(1-top[3])).concat(1) as RGBA;
const luminance=(c:RGBA)=>c.slice(0,3).map(x=>x/255).map(x=>x<=.04045?x/12.92:((x+.055)/1.055)**2.4).reduce((s,x,i)=>s+x*[.2126,.7152,.0722][i],0);
const ratio=(a:RGBA,b:RGBA)=>{const [hi,lo]=[luminance(a),luminance(b)].sort((x,y)=>y-x);return (hi+.05)/(lo+.05)};
const hex=(v:RGBA)=>'#'+v.slice(0,3).map(n=>Math.round(n).toString(16).padStart(2,'0')).join('');
const roles:Record<string,string>={eyebrow:'Eyebrow',title:'Title',lead:'Description',content:'Description',itemTitle:'Item Title',itemContent:'Item Description'};
/** Read-only diagnostics. Photo/gradient backdrops require visual review, never a fabricated pass. */
export function auditSectionReadability(root:HTMLElement){
 const checks:Array<Record<string,unknown>>=[],seen=new Set<Text>();
 const owners=[...root.querySelectorAll<HTMLElement>('[data-cms-text],[data-cms-button]')];
 for(const owner of owners){
  const channel=owner.dataset.cmsButton;const role=channel?channel==='secondary'?'CTA 2':'CTA 1':roles[owner.dataset.cmsText??''];if(!role)continue;
  const walk=document.createTreeWalker(owner,NodeFilter.SHOW_TEXT);let node:Text|null;
  while((node=walk.nextNode() as Text|null)){
   if(seen.has(node)||!node.textContent?.trim())continue;seen.add(node);
   const element=node.parentElement!;const style=getComputedStyle(element),rect=element.getBoundingClientRect();
   if(!rect.width||!rect.height||style.visibility==='hidden'||style.display==='none')continue;
   const chain:HTMLElement[]=[];for(let p:HTMLElement|null=element;p;p=p.parentElement)chain.unshift(p);
   let opacity=1,bg:RGBA=[255,255,255,1],opaque:HTMLElement|null=null;const uncertain=new Set<string>();
   for(const p of chain){const s=getComputedStyle(p),paint=rgba(s.backgroundColor);opacity*=Number(s.opacity);if(paint[3]===1){uncertain.clear();opaque=p;}bg=over(paint,bg);if(s.backgroundImage!=='none')uncertain.add('background image / gradient');}
   if(opacity<.05)continue;
   const fg=rgba(style.color);fg[3]*=opacity;const ink=over(fg,bg);const contrast=ratio(ink,bg);const large=parseFloat(style.fontSize)>=24||parseFloat(style.fontSize)>=18.66&&Number(style.fontWeight)>=700;
   // A positioned image may sit behind its caption even though it is not a CSS background.
   for(const p of chain.slice(-5)){if(opaque&&p!==opaque&&p.contains(opaque))continue;for(const img of p.querySelectorAll<HTMLImageElement>('img')){const r=img.getBoundingClientRect();if(r.left<=rect.left&&r.right>=rect.right&&r.top<=rect.top&&r.bottom>=rect.bottom)uncertain.add('image under text');}}
   const disabled=!!element.closest(':disabled,[aria-disabled="true"]');
   checks.push({role,text:node.textContent.trim().slice(0,90),tag:element.tagName.toLowerCase(),className:element.className,context:chain.slice(-5).map(e=>e.tagName.toLowerCase()+'.'+e.className).join(' > '),color:hex(ink),background:hex(bg),ratio:Number(contrast.toFixed(2)),minimum:large?3:4.5,uncertain:[...uncertain],disabled,failed:!disabled&&contrast<(large?3:4.5),severe:contrast<1.5});
  }
 }
 const counts:Record<string,number>={};for(const c of checks)counts[String(c.role)]=(counts[String(c.role)]??0)+1;
 return {counts,total:checks.length,failures:checks.filter(c=>c.failed&&!((c.uncertain as string[]).length)),review:checks.filter(c=>(c.uncertain as string[]).length)};
}
