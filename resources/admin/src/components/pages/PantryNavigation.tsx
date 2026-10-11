import React from 'react';
import {ArrowRight} from 'lucide-react';
import type {PageSection} from '../../types';
import type {PageRuntimePayload} from '../../cms/PageRuntimeContext';
import {resolveShopCard} from '../../cms/shopNavigation';
import {typographyStyle} from '../../cms/inspectorCapabilities';
import {ScopedImage,imageSlotFrameStyle} from './ScopedImage';
import {MotionItem,MotionMedia} from './motion/CmsMotionWrapper';
import {BeautySectionHeading} from './BeautyLayouts';
const columns={2:'@min-[640px]:grid-cols-2',3:'@min-[640px]:grid-cols-3',4:'@min-[640px]:grid-cols-4',5:'@min-[640px]:grid-cols-3 @min-[1024px]:grid-cols-5',6:'@min-[640px]:grid-cols-3 @min-[1024px]:grid-cols-6'};
/** Authored editorial cards; category/collection links still follow the runtime taxonomy. */
export function PantryNavigation({section,catalog,editing=false}:{section:PageSection;catalog:PageRuntimePayload['catalog'];editing?:boolean}) {
 const recipes=section.shopNavigationPreset==='recipe-cards';
 return <div data-pantry-navigation className={`@container ${section.paddingY==='spacious'?'py-20':section.paddingY==='normal'?'py-12 @min-[1024px]:py-16':'py-8'}`}><BeautySectionHeading section={section} editing={editing}/><div className={`grid grid-cols-2 gap-x-5 gap-y-7 ${columns[section.columnsCount??(recipes?3:6)]}`}>
  {(section.items??[]).map(item=>{
   const card=resolveShopCard(item,catalog);if(!editing&&!card.available)return null;
   const target=`item:${item.id}:imageUrl`;
   return <MotionItem key={item.id}><a href={card.href||undefined} data-cms-item={item.id} data-cms-action="link" onClick={e=>{if(editing)e.preventDefault();}} className="block min-w-0 group focus-visible:outline focus-visible:outline-2">
    <MotionMedia className={`overflow-hidden bg-black/5 ${recipes?'aspect-[16/10]':'aspect-square'}`} style={imageSlotFrameStyle(section,target)}>{card.image&&<ScopedImage section={section} targetId={target} src={card.image} alt={card.title} className="w-full h-full object-cover transition-transform duration-500 group-hover:scale-[1.03]"/>}</MotionMedia>
    <div style={{textAlign:section.textAlignment}} className={recipes?'bg-white/70 p-3':'pt-3'}>
     {recipes&&item.badge&&<span data-cms-text="eyebrow" className="inline-block text-[10px] uppercase px-2 py-1 mb-2" style={{backgroundColor:section.customColors?.primaryBtnText??'#faf8f2',color:section.customColors?.eyebrowColor??'#46512c',...typographyStyle(section,'eyebrow')}}>{item.badge}</span>}
     <h3 data-cms-text="itemTitle" style={typographyStyle(section,'itemTitle')} className="text-sm font-semibold leading-snug">{card.title}</h3>
     {item.description&&<p data-cms-text="itemContent" style={typographyStyle(section,'itemContent')} className="text-xs opacity-75 mt-1 whitespace-pre-line">{item.description}</p>}
     {item.buttonText&&<span className="inline-flex text-xs gap-2 items-center mt-3">{item.buttonText}<ArrowRight size={13}/></span>}
    </div>
   </a></MotionItem>;
  })}
 </div></div>;
}
