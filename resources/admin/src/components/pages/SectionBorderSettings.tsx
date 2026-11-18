import {ImageChoiceDropdown} from './ImageChoiceDropdown';
import React from 'react';
import type {PageSection} from '../../types';
const options:Array<[string,string]>=[['default','Default (background style)'],['none','None'],['top','Top'],['bottom','Bottom'],['both','Both']];
function BorderIcon({value}:{value:string}){
 return <svg aria-hidden="true" width="20" height="16" viewBox="0 0 20 16" className="shrink-0 text-neutral-500 dark:text-neutral-300">
  <rect x="1" y="2" width="18" height="12" fill="currentColor" opacity=".25"/>
  {value!=='none'&&<path fill="none" stroke="currentColor" strokeDasharray={value==='default'?'2 2':undefined}
   d={value==='top'?'M1 2h18':value==='bottom'?'M1 14h18':'M1 2h18M1 14h18'}/>}
 </svg>;
}
export function SectionBorderSettings({section,onChange}:{section:PageSection;onChange:(patch:Partial<PageSection>)=>void}) {
 const color=section.sectionBorderColor??'#e5e5e5';
 const swatch=/^#[0-9a-f]{6}$/i.test(color)?color:/^#[0-9a-f]{3}$/i.test(color)?'#'+color.slice(1).split('').map(c=>c+c).join(''):'#e5e5e5';
 return <div className="space-y-3">
  <ImageChoiceDropdown overlay label="Section borders" options={options} value={section.sectionBorder??'default'}
   onChange={value=>onChange({sectionBorder:value as PageSection['sectionBorder']})}
   icon={index=><BorderIcon value={options[index][0]}/>}/>
  {section.sectionBorder && !['default','none'].includes(section.sectionBorder) && <div className="flex items-center justify-between gap-2"><span className="text-xs text-neutral-600 dark:text-neutral-400">Border color</span><div className="flex items-center gap-2"><input type="color" aria-label="Section border color picker" value={swatch} onChange={event=>onChange({sectionBorderColor:event.target.value})} className="w-7 h-7 rounded-lg border border-neutral-300 dark:border-neutral-600 cursor-pointer bg-transparent"/><input aria-label="Section border color Hex" value={color} onChange={event=>onChange({sectionBorderColor:event.target.value})} className="w-20 px-2 py-1 text-[11px] font-mono rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-neutral-900 dark:text-white"/></div></div>}
  <p className="text-[10px] text-neutral-400">Controls the outer section edges. Item dividers have separate settings.</p>
 </div>;
}
