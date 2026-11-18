import React from 'react';
import type {PageSection} from '../../types';
import type {TextRole} from '../../cms/inspectorCapabilities';
import {getTextColorBinding,setTextColorBinding,resetTextColorBinding,requiresTextColorItem,type TextColorSource} from '../../cms/textColorBindings';
export function TextColorSourceControl({section,role,itemId,color,onChange,compact=false,showLabel=false}:{section:PageSection;role:TextRole;itemId?:string;color:string;onChange:(s:PageSection)=>void;compact?:boolean;showLabel?:boolean}){
 const selectId=React.useId();
 const binding=getTextColorBinding(section,role,itemId),needsItem=requiresTextColorItem(section,role)&&!itemId;
 const source=binding?.source??(section.typography?.[role]?.color?'custom':'auto');
 const scope=itemId?'This item':['itemTitle','itemContent','label','number'].includes(role)?'All matching text':'This section';
 const itemTitle=itemId?section.items?.find(i=>i.id===itemId)?.title:undefined;
 if(needsItem)return compact?null:<p className="text-xs text-neutral-500">Select item text on the canvas to change its color. Existing shared colors are preserved.</p>;
 return <div className={compact?'inline-flex items-center gap-1':'space-y-2'}>
  {showLabel&&<label htmlFor={selectId} className="block">Color source</label>}
  {!compact&&<p className="text-xs text-neutral-500">Color applies to: {scope}{itemTitle?` · ${itemTitle}`:''}</p>}
  <select id={selectId} title={'Color source · '+scope} aria-label={compact?'Toolbar color source':'Text color source'} value={source} onChange={e=>{const source=e.target.value as TextColorSource;onChange(setTextColorBinding(section,role,itemId,source==='custom'?{source,color:binding?.color??color}:{source}))}} className={compact?'bubble-color-source':'w-full rounded-lg border border-neutral-300 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800 px-2 py-2 text-sm'}>
   <option value="auto">Auto</option><option value="dark">Dark text</option><option value="light">Light text</option><option value="custom">Custom</option>
  </select>
  {source==='custom'&&<input title={'Custom text color · '+scope} aria-label={compact?'Toolbar text color':'Text color picker'} type="color" value={binding?.color??color} onChange={e=>onChange(setTextColorBinding(section,role,itemId,{source:'custom',color:e.target.value}))}/>}
  {source==='custom'&&!compact&&<input key={binding?.color??color} aria-label="Text color hex" defaultValue={binding?.color??color} className="w-full rounded-lg border border-neutral-300 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800 px-2 py-2 text-sm" onBlur={e=>{if(/^#[a-fA-F0-9]{6}$/.test(e.target.value))onChange(setTextColorBinding(section,role,itemId,{source:'custom',color:e.target.value}));else e.target.value=binding?.color??color}} onKeyDown={e=>{if(e.key==='Enter')e.currentTarget.blur()}}/>}
  {binding&&<button type="button" className={compact?'':'text-xs underline'} title={itemId?'Reset item color':'Reset role color source'} onClick={()=>onChange(resetTextColorBinding(section,role,itemId))}>{itemId?'Reset item color':'Reset color source'}</button>}

 </div>;
}
