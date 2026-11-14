import {SectionEyebrow} from './SectionEyebrow';
import {PolicyDocument} from './PolicyDocument';
import {SectionIcon} from './IconPicker';
import React,{useState} from 'react';
import {Package,ArrowLeftRight,ShieldCheck,FileText,ArrowRight,ChevronRight} from 'lucide-react';
import type {PageSection,SectionItem} from '../../types';
import {typographyStyle} from '../../cms/inspectorCapabilities';
import {EditorialSectionAction} from './EditorialSectionAction';
import {ScopedImage,imageSlotFrameStyle} from './ScopedImage';
import {MediaWidthRegion} from './MediaWidthRegion';
export function PolicyContent({section,isEditingMode=false,embedded=false}:{section:PageSection;isEditingMode?:boolean;embedded?:boolean}) {
 const preset=section.policyPreset??'guidelines';
 const entries=(section.items??[]).filter(item=>item.imageUrl===undefined);
 const photos=(section.items??[]).filter(item=>item.imageUrl!==undefined);
 const groups=Array.from(new Set(entries.map(item=>item.category||'Guidelines')));
 const [selected,setSelected]=useState(groups[0]);const active=groups.includes(selected)?selected:groups[0];
 const shown=preset==='tabs'?entries.filter(item=>(item.category||'Guidelines')===active):entries;
 const color=(role:'eyebrow'|'title'|'content'|'itemTitle'|'itemContent')=>({color:role==='eyebrow'?section.customColors?.eyebrowColor:role==='title'||role==='itemTitle'?section.customColors?.titleColor:section.customColors?.contentColor,...typographyStyle(section,role)});
 const title=(item:SectionItem)=><h3 data-cms-text="itemTitle" data-cms-item={item.id} style={color('itemTitle')} className="font-bold text-lg break-words">{item.title}</h3>;
 const description=(item:SectionItem)=><p data-cms-text="itemContent" data-cms-item={item.id} style={color('itemContent')} className="mt-2 text-sm leading-relaxed whitespace-pre-line break-words">{item.description}</p>;
 if(['document','directory'].includes(preset))return <PolicyDocument section={section} isEditingMode={isEditingMode} embedded={embedded}/>;
 return <div data-policy-content={preset} className={`@container min-w-0 ${embedded?'':section.paddingY==='compact'?'py-8':section.paddingY==='spacious'?'py-20':'py-12 @min-[1024px]:py-16'}`}>
  {section.showSectionHeading!==false&&<div className={`mb-8 ${section.textAlignment==='center'?'text-center':''}`}>
   {<SectionEyebrow section={section}/>}
   <h2 data-cms-text="title" style={color('title')} className="text-3xl @min-[640px]:text-4xl font-extrabold tracking-tight leading-tight whitespace-pre-line">{section.title}</h2>
   {section.content&&<div data-cms-text="content" style={color('content')} className="mt-4 space-y-3 text-base leading-relaxed">{section.content.split(/\n\s*\n/).map((p,i)=><p key={i}>{p}</p>)}</div>}
  </div>}
  {preset==='tabs'&&<div role="tablist" aria-label={`${section.title} guidelines`} className="flex flex-wrap gap-6 border-b border-neutral-200 mb-2">{groups.map(group=><button key={group} type="button" role="tab" aria-selected={group===active} id={`${section.id}-tab-${groups.indexOf(group)}`} aria-controls={`${section.id}-panel`} onClick={()=>setSelected(group)} className={`py-4 border-b-2 font-semibold text-sm ${group===active?'border-indigo-600 text-indigo-600':'border-transparent text-neutral-500'}`}>{group}</button>)}</div>}
  {preset==='process-flow'?<ol className="grid grid-cols-1 @min-[640px]:grid-cols-3 gap-x-12 gap-y-10">
   {shown.map((item,index)=><li key={item.id} data-inspector-item={item.id} className="relative min-w-0">
    <span className="block text-3xl font-semibold text-neutral-400 mb-3">{String(index+1).padStart(2,'0')}</span>
    {title(item)}{description(item)}
    {item.linkUrl&&<div className="mt-3"><EditorialSectionAction section={{...section,buttonText:item.buttonText||'Learn more',buttonLink:item.linkUrl,buttonStyle:'text-link'}} isEditingMode={isEditingMode}/></div>}
    {index<shown.length-1&&<ChevronRight aria-hidden="true" className={`absolute w-5 h-5 text-neutral-400 left-1/2 -bottom-8 rotate-90 @min-[640px]:rotate-0 @min-[640px]:left-auto @min-[640px]:-right-8 @min-[640px]:top-1/2 ${index%3===2?'@min-[640px]:hidden':''}`}/>}
   </li>)}
  </ol>:<>
  <div {...(preset==='tabs'?{role:'tabpanel','aria-labelledby':`${section.id}-tab-${groups.indexOf(active)}`,id:`${section.id}-panel`}:{})} className={['choices','columns','timeline'].includes(preset)?`grid grid-cols-1 ${section.columnsCount===2?'@min-[640px]:grid-cols-2':section.columnsCount===3?'@min-[640px]:grid-cols-3':preset==='columns'?'@min-[640px]:grid-cols-2':'@min-[640px]:grid-cols-3'} gap-6`:''}>
   {shown.map((item,index)=>{const Icon=item.icon==='ArrowLeftRight'?ArrowLeftRight:item.icon==='ShieldCheck'?ShieldCheck:item.icon==='FileText'?FileText:Package;
    if(preset==='guidelines'||preset==='tabs')return <div key={item.id} data-inspector-item={item.id} className="grid grid-cols-1 @min-[640px]:grid-cols-[1fr_2fr] gap-2 @min-[640px]:gap-8 py-5 border-b border-neutral-200">{title(item)}<div>{description(item)}{item.linkUrl&&<a data-cms-button="primary" data-cms-button-kind="link" data-cms-action="itemLink" data-cms-item={item.id} href={item.linkUrl} onClick={e=>{if(isEditingMode)e.preventDefault();else if(item.linkUrl==='#storefront-support-widget'){e.preventDefault();document.getElementById('sf-support-launcher')?.click();}}} className="inline-flex items-center gap-2 text-indigo-600 text-sm mt-2">{item.buttonText||'Learn more'}<ArrowRight className="w-4 h-4"/></a>}</div></div>;
    if(preset==='steps')return <div key={item.id} className="grid grid-cols-[56px_minmax(0,1fr)] gap-6 py-5 border-b border-neutral-200"><span className="text-5xl font-light text-neutral-300">{String(index+1).padStart(2,'0')}</span><div>{title(item)}{description(item)}</div></div>;
    return <div key={item.id} className={preset==='choices'?'p-6 border border-neutral-200 rounded-lg text-center':preset==='timeline'?'relative py-5 @min-[640px]:border-t border-neutral-300':'py-4'}>{preset==='timeline'?<span className="flex w-8 h-8 rounded-full bg-indigo-600 text-white items-center justify-center mb-4">{index+1}</span>:<SectionIcon name={item.icon} fallback="Package" className={`w-9 h-9 mb-4 ${preset==='choices'?'mx-auto':''}`}/>} {title(item)}{description(item)}{item.linkUrl&&<div className="mt-5"><EditorialSectionAction section={{...section,buttonText:item.buttonText||'Contact support',buttonLink:item.linkUrl,buttonStyle:'text-link'}} isEditingMode={isEditingMode}/></div>}</div>;
   })}
  </div>
  </>}
  {section.policyNote&&<p className="mt-5 text-xs leading-relaxed text-neutral-500 whitespace-pre-line">{section.policyNote}</p>}
  {section.buttonText&&<div className="mt-6"><EditorialSectionAction section={section} isEditingMode={isEditingMode}/></div>}
  {photos.length>0&&<MediaWidthRegion section={section}><div className="grid grid-cols-1 @min-[640px]:grid-cols-2 gap-5 mt-8">{photos.map(item=><div key={item.id} className="aspect-[4/3] overflow-hidden" style={imageSlotFrameStyle(section,`item:${item.id}:imageUrl`)}>{item.imageUrl&&<ScopedImage section={section} targetId={`item:${item.id}:imageUrl`} src={item.imageUrl} alt={item.title} className="w-full h-full object-cover"/>}</div>)}</div></MediaWidthRegion>}
 </div>;
}
