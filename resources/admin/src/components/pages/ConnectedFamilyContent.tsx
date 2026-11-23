import React from 'react';
import {Plus} from 'lucide-react';
import type {PageSection} from '../../types';
import type {ConnectedItem,ConnectedSection} from '../../cms/connectedFamilies';
import {connectedPassport,connectedEntryFields} from '../../cms/connectedFamilies';
import {ContentDisclosure} from './ContentDisclosure';
import {InspectorDeleteButton} from './InspectorDeleteButton';
import {SectionTitleEditor} from './SectionTitleEditor';
const inputClass='w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-900';
type AssetTarget={id:string;url:string};
export function ConnectedFamilyContent({section,onChange,assetControl}:{section:PageSection;onChange:(section:PageSection)=>void;assetControl?:(target:AssetTarget)=>React.ReactNode}){
 const [selectedIllustration,setSelectedIllustration]=React.useState('');
 const s=section as ConnectedSection,meta=connectedPassport(s);if(!meta)return null;
 const patch=(change:Partial<ConnectedSection>)=>onChange({...s,...change});
 const items=s.items??[];
 const itemAlt=(id:string)=>s.imageSlots?.find(slot=>slot.target===`item:${id}:imageUrl`)?.mediaAlt??'';
 const editItem=(id:string,key:string,value:unknown)=>{if(key==='mediaAlt'){const target=`item:${id}:imageUrl`,old=s.imageSlots??[],exists=old.some(slot=>slot.target===target);patch({imageSlots:exists?old.map(slot=>slot.target===target?{...slot,mediaAlt:String(value)}:slot):[...old,{target,mediaAlt:String(value)}]});return;}patch({items:items.map(item=>item.id===id?{...item,[key]:value}:item)});};
 const field=(label:string,value:unknown,change:(value:string)=>void,multiline=false,fieldKey?:string)=><label className="block space-y-1 text-xs font-medium"><span>{label}</span>{multiline?<textarea data-inspector-field={fieldKey} aria-label={label} className={inputClass} value={String(value??'')} onChange={event=>change(event.target.value)} rows={3}/>:<input data-inspector-field={fieldKey} aria-label={label} className={inputClass} value={String(value??'')} onChange={event=>change(event.target.value)}/>}</label>;
 const sectionFields=meta.sectionFields.filter(key=>!['mediaUrl','mediaAlt','buttonText','buttonLink','secondaryButtonText','secondaryButtonLink'].includes(key)).sort((a,b)=>['eyebrow','title','subtitle','leadText','content'].indexOf(a)-['eyebrow','title','subtitle','leadText','content'].indexOf(b));
 const image=(id:string,url:string,change:(v:string)=>void)=>assetControl?.({id,url})??field('Image URL',url,change);
 const move=(id:string,offset:number)=>{const next=[...items],from=next.findIndex(item=>item.id===id),item=next[from];if(!item)return;const peers=next.map((entry,index)=>({entry,index})).filter(({entry})=>entry.layoutGroup===item.layoutGroup),at=peers.findIndex(({entry})=>entry.id===id),to=peers[at+offset]?.index;if(to===undefined)return;[next[from],next[to]]=[next[to],next[from]];patch({items:next});};
 const illustrationCards=meta.details.flatMap(slot=>{
  const item=items.find(entry=>entry.layoutGroup==='details'&&entry.layoutSlot===slot.slot);
  return item?[{slot,item}]:[];
 });
 const availableIllustrations=meta.details.filter(slot=>!illustrationCards.some(card=>card.slot.slot===slot.slot));
 const availableIllustration=availableIllustrations.find(slot=>slot.slot===selectedIllustration)??availableIllustrations[0];
 const addIllustration=()=>{
  if(!availableIllustration)return;
  const {slot,field}=availableIllustration;
  patch({items:[...items,{id:`${s.id}-label-${globalThis.crypto?.randomUUID?.()??Date.now()}`,title:'',layoutGroup:'details',layoutSlot:slot,[field]:''}]});
 };
 const deleteIllustration=(id:string)=>patch({
  items:items.filter(item=>item.id!==id),
  imageSlots:s.imageSlots?.filter(slot=>!slot.target.startsWith(`item:${id}:`)),
 });
 const titleEditor=<SectionTitleEditor key={s.id} section={s} onChange={onChange}/>;
 return <div className="space-y-3" data-connected-content>
  {!!sectionFields.length&&<ContentDisclosure title="Text & headings">{sectionFields.map(key=><React.Fragment key={key}>{key==='title'?titleEditor:field(key==='eyebrow'?'Eyebrow':key==='content'?'Description':key,(s as unknown as Record<string,unknown>)[key],value=>patch({[key]:value}),key==='content',key)}</React.Fragment>)}</ContentDisclosure>}
  {meta.sectionFields.includes('mediaUrl')&&<ContentDisclosure title="Images">{image('mediaUrl',s.mediaUrl??'',value=>patch({mediaUrl:value}))}{field('Alt text',s.mediaAlt,value=>patch({mediaAlt:value}))}</ContentDisclosure>}
  {(s.type==='faq_accordion'?[]:meta.groups).map(group=><ContentDisclosure key={group.id} title={group.label}>{items.filter(item=>item.layoutGroup===group.id).map((item,index)=><details key={item.id} data-inspector-item={item.id} className="rounded-lg border border-neutral-200 p-3 dark:border-neutral-700" open><summary className="text-sm font-medium">{item.title||`Item ${index+1}`}</summary><div className="mt-3 space-y-3">{connectedEntryFields(s,item).map(key=><React.Fragment key={key}>{key==='imageUrl'?image(`item:${item.id}:imageUrl`,item.imageUrl??'',value=>editItem(item.id,key,value)):field(key==='mediaAlt'?'Alt text':key==='linkUrl'?'Destination':key==='buttonText'?'Action label':key.replace(/([A-Z])/g,' $1'),(key==='mediaAlt'?itemAlt(item.id):(item as unknown as Record<string,unknown>)[key]),value=>editItem(item.id,key,value),key==='description')}</React.Fragment>)}<div className="flex items-center gap-2"><button type="button" className="text-xs" disabled={index===0} onClick={()=>move(item.id,-1)}>Move up</button><button type="button" className="text-xs" disabled={index===items.filter(entry=>entry.layoutGroup===group.id).length-1} onClick={()=>move(item.id,1)}>Move down</button><InspectorDeleteButton onClick={()=>patch({items:items.filter(entry=>entry.id!==item.id),imageSlots:s.imageSlots?.filter(slot=>!slot.target.startsWith(`item:${item.id}:`))})}/></div></div></details>)}<button type="button" className="text-sm font-medium text-indigo-600" disabled={items.filter(item=>item.layoutGroup===group.id).length>=group.max} onClick={()=>{const entry:ConnectedItem={id:`${s.id}-item-${globalThis.crypto?.randomUUID?.()??Date.now()}`,title:'',layoutGroup:group.id,layoutVariant:0};for(const key of connectedEntryFields(s,entry))if(key!=='mediaAlt')(entry as unknown as Record<string,unknown>)[key]='';patch({items:[...items,entry]});}}>Add item</button></ContentDisclosure>)}
  {!!meta.details.length&&<ContentDisclosure title="Illustration labels" defaultOpen={false}>
   <div className="flex items-center justify-between gap-2">
    <span className="text-xs font-semibold uppercase tracking-wide">Illustration labels ({illustrationCards.length})</span>
    <button type="button" className="inline-flex items-center gap-1 rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white disabled:opacity-40" disabled={!availableIllustration} onClick={addIllustration}><Plus size={13} aria-hidden="true"/>Add Label</button>
   </div>
   {availableIllustrations.length>1&&<label className="block space-y-1 text-xs font-medium">
    <span>Label position</span>
    <select className={inputClass} value={availableIllustration?.slot??''} onChange={event=>setSelectedIllustration(event.target.value)}>
     {availableIllustrations.map(slot=><option key={slot.slot} value={slot.slot}>{slot.label}</option>)}
    </select>
   </label>}
   <div className="max-h-96 space-y-3 overflow-y-auto">
    {illustrationCards.map(({slot,item},index)=><div key={item.id} data-inspector-item={item.id} className="space-y-3 rounded-xl border border-neutral-200 bg-neutral-50 p-3 dark:border-neutral-800 dark:bg-neutral-950">
     <div className="flex items-center justify-between">
      <span className="text-xs font-semibold uppercase tracking-wide text-indigo-500">Label #{index+1}</span>
      <InspectorDeleteButton label={`Delete ${slot.label}`} onClick={()=>deleteIllustration(item.id)}/>
     </div>
     {slot.field==='imageUrl'
      ?<div className="space-y-1"><span className="text-xs font-medium">{slot.label}</span>{image(`item:${item.id}:imageUrl`,item.imageUrl??'',value=>editItem(item.id,slot.field,value))}</div>
      :field(slot.label,(item as unknown as Record<string,unknown>)[slot.field],value=>editItem(item.id,slot.field,value),slot.field==='description')}
    </div>)}
   </div>
  </ContentDisclosure>}
  {meta.sectionFields.some(key=>['buttonText','secondaryButtonText'].includes(key))&&<ContentDisclosure title="Actions">{meta.sectionFields.filter(key=>['buttonText','secondaryButtonText'].includes(key)).map(key=><React.Fragment key={key}>{field(key==='buttonText'?'Primary action':'Secondary action',(s as unknown as Record<string,unknown>)[key],value=>patch({[key]:value}))}{field(key==='buttonText'?'Primary destination':'Secondary destination',key==='buttonText'?s.buttonLink:s.secondaryButtonLink,value=>patch({[key==='buttonText'?'buttonLink':'secondaryButtonLink']:value}))}</React.Fragment>)}</ContentDisclosure>}
 </div>;
}
