import {ImageChoiceDropdown} from './ImageChoiceDropdown';
import React from 'react';
import type {PageSection} from '../../types';
import {actionCapabilities,hasActions,type ButtonColors} from '../../cms/actionAppearance';
import {stylePresets,type PageStyle} from '../../cms/pageStyle';
const corners:Array<[string,string]>=[['','Follow page / layout'],['0','Square'],['6','Rounded'],['999','Pill']];
const sizes:Array<[string,string]>=[['','Follow page / layout'],['compact','Compact'],['normal','Standard'],['large','Large']];
function ButtonGlyph({kind,value}:{kind:'size'|'radius';value:string}){
 const height=kind==='size'?({compact:6,normal:10,large:14}[value]??10):10;
 const radius=kind==='radius'?({'6':3,'999':5}[value]??0):2;
 return <svg aria-hidden="true" width="22" height="18" viewBox="0 0 22 18" fill="none" stroke="currentColor" className="shrink-0 text-neutral-500 dark:text-neutral-300">
  <rect x="2" y={9-height/2} width="18" height={height} rx={radius} strokeDasharray={value?undefined:'2 2'}/>
 </svg>;
}
const field='w-full mt-2 p-2 rounded-lg border border-neutral-300 dark:border-neutral-700 bg-transparent';
export function SectionButtonSettings({section,onChange,pageStyle}:{children?:React.ReactNode;section:PageSection;onChange:(s:PageSection)=>void;pageStyle?:Partial<PageStyle>}){
 if(!hasActions(section))return null;
 const cap=actionCapabilities(section),geometry=cap.primary.button||cap.secondary.button;
 const local=section.buttonRadius!==undefined||section.buttonSize!==undefined||Object.keys(section.buttonColors??{}).length>0;
 const change=(key:keyof ButtonColors,value?:string)=>{const colors={...section.buttonColors};if(value===undefined)delete colors[key];else colors[key]=value;const next={...section};if(Object.keys(colors).length)next.buttonColors=colors;else delete next.buttonColors;onChange(next)};
 const palette=pageStyle?.palette??stylePresets[0].palette;
 const buttonColor=(key:keyof ButtonColors,label:string,fallback:string,channel:string)=>{
  const value=section.buttonColors?.[key];
  return <div key={key} className="flex min-h-8 items-center justify-between gap-2 text-sm">
   <span>{label}</span>
   <div className="flex items-center gap-2">
    <small className="text-neutral-500">{value?'Custom':'Inherited'}</small>
    <input aria-label={channel+' '+label} title={value??'Set custom '+label.toLowerCase()} type="color" value={value??fallback}
     className="h-6 w-7 cursor-pointer rounded border-0 bg-transparent p-0" onChange={event=>change(key,event.target.value)}/>
    {value&&<button type="button" aria-label={'Reset '+channel+' '+label} className="rounded-md border border-neutral-300 px-2 py-1 text-xs dark:border-neutral-700 dark:bg-neutral-800" onClick={()=>change(key)}>Reset</button>}
   </div>
  </div>;
 };
 const color=(key:keyof ButtonColors,label:string,fallback:string)=>{const value=section.buttonColors?.[key];return <div key={key} className="space-y-2"><div className="flex justify-between gap-2 text-xs text-neutral-500"><span>{label}</span><small>{value?'Custom':'Following layout / page'}</small></div>{value?<><div className="flex gap-2"><input aria-label={label+' picker'} type="color" value={value} className="w-9 h-9 rounded border dark:border-neutral-700" onChange={e=>change(key,e.target.value)}/><input aria-label={label} key={value} defaultValue={value} className={field+' !mt-0 min-w-0'} onBlur={e=>{if(/^#[\da-f]{6}$/i.test(e.target.value)&&e.target.value!==value)change(key,e.target.value);else e.target.value=value}} onKeyDown={e=>{if(e.key==='Enter')e.currentTarget.blur()}}/></div><button type="button" aria-label={'Reset '+label} className="text-xs underline text-indigo-500" onClick={()=>change(key)}>Use layout / page color</button></>:<button type="button" aria-label={'Customize '+label} className="text-xs border rounded-lg px-3 py-2 dark:border-neutral-700" onClick={()=>change(key,fallback)}>Customize color</button>}</div>};
 return <details className="cms-settings-group" data-section-button-style><summary>{geometry?'Buttons':'Action links'}</summary><div className="cms-settings-group-content space-y-4">{!geometry&&<p className="text-xs text-neutral-500">{local?'Custom action styling':'Following page / layout defaults'}</p>}
 {geometry&&<div className="space-y-2">
  <ImageChoiceDropdown overlay label="Corner radius" options={corners} value={String(section.buttonRadius??'')}
   icon={index=><ButtonGlyph kind="radius" value={corners[index][0]}/>}
   onChange={value=>{const next={...section};if(value==='')delete next.buttonRadius;else next.buttonRadius=Number(value) as PageSection['buttonRadius'];onChange(next)}}/>
  <ImageChoiceDropdown overlay label="Button size" options={sizes} value={section.buttonSize??''}
   icon={index=><ButtonGlyph kind="size" value={sizes[index][0]}/>}
   onChange={value=>{const next={...section};if(value==='')delete next.buttonSize;else next.buttonSize=value as PageSection['buttonSize'];onChange(next)}}/>
 </div>}
 {(['primary','secondary'] as const).map(channel=>{
  const capability=cap[channel];if(!capability.button&&!capability.link)return null;
  const name=channel==='primary'?'Primary':'Secondary';
  const ink=channel==='primary'?palette.light:palette.secondaryInk;
  return <div key={channel} className="space-y-1 border-t border-neutral-200 pt-3 dark:border-neutral-700" data-action-channel={channel}>
   <p className="text-sm font-semibold">{name} {capability.button?'button':'link'}</p>
   {capability.button?<>
    {buttonColor(`${channel}Bg`,'Background',channel==='primary'?palette.accent:palette.secondary,name)}
    {buttonColor(`${channel}Text`,'Text',ink,name)}
    {buttonColor(`${channel}Hover`,'Hover background',pageStyle?.buttonHoverColor??palette.hover,name)}
   </>:<>
    {color(`${channel}Text`,name+' action text',ink)}
    {color(`${channel}Hover`,name+' hover color',palette.hover)}
   </>}
  </div>;
 })}
 {local&&<button type="button" className="text-xs text-indigo-500 underline" onClick={()=>{const next={...section};delete next.buttonRadius;delete next.buttonSize;delete next.buttonColors;onChange(next)}}>{geometry?'Restore page / layout defaults':'Use page / layout action defaults'}</button>}
 </div></details>;
}
