import {ImageChoiceDropdown} from './ImageChoiceDropdown';
import {itemTextRoles} from '../../cms/textColorBindings';
import {TextColorSourceControl} from './TextColorSourceControl';
import type {RenderedText} from './BuilderTextToolbar';
import React from 'react';
import {FontPicker} from './FontPicker';
import {fontCatalog} from '../../cms/fontCatalog';
import type {PageSection} from '../../types';
import {textRoles,textRoleLabels,type TextRole,type TextStyle,type SectionTypography} from '../../cms/inspectorCapabilities';
import {legacyTypographyRoles} from '../../cms/sectionHeading';
import {resolveStyledSection,stylePresets,type BrandPalette} from '../../cms/pageStyle';
import {resetTextRole,resetTextProperty,textStyleHasOverride} from '../../cms/styleInheritance';
const compactInput='w-full min-w-0 rounded-md border border-neutral-300 bg-neutral-50 px-2 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800 dark:text-white';
const input='w-full mt-2 px-3 py-2 rounded-lg border border-neutral-300 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/60 text-neutral-900 dark:text-white';
type TypographyProps=RoleProps;
export function TypographySettings(props:TypographyProps){
 const roles=props.availableRoles??textRoles(props.section);if(!roles.length)return null;
 const role=props.selectedRole&&roles.includes(props.selectedRole)?props.selectedRole:roles[0];
 const label=(value:TextRole)=>value==='itemTitle'?'Item title':value==='itemContent'?'Item description':textRoleLabels[value];
 const glyphs:Partial<Record<TextRole,string>>={title:'Aa',secondaryTitle:'Aa',eyebrow:'•─',content:'≡',lead:'≡',itemTitle:'▤',itemContent:'≡',number:'#'};
 return <details open data-inspector-typography className="cms-settings-group">
   <summary>Typography</summary>
   <div className="cms-settings-group-content space-y-3">
     <ImageChoiceDropdown label={props.scope==='page'?'Editing':'Text role'} fullWidthOverlay={props.scope!=='page'} value={role} options={roles.map(value=>[value,label(value)])}
       icon={index=><span aria-hidden className="w-5 shrink-0 text-center font-semibold text-neutral-500 dark:text-neutral-300">{glyphs[roles[index]]??'Aa'}</span>}
       onChange={value=>props.onSelectRole(value as TextRole)}/>
     <TypographyRoleSettings key={role} {...props} role={role}/>
   </div>
 </details>;
}
type RoleProps={colorItemId?:string;renderedText?:RenderedText;preserveLayout?:boolean;scope?:'page'|'section';availableRoles?:TextRole[];inheritedTypography?:SectionTypography;inheritedPalette?:BrandPalette;section:PageSection;onChange:(section:PageSection)=>void;selectedRole?:TextRole;onSelectRole:(role:TextRole)=>void};
function TypographyRoleSettings({colorItemId,renderedText,section,onChange,role,inheritedTypography,inheritedPalette,preserveLayout=false,scope='section'}:RoleProps & {role:TextRole}){

 const local=section.typography?.[role]??(legacyTypographyRoles(section).includes(role as any)?section.typography?.eyebrow:undefined)??{};
 const inherited=inheritedTypography?.[role]??(legacyTypographyRoles(section).includes(role as any)?inheritedTypography?.eyebrow:undefined);
 const effective={...inherited,...local};
 const context=scope==='page'||section.brandPalette||inheritedPalette||inheritedTypography?{palette:(section.surface==='page'?undefined:section.brandPalette)??inheritedPalette??stylePresets[0].palette,typography:inheritedTypography,preserveLayout}:undefined;
 const resolved=resolveStyledSection(section,context);
 const rawColor=role==='eyebrow'||role==='label'||role==='number'?section.customColors?.eyebrowColor:role==='title'||role==='itemTitle'||role==='secondaryTitle'?section.customColors?.titleColor:section.customColors?.contentColor;
 const ownedColor=local.colorOverride?local.color:!local.color&&inheritedTypography?.[role]?.colorOverride?inheritedTypography[role]?.color:undefined;
 const color=(renderedText?.role===role?renderedText.color:undefined)??ownedColor??resolved.typography?.[role]?.color??local.color??rawColor??(section.textColor==='light'?'#ffffff':'#292b24');
 const hasOverride=scope==='section'&&textStyleHasOverride(section,role);
 const legacyColor=!ownedColor&&!local.inheritPage&&section.surface===undefined&&(role==='eyebrow'?section.customColors?.eyebrowColor:role==='title'||role==='itemTitle'||role==='secondaryTitle'?section.customColors?.titleColor:section.customColors?.contentColor);
 const source=(key:keyof TextStyle)=>scope==='page'?'Page default':local[key]!==undefined||key==='color'&&legacyColor?'Custom':'Following page';
 const change=(patch:Partial<TextStyle>)=>{
  const key=Object.keys(patch)[0] as keyof TextStyle;
  if(scope==='section'&&patch[key]===undefined){onChange(resetTextProperty(section,role,key));return;}
  const style={...local,...patch,...(patch.color?{colorOverride:true}:{})};if(key==='color'&&patch.color===undefined)delete style.colorOverride;for(const k of Object.keys(style) as Array<keyof TextStyle>)if(style[k]===undefined)delete style[k];
  onChange({...section,typography:{...section.typography,[role]:style}});
 };
 const reset=()=>{if(scope==='section'){onChange(resetTextRole(section,role));return;}const next={...section.typography};delete next[role];onChange({...section,typography:next});};
 const sharedScope=role==='itemTitle'?'All item titles':role==='itemContent'?'All item descriptions':role==='label'||role==='number'?'All matching item text':'This section';
 return <div data-typography-role={role} className="space-y-3">
  <div role="status" className="flex flex-wrap items-center justify-between gap-1 text-xs">
    <span>{scope==='page'?'Page typography':`Text styles: ${sharedScope}`}</span>
    <span className={hasOverride?'text-indigo-500':'text-neutral-500'}>{scope==='page'?'Page default':hasOverride?'Custom':'Following page'}</span>
  </div>
  {scope==='page'?<><FontPicker value={effective.fontFamily} onChange={fontFamily=>change({fontFamily,...(fontCatalog.find(f=>f.id===fontFamily)?.weights.includes(effective.fontWeight??400)?{}:{fontWeight:400})})}/>
  <label className="block text-neutral-500"><span className="flex justify-between">Size<small>{source('fontSize')}</small></span><TypographyNumber label="Text size" inheritedValue={(renderedText?.role===role?renderedText.fontSize:undefined)??inherited?.fontSize??(role==='title'?36:role==='itemTitle'?20:role==='eyebrow'?13:16)} value={local.fontSize} min={8} max={120} step={1} onChange={fontSize=>change({fontSize})}/></label>
  <fieldset><legend className="text-neutral-500 mb-2">Weight <small>{source('fontWeight')}</small></legend><div className="grid grid-cols-3 gap-2">{(fontCatalog.find(f=>f.id===effective.fontFamily)?.weights??[400,500,600,700,800,900]).map(value=><button type="button" key={value} aria-label={'Text weight '+value} aria-pressed={(effective.fontWeight??(renderedText?.role===role?renderedText.fontWeight:undefined))===value} onClick={()=>change({fontWeight:value})} className={`rounded-lg border py-2 text-xs ${(effective.fontWeight??(renderedText?.role===role?renderedText.fontWeight:undefined))===value?'border-indigo-500 bg-indigo-500/10':'border-neutral-200 dark:border-neutral-700'}`}>{({400:'Regular',500:'Medium',600:'Semibold',700:'Bold',800:'Extra bold',900:'Black'} as Record<number,string>)[value]}</button>)}</div>{local.fontWeight!==undefined&&<button type="button" className="text-xs underline text-neutral-500 mt-2" onClick={()=>change({fontWeight:undefined})}>{scope==='page'?'Use layout weight':'Use page weight'}</button>}</fieldset></>:<>
    <div className="space-y-1"><span>Font family</span><FontPicker value={effective.fontFamily} onChange={fontFamily=>change({fontFamily,...(fontCatalog.find(f=>f.id===fontFamily)?.weights.includes(effective.fontWeight??400)?{}:{fontWeight:400})})}/></div>
    <div className="grid grid-cols-2 gap-2">
      <label className="grid min-w-0 gap-1">Size<TypographyNumber compact label="Text size" inheritedValue={(renderedText?.role===role?renderedText.fontSize:undefined)??inherited?.fontSize??(role==='title'?36:role==='itemTitle'?20:role==='eyebrow'?13:16)} value={local.fontSize} min={8} max={120} step={1} onChange={fontSize=>change({fontSize})}/></label>
      <label className="grid min-w-0 gap-1">Weight
        <select aria-label="Text weight" className={compactInput} value={local.fontWeight??''}
          onChange={event=>change({fontWeight:event.target.value?Number(event.target.value):undefined})}>
          <option value="">Default ({effective.fontWeight??(renderedText?.role===role?renderedText.fontWeight:undefined)??400})</option>
          {[...new Set([...(fontCatalog.find(f=>f.id===effective.fontFamily)?.weights??[400,500,600,700,800,900]),...(local.fontWeight===undefined?[]:[local.fontWeight])])].sort((a,b)=>a-b).map(weight=><option key={weight} value={weight}>{weight}</option>)}
        </select>
      </label>
    </div>
  </>}
  {scope==='section'?<TextColorSourceControl section={section} role={role} showLabel itemId={itemTextRoles.includes(role)?colorItemId:renderedText?.role===role?renderedText.itemId:undefined} color={color} onChange={onChange}/>:<>  <label className="block text-neutral-500"><span className="flex justify-between">Color<small>{source('color')}</small></span><div className="flex gap-2 mt-2"><input aria-label="Text color picker" type="color" value={color} className="w-9 h-9 rounded border border-neutral-600 bg-transparent" onChange={e=>change({color:e.target.value})}/><input aria-label="Text color hex" key={`${role}-${color}`} defaultValue={color} className={`${input} !mt-0`} onBlur={e=>{if(/^#[\da-f]{6}$/i.test(e.target.value)&&e.target.value.toLowerCase()!==color.toLowerCase())change({color:e.target.value});else e.target.value=color;}} onKeyDown={e=>{if(e.key==='Enter')e.currentTarget.blur();}}/></div></label>
  {hasOverride&&<button type="button" className="text-xs underline text-neutral-500" onClick={()=>change({color:undefined})}>Use page / surface color</button>}
</>}
  {scope==='page'?<details><summary className="text-neutral-500 cursor-pointer">More typography options</summary><div className="pt-3 space-y-3"><label className="block text-neutral-500">Line height<TypographyNumber inheritedValue={inherited?.lineHeight??(role==='eyebrow'?1.25:1.5)} label="Text line height" value={local.lineHeight} min={.8} max={3} step={.1} onChange={lineHeight=>change({lineHeight})}/></label><label className="block text-neutral-500">Letter spacing<TypographyNumber inheritedValue={inherited?.letterSpacing??0} label="Text letter spacing" value={local.letterSpacing} min={-5} max={10} step={.5} onChange={letterSpacing=>change({letterSpacing})}/></label></div></details>:<div className="grid grid-cols-2 gap-2"><label className="grid min-w-0 gap-1">Line height<TypographyNumber compact inheritedValue={inherited?.lineHeight??(role==='eyebrow'?1.25:1.5)} label="Text line height" value={local.lineHeight} min={.8} max={3} step={.1} onChange={lineHeight=>change({lineHeight})}/></label><label className="grid min-w-0 gap-1">Letter spacing<TypographyNumber compact inheritedValue={inherited?.letterSpacing??0} label="Text letter spacing" value={local.letterSpacing} min={-5} max={10} step={.5} onChange={letterSpacing=>change({letterSpacing})}/></label></div>}

  {scope==='page'||hasOverride?<button type="button" className={scope==='page'?'text-indigo-500 underline underline-offset-4 text-xs':'rounded-md border border-neutral-300 bg-neutral-50 px-2 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800'} onClick={reset}>{scope==='page'?'Use layout text defaults':'Reset role styles'}</button>:null}
 </div>;
}
function TypographyNumber({label,value,inheritedValue,min,max,step,onChange,compact=false}:{compact?:boolean;inheritedValue?:number;label:string;value?:number;min:number;max:number;step:number;onChange:(value:number|undefined)=>void}){return <div className={compact?'min-w-0':'mt-2 flex gap-3 items-center'}>{!compact&&<input type="range" aria-label={label+' slider'} min={min} max={max} step={step} value={value??inheritedValue??min} onChange={e=>onChange(Number(e.target.value))} className="min-w-0 flex-1 accent-indigo-500"/>}<input key={label+'-'+value+'-'+inheritedValue} aria-label={label} type="number" min={min} max={max} step={step} placeholder={String(inheritedValue??'Default')} defaultValue={value??''} className={compact?compactInput:`${input} !mt-0 !w-20`} onBlur={e=>{const raw=e.target.value,next=Number(raw);if(raw===''&&value!==undefined)onChange(undefined);else if(Number.isFinite(next)&&next>=min&&next<=max&&Math.abs(next/step-Math.round(next/step))<.0001&&next!==value)onChange(next);else e.target.value=value===undefined?'':String(value);}} onKeyDown={e=>{if(e.key==='Enter')e.currentTarget.blur();}}/></div>;}
