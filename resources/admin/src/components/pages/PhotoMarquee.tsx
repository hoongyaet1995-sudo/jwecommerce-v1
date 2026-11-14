import {SectionEyebrow} from './SectionEyebrow';
import React,{useState} from 'react';
import {Play,Pause} from 'lucide-react';
import type {PageSection} from '../../types';
import {ScopedImage} from './ScopedImage';
import {MediaWidthRegion} from './MediaWidthRegion';
import {typographyStyle} from '../../cms/inspectorCapabilities';
import './PhotoMarquee.css';
/** Shared gallery presentation: no product or CMS persistence dependencies. */
export function PhotoMarquee({section,editing=false}:{section:PageSection;editing?:boolean}){
 const [paused,setPaused]=useState(false);
 const photos=(section.items??[]).filter(item=>item.imageUrl);
 const renderPhotos=(duplicate=false)=>photos.map(item=><div key={item.id} className="cms-photo-marquee-photo" data-cms-item={duplicate?undefined:item.id}><ScopedImage section={section} targetId={`item:${item.id}:imageUrl`} src={item.imageUrl} alt={duplicate?'':item.title??''} className="h-full w-auto max-w-none object-contain"/></div>);
 return <div className={`@container w-full ${section.paddingY==='spacious'?'py-20':section.paddingY==='normal'?'py-12':'py-6'}`} data-photo-marquee>
  <div className="flex justify-between items-center gap-4 mb-4" style={{textAlign:section.textAlignment}}>
   <div>{section.showSectionHeading!==false&&<><SectionEyebrow section={section}/><h2 data-cms-text="title" className="text-3xl whitespace-pre-line" style={{color:section.customColors?.titleColor,...typographyStyle(section,'title')}}>{section.title}</h2>{section.content&&<p data-cms-text="content" style={{color:section.customColors?.contentColor,...typographyStyle(section,'content')}}>{section.content}</p>}</>}{section.subtitle&&<p data-cms-text="label" className="text-xs tracking-widest uppercase" style={{color:section.customColors?.eyebrowColor,...typographyStyle(section,'label')}}>{section.subtitle}</p>}</div>
   <button type="button" aria-label={paused?'Play photo marquee':'Pause photo marquee'} aria-pressed={paused} className="cms-photo-marquee-toggle border rounded-full w-8 h-8 shrink-0" onClick={()=>setPaused(value=>!value)}>{paused?<Play size={15} strokeWidth={1.7} aria-hidden="true"/>:<Pause size={15} strokeWidth={1.7} aria-hidden="true"/>}</button>
  </div>
  <MediaWidthRegion section={section}><div className={`cms-photo-marquee ${section.marqueePauseOnHover!==false?'cms-photo-marquee-hover':''}`} data-paused={paused||editing||section.marqueeSpeed===0} style={{'--marquee-duration':`${section.marqueeSpeed??40}s`,'--marquee-direction':section.marqueeDirection==='right'?'reverse':'normal'} as React.CSSProperties} tabIndex={0} aria-label="Photo gallery — scroll to explore">
   <div className="cms-photo-marquee-track"><div className="cms-photo-marquee-group">{renderPhotos()}</div><div className="cms-photo-marquee-group cms-photo-marquee-duplicate" aria-hidden="true">{renderPhotos(true)}</div></div>
  </div></MediaWidthRegion>
 </div>;
}
