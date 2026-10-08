import React from 'react';
import type {PageSection} from '../../types';
const inputClass='w-full px-3 py-2 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-neutral-900 dark:text-neutral-100 text-sm';
export function ValuesListEditor({section,onChange}:{section:PageSection;onChange:(patch:Partial<PageSection>)=>void}) {
  const items=section.items??[];
  const move=(index:number,direction:number)=>{const next=[...items];[next[index],next[index+direction]]=[next[index+direction],next[index]];onChange({items:next});};
  return <div className="space-y-3 pb-4 border-b border-neutral-100 dark:border-neutral-800">
    <div className="flex items-center justify-between"><label className="text-[11px] font-bold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Values ({items.length})</label><button type="button" onClick={()=>onChange({items:[...items,{id:crypto.randomUUID(),title:'New value',description:''}]})} className="px-2.5 py-1 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-[11px] font-bold">Add Value</button></div>
    {items.map((item,index)=><div data-inspector-item={item.id} key={item.id} className="p-3 rounded-xl border border-neutral-200 dark:border-neutral-800 bg-neutral-50/50 dark:bg-neutral-950/50 space-y-2.5">
      <div className="flex flex-wrap items-center gap-2"><span className="text-xs font-bold text-indigo-600 dark:text-indigo-400 mr-auto">Value {String(index+1).padStart(2,'0')}</span>
        <button type="button" aria-label={`Move value ${index+1} up`} disabled={index===0} onClick={()=>move(index,-1)} className="text-xs text-neutral-500 disabled:opacity-30">Up</button>
        <button type="button" aria-label={`Move value ${index+1} down`} disabled={index===items.length-1} onClick={()=>move(index,1)} className="text-xs text-neutral-500 disabled:opacity-30">Down</button>
        <button type="button" aria-label={`Delete value ${index+1}`} onClick={()=>onChange({items:items.filter((_,position)=>position!==index)})} className="text-xs text-rose-500">Delete</button>
      </div>
      <label className="block text-xs text-neutral-500">Value title<input aria-label={`Value ${index+1} title`} className={`${inputClass} mt-1`} value={item.title} onChange={event=>onChange({items:items.map((entry,position)=>position===index?{...entry,title:event.target.value}:entry)})}/></label>
      <label className="block text-xs text-neutral-500">Value description<textarea aria-label={`Value ${index+1} description`} className={`${inputClass} mt-1`} rows={3} value={item.description??''} onChange={event=>onChange({items:items.map((entry,position)=>position===index?{...entry,description:event.target.value}:entry)})}/></label>
    </div>)}
  </div>;
}
