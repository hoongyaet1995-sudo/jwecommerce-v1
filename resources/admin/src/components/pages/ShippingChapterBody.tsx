import {SectionEyebrow} from './SectionEyebrow';
import {SectionIcon} from './IconPicker';
import React from 'react';
import {FileText,Package,Truck} from 'lucide-react';
import type {PageSection} from '../../types';
import {typographyStyle} from '../../cms/inspectorCapabilities';
import {DeliveryTable} from './ShippingSections';
import {ScopedImage,imageSlotFrameStyle} from './ScopedImage';
import {MediaWidthRegion} from './MediaWidthRegion';
import {EditorialSectionAction} from './EditorialSectionAction';
export function ShippingChapterBody({section,isEditingMode}:{section:PageSection;isEditingMode:boolean}) {
 const color=(role:'title'|'content'|'itemTitle'|'itemContent')=>({color:role==='title'||role==='itemTitle'?section.customColors?.titleColor:section.customColors?.contentColor,...typographyStyle(section,role)});
 return <div><><SectionEyebrow section={section}/><h2 data-cms-text="title" style={color('title')} className="text-3xl @min-[640px]:text-4xl font-extrabold tracking-tight leading-tight whitespace-pre-line">{section.title}</h2></><div data-cms-text="content" style={color('content')} className="mt-5 space-y-3 text-base leading-relaxed">{(section.content??'').split(/\n\s*\n/).map((p,i)=><p key={i}>{p}</p>)}</div>
 {section.chapterPreset==='process'&&<><div className="grid grid-cols-1 @min-[640px]:grid-cols-3 gap-6 mt-8">{section.items?.filter(item=>item.imageUrl===undefined).map((item,i)=>{const Icon=item.icon==='Truck'?Truck:item.icon==='Package'?Package:FileText;return <div key={item.id} className={i?'@min-[640px]:border-l border-neutral-200 @min-[640px]:pl-6':''}><SectionIcon name={item.icon} fallback="FileText" className="w-8 h-8 mb-4"/><h3 data-cms-text="itemTitle" data-cms-item={item.id} style={color('itemTitle')} className="font-bold">{item.title}</h3><p data-cms-text="itemContent" data-cms-item={item.id} style={color('itemContent')} className="mt-2 text-sm leading-relaxed">{item.description}</p></div>;})}</div></>}
 {section.chapterPreset==='delivery'&&<><DeliveryTable section={section} embedded/><MediaWidthRegion section={section}><div className="grid grid-cols-1 @min-[640px]:grid-cols-2 gap-5 mt-6">{section.items?.filter(item=>item.imageUrl!==undefined).map(item=><div key={item.id} style={imageSlotFrameStyle(section,`item:${item.id}:imageUrl`)} className="aspect-[8/5] overflow-hidden">{item.imageUrl&&<ScopedImage section={section} targetId={`item:${item.id}:imageUrl`} src={item.imageUrl} alt={item.title} className="w-full h-full object-cover"/>}</div>)}</div></MediaWidthRegion></>}
 {section.chapterPreset==='actions'&&<div className="flex flex-wrap gap-6 items-center mt-6"><EditorialSectionAction section={section} isEditingMode={isEditingMode}/>{section.secondaryButtonText&&<EditorialSectionAction section={{...section,buttonText:section.secondaryButtonText,buttonLink:section.secondaryButtonLink,buttonStyle:'text-link'}} isEditingMode={isEditingMode}/>}</div>}
 </div>;
}
