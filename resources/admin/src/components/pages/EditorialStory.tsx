import {SectionEyebrow} from './SectionEyebrow';
import {MediaWidthRegion} from './MediaWidthRegion';
import {typographyStyle} from '../../cms/inspectorCapabilities';
import React from 'react';
import type {PageSection} from '../../types';
import {SectionImage,hasCustomImageGeometry} from './SectionImage';
import {MotionItem,MotionMedia} from './motion/CmsMotionWrapper';
import {EditorialSectionAction} from './EditorialSectionAction';
const ratios={'4:3':'aspect-[4/3]','16:9':'aspect-video','1:1':'aspect-square',auto:'aspect-auto'};
export function EditorialStory({section,isEditingMode}:{section:PageSection;isEditingMode:boolean}) {
  const fullMedia=section.mediaContainerMode==='full-bleed';
  const left=section.mediaPosition!=='right';
  const colors=section.customColors;
  const paragraphs=(section.content??'').split(/\r?\n[ \t]*\r?\n+/).filter(p=>p.trim());
  const ratio=section.mediaAspectRatio??'4:3';
  const spacing=section.paddingY==='compact'?'py-8 @min-[1024px]:py-10':section.paddingY==='spacious'?'py-20 @min-[1024px]:py-28':'py-14 @min-[1024px]:py-16';
  return <div data-editorial-story className="@container w-full">
    <div className={`grid grid-cols-1 gap-8 @min-[1024px]:gap-16 items-center ${spacing} ${section.mediaUrl&&!fullMedia?(left?'@min-[1024px]:grid-cols-[minmax(0,11fr)_minmax(0,9fr)]':'@min-[1024px]:grid-cols-[minmax(0,9fr)_minmax(0,11fr)]'):''}`}>
      {section.mediaUrl && <MediaWidthRegion section={section} className={fullMedia?'order-2':left?'@min-[1024px]:order-1':'@min-[1024px]:order-2'}><MotionMedia className={`min-w-0 overflow-hidden ${hasCustomImageGeometry(section)?'':ratios[ratio]} ${left?'@min-[1024px]:order-1':'@min-[1024px]:order-2'}`}>
        <SectionImage section={section} className={ratio==='auto'?'':'h-full'} />
      </MotionMedia></MediaWidthRegion>}
      <div className={`max-w-xl min-w-0 ${fullMedia?'order-1':left?'@min-[1024px]:order-2':'@min-[1024px]:order-1'}`}>
        {<MotionItem><SectionEyebrow section={section}/></MotionItem>}
        <MotionItem><h2 data-cms-text="title" style={{...({color:colors?.titleColor}),...typographyStyle(section,'title')}} className="text-3xl @min-[640px]:text-4xl @min-[1024px]:text-5xl font-extrabold leading-[1.08] tracking-tight whitespace-pre-line break-words">{section.title}</h2></MotionItem>
        {paragraphs.length>0 && <div className="mt-6 space-y-5 text-base leading-relaxed">{paragraphs.map((paragraph,index)=><MotionItem key={index}><p data-cms-text="content" style={{...({color:colors?.contentColor}),...typographyStyle(section,'content')}} className="whitespace-pre-line break-words text-neutral-500">{paragraph}</p></MotionItem>)}</div>}
        {section.layoutPreset==='story-with-steps'&&<div className="mt-8 grid grid-cols-1 @min-[480px]:grid-cols-2 @min-[1024px]:grid-cols-3 gap-4">{section.items?.map((item,i)=><MotionItem key={item.id} independentReveal><div className={i?'border-l border-neutral-200 pl-4':''}><span style={{color:colors?.titleColor}} className="block font-bold text-3xl">{String(i+1).padStart(2,'0')}</span><h3 data-cms-text="itemTitle" data-cms-item={item.id} style={{color:colors?.titleColor,...typographyStyle(section,'itemTitle')}} className="mt-2 text-base leading-snug whitespace-pre-line">{item.title}</h3><p data-cms-text="itemContent" data-cms-item={item.id} style={{color:colors?.contentColor,...typographyStyle(section,'itemContent')}} className="mt-1 text-sm leading-relaxed">{item.description}</p></div></MotionItem>)}</div>}
        {section.buttonText && section.buttonLink && <div className="mt-8"><EditorialSectionAction section={section} isEditingMode={isEditingMode} /></div>}
      </div>
    </div>
  </div>;
}
