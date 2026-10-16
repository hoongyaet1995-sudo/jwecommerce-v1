import React from 'react';
import type {PageSection} from '../../types';
import {VisualChoiceSelect} from './VisualChoiceSelect';
import {InspectorToggle} from './InspectorToggle';

export function PhotoMarqueeSettings({section,onChange}:{section:PageSection;onChange:(section:PageSection)=>void}){
 const duration=section.marqueeSpeed??40;
 const changeDuration=(value:number)=>onChange({...section,marqueeSpeed:Math.max(0,Math.min(100,Math.round(value)))});
 return <div className="space-y-4">
  <div className="text-xs text-neutral-500">Loop duration
   <div className="flex items-center gap-3 mt-2"><span>0</span><input aria-label="Photo marquee duration slider" type="range" min={0} max={100} step={1} value={duration} onChange={event=>changeDuration(Number(event.target.value))} className="min-w-0 flex-1 accent-indigo-500"/><span>100</span></div>
   <input aria-label="Photo marquee loop duration" type="number" min={0} max={100} step={1} value={duration} onChange={event=>changeDuration(Number(event.target.value))} className="mt-2 w-full rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800 px-3 py-2.5 text-sm text-neutral-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"/>
   <p className="mt-1 text-[10px]">Seconds per loop - 0 stops automatic scrolling</p>
  </div>
  <div className="text-xs text-neutral-500">Direction<VisualChoiceSelect aria-label="Photo marquee direction" value={'marquee-'+(section.marqueeDirection??'left')} onChange={event=>onChange({...section,marqueeDirection:event.target.value.replace('marquee-','') as 'left'|'right'})}><option value="marquee-left">Move left</option><option value="marquee-right">Move right</option></VisualChoiceSelect></div>
  <InspectorToggle label="Pause on hover" checked={section.marqueePauseOnHover!==false} onChange={checked=>onChange({...section,marqueePauseOnHover:checked})}/>
  <p className="text-xs text-neutral-500">Mobile and reduced motion use manual scrolling.</p>
 </div>;
}
