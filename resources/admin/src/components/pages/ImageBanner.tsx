import {SectionEyebrow} from './SectionEyebrow';
import {typographyStyle} from '../../cms/inspectorCapabilities';
import React from 'react';
import type {PageSection} from '../../types';
import {ScopedImage} from './ScopedImage';
import {EditorialSectionAction} from './EditorialSectionAction';
import {MotionItem,MotionButton} from './motion/CmsMotionWrapper';
export function ImageBanner({section,isEditingMode}:{section:PageSection;isEditingMode:boolean}) {
 const height=section.ctaBannerHeight==='compact'?'min-h-[240px]':section.ctaBannerHeight==='spacious'?'min-h-[440px]':'min-h-[320px]';
 const colors=section.customColors;
 const spacing=section.paddingY==='compact'?'py-8':section.paddingY==='spacious'?'py-20':'py-14';
 return <div data-image-banner className={`@container w-full ${spacing}`}>
  <div className={`relative isolate overflow-hidden bg-neutral-800 text-white flex items-center ${height}`}>
   {section.mediaUrl && <ScopedImage section={section} targetId="mediaUrl" src={section.mediaUrl} alt="" className="absolute inset-0 w-full h-full object-cover"/>}
   <div className={`relative z-10 pointer-events-none w-full mx-auto py-10 px-6 @min-[640px]:px-10 ${section.containerMode==='full-bleed'?'max-w-[var(--cms-page-width)] @min-[1024px]:px-16':''}`}><div className="max-w-[440px]">
    {<MotionItem><SectionEyebrow section={section}/></MotionItem>}
    <MotionItem><h2 data-cms-text="title" style={{...({color:colors?.titleColor}),...typographyStyle(section,'title')}} className="text-3xl @min-[640px]:text-4xl font-extrabold tracking-tight leading-[1.08] whitespace-pre-line break-words">{section.title}</h2></MotionItem>
    {section.content && <MotionItem><p data-cms-text="content" style={{...({color:colors?.contentColor}),...typographyStyle(section,'content')}} className="mt-5 text-sm leading-relaxed whitespace-pre-line break-words">{section.content}</p></MotionItem>}
    <div className="mt-6 flex flex-wrap gap-4 items-center pointer-events-auto">
     <EditorialSectionAction section={section} isEditingMode={isEditingMode}/>
     {section.secondaryButtonText && section.secondaryButtonLink && <MotionButton><a data-cms-button="secondary" data-cms-button-kind="button" data-cms-action="button" href={section.secondaryButtonLink} onClick={event=>isEditingMode&&event.preventDefault()} style={{color:colors?.secondaryBtnText,backgroundColor:colors?.secondaryBtnBg}} className="inline-flex px-6 py-3 rounded-lg border border-white/50 text-white font-bold text-sm hover:opacity-80">{section.secondaryButtonText}</a></MotionButton>}
    </div>
   </div></div>
  </div>
 </div>;
}
