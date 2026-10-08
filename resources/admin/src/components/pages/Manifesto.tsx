import {typographyStyle} from '../../cms/inspectorCapabilities';
import React from 'react';
import type {PageSection} from '../../types';
import {MotionItem} from './motion/CmsMotionWrapper';
import {richTextHasDarkBackground} from '../../cms/richTextPresets';

export function Manifesto({section}:{section:PageSection}) {
  const paragraphs=(section.content??'').split(/\r?\n[ \t]*\r?\n+/).filter(paragraph=>paragraph.trim());
  const padding=section.paddingY==='compact'?'py-8 @min-[640px]:py-10':section.paddingY==='spacious'?'py-16 @min-[640px]:py-24':'py-12 @min-[640px]:py-16';
  const light=richTextHasDarkBackground(section);
  return <div data-editorial-manifesto className="@container w-full">
    <div className={`text-center ${padding}`}>
      {section.subtitle && <MotionItem><span data-cms-text="eyebrow" style={{...({color:section.customColors?.eyebrowColor}),...typographyStyle(section,'eyebrow')}} className="block mb-6 text-xs font-bold uppercase tracking-widest text-indigo-600">{section.subtitle}</span></MotionItem>}
      <MotionItem><h2 data-cms-text="title" style={{...({color:section.customColors?.titleColor}),...typographyStyle(section,'title')}} className={`text-3xl @min-[640px]:text-4xl font-extrabold leading-[1.12] tracking-tight whitespace-pre-line break-words ${light?'text-white':'text-neutral-900'}`}>{section.title}</h2></MotionItem>
      {paragraphs.length>0 && <div className="max-w-[720px] mx-auto mt-6 space-y-5">{paragraphs.map((paragraph,index)=><MotionItem key={index}><p data-cms-text="content" style={{...({color:section.customColors?.contentColor}),...typographyStyle(section,'content')}} className={`text-base leading-relaxed whitespace-pre-line break-words ${light?'text-neutral-300':'text-neutral-500'}`}>{paragraph}</p></MotionItem>)}</div>}
    </div>
  </div>;
}
