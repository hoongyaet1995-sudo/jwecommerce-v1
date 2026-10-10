import React from 'react';
import {ArrowRight,Image} from 'lucide-react';
import type {PageSection} from '../../types';
import type {PageRuntimePayload} from '../../cms/PageRuntimeContext';
import {resolveShopCard} from '../../cms/shopNavigation';
import {typographyStyle} from '../../cms/inspectorCapabilities';
import {ScopedImage} from './ScopedImage';
import {MotionItem} from './motion/CmsMotionWrapper';

export function CollectionBanners({section,catalog,editing=false}:{section:PageSection;catalog:PageRuntimePayload['catalog'];editing?:boolean}) {
 const columns=section.bannerProportions==='40:60'?'@min-[640px]:grid-cols-[2fr_3fr]':section.bannerProportions==='50:50'?'@min-[640px]:grid-cols-2':'@min-[640px]:grid-cols-[3fr_2fr]';
 const height=section.bannerHeight==='compact'?240:section.bannerHeight==='tall'?400:320;
 return <div data-collection-banners className={`@container w-full ${section.paddingY==='compact'?'py-0':section.paddingY==='spacious'?'py-20':'py-12'}`}>
  {section.showSectionHeading!==false&&<header className="px-6 py-8" style={{textAlign:section.textAlignment}}>{section.subtitle&&<p data-cms-text="eyebrow" style={typographyStyle(section,'eyebrow')} className="text-xs uppercase tracking-widest mb-3">{section.subtitle}</p>}<h2 data-cms-text="title" style={typographyStyle(section,'title')} className="text-3xl font-serif whitespace-pre-line">{section.title}</h2>{section.content&&<p data-cms-text="content" style={typographyStyle(section,'content')} className="mt-3 whitespace-pre-line">{section.content}</p>}</header>}
  <div className={`grid grid-cols-1 gap-2 ${columns}`}>
   {(section.items??[]).map((item,index)=>{const card=resolveShopCard(item,catalog);if(!editing&&!card.available)return null;
    return <article key={item.id} data-cms-item={item.id} className="relative min-w-0 overflow-hidden flex items-center" style={{minHeight:height}}>
     {card.image?<ScopedImage section={section} targetId={`item:${item.id}:imageUrl`} src={card.image} alt={card.title} className="absolute inset-0 w-full h-full object-cover"/>:<div className="absolute inset-0 bg-neutral-700 flex items-center justify-center"><Image aria-hidden="true" size={32}/></div>}
     <MotionItem className="relative w-full p-7 @min-[640px]:p-9 text-white"><h3 data-cms-text="itemTitle" data-cms-item={item.id} className="font-serif text-[clamp(28px,5cqw,40px)] leading-none whitespace-pre-line break-words" style={typographyStyle(section,'itemTitle')}>{card.title}</h3>{item.description&&<p data-cms-text="itemContent" data-cms-item={item.id} className="mt-4 text-sm leading-relaxed whitespace-pre-line" style={typographyStyle(section,'itemContent')}>{item.description}</p>}{card.href&&item.buttonText&&<a href={card.href} data-cms-action="button" data-cms-item={item.id} onClick={e=>{if(editing)e.preventDefault()}} className="pointer-events-auto inline-flex items-center gap-4 mt-5 px-5 py-3 text-xs focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4" style={{backgroundColor:index%2===0?section.customColors?.primaryBtnBg??'#454e37':section.customColors?.secondaryBtnBg??'#f7f6f2',color:index%2===0?section.customColors?.primaryBtnText??'#ffffff':section.customColors?.secondaryBtnText??'#292b24'}}>{item.buttonText}<ArrowRight size={15} aria-hidden="true"/></a>}{editing&&!card.available&&<p className="mt-3 text-xs">Select a destination</p>}</MotionItem>
    </article>;
   })}
  </div>
 </div>;
}
