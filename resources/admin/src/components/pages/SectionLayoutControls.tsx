import React from 'react';
import {AlignLeft,AlignCenter,AlignRight} from 'lucide-react';
import type {PageSection} from '../../types';
import {layoutControlCapabilities} from '../../cms/layoutControls';

export function SectionLayoutControls({section,onChange,pageSpacing}:{section:PageSection;onChange:(s:PageSection)=>void;pageSpacing?:string}){
 const capability=layoutControlCapabilities(section);
 const reset=(key:'paddingY'|'textAlignment')=>{const next={...section};delete next[key];onChange(next)};
 const choice='py-1.5 flex items-center justify-center rounded-lg border transition-colors cursor-pointer ';
 const selected='border-indigo-600 bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 font-bold';
 const normal='border-neutral-200 dark:border-neutral-700 text-neutral-500';
 return <>
 {capability.alignment.length>1&&<div data-layout-alignment><span className="text-neutral-600 dark:text-neutral-400 font-medium mb-1 block">Text Alignment</span>
  <button type="button" aria-label="Use layout alignment" aria-pressed={section.textAlignment===undefined} className="text-xs text-indigo-500 underline mb-2" onClick={()=>reset('textAlignment')}>Use layout alignment</button>
  <div className={capability.alignment.length===2?'grid grid-cols-2 gap-2':'grid grid-cols-3 gap-2'}>{capability.alignment.map(value=>{const Icon={left:AlignLeft,center:AlignCenter,right:AlignRight}[value];return <button type="button" key={value} aria-label={`Text alignment ${value}`} aria-pressed={section.textAlignment===value} onClick={()=>onChange({...section,textAlignment:value})} className={choice+(section.textAlignment===value?selected:normal)}><Icon className="w-4 h-4 mr-1"/>{value==='left'?'Left':value==='center'?'Center':'Right'}</button>})}</div>
  {section.textAlignment&&!capability.alignment.includes(section.textAlignment)&&<p className="text-xs text-neutral-500 mt-2">Existing alignment is kept by this layout.</p>}
 </div>}
 {capability.spacing&&<div data-layout-spacing><span className="text-neutral-600 dark:text-neutral-400 font-medium mb-1 block">Vertical Spacing</span>
  <button type="button" aria-label="Follow page spacing" aria-pressed={section.paddingY===undefined} className="text-xs text-indigo-500 underline mb-2" onClick={()=>reset('paddingY')}>Follow page spacing{pageSpacing?` (${pageSpacing})`:''}</button>
  <div className="grid grid-cols-3 gap-2">{(['compact','normal','spacious'] as const).map(value=><button type="button" key={value} aria-label={`Vertical spacing ${value}`} aria-pressed={section.paddingY===value} onClick={()=>onChange({...section,paddingY:value})} className={`p-2 capitalize text-center rounded-lg border transition-colors cursor-pointer ${section.paddingY===value?selected:normal}`}><span aria-hidden="true" className="flex h-14 bg-neutral-500/10 items-center justify-center mb-2"><span className="w-4/5 border-y border-dashed border-indigo-400/50 flex items-center justify-center" style={{height:value==='compact'?24:value==='normal'?38:52}}><span className="h-2 w-2/3 bg-indigo-400 rounded-sm"/></span></span>{value}</button>)}</div>
 </div>}
 </>;
}
