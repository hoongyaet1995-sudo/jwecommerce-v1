import {LayoutDiagramDropdown} from './LayoutDiagramDropdown';
import React from 'react';
import type {PageSection} from '../../types';
export function HeroSliderLayouts({section,onChange}:{section:PageSection;onChange:(section:PageSection)=>void}) {
 return <fieldset className="space-y-3"><legend className="text-xs uppercase font-bold text-neutral-500 mb-3">Slider layout</legend><LayoutDiagramDropdown label="Slider layout" value={section.sliderPreset??'default'}
 choices={([['default','Cinematic'],['editorial-overlay','Editorial Overlay'],['fashion-editorial','Fashion Hero']] as const).map(([value,label])=>({value:value,label:label,diagram:<span aria-hidden="true" className="relative block h-16 bg-neutral-300 dark:bg-neutral-800 rounded overflow-hidden"><span className="absolute right-3 top-3 bottom-3 w-12 bg-neutral-400/30 rounded"/><span className={value==='fashion-editorial'?'absolute top-3 left-3 bottom-3 w-16 p-2 flex flex-col gap-2':value==='default'?'absolute top-6 inset-x-6 flex flex-col gap-2':'absolute top-3 left-3 bottom-3 w-16 p-2 bg-white/80 flex flex-col gap-2'}><span className="h-2 bg-neutral-500/80"/><span className="h-1 bg-neutral-400/70"/><span className="h-2 w-6 bg-neutral-500"/></span><span className="absolute bottom-1 left-3 text-neutral-500 text-[10px]">•••</span></span>}))}
 onChange={value=>onChange({...section,sliderPreset:value})}/></fieldset>;
}
