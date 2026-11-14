import {SectionEyebrow} from './SectionEyebrow';
import React from 'react';
import {ArrowRight,Image} from 'lucide-react';
import type {PageSection} from '../../types';
import type {PageRuntimePayload} from '../../cms/PageRuntimeContext';
import {resolveShopCard,shopNavigationHref} from '../../cms/shopNavigation';
import {typographyStyle} from '../../cms/inspectorCapabilities';
import {ScopedImage} from './ScopedImage';

/** Authored navigation campaigns; labels never change operational discount rules. */
export function MarketNavigation({section,catalog,editing=false}:{section:PageSection;catalog:PageRuntimePayload['catalog'];editing?:boolean}) {
 const hero=section.shopNavigationPreset==='market-hero';
 const colors=section.customColors;
 const height=section.bannerHeight==='compact'?200:section.bannerHeight==='tall'?320:260;
 return <div data-market-navigation className={`@container w-full ${hero?'py-0':'py-6'}`} style={{color:colors?.titleColor??'#0c2337'}}>
  {section.showSectionHeading!==false&&<header className="flex flex-wrap justify-between items-end gap-4 mb-5">
   <div>{<SectionEyebrow section={section}/>}<h2 data-cms-text="title" className="text-2xl font-bold whitespace-pre-line" style={typographyStyle(section,'title')}>{section.title}</h2>{section.content&&<p data-cms-text="content" className="mt-2 text-sm whitespace-pre-line" style={typographyStyle(section,'content')}>{section.content}</p>}</div>
   {section.buttonText&&shopNavigationHref(section.buttonLink??'')&&<a data-cms-button="primary" data-cms-button-kind="link" data-cms-action="button" href={shopNavigationHref(section.buttonLink??'')} onClick={e=>{if(editing)e.preventDefault()}} className="inline-flex gap-2 items-center text-xs">{section.buttonText}<ArrowRight size={14}/></a>}
  </header>}
  <div className={hero?'grid gap-2 grid-cols-1 @min-[640px]:grid-cols-[2fr_1fr]':'grid gap-4 grid-cols-1 @min-[640px]:grid-cols-3'}>
   {(section.items??[]).map((item,index)=>{const card=resolveShopCard(item,catalog);if(!editing&&!card.available)return null;const large=hero&&index===0;return <article data-cms-item={item.id} key={item.id} className={`relative overflow-hidden min-w-0 flex items-center ${large?'@min-[640px]:row-span-2':''}`} style={{minHeight:large?height*2+8:height}}>
    {card.image?<ScopedImage section={section} targetId={`item:${item.id}:imageUrl`} src={card.image} alt={card.title} className="absolute inset-0 w-full h-full object-cover"/>:<div className="absolute inset-0 bg-neutral-100 flex items-center justify-center"><Image size={28}/></div>}
    <div aria-hidden="true" className="absolute inset-0 bg-gradient-to-r from-white/60 via-white/15 to-transparent pointer-events-none"/>
    <div className={`relative z-10 w-full ${large?'p-7 @min-[640px]:p-10':'p-6'}`}>
     <div className={large?'max-w-[85%] @min-[640px]:max-w-[65%]':'max-w-[65%]'}>
      {item.subtitle&&<p data-cms-item={item.id} data-cms-text="label" className="text-[10px] mb-3 uppercase tracking-widest" style={typographyStyle(section,'label')}>{item.subtitle}</p>}
      <h3 data-cms-item={item.id} data-cms-text={large?'lead':'itemTitle'} className={`font-bold whitespace-pre-line leading-tight ${large?'text-[clamp(32px,5cqw,58px)]':'text-xl'}`} style={typographyStyle(section,large?'lead':'itemTitle')}>{card.title}</h3>
      {item.description&&<p data-cms-item={item.id} data-cms-text="itemContent" className="text-sm mt-3 whitespace-pre-line leading-snug" style={{color:colors?.contentColor,...typographyStyle(section,'itemContent')}}>{item.description}</p>}
      {card.href&&item.buttonText&&<a data-cms-button={large?'primary':'secondary'} data-cms-button-kind="button" data-cms-item={item.id} data-cms-action="button" href={card.href} onClick={e=>{if(editing)e.preventDefault()}} className="mt-4 inline-flex gap-2 items-center px-4 py-3 rounded text-xs font-semibold" style={{background:large?colors?.primaryBtnBg??'#ff6749':colors?.secondaryBtnBg??'#ffffff',color:large?colors?.primaryBtnText??'#ffffff':colors?.secondaryBtnText??'#0c2337'}}>{item.buttonText}<ArrowRight size={14}/></a>}
     </div>
    </div>
    {!hero&&item.badge&&<span data-cms-item={item.id} data-cms-text="label" className="absolute z-20 right-3 top-3 rounded-full h-20 w-20 flex items-center justify-center text-center whitespace-pre-line text-xs font-semibold" style={{background:colors?.primaryBtnBg??'#ff6749',color:colors?.primaryBtnText??'#ffffff'}}>{item.badge}</span>}
   </article>})}
  </div>
 </div>;
}
