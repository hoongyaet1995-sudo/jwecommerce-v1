import {SectionEyebrow} from './SectionEyebrow';
import React from 'react';
import './CorporateSections.css';
import {ArrowRight} from 'lucide-react';
import type {PageSection} from '../../types';
import type {PageRuntimePayload} from '../../cms/PageRuntimeContext';
import {typographyStyle} from '../../cms/inspectorCapabilities';
import {resolveShopCard} from '../../cms/shopNavigation';
import {ScopedImage} from './ScopedImage';
import {MotionItem} from './motion/CmsMotionWrapper';
export function BeautySectionHeading({section,editing=false}:{section:PageSection;editing?:boolean}) {
 return section.showSectionHeading===false?null:<header className={`flex gap-4 mb-5 ${section.textAlignment==='center'?'flex-col items-center':section.textAlignment==='right'?'flex-col items-end':'flex-wrap justify-between items-end'}`}><div style={{textAlign:section.textAlignment}}><SectionEyebrow section={section}/><h2 data-cms-text="title" className="font-serif text-3xl whitespace-pre-line" style={typographyStyle(section,'title')}>{section.title}</h2>{section.content&&<p data-cms-text="content" className="text-sm mt-3 whitespace-pre-line" style={typographyStyle(section,'content')}>{section.content}</p>}</div>{section.buttonText&&section.buttonLink&&<a data-cms-button="primary" data-cms-button-kind="link" data-cms-action="button" href={section.buttonLink} onClick={e=>{if(editing)e.preventDefault()}} className="text-xs inline-flex items-center gap-2">{section.buttonText}<ArrowRight size={14}/></a>}</header>;
}
export function ImageCaptionNavigation({section,catalog,editing=false}:{section:PageSection;catalog:PageRuntimePayload['catalog'];editing?:boolean}) {
 const light=section.shopNavigationPreset==='image-caption';const cols=section.columnsCount??4;
 const grids={2:'@min-[640px]:grid-cols-2',3:'@min-[640px]:grid-cols-3',4:'@min-[640px]:grid-cols-4',5:'@min-[640px]:grid-cols-3 @min-[1024px]:grid-cols-5',6:'@min-[640px]:grid-cols-3 @min-[1024px]:grid-cols-6'};
 return <div data-image-caption-navigation className="@container py-8"><BeautySectionHeading section={section} editing={editing}/><div className={`grid grid-cols-2 gap-4 ${grids[cols]}`}>{(section.items??[]).map(item=>{const card=resolveShopCard(item,catalog);if(!editing&&!card.available)return null;const body=<><div className={`relative overflow-hidden ${light?'aspect-[16/9]':'aspect-[5/4]'}`}>{card.image&&<ScopedImage section={section} targetId={`item:${item.id}:imageUrl`} src={card.image} alt={card.title} className="w-full h-full object-cover transition-transform duration-500 group-hover:scale-[1.03]"/>}<div className={`absolute inset-0 ${light?'':'bg-gradient-to-t from-black/65 via-transparent to-transparent'}`}/><div className={`absolute inset-x-0 bottom-0 p-3 @min-[640px]:p-4 ${light?'bg-white/65':'text-white'}`}><h3 data-cms-text="itemTitle" data-cms-item={item.id} className="font-serif text-lg" style={typographyStyle(section,'itemTitle')}>{card.title}</h3>{item.description&&<p data-cms-text="itemContent" data-cms-item={item.id} className="text-xs mt-1 pr-6" style={typographyStyle(section,'itemContent')}>{item.description}</p>}{!light&&<span aria-hidden="true" className="absolute bottom-4 right-3 bg-white text-[#43273b] w-6 h-6 rounded-full flex items-center justify-center"><ArrowRight size={14}/></span>}</div></div>{item.buttonText&&<span className="block text-xs mt-2">{item.buttonText} →</span>}</>;return <MotionItem key={item.id}><a href={card.href||undefined} data-cms-item={item.id} data-cms-action="link" onClick={e=>{if(editing)e.preventDefault()}} className="block group min-w-0 focus-visible:outline focus-visible:outline-2">{body}</a></MotionItem>;})}</div></div>;
}
export function RoutineSteps({section,editing=false}:{section:PageSection;editing?:boolean}) {
 if(section.galleryPreset==='corporate-illustrated-steps'){
  const items=section.items??[];
  return <div className="corp corp-illustrated-steps" data-stacked={items.length>4?'true':undefined} style={{'--corp-step-count':Math.max(1,items.length),'--corp-space':section.paddingY==='compact'?'32px':section.paddingY==='spacious'?'96px':'64px'} as React.CSSProperties}>
   <header className="corp-steps-heading"><><SectionEyebrow section={section}/><h2 data-cms-text="title" style={{color:section.customColors?.titleColor,...typographyStyle(section,'title')}}>{section.title}</h2></>{section.content&&<p data-cms-text="content" style={{color:section.customColors?.contentColor,...typographyStyle(section,'content')}}>{section.content}</p>}</header>
   <ol className="corp-steps-grid">{items.map((item,i)=><li key={item.id} className="corp-step" data-cms-item={item.id}>
    <div className="corp-step-art">{item.imageUrl?<ScopedImage section={section} targetId={`item:${item.id}:imageUrl`} src={item.imageUrl} alt={item.title} className="w-full h-full object-contain"/>:editing?<p>Add step illustration</p>:null}</div>
    <span className="corp-step-number" data-step-number={i+1} aria-hidden="true">{String(i+1).padStart(2,'0')}</span>
    <h3 data-cms-text="itemTitle" data-cms-item={item.id} style={{color:section.customColors?.titleColor,...typographyStyle(section,'itemTitle')}}>{item.title}</h3>
    {item.description&&<p data-cms-text="itemContent" data-cms-item={item.id} style={{color:section.customColors?.contentColor,...typographyStyle(section,'itemContent')}}>{item.description}</p>}
    {i<items.length-1&&<svg data-step-connector={item.id} className="corp-step-connector" viewBox="0 0 180 44" aria-hidden="true" focusable="false"><path d="M4 30 Q86 -10 168 30 M157 17 L169 31 L151 29" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"/></svg>}
   </li>)}</ol>
  </div>;
 }
 const grids={2:'@min-[640px]:grid-cols-2',3:'@min-[640px]:grid-cols-3',4:'@min-[640px]:grid-cols-4',5:'@min-[640px]:grid-cols-5',6:'@min-[640px]:grid-cols-6'};
 return <div data-routine-steps className="@container py-8"><BeautySectionHeading section={section} editing={editing}/><div className={`grid grid-cols-1 gap-7 ${grids[section.columnsCount??3]}`}>{(section.items??[]).map((item,i)=><MotionItem key={item.id}><article data-cms-item={item.id} className="flex items-start gap-4"><span aria-hidden="true" className="font-serif text-5xl leading-none" style={{color:section.customColors?.eyebrowColor??'#cca5a6'}}>{String(i+1).padStart(2,'0')}</span><div className="min-w-0 flex-1">{item.imageUrl&&<ScopedImage section={section} targetId={`item:${item.id}:imageUrl`} src={item.imageUrl} alt={item.title} className="w-full aspect-[3/2] object-cover"/>}<h3 data-cms-text="itemTitle" data-cms-item={item.id} className="text-sm font-semibold mt-3" style={typographyStyle(section,'itemTitle')}>{item.title}</h3>{item.description&&<p data-cms-text="itemContent" data-cms-item={item.id} className="text-xs mt-1" style={typographyStyle(section,'itemContent')}>{item.description}</p>}</div></article></MotionItem>)}</div></div>;
}
