import React from 'react';
type Horizontal='left'|'center'|'right';
type Vertical='top'|'middle'|'bottom';
export function ThemeImageFocus({image,mobileImage,focus='center',focusY='middle',onChange}:{image?:string;mobileImage?:string;focus?:Horizontal;focusY?:Vertical;onChange:(focus:Horizontal,focusY:Vertical)=>void}){
 const position=`${focus} ${focusY==='middle'?'center':focusY}`;
 return <fieldset className="space-y-3"><legend className="text-xs font-semibold">Image focus</legend><p className="text-xs text-neutral-500">Choose which part stays visible when the image is cropped.</p>
 <div className="flex flex-wrap gap-4 items-start"><div className="relative w-48 h-36 rounded-lg overflow-hidden bg-neutral-200 dark:bg-neutral-800 border border-neutral-300 dark:border-neutral-700">
 {image&&<img src={image} alt="" className="absolute inset-0 w-full h-full object-cover opacity-60"/>}
 <div role="group" aria-label="Image focal position" className="absolute inset-0 grid grid-cols-3 grid-rows-3">{(['top','middle','bottom'] as const).flatMap(y=>(['left','center','right'] as const).map(x=><button key={x+y} type="button" aria-label={`Focus ${y} ${x}`} aria-pressed={focus===x&&focusY===y} onClick={()=>onChange(x,y)} className="flex items-center justify-center border border-white/25 focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-500"><span className={`w-4 h-4 rounded-full border-2 border-white shadow ${focus===x&&focusY===y?'bg-indigo-600 ring-4 ring-indigo-400/60':'bg-black/30'}`}/></button>))}</div></div>
 <div className="flex-1 min-w-[200px] space-y-2"><span className="block text-xs capitalize">{focusY} · {focus}</span><div className="rounded-lg overflow-hidden bg-neutral-200 dark:bg-neutral-800 h-20 border border-neutral-300 dark:border-neutral-700">{image&&<img src={image} alt="Desktop crop preview" className="w-full h-full object-cover" style={{objectPosition:position}}/>}</div><span className="text-[11px] text-neutral-500">Desktop crop</span><div className="w-28 h-20 rounded-lg overflow-hidden bg-neutral-200 dark:bg-neutral-800 border border-neutral-300 dark:border-neutral-700">{(mobileImage||image)&&<img src={mobileImage||image} alt="Mobile crop preview" className="w-full h-full object-cover" style={{objectPosition:position}}/>}</div><span className="text-[11px] text-neutral-500">Mobile crop</span></div></div>
 </fieldset>;
}
