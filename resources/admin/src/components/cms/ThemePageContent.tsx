import React from 'react';
export type PageMessage={text?:string;hidden?:boolean};
export type PageContent=Record<string,Record<string,PageMessage>>;
export type ContentContracts=Record<string,Record<string,Record<string,string>>>;

export function ThemePageContent({surface,fields,value={},onChange}:{surface:string;fields:Record<string,string>;value?:Record<string,PageMessage>;onChange:(value:Record<string,PageMessage>)=>void}){
 const keys=Object.keys(fields),[selected,setSelected]=React.useState(keys[0]);
 const active=keys.includes(selected)?selected:keys[0];
 if(!active)return null;
 const message=value[active]??{};
 return <details className="rounded-lg border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 p-4">
  <summary className="text-xs font-semibold cursor-pointer">Page Content</summary>
  <p className="text-xs text-neutral-500 mt-3">Select a numbered message below, then edit its words. The theme keeps its position.</p>
  <div role="group" aria-label="Message positions" className="relative mt-3 border border-neutral-300 dark:border-neutral-700 bg-neutral-100 dark:bg-neutral-950 rounded-lg h-36 overflow-hidden">
   <div className="absolute left-3 top-3 text-[10px] text-neutral-500">{surface==='checkoutSuccess'?'Order received':surface==='cart'?'Your cart':surface==='checkout'?'Checkout':'Track your order'}</div>
   <div className="absolute left-3 top-14 w-1/2 h-16 border border-dashed border-neutral-300 dark:border-neutral-700 flex items-center justify-center text-[10px] text-neutral-500">{surface==='tracking'?'Order details':'Products'}</div>
   <div className="absolute right-3 top-14 w-1/4 h-16 border border-dashed border-neutral-300 dark:border-neutral-700 flex items-center justify-center text-[10px] text-neutral-500">Order summary</div>
   {keys.map((key,i)=><button key={key} type="button" aria-label={fields[key]} aria-pressed={active===key} onClick={()=>setSelected(key)} className={'absolute text-xs px-2 py-1 rounded border '+(active===key?'bg-indigo-600 text-white border-indigo-600':'bg-white dark:bg-neutral-800 border-neutral-400')} style={surface==='checkoutSuccess'?{left:i===0?'4%':i===1?'75%':'84%',top:i===2?'75%':'10%'}:{right:i===0?'25%':'3%',top:'10%'}}>{i+1}</button>)}
  </div>
  <label className="block text-xs font-semibold mt-4">{keys.indexOf(active)+1}. {fields[active]}<textarea aria-label={fields[active]} maxLength={160} rows={3} value={message.text??''} placeholder="Enter your message" onChange={e=>onChange({...value,[active]:{...message,text:e.target.value}})} className="mt-2 block w-full border rounded-lg p-2 bg-white dark:bg-neutral-950 border-neutral-300 dark:border-neutral-700"/></label>
  <label className="flex gap-2 text-xs mt-3"><input type="checkbox" checked={!message.hidden} onChange={e=>onChange({...value,[active]:{...message,hidden:!e.target.checked}})}/>Show this message</label>
  <button type="button" className="text-xs underline mt-3" onClick={()=>{const next={...value};delete next[active];onChange(next)}}>Clear this message</button>
  <p className="text-[11px] text-neutral-500 mt-3">Blank messages stay hidden. Font and colors follow Brand &amp; Style. Words inside a photo are changed in Page Media.</p>
 </details>;
}
