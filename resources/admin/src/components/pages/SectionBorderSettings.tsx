import {VisualChoiceSelect} from './VisualChoiceSelect';
import React from 'react';
import type {PageSection} from '../../types';
export function SectionBorderSettings({section,onChange}:{section:PageSection;onChange:(patch:Partial<PageSection>)=>void}) {
 const color=section.sectionBorderColor??'#e5e5e5';
 const swatch=/^#[0-9a-f]{6}$/i.test(color)?color:/^#[0-9a-f]{3}$/i.test(color)?'#'+color.slice(1).split('').map(c=>c+c).join(''):'#e5e5e5';
 return <div className="space-y-3">
  <label className="block text-xs text-neutral-600 dark:text-neutral-400">Section borders<VisualChoiceSelect aria-label="Section borders" value={section.sectionBorder??'default'} onChange={event=>onChange({sectionBorder:event.target.value as PageSection['sectionBorder']})} className="w-full mt-1 px-3 py-2 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/60 text-neutral-900 dark:text-white"><option value="default">Default (background style)</option><option value="none">None</option><option value="top">Top</option><option value="bottom">Bottom</option><option value="both">Both</option></VisualChoiceSelect></label>
  {section.sectionBorder && !['default','none'].includes(section.sectionBorder) && <div className="flex items-center justify-between gap-2"><span className="text-xs text-neutral-600 dark:text-neutral-400">Border color</span><div className="flex items-center gap-2"><input type="color" aria-label="Section border color picker" value={swatch} onChange={event=>onChange({sectionBorderColor:event.target.value})} className="w-7 h-7 rounded-lg border border-neutral-300 dark:border-neutral-600 cursor-pointer bg-transparent"/><input aria-label="Section border color Hex" value={color} onChange={event=>onChange({sectionBorderColor:event.target.value})} className="w-20 px-2 py-1 text-[11px] font-mono rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-neutral-900 dark:text-white"/></div></div>}
  <p className="text-[10px] text-neutral-400">Controls the outer section edges. Item dividers have separate settings.</p>
 </div>;
}
