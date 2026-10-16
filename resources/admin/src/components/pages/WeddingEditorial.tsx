import React from 'react';
import type {PageSection} from '../../types';
import {ScopedImage} from './ScopedImage';
import {typographyStyle} from '../../cms/inspectorCapabilities';
import {EditorialSectionAction} from './EditorialSectionAction';
import {MotionItem} from './motion/CmsMotionWrapper';
export function EditorialGradientHero({section,editing=false,isPageHeading=true}:{section:PageSection;editing?:boolean;isPageHeading?:boolean}){
 const Heading=isPageHeading?'h1':'h2';
 const alignment=section.textAlignment??'left';
 const spacing=section.paddingY==='compact'?'py-12':section.paddingY==='spacious'?'py-24':'py-16';
 return <div data-editorial-gradient-hero className="@container relative min-h-[420px] @min-[1024px]:min-h-[600px] flex items-center overflow-hidden">
  {section.mediaUrl&&<ScopedImage section={section} targetId="mediaUrl" src={section.mediaUrl} alt={section.title} className="absolute inset-0 w-full h-full object-cover"/>}
  <div className="absolute inset-0 pointer-events-none" style={{background:`linear-gradient(90deg, ${section.customColors?.customBgColor??'#F7F4EE'} 0%, ${section.customColors?.customBgColor??'#F7F4EE'}CC 30%, transparent 75%)`}}/>
  <div data-cms-hero-safe className={`relative w-full ${spacing} ${section.containerMode==='full-bleed'?'max-w-[var(--cms-page-width)] mx-auto':''}`} style={{textAlign:alignment,...(section.containerMode==='full-bleed'?{paddingInline:'var(--cms-page-gutter, clamp(24px, 4vw, 64px))',boxSizing:'border-box' as const}:{})}}>
   {section.subtitle&&<MotionItem><p data-cms-text="eyebrow" className="text-xs tracking-widest uppercase mb-6" style={{color:section.customColors?.eyebrowColor,...typographyStyle(section,'eyebrow')}}>{section.subtitle}</p></MotionItem>}
   <MotionItem><Heading data-cms-text="title" className={`text-5xl @min-[1024px]:text-7xl leading-none max-w-2xl whitespace-pre-line ${alignment==='center'?'mx-auto':alignment==='right'?'ml-auto':''}`} style={{color:section.customColors?.titleColor,...typographyStyle(section,'title')}}>{section.title}</Heading></MotionItem>
   {section.content&&<MotionItem><p data-cms-text="content" className={`mt-6 max-w-lg whitespace-pre-line ${alignment==='center'?'mx-auto':alignment==='right'?'ml-auto':''}`} style={{color:section.customColors?.contentColor,...typographyStyle(section,'content')}}>{section.content}</p></MotionItem>}
   <div className={`flex flex-wrap gap-6 items-center mt-8 ${alignment==='center'?'justify-center':alignment==='right'?'justify-end':''}`}><EditorialSectionAction section={section} isEditingMode={editing}/>{section.secondaryButtonText&&section.secondaryButtonLink&&<EditorialSectionAction section={{...section,buttonText:section.secondaryButtonText,buttonLink:section.secondaryButtonLink,buttonStyle:'text-link'}} isEditingMode={editing}/>}</div>
  </div>
 </div>;
}
export function EditorialQuote({section}:{section:PageSection}){
 return <div data-editorial-quote className={`@container ${section.paddingY==='compact'?'py-10':section.paddingY==='spacious'?'py-24':'py-12 @min-[1024px]:py-16'}`} style={{textAlign:section.textAlignment??'center'}}>
 {section.subtitle&&<p data-cms-text="eyebrow" className="text-xs uppercase tracking-widest mb-5" style={{color:section.customColors?.eyebrowColor,...typographyStyle(section,'eyebrow')}}>{section.subtitle}</p>}
 <div data-cms-text="title">{section.title&&<h2 className="text-3xl mb-4" style={{color:section.customColors?.titleColor,...typographyStyle(section,'title')}}>{section.title}</h2>}</div>
 {section.content&&<p data-cms-text="content" className="mb-5" style={{color:section.customColors?.contentColor,...typographyStyle(section,'content')}}>{section.content}</p>}
 {(section.items??[]).map(item=><figure key={item.id} className="my-5"><blockquote data-cms-text="itemContent" data-cms-item={item.id} className="text-3xl @min-[1024px]:text-5xl whitespace-pre-line" style={{color:section.customColors?.contentColor,...typographyStyle(section,'itemContent')}}>“{item.description}”</blockquote><figcaption data-cms-text="itemTitle" data-cms-item={item.id} className="text-xs mt-5" style={{color:section.customColors?.titleColor,...typographyStyle(section,'itemTitle')}}>{item.title}</figcaption></figure>)}
 </div>;
}
