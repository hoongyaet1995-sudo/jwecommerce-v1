import {isConnectedFamily} from '../../cms/connectedFamilies';
import React from 'react';
import type {PageSection,SectionColorConfig} from '../../types';
import {hasContainedSurface} from '../../cms/containedSurface';
import capabilities from '../../../../cms/ai-capabilities.v1.json';
import {useCmsBrand} from '../../cms/useCmsBrand';
import {resolveStyledSection,stylePresets,type BrandPalette} from '../../cms/pageStyle';
import {SurfaceCards} from './SurfaceCards';
import {renderedColorFields} from '../../cms/sectionDesignCapabilities';
import {supportsSharedButtons} from '../../cms/styleInheritance';
import {textRoles} from '../../cms/inspectorCapabilities';
export const surfaceUnsupported=new Set(Object.entries(capabilities.sections).filter(([,c])=>c.surfaces.length===1).map(([type])=>type));
const labels:Partial<Record<keyof SectionColorConfig,string>>={primaryBtnBg:'Primary button background',primaryBtnText:'Primary button text',secondaryBtnBg:'Secondary button background',secondaryBtnText:'Secondary button text',eyebrowColor:'Accent color'};
export function SectionStyleSettings({section,onChange,pagePalette,followTheme=false,preserveLayout=false,customBackground,mode='surface'}:{preserveLayout?:boolean;mode?:'surface'|'button-colors';customBackground?:React.ReactNode;followTheme?:boolean;pagePalette?:BrandPalette;section:PageSection;onChange:(s:PageSection)=>void}){
 const brand=useCmsBrand(!pagePalette),palette=(section.surface==='page'?undefined:section.brandPalette)??(followTheme||!pagePalette?brand?.palette:undefined)??pagePalette??stylePresets[0].palette;
 const resolved=resolveStyledSection(section,{palette,preserveLayout}),baseline=resolveStyledSection({...section,surface:undefined},{palette}).customColors;
 const defaults={...palette,paper:baseline?.customBgColor||palette.paper,ink:baseline?.titleColor||palette.ink,muted:baseline?.contentColor||palette.muted,accent:baseline?.primaryBtnBg||palette.accent,light:baseline?.primaryBtnText||palette.light};
 const legacySurface=section.surface===undefined&&!!(preserveLayout||section.backgroundColor||section.brandPalette||section.customColors?.customBgColor||!pagePalette);
 const fields=renderedColorFields(section),has=(key:string)=>fields?fields.includes(key):true;
 const accents=['eyebrowColor'] as const;
 const buttons=['primaryBtnBg','primaryBtnText','secondaryBtnBg','secondaryBtnText'] as const;
 const changeColor=(key:keyof SectionColorConfig,value?:string)=>{
  const colors={...section.customColors},overrides=new Set(section.colorOverrides??[]);if(value===undefined){delete colors[key];overrides.delete(key);}else{colors[key]=value;overrides.add(key);}
  onChange({...section,customColors:Object.keys(colors).length?colors:undefined,colorOverrides:overrides.size?[...overrides]:undefined});
 };
 const colorField=(key:keyof SectionColorConfig)=>{const value=resolved.customColors?.[key]??'#454e37',stored=section.customColors?.[key],authored=!!section.colorOverrides?.includes(key),active=authored||!section.surface&&stored!==undefined,hex=/^#[\da-f]{6}$/i.test(value)?value:'#454e37';return <label className="block text-xs text-neutral-500" key={key}><span className="flex justify-between gap-2">{key==='secondaryBtnBg'?'Panel background':'Panel accent'}<small>{active?'Custom':'Following surface'}</small></span><div className="flex gap-2 mt-2"><input type="color" aria-label={(key==='secondaryBtnBg'?'Panel background':'Panel accent')+' picker'} value={hex} className="w-9 h-9 rounded border border-neutral-600" onChange={e=>changeColor(key,e.target.value)}/><input key={key+'-'+stored+'-'+value} aria-label={key==='secondaryBtnBg'?'Panel background':'Panel accent'} defaultValue={value} placeholder={hex} className="w-full min-w-0 px-2 rounded-lg border border-neutral-300 dark:border-neutral-700 bg-transparent" onBlur={e=>{if(e.target.value&&e.target.value!==value)changeColor(key,e.target.value);else if(!e.target.value&&stored!==undefined)changeColor(key,undefined);}} onKeyDown={e=>{if(e.key==='Enter')e.currentTarget.blur()}}/></div>{active&&<button type="button" className="text-xs underline mt-1" onClick={()=>changeColor(key)}>Use page / surface color</button>}</label>;};
 if(mode==='button-colors')return null;
 return <div data-section-surface className="space-y-3"><p className="text-xs text-neutral-500">{section.surface==='page'?'Following page palette':section.brandPalette?'Legacy section palette · kept':legacySurface?'Existing layout surface · kept':'Following page palette'}</p>
 {(isConnectedFamily(section)||!surfaceUnsupported.has(section.type)||(section.type==='hero_banner'&&section.heroBannerPreset==='corporate-split-composite'))&&<><SurfaceCards compact palette={hasContainedSurface(section)?{...palette,accent:palette.ink}:palette} defaultPalette={(followTheme?brand?.palette:undefined)??pagePalette??brand?.palette??stylePresets[0].palette} value={section.surface==='page'?'default':section.surface??(legacySurface?'legacy':'default')} onChange={surface=>{const next={...section};next.surface=surface==='default'?'page':surface;next.colorOverrides=next.colorOverrides?.filter(k=>k!=='customBgColor');onChange(next)}}/><p className="text-[11px] text-neutral-500">{hasContainedSurface(section)?'Changes the card or panel only.':'Automatic text colors adapt to this surface.'}</p></>}
 {customBackground&&<details><summary className="text-xs text-neutral-500 cursor-pointer">Custom background & gradients</summary><div className="pt-3 space-y-3">{customBackground}</div></details>}
 {section.brandPalette&&<button type="button" className="text-xs underline text-indigo-500" onClick={()=>{const next={...section};delete next.brandPalette;onChange(next)}}>Follow page palette</button>}
 {section.type==='media_text_split'&&['editorial-story-cards','editorial-journal-rows'].includes(section.layoutPreset??'')&&<details><summary className="text-xs text-neutral-500 cursor-pointer">Card surface</summary><div className="pt-3">{colorField('secondaryBtnBg')}</div></details>}
 {section.type==='faq_accordion'&&section.faqPreset==='framed-faq'&&<details><summary className="text-xs text-neutral-500 cursor-pointer">Panel accents</summary><div className="space-y-3 pt-3">{colorField('primaryBtnBg')}{colorField('secondaryBtnBg')}</div></details>}
 </div>;
}
