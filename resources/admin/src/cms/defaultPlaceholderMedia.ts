/** Shared owned-placeholder measurements. Never writes authored PageSection media or geometry. */
export type MediaBox={x:number;y:number;width:number;height:number};
export type SampleMediaSlot={key:string;kind:'photo'|'logo'|'avatar';width:number;height:number;ratio:number;fit:string;position:string;overlays:MediaBox[];safeSide:'left'|'right'|'center';state?:string;source:string};
export type PlaceholderStyle='neutral'|'gradient'|'blueprint';
export function ratioName(ratio:number):string{
 const ratios:[[string,number],...[string,number][]]=[['1:1',1],['4:3',4/3],['3:2',1.5],['16:9',16/9],['21:9',21/9],['3:4',.75],['2:3',2/3],['9:16',9/16]];
 const closest=ratios.reduce((a,b)=>Math.abs(Math.log(ratio/a[1]))<Math.abs(Math.log(ratio/b[1]))?a:b);
 return Math.abs(ratio/closest[1]-1)<.08?closest[0]:ratio.toFixed(2)+':1';
}
export function placeholderForSlot(slot:SampleMediaSlot,style:PlaceholderStyle):string{
 const width=1600,height=Math.max(320,Math.round(width/slot.ratio));
 const dark=style==='blueprint',ink=dark?'#c0d3e5':'#58647a',muted=dark?'#799bb7':'#8993a3';
 const x=slot.safeSide==='left'?width*.26:slot.safeSide==='right'?width*.74:width*.5;
 const y=height*.48,size=Math.min(width*.125,height*.19),small=slot.kind==='logo'||slot.kind==='avatar';
 const label=slot.kind==='logo'?'YOUR LOGO':slot.kind==='avatar'?'YOUR PORTRAIT':'YOUR IMAGE HERE';
 const textSize=small?60:Math.min(62,height*.067),hintSize=Math.min(34,height*.04);
 const background=style==='gradient'?'<linearGradient id="bg" x2="1" y2="1"><stop stop-color="#eef1fa"/><stop offset=".5" stop-color="#e4e1ef"/><stop offset="1" stop-color="#dce8e9"/></linearGradient>':style==='blueprint'?'<pattern id="grid" width="80" height="80" patternUnits="userSpaceOnUse"><path d="M80 0H0V80" fill="none" stroke="#7f9bb5" stroke-opacity=".13" stroke-width="2"/></pattern>':'';
 const fill=style==='gradient'?'url(#bg)':dark?'#263b50':'#edf0f3';
 const icon=slot.kind==='avatar'?`<circle cx="${x}" cy="${y-size*.3}" r="${size*.45}"/><path d="M${x-size*.85} ${y+size*.8}q0-${size} ${size*.85}-${size}t${size*.85} ${size}"/>`:slot.kind==='logo'?`<path d="M${x-size*.7} ${y-size*.65}h${size*1.4}v${size*1.3}h-${size*1.4}zM${x-size*.6} ${y+size*.45}l${size*.5}-${size*.6} ${size*.3} ${size*.3} ${size*.35}-${size*.4}"/>`:`<rect x="${x-size}" y="${y-size*.8}" width="${size*2}" height="${size*1.6}" rx="${size*.16}"/><circle cx="${x+size*.38}" cy="${y-size*.29}" r="${size*.17}"/><path d="M${x-size*.88} ${y+size*.58}l${size*.65}-${size*.72} ${size*.38} ${size*.39} ${size*.4}-${size*.52} ${size*.34} ${size*.43}"/>`;
 const svg=`<svg xmlns="http://www.w3.org/2000/svg" width="${width}" height="${height}" viewBox="0 0 ${width} ${height}"><defs>${background}</defs><rect width="100%" height="100%" fill="${fill}"/>${dark?'<rect width="100%" height="100%" fill="url(#grid)"/>':''}<rect x="32" y="32" width="${width-64}" height="${height-64}" rx="24" fill="none" stroke="${muted}" stroke-opacity=".23" stroke-width="2" stroke-dasharray="12 12"/><g stroke="${ink}" stroke-width="7" fill="none" opacity=".65">${icon}</g><text x="${x}" y="${y+size+textSize*1.1}" text-anchor="middle" fill="${ink}" font-family="Arial,sans-serif" font-size="${textSize}" letter-spacing="3">${label}</text>${!small?`<text x="${x}" y="${y+size+textSize*1.1+hintSize*1.8}" text-anchor="middle" fill="${muted}" font-family="Arial,sans-serif" font-size="${hintSize}">Upload or replace image</text>`:''}</svg>`;
 return 'data:image/svg+xml;charset=utf-8,'+encodeURIComponent(svg);
}
export function measuredMedia(root:Element):Array<{element:HTMLImageElement;slot:SampleMediaSlot}>{
 const text=[...root.querySelectorAll<HTMLElement>('[data-cms-text],h1,h2,h3,p,figcaption,a')].filter(n=>n.textContent?.trim()&&n.getClientRects().length);
 return [...root.querySelectorAll<HTMLImageElement>('img')].flatMap((img,index)=>{
  const source=img.dataset.sampleMediaSource||img.getAttribute('src')||'';
  if(/\/icons\/|data:image\/gif/.test(source)||(source.startsWith('data:image/svg')&&!img.dataset.samplePlaceholder))return [];
  const rect=img.getBoundingClientRect();if(rect.width<2||rect.height<2)return [];
  const ancestry=img.closest('[data-image-slot]'),style=getComputedStyle(img);
  const key=ancestry?.getAttribute('data-image-slot')||`rendered-image-${index}`;
  const classes=String(img.className)+' '+String(img.parentElement?.className)+' '+String(img.parentElement?.parentElement?.className);
  const kind=/authorAvatar|avatar|reviewer/i.test(key+' '+classes)?'avatar':/logo|payment|gateway|courier-logo/i.test(classes)||source.endsWith('/logo.svg')?'logo':'photo';
  const overlays:MediaBox[]=[];
  if(kind==='photo')for(const n of text){
   if(n.contains(img)||img.contains(n))continue;
   const r=n.getBoundingClientRect(),x=Math.max(rect.left,r.left),y=Math.max(rect.top,r.top),right=Math.min(rect.right,r.right),bottom=Math.min(rect.bottom,r.bottom);
   if(right-x<4||bottom-y<4)continue;
   overlays.push({x:(x-rect.left)/rect.width,y:(y-rect.top)/rect.height,width:(right-x)/rect.width,height:(bottom-y)/rect.height});
  }
  const left=overlays.reduce((sum,b)=>sum+(b.x+b.width/2<.5?b.width*b.height:0),0),right=overlays.reduce((sum,b)=>sum+(b.x+b.width/2>=.5?b.width*b.height:0),0);
  const safeSide=right-left>.025?'left':left-right>.025?'right':'center';
  return [{element:img,slot:{key,kind,width:Math.round(rect.width),height:Math.round(rect.height),ratio:Number((rect.width/rect.height).toFixed(4)),fit:style.objectFit,position:style.objectPosition,overlays,safeSide,source}}];
 });
}
export function applyPlaceholderMedia(root:Element,style:PlaceholderStyle){
 for(const {element:img,slot}of measuredMedia(root)){
  if(!img.dataset.samplePlaceholder){img.dataset.sampleMediaSource=slot.source;img.width=slot.width;img.height=slot.height;img.dataset.sampleRatio=ratioName(slot.ratio);img.dataset.sampleInitialRatio=String(slot.ratio);img.dataset.samplePlaceholder='1';}
  const src=placeholderForSlot(slot,style);
  if(img.getAttribute('src')!==src){img.src=src;if(!img.alt||/^(Demo image|Lorem ipsum demo image)/.test(img.alt))img.alt=slot.kind==='logo'?'Your logo — upload or replace':slot.kind==='avatar'?'Your portrait — upload or replace':'Your image here — upload or replace';}
 }
}
