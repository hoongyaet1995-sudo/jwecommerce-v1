import {typographyStyle} from '../../cms/inspectorCapabilities';
import React from 'react';
import type {PageSection} from '../../types';
import {SectionImage,hasCustomImageGeometry} from './SectionImage';
import {MotionItem,MotionMedia} from './motion/CmsMotionWrapper';
import {EditorialSectionAction} from './EditorialSectionAction';
const ratios={'4:3':'aspect-[4/3]','16:9':'aspect-video','1:1':'aspect-square',auto:'aspect-auto'};
export function EditorialStory({section,isEditingMode}:{section:PageSection;isEditingMode:boolean}) {
  const left=section.mediaPosition!=='right';
  const colors=section.customColors;
  const paragraphs=(section.content??'').split(/\r?\n[ \t]*\r?\n+/).filter(p=>p.trim());
  const ratio=section.mediaAspectRatio??'4:3';
  const spacing=section.paddingY==='compact'?'py-8 @min-[1024px]:py-10':section.paddingY==='spacious'?'py-20 @min-[1024px]:py-28':'py-14 @min-[1024px]:py-16';
  return <div data-editorial-story className="@container w-full">
    <div className={`grid grid-cols-1 gap-8 @min-[1024px]:gap-16 items-center ${spacing} ${section.mediaUrl?(left?'@min-[1024px]:grid-cols-[minmax(0,11fr)_minmax(0,9fr)]':'@min-[1024px]:grid-cols-[minmax(0,9fr)_minmax(0,11fr)]'):''}`}>
      {section.mediaUrl && <MotionMedia className={`min-w-0 overflow-hidden ${hasCustomImageGeometry(section)?'':ratios[ratio]} ${left?'@min-[1024px]:order-1':'@min-[1024px]:order-2'}`}>
        <SectionImage section={section} className={ratio==='auto'?'':'h-full'} />
      </MotionMedia>}
      <div className={`max-w-xl min-w-0 ${left?'@min-[1024px]:order-2':'@min-[1024px]:order-1'}`}>
        {section.subtitle && <MotionItem><span data-cms-text="eyebrow" style={{...({color:colors?.eyebrowColor}),...typographyStyle(section,'eyebrow')}} className="block text-xs font-bold uppercase tracking-widest text-indigo-600 mb-6">{section.subtitle}</span></MotionItem>}
        <MotionItem><h2 data-cms-text="title" style={{...({color:colors?.titleColor}),...typographyStyle(section,'title')}} className="text-3xl @min-[640px]:text-4xl @min-[1024px]:text-5xl font-extrabold leading-[1.08] tracking-tight whitespace-pre-line break-words">{section.title}</h2></MotionItem>
        {paragraphs.length>0 && <div className="mt-6 space-y-5 text-base leading-relaxed">{paragraphs.map((paragraph,index)=><MotionItem key={index}><p data-cms-text="content" style={{...({color:colors?.contentColor}),...typographyStyle(section,'content')}} className="whitespace-pre-line break-words text-neutral-500">{paragraph}</p></MotionItem>)}</div>}
        {section.buttonText && section.buttonLink && <div className="mt-8"><EditorialSectionAction section={section} isEditingMode={isEditingMode} /></div>}
      </div>
    </div>
  </div>;
}
