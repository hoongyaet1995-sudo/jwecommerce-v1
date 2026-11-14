import {SectionEyebrow} from './SectionEyebrow';
import {MediaWidthRegion} from './MediaWidthRegion';
import {typographyStyle} from '../../cms/inspectorCapabilities';
import React from 'react';
import {EditorialSectionAction} from './EditorialSectionAction';
import {SectionImage,hasCustomImageGeometry} from './SectionImage';
import type {PageSection} from '../../types';
import {MotionItem, MotionMedia} from './motion/CmsMotionWrapper';

export function EditorialHero({section,isPageHeading,isEditingMode}: {
  section: PageSection; isPageHeading: boolean; isEditingMode: boolean;
}) {
  const Heading = isPageHeading ? 'h1' : 'h2';
  const colors=section.customColors;
  const fullMedia=section.mediaContainerMode==='full-bleed';
  const left=section.mediaPosition==='left';
  const spacing=section.paddingY==='compact' ? 'py-8 @min-[1024px]:py-10' : section.paddingY==='spacious' ? 'py-16 @min-[1024px]:py-24' : 'py-12 @min-[1024px]:py-16';
  return <div data-editorial-hero className="@container w-full">
    <div className={`grid grid-cols-1 ${fullMedia?'':left ? '@min-[1024px]:grid-cols-[minmax(0,56fr)_minmax(0,44fr)]' : '@min-[1024px]:grid-cols-[minmax(0,44fr)_minmax(0,56fr)]'} items-stretch gap-8 @min-[1024px]:gap-16 ${spacing}`}>
      <div className={`flex flex-col justify-center py-8 @min-[1024px]:py-12 ${fullMedia?'order-1':left ? '@min-[1024px]:order-2' : '@min-[1024px]:order-1'}`}>
        {<MotionItem><SectionEyebrow section={section}/></MotionItem>}
        <MotionItem><Heading data-cms-text="title" style={{...({color:colors?.titleColor}),...typographyStyle(section,'title')}} className="text-4xl @min-[640px]:text-5xl @min-[1280px]:text-6xl leading-[1.04] font-extrabold tracking-tight whitespace-pre-line break-words">{section.title}</Heading></MotionItem>
        {section.leadText && <MotionItem><p data-cms-text="lead" style={{color:colors?.contentColor,...typographyStyle(section,'lead')}} className="mt-6 max-w-md text-lg leading-relaxed">{section.leadText}</p></MotionItem>}
        {section.content && <MotionItem><p data-cms-text="content" style={{...({color:colors?.contentColor}),...typographyStyle(section,'content')}} className="mt-6 max-w-md text-base leading-relaxed whitespace-pre-line text-neutral-500">{section.content}</p></MotionItem>}
        {((section.buttonText&&section.buttonLink)||(section.secondaryButtonText&&section.secondaryButtonLink))&&<div className="mt-8 flex flex-wrap items-center gap-6"><EditorialSectionAction section={section} isEditingMode={isEditingMode}/>{section.secondaryButtonText&&section.secondaryButtonLink&&<EditorialSectionAction actionChannel="secondary" section={{...section,buttonText:section.secondaryButtonText,buttonLink:section.secondaryButtonLink,buttonStyle:'text-link'}} isEditingMode={isEditingMode}/>}</div>}
      </div>
      {section.mediaUrl && <MediaWidthRegion section={section} className={fullMedia?'order-2':left?'@min-[1024px]:order-1':'@min-[1024px]:order-2'}><MotionMedia className={`min-w-0 ${hasCustomImageGeometry(section)?'':'aspect-square @min-[1024px]:aspect-auto @min-[1024px]:h-[620px]'} overflow-hidden ${left ? '@min-[1024px]:order-1' : '@min-[1024px]:order-2'}`}>
        <SectionImage section={section} />
      </MotionMedia></MediaWidthRegion>}
    </div>
  </div>;
}
