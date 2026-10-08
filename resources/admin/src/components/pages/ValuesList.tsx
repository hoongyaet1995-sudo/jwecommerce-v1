import {typographyStyle} from '../../cms/inspectorCapabilities';
import React from 'react';
import type {PageSection} from '../../types';
import {MotionItem} from './motion/CmsMotionWrapper';
import {richTextHasDarkBackground} from '../../cms/richTextPresets';

export function ValuesList({section}:{section:PageSection}) {
  const dark=richTextHasDarkBackground(section);
  const colors=section.customColors;
  const padding=section.paddingY==='compact'?'py-8 @min-[640px]:py-10':section.paddingY==='spacious'?'py-16 @min-[640px]:py-24':'py-12 @min-[640px]:py-16';
  const rowPadding=section.valuesRowSpacing==='compact'?'py-4 @min-[768px]:py-5':section.valuesRowSpacing==='spacious'?'py-8 @min-[768px]:py-10':'py-6 @min-[768px]:py-7';
  return <div data-values-list className="@container w-full">
    <div className={`${padding}`}>
      {section.subtitle && <MotionItem><span data-cms-text="eyebrow" style={{...({color:colors?.eyebrowColor}),...typographyStyle(section,'eyebrow')}} className="block mb-4 text-xs font-bold uppercase tracking-widest text-indigo-600">{section.subtitle}</span></MotionItem>}
      {section.valuesShowHeading && <div className="mb-8">
        <MotionItem><h2 data-cms-text="title" style={{...({color:colors?.titleColor}),...typographyStyle(section,'title')}} className={`text-3xl @min-[640px]:text-4xl font-extrabold tracking-tight whitespace-pre-line break-words ${dark?'text-white':'text-neutral-900'}`}>{section.title}</h2></MotionItem>
        {section.content && <MotionItem><p data-cms-text="content" style={{...({color:colors?.contentColor}),...typographyStyle(section,'content')}} className={`mt-4 max-w-[720px] text-base leading-relaxed whitespace-pre-line break-words ${dark?'text-neutral-300':'text-neutral-500'}`}>{section.content}</p></MotionItem>}
      </div>}
      <ol role="list" className="list-none m-0 p-0">{(section.items??[]).map((item,index)=><li data-value-id={item.id} key={item.id} style={{borderColor:section.valuesDividerColor}} className={`${section.valuesShowDividers===false?'':'border-b last:border-b-0'} ${dark?'border-white/15':'border-neutral-200'}`}>
        <MotionItem className={`grid grid-cols-[56px_minmax(0,1fr)] @min-[768px]:grid-cols-[96px_minmax(0,1fr)_minmax(0,1fr)] gap-x-5 @min-[768px]:gap-x-12 gap-y-3 ${rowPadding} items-start @min-[768px]:items-center`}>
          <span aria-hidden="true" data-value-number style={{color:section.valuesNumberColor}} className={`row-span-2 @min-[768px]:row-span-1 text-4xl @min-[768px]:text-7xl font-light leading-none tabular-nums ${dark?'text-neutral-500':'text-neutral-300'}`}>{String(index+1).padStart(2,'0')}</span>
          <h3 data-cms-text="itemTitle" data-cms-item={item.id} style={{...({color:colors?.titleColor}),...typographyStyle(section,'itemTitle')}} className={`min-w-0 text-xl @min-[768px]:text-2xl font-bold tracking-tight whitespace-pre-line break-words ${dark?'text-white':'text-neutral-900'}`}>{item.title}</h3>
          <p data-cms-text="itemContent" data-cms-item={item.id} style={{...({color:colors?.contentColor}),...typographyStyle(section,'itemContent')}} className={`min-w-0 text-sm @min-[768px]:text-base leading-relaxed whitespace-pre-line break-words ${dark?'text-neutral-300':'text-neutral-500'}`}>{item.description}</p>
        </MotionItem>
      </li>)}</ol>
    </div>
  </div>;
}
