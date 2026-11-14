import {SectionEyebrow} from './SectionEyebrow';
import {sectionEyebrow} from '../../cms/sectionHeading';
import React,{useId,useState} from 'react';
import type {PageSection} from '../../types';
import {fixedImageCardItems} from '../../cms/imageCardsThree';
import {typographyStyle} from '../../cms/inspectorCapabilities';
import {ScopedImage} from './ScopedImage';
import './ImageFlow.css';

export function ImageFlow({section:s,editing=false}:{section:PageSection;editing?:boolean}){
 const [paused,setPaused]=useState(false),uid=`flow-${s.id.replace(/[^a-zA-Z0-9_-]/g,'')}-${useId().replace(/[^a-zA-Z0-9_-]/g,'')}`;
 const items=fixedImageCardItems(s);
 return <div className={`image-flow ${paused?'is-paused':''}`} data-image-flow data-item-layout={s.itemLayoutMode} data-item-count={items.length} style={{'--flow-primary':s.customColors?.primaryBtnBg||'currentColor','--flow-secondary':s.customColors?.secondaryBtnBg||'currentColor','--flow-space':s.paddingY==='compact'?'32px':s.paddingY==='spacious'?'96px':'64px',backgroundColor:s.customColors?.customBgColor} as React.CSSProperties}>
  {(s.title||s.content||sectionEyebrow(s))&&<header className="image-flow-heading"><SectionEyebrow section={s}/>{s.title&&<h2 data-cms-text="title" style={{color:s.customColors?.titleColor,...typographyStyle(s,'title')}}>{s.title}</h2>}{s.content&&<p data-cms-text="content" style={{color:s.customColors?.contentColor,...typographyStyle(s,'content')}}>{s.content}</p>}</header>}
  <div className="image-flow-stage"><button type="button" className="image-flow-toggle" aria-pressed={paused} onClick={()=>setPaused(!paused)}>{paused?'Play motion':'Pause motion'}</button><div className="image-flow-grid">{items.map((item,index)=><React.Fragment key={item.id}>
   <figure className="image-flow-card" data-cms-item={item.id} style={{animationDelay:`${-index*2.4}s`}}>{item.imageUrl?<ScopedImage section={s} targetId={`item:${item.id}:imageUrl`} src={item.imageUrl} alt=""/>:editing?<div className="image-flow-empty">Add image {index+1} in Content → Images.</div>:<div className="image-flow-empty" aria-hidden="true"/>}</figure>
   {index<items.length-1&&<div className={`image-flow-arrow image-flow-arrow-${index}`} aria-hidden="true"><svg viewBox="0 0 64 24"><defs><linearGradient id={`${uid}-${index}`}><stop stopColor="var(--flow-primary)"/><stop offset="1" stopColor="var(--flow-secondary)"/></linearGradient></defs><path className="image-flow-track" d="M4 12H56"/><path className="image-flow-head" d="m50 6 6 6-6 6"/><path className="image-flow-stream" stroke={`url(#${uid}-${index})`} d="M4 12H56"/></svg></div>}
  </React.Fragment>)}</div></div>
 </div>;
}
