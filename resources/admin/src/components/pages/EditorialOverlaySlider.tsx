import {SectionEyebrow} from './SectionEyebrow';
import React from 'react';
import {ArrowRight} from 'lucide-react';
import type {PageSection,SectionItem} from '../../types';
import {shopNavigationHref} from '../../cms/shopNavigation';
import {ScopedImage} from './ScopedImage';
import {typographyStyle} from '../../cms/inspectorCapabilities';

/** A reusable photographic carousel; text and actions remain editable CMS content. */
export function EditorialOverlaySlider({section,index,onSelect,onPause,isEditingMode}:{section:PageSection;index:number;onSelect:(index:number)=>void;onPause:(paused:boolean)=>void;isEditingMode:boolean}) {
 const fashion=section.sliderPreset==='fashion-editorial';
 const slides=section.items??[];
 const active=slides.length?index%slides.length:0;
 const slide:SectionItem|undefined=slides[active];
 const colors=section.customColors;
 const panoramic=section.sliderHeight==='panoramic';
 const height=panoramic?'min-h-[360px] @min-[640px]:min-h-[280px] @min-[1024px]:aspect-[5/1]':section.sliderHeight==='fullscreen'?'min-h-[85svh]':section.sliderHeight==='compact'?'min-h-[400px]':section.sliderHeight==='spacious'?'min-h-[660px]':'min-h-[540px]';
 const placement=section.textAlignment==='right'?'ml-auto':section.textAlignment==='center'?'mx-auto':'';
 return <div data-editorial-overlay className={`relative w-full overflow-hidden bg-stone-100 ${height}`} role="region" aria-roledescription="carousel" aria-label={section.title||'Featured collections'} onMouseEnter={()=>onPause(true)} onMouseLeave={()=>onPause(false)} onFocusCapture={()=>onPause(true)} onBlurCapture={event=>{if(!event.currentTarget.contains(event.relatedTarget as Node))onPause(false);}}>
  {slides.map((item,i)=><div key={item.id} aria-hidden={i!==active} className={`absolute inset-0 transition-opacity duration-700 motion-reduce:transition-none ${i===active?'opacity-100':'opacity-0 pointer-events-none'}`}>
   {item.videoUrl?<video src={item.videoUrl} poster={item.imageUrl} className="w-full h-full object-cover" muted playsInline loop autoPlay={!isEditingMode&&i===active}/>:item.imageUrl&&<ScopedImage section={section} targetId={`item:${item.id}:imageUrl`} src={item.imageUrl} alt={item.title||'Featured collection'} className="w-full h-full object-cover"/>}
  </div>)}
  <div aria-hidden="true" className="absolute inset-0 bg-black pointer-events-none" style={{opacity:(section.overlayOpacity??0)/100}}/>
  <div data-cms-hero-safe className={`relative pointer-events-none w-full ${panoramic?'py-6':'py-12 @min-[640px]:py-12'} ${section.containerMode==='full-bleed'?'max-w-[var(--cms-page-width)] mx-auto px-6 @min-[640px]:px-10 @min-[1024px]:px-16':''}`}>
   <div className={`pointer-events-auto max-w-[430px] ${panoramic?'p-6':'p-7 @min-[640px]:p-8'} ${fashion?'bg-transparent':'bg-[#faf8f1]/65'} ${placement}`} style={{textAlign:section.textAlignment??'left'}} aria-live={section.sliderAutoplay===false?'polite':'off'}>
    <SectionEyebrow section={section} item={slide}/>
    <h1 data-cms-text="title" data-cms-item={slide?.id} className="font-serif text-[42px] @min-[640px]:text-[56px] leading-[1.05] tracking-[-.025em] whitespace-pre-line mb-5" style={{color:colors?.titleColor??'#292b24',...typographyStyle(section,'title')}}>{slide?.title||(!slides.length?'Add your first slide':'')}</h1>
    {slide?.description&&<p data-cms-text="content" data-cms-item={slide.id} className="text-sm leading-relaxed whitespace-pre-line mb-6" style={{color:colors?.contentColor??'#66685d',...typographyStyle(section,'content')}}>{slide.description}</p>}
    <div className={fashion?`flex flex-col gap-4 ${section.textAlignment==='center'?'items-center':section.textAlignment==='right'?'items-end':'items-start'}`:`flex flex-wrap gap-4 items-center ${section.textAlignment==='center'?'justify-center':section.textAlignment==='right'?'justify-end':'justify-start'}`}>
     {slide?.buttonText&&slide.linkUrl&&<a data-cms-button="primary" data-cms-button-kind="button" data-cms-action="button" data-cms-item={slide.id} href={slide.linkUrl} onClick={event=>{if(isEditingMode)event.preventDefault();}} className="inline-flex gap-3 items-center px-5 py-3 text-xs font-semibold transition-opacity hover:opacity-80" style={{background:colors?.primaryBtnBg??'#454e37',color:colors?.primaryBtnText??'#ffffff'}}>{slide.buttonText}<ArrowRight size={14}/></a>}
     {slide?.secondaryButtonText&&slide.secondaryButtonLink&&<a data-cms-button="secondary" data-cms-button-kind="link" data-cms-action="button" data-cms-item={slide.id} href={slide.secondaryButtonLink} onClick={event=>{if(isEditingMode)event.preventDefault();}} className="inline-flex gap-2 items-center text-xs underline underline-offset-4 px-2 py-2" style={{background:colors?.secondaryBtnBg??'transparent',color:colors?.secondaryBtnText??'#454e37'}}>{slide.secondaryButtonText}<ArrowRight size={14}/></a>}
     {fashion&&slide?.thirdButtonText&&shopNavigationHref(slide.thirdButtonLink??'')&&<a data-cms-button="secondary" data-cms-button-kind="link" data-cms-action="button" data-cms-item={slide.id} href={shopNavigationHref(slide.thirdButtonLink??'')} onClick={e=>{if(isEditingMode)e.preventDefault()}} className="inline-flex gap-2 items-center text-xs py-2" style={{color:colors?.secondaryBtnText??'#ffffff'}}>{slide.thirdButtonText}<ArrowRight size={14}/></a>}
    </div>
   </div>
  </div>
  {(slides.length>1||slide?.caption)&&<div className={`${fashion?'absolute right-0 top-8 hidden @min-[640px]:flex':'relative'} flex items-end justify-between gap-6 pb-6 ${section.containerMode==='full-bleed'?'max-w-[var(--cms-page-width)] mx-auto px-6 @min-[640px]:px-10 @min-[1024px]:px-16':''}`}>
   <div className={slides.length>1?"flex gap-2 p-2 bg-white/75 rounded-full":""}>{slides.length>1&&slides.map((item,i)=><button type="button" key={item.id} aria-label={`Show slide ${i+1}`} aria-pressed={i===active} className={`h-2.5 rounded-full transition-all ${i===active?'w-6 bg-stone-800':'w-2.5 bg-stone-400 hover:bg-stone-600'}`} onClick={event=>{event.stopPropagation();onSelect(i);}}/>)}</div>
   {slide?.caption&&<p data-cms-item={slide.id} data-cms-text="content" className={`max-w-[240px] text-right text-white whitespace-pre-line ${fashion?'font-sans uppercase text-xs tracking-[.25em] leading-[2]':'font-serif text-lg leading-tight'}`} style={{textShadow:'0 1px 4px rgba(0,0,0,.7)',...(fashion?{color:colors?.eyebrowColor??'#151515'}:typographyStyle(section,'content'))}}>{slide.caption}</p>}
  </div>}
 </div>;
}
