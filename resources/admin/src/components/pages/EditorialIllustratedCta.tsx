import {saasAction} from '../../cms/saasPackages';
import React from 'react';
import {ArrowRight} from 'lucide-react';
import type {PageSection} from '../../types';
import {ScopedImage} from './ScopedImage';
import {typographyStyle} from '../../cms/inspectorCapabilities';
import {shopNavigationHref} from '../../cms/shopNavigation';
import './EditorialIllustratedCta.css';
/** Presentation only: authored fields and resolved Page Settings are shared with every CTA strip. */
export function EditorialIllustratedCta({section:s,editing=false}:{section:PageSection;editing?:boolean}){
 const c=s.customColors,href=shopNavigationHref(s.buttonLink??'');
 const style={'--editorial-cta-primary':c?.primaryBtnBg||'currentColor','--editorial-cta-secondary':c?.secondaryBtnBg||c?.primaryBtnBg||'currentColor','--editorial-cta-ink':c?.primaryBtnText||'inherit','--editorial-cta-paper':c?.customBgColor||'transparent','--editorial-cta-space':s.paddingY==='compact'?'32px':s.paddingY==='spacious'?'96px':'64px'} as React.CSSProperties;
 return <div className="editorial-illustrated-cta" style={style}><div className={`editorial-illustrated-cta-panel ${s.mediaUrl?'':'without-image'}`}>
  <div className="editorial-illustrated-cta-copy">
   {s.subtitle&&<p className="editorial-illustrated-cta-eyebrow" data-cms-text="eyebrow" style={{color:c?.eyebrowColor,...typographyStyle(s,'eyebrow')}}>{s.subtitle}</p>}
   <h2 data-cms-text="title" style={{color:c?.titleColor,...typographyStyle(s,'title')}}>{s.title}</h2>
   {s.content&&<p className="editorial-illustrated-cta-body" data-cms-text="content" style={{color:c?.contentColor,...typographyStyle(s,'content')}}>{s.content}</p>}
   {s.buttonText&&href&&<a data-cms-action="button" href={href} onClick={e=>saasAction(e,href,editing)}>{s.buttonText}<ArrowRight size={20} aria-hidden="true"/></a>}
  </div>
  {s.mediaUrl&&<div className="editorial-illustrated-cta-art"><ScopedImage section={s} targetId="mediaUrl" src={s.mediaUrl} alt={s.mediaAlt??''}/></div>}
 </div></div>;
}
