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
  const left=section.mediaPosition==='left';
  const spacing=section.paddingY==='compact' ? 'py-8 @min-[1024px]:py-10' : section.paddingY==='spacious' ? 'py-16 @min-[1024px]:py-24' : 'py-12 @min-[1024px]:py-16';
  return <div data-editorial-hero className="@container w-full">
    <div className={`grid grid-cols-1 ${left ? '@min-[1024px]:grid-cols-[minmax(0,56fr)_minmax(0,44fr)]' : '@min-[1024px]:grid-cols-[minmax(0,44fr)_minmax(0,56fr)]'} items-stretch gap-8 @min-[1024px]:gap-16 ${spacing}`}>
      <div className={`flex flex-col justify-center py-8 @min-[1024px]:py-12 ${left ? '@min-[1024px]:order-2' : '@min-[1024px]:order-1'}`}>
        {section.subtitle && <MotionItem><span data-cms-text="eyebrow" style={{...({color:colors?.eyebrowColor}),...typographyStyle(section,'eyebrow')}} className="block text-xs font-bold uppercase tracking-widest text-indigo-600 mb-6">{section.subtitle}</span></MotionItem>}
        <MotionItem><Heading data-cms-text="title" style={{...({color:colors?.titleColor}),...typographyStyle(section,'title')}} className="text-4xl @min-[640px]:text-5xl @min-[1280px]:text-6xl leading-[1.04] font-extrabold tracking-tight whitespace-pre-line break-words">{section.title}</Heading></MotionItem>
        {section.content && <MotionItem><p data-cms-text="content" style={{...({color:colors?.contentColor}),...typographyStyle(section,'content')}} className="mt-6 max-w-md text-base leading-relaxed whitespace-pre-line text-neutral-500">{section.content}</p></MotionItem>}
        {section.buttonText && section.buttonLink && <div className="mt-8"><EditorialSectionAction section={section} isEditingMode={isEditingMode} /></div>}
      </div>
      {section.mediaUrl && <MotionMedia className={`min-w-0 ${hasCustomImageGeometry(section)?'':'aspect-square @min-[1024px]:aspect-auto @min-[1024px]:h-[620px]'} overflow-hidden ${left ? '@min-[1024px]:order-1' : '@min-[1024px]:order-2'}`}>
        <SectionImage section={section} />
      </MotionMedia>}
    </div>
  </div>;
}
