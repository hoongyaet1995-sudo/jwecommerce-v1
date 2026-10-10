import React from 'react';
import {CollectionBanners} from './CollectionBanners';
import {ArrowRight,Image} from 'lucide-react';
import type {PageSection} from '../../types';
import type {PageRuntimePayload} from '../../cms/PageRuntimeContext';
import {resolveShopCard,shopNavigationHref} from '../../cms/shopNavigation';
import {typographyStyle} from '../../cms/inspectorCapabilities';
import {ScopedImage,imageSlotFrameStyle} from './ScopedImage';
import {MotionItem,MotionMedia} from './motion/CmsMotionWrapper';
const grids={2:'@min-[640px]:grid-cols-2',3:'@min-[640px]:grid-cols-3',4:'@min-[640px]:grid-cols-4',5:'@min-[640px]:grid-cols-3 @min-[1024px]:grid-cols-5',6:'@min-[640px]:grid-cols-3 @min-[1024px]:grid-cols-6'};
export function ShopNavigation({section,catalog,editing=false}:{section:PageSection;catalog:PageRuntimePayload['catalog'];editing?:boolean}) {
 if(section.shopNavigationPreset==='collection-banners')return <CollectionBanners section={section} catalog={catalog} editing={editing}/>;
 return <div data-shop-navigation style={{color:section.customColors?.titleColor??'#292b24'}} className={`@container w-full ${section.paddingY==='compact'?'py-8':section.paddingY==='spacious'?'py-20':'py-12 @min-[1024px]:py-16'}`}>
  <div className={`flex gap-4 mb-7 ${section.textAlignment==='center'?'flex-col items-center':section.textAlignment==='right'?'flex-col items-end':'flex-wrap items-end justify-between'}`}>
   <div className={`min-w-0 ${section.textAlignment==='center'||section.textAlignment==='right'?'w-full':' '}`} style={{textAlign:section.textAlignment}}><MotionItem>{section.subtitle&&<p data-cms-text="eyebrow" style={typographyStyle(section,'eyebrow')} className="text-xs uppercase tracking-widest mb-3">{section.subtitle}</p>}<h2 data-cms-text="title" style={{color:section.customColors?.titleColor,...typographyStyle(section,'title')}} className="text-3xl font-serif whitespace-pre-line break-words">{section.title}</h2>{section.content&&<p data-cms-text="content" style={{color:section.customColors?.contentColor,...typographyStyle(section,'content')}} className="mt-3 text-sm whitespace-pre-line">{section.content}</p>}</MotionItem></div>
   {section.buttonText&&shopNavigationHref(section.buttonLink??'')&&<a data-editorial-action="text-link" href={shopNavigationHref(section.buttonLink??'')} onClick={e=>{if(editing)e.preventDefault()}} className="inline-flex items-center gap-2 text-xs hover:underline underline-offset-4" style={{color:section.customColors?.primaryBtnBg}}>{section.buttonText}<ArrowRight aria-hidden="true" size={15}/></a>}
  </div>
  <div className={`grid grid-cols-2 gap-x-5 gap-y-7 ${grids[section.columnsCount??5]}`}>
   {(section.items??[]).map(item=>{const card=resolveShopCard(item,catalog);if(!editing&&!card.available)return null;const target=`item:${item.id}:imageUrl`;const body=<><MotionMedia className="aspect-[4/3] overflow-hidden bg-black/5" style={imageSlotFrameStyle(section,target)}>{card.image?<ScopedImage section={section} targetId={target} src={card.image} alt={card.title} className="w-full h-full object-cover transition-transform duration-500 group-hover:scale-[1.03]"/>:<div className="flex h-full items-center justify-center text-neutral-400"><Image aria-hidden="true" size={28}/></div>}</MotionMedia><MotionItem><div className="flex justify-between items-center gap-2 mt-3"><h3 data-cms-item={item.id} data-cms-text="itemTitle" style={{color:section.customColors?.titleColor,...typographyStyle(section,'itemTitle')}} className="font-serif text-base leading-snug break-words">{card.title}</h3><ArrowRight aria-hidden="true" size={16} className="shrink-0"/></div></MotionItem></>;
    return card.href?<a key={item.id} href={card.href} data-cms-action="link" data-cms-item={item.id} onClick={e=>{if(editing)e.preventDefault()}} className="group min-w-0 block focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-indigo-500">{body}</a>:<div key={item.id} data-cms-item={item.id} className="min-w-0">{body}{editing&&<p className="text-xs mt-2 text-neutral-500">Select a destination</p>}</div>;
   })}
  </div>
 </div>;
}
