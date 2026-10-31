import React from 'react';
import type {PageSection} from '../../types';
import {ScopedImage} from './ScopedImage';
import {typographyStyle} from '../../cms/inspectorCapabilities';
import './PartnerLogos.css';

export function PartnerLogos({section,editing=false}:{section:PageSection;editing?:boolean}) {
 const items=(section.items??[]).filter(item=>item.imageUrl);
 const scrolling=items.length>=4&&(section.marqueeSpeed??32)>0;
 const group=(duplicate=false)=>items.map(item=><div className="cms-partner-logo" key={item.id} data-cms-item={duplicate?undefined:item.id}>{item.linkUrl&&item.linkUrl!=='#'&&!editing?<a href={item.linkUrl} target="_blank" rel="noopener noreferrer"><ScopedImage section={section} targetId={`item:${item.id}:imageUrl`} src={item.imageUrl} alt={duplicate?'':item.title||'Brand logo'}/></a>:<ScopedImage section={section} targetId={`item:${item.id}:imageUrl`} src={item.imageUrl} alt={duplicate?'':item.title||'Brand logo'}/>}</div>);
 return <div className={`cms-partners ${section.paddingY==='spacious'?'py-20':section.paddingY==='normal'?'py-12':'py-8'}`} style={{'--partner-height':`${section.logoHeight??42}px`,'--partner-gap':`${section.logoGap??48}px`,'--partner-duration':`${section.marqueeSpeed??32}s`,'--partner-direction':section.marqueeDirection==='right'?'reverse':'normal'} as React.CSSProperties}>
  {section.subtitle&&<span data-cms-text="eyebrow" style={{color:section.customColors?.eyebrowColor||'#718168',...typographyStyle(section,'eyebrow')}} className="block text-[10px] tracking-[.18em] uppercase mb-2 opacity-65">{section.subtitle}</span>}
  <h2 data-cms-text="title" className="text-2xl font-semibold tracking-tight" style={{color:section.customColors?.titleColor||'#233c2d',...typographyStyle(section,'title')}}>{section.title}</h2>
  {section.content&&<p data-cms-text="content" className="mt-3 text-sm opacity-65" style={{color:section.customColors?.contentColor||'#74806f',...typographyStyle(section,'content')}}>{section.content}</p>}
  {!items.length&&editing?<p className="mt-8 text-sm opacity-60">Add your brand logos in Content → Images.</p>:<div className="cms-partner-window mt-8" data-scroll={scrolling&&!editing} data-hover={section.marqueePauseOnHover!==false} tabIndex={scrolling?0:undefined} aria-label="Brand logos"><div className="cms-partner-track"><div className="cms-partner-group">{group()}</div>{scrolling&&!editing&&<div className="cms-partner-group" aria-hidden="true" inert>{group(true)}</div>}</div></div>}
 </div>;
}
