import React,{useState} from 'react';
import type {PageSection} from '../../types';
import type {PageRuntimePayload,PageRuntimeSectionFacts} from '../../cms/PageRuntimeContext';
import {EditorialProductGrid} from './EditorialProductGrid';

export function EditorialProductTabs({section,facts,context,editing=false}:{section:PageSection;facts:PageRuntimeSectionFacts;context:PageRuntimePayload['context'];editing?:boolean}) {
 const tabs=section.productTabs??[];
 const [selected,setSelected]=useState<string>();
 const active=Math.max(0,tabs.findIndex(tab=>tab.id===selected));
 const tab=tabs[active],feed=facts.tabs?.[active];
 const select=(index:number)=>setSelected(tabs[index].id);
 const header=<div role="tablist" aria-label="Product collections" className="flex gap-6 max-w-full overflow-x-auto" data-cms-source="">
  {tabs.map((item,index)=><button type="button" key={item.id} id={`product-tab-${section.id}-${item.id}`} role="tab" aria-selected={index===active} aria-controls={`product-panel-${section.id}`} tabIndex={index===active?0:-1} onClick={event=>{event.stopPropagation();select(index);}} onKeyDown={event=>{let next:number;if(event.key==='ArrowRight')next=(index+1)%tabs.length;else if(event.key==='ArrowLeft')next=(index-1+tabs.length)%tabs.length;else if(event.key==='Home')next=0;else if(event.key==='End')next=tabs.length-1;else return;event.preventDefault();select(next);document.getElementById(`product-tab-${section.id}-${tabs[next].id}`)?.focus();}} className={`shrink-0 whitespace-nowrap py-2 text-xs border-b ${index===active?'border-current':'border-transparent opacity-60'} focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-500`} style={{color:section.customColors?.titleColor}}>{item.label||'Untitled tab'}</button>)}
 </div>;
 return <EditorialProductGrid section={section} products={feed?.products??[]} context={context} editing={editing} header={header} panel={{id:`product-panel-${section.id}`,labelledBy:tab?`product-tab-${section.id}-${tab.id}`:undefined}} emptyMessage={!tab?'Add a product tab in Content.':!feed?.sourceId.valid?'This product group is unavailable. Choose another group in Content.':'No products are available in this group.'}/>;
}
