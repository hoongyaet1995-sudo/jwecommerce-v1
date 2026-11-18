import React from 'react';
import type {PageSection} from '../../types';
import {layoutControlCapabilities} from '../../cms/layoutControls';
import {ImageChoiceDropdown} from './ImageChoiceDropdown';

const alignmentPaths = {
 left:'M2 4h14M2 9h9M2 14h12',
 center:'M2 4h14M5 9h8M3 14h12',
 right:'M2 4h14M7 9h9M4 14h12',
} as const;
function AlignmentIcon({value}:{value:'left'|'center'|'right'|''}){
 return <svg aria-hidden="true" width="18" height="18" viewBox="0 0 18 18" fill="none" stroke="currentColor" strokeWidth="1.4" strokeLinecap="round" className="shrink-0 text-neutral-500 dark:text-neutral-300">
  {value?<path d={alignmentPaths[value]}/>:<><rect x="1.5" y="2" width="15" height="14" rx="2" strokeDasharray="2 2"/><path d="M5 7h8m-8 4h5"/></>}
 </svg>;
}
function SpacingIcon({value}:{value:string}){
 const gap=value==='compact'?4:value==='normal'?7:value==='spacious'?10:7;
 return <svg aria-hidden="true" width="20" height="22" viewBox="0 0 20 22" fill="none" stroke="currentColor" className="shrink-0 text-neutral-500 dark:text-neutral-300">
  <path d={`M1 ${11-gap}h18M1 ${11+gap}h18`} strokeDasharray={value?undefined:'2 2'} />
  <path d="M5 11h10" strokeWidth="3" strokeLinecap="round"/>
 </svg>;
}

export function SectionLayoutControls({section,onChange,pageSpacing}:{section:PageSection;onChange:(s:PageSection)=>void;pageSpacing?:string}){
 const capability=layoutControlCapabilities(section);
 const alignmentOptions:Array<[string,string]>=[['','Use layout alignment'],...capability.alignment.map(value=>[value,value[0].toUpperCase()+value.slice(1)] as [string,string])];
 const spacingOptions:Array<[string,string]>=[['',pageSpacing?`Follow page spacing (${pageSpacing})`:'Follow page spacing'],['compact','Compact'],['normal','Normal'],['spacious','Spacious']];
 const change=(key:'paddingY'|'textAlignment',value:string)=>{
  const next={...section};
  if(!value)delete next[key];
  else if(key==='paddingY')next.paddingY=value as PageSection['paddingY'];
  else next.textAlignment=value as PageSection['textAlignment'];
  onChange(next);
 };
 return <>
  {capability.alignment.length>1&&<div data-layout-alignment>
   <ImageChoiceDropdown overlay label="Text alignment" options={alignmentOptions}
    value={section.textAlignment??''} onChange={value=>change('textAlignment',value)}
    icon={index=><AlignmentIcon value={alignmentOptions[index][0] as keyof typeof alignmentPaths|''}/>}/>
   {section.textAlignment&&!capability.alignment.includes(section.textAlignment)&&<p className="mt-1 text-xs text-neutral-500">Existing alignment is kept by this layout.</p>}
  </div>}
  {capability.spacing&&<div data-layout-spacing>
   <ImageChoiceDropdown overlay label="Vertical spacing" options={spacingOptions}
    value={section.paddingY??''} onChange={value=>change('paddingY',value)}
    icon={index=><SpacingIcon value={spacingOptions[index][0]}/>}/>
  </div>}
 </>;
}
