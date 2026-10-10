import {VisualChoiceSelect} from './VisualChoiceSelect';
import React from 'react';
import {InspectorToggle} from './InspectorToggle';
import type {PageSection} from '../../types';
import {imagePositions} from './SectionImage';
const inputClass='w-full px-3 py-2 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-neutral-900 dark:text-neutral-100 text-sm';
const legacyPosition={top:'top-center',bottom:'bottom-center',left:'center-left',right:'center-right',center:'center'} as const;
export function SectionImageSettings({section,onChange,assetControl,geometryLocked=false,mode='all'}:{section:PageSection;onChange:(patch:Partial<PageSection>)=>void;assetControl:React.ReactNode;geometryLocked?:boolean;mode?:'all'|'content'|'design'}) {
  const select=(label:string,key:keyof PageSection,value:string,options:Array<[string,string]>)=><label className="block text-sm text-neutral-600 dark:text-neutral-400">{label}<VisualChoiceSelect disabled={geometryLocked && ['imageRatio','imageWidth','imageShape'].includes(String(key))} aria-label={label} className={inputClass} value={value} onChange={e=>onChange({[key]:e.target.value})}>{options.map(([value,text])=><option key={value} value={value}>{text}</option>)}</VisualChoiceSelect></label>;
  return <div className="space-y-3 pb-4 border-b border-neutral-100 dark:border-neutral-800" data-section-image-settings>
    <h4 className="text-[11px] font-bold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Image</h4>
    {mode!=='design'&&<>{assetControl}
    {geometryLocked && <p className="text-xs text-neutral-500 dark:text-neutral-400">Size and shape follow this section layout. Crop, fill and color layers remain editable.</p>}
    <label className="block text-sm text-neutral-600 dark:text-neutral-400">Image alternative text<input aria-label="Image alternative text" className={inputClass} value={section.mediaAlt??''} onChange={e=>onChange({mediaAlt:e.target.value})}/></label>
    <p className="text-xs text-neutral-500 dark:text-neutral-400">Describe meaningful images; leave empty for decorative images.</p>
    </>}
    {mode!=='content'&&<>
    {select('Image ratio','imageRatio',section.imageRatio??'default',[['default','Default (section layout)'],['auto','Original image'],...['16:9','3:2','4:3','1:1','3:4'].map(r=>[r,r] as [string,string])])}
    <fieldset><legend className="text-sm text-neutral-600 dark:text-neutral-400 mb-2">Image position</legend><div className="grid grid-cols-3 gap-1 max-w-[180px]">{Object.keys(imagePositions).map(position=>{
      const selected=(section.imagePosition??legacyPosition[section.mediaFocalPoint??'center'])===position;
      return <button key={position} type="button" aria-label={`Image position ${position}`} aria-pressed={selected} title={position.replaceAll('-',' ')} onClick={()=>onChange({imagePosition:position as PageSection['imagePosition']})} className={`p-2 rounded-lg border text-sm ${selected?'bg-indigo-600 border-indigo-600 text-white':'border-neutral-200 dark:border-neutral-700 text-neutral-500'}`}><span aria-hidden="true">{position==='center'?'●':'○'}</span></button>;
    })}</div></fieldset>
    {select('Image fill mode','imageFit',section.imageFit??'cover',[['cover','Cover — crop to fill'],['contain','Contain — show whole image']])}
    <details><summary className="text-sm font-semibold text-neutral-600 dark:text-neutral-400 cursor-pointer">Advanced Image</summary><div className="space-y-3 pt-3">
      {select('Image width','imageWidth',section.imageWidth??'100',[['100','100% of image column'],['75','75% of image column'],['50','50% of image column']])}
      {select('Image shape','imageShape',section.imageShape??'default',[['default','Default'],['rectangle','Rectangle'],['rounded','Rounded'],['circle','Circle (square frame)']])}
      <InspectorToggle label="Dark overlay" ariaLabel="Image overlay" checked={section.imageOverlay??false} onChange={checked=>onChange({imageOverlay:checked})}/>
      {section.imageOverlay && <label className="block text-sm text-neutral-600 dark:text-neutral-400">Overlay opacity ({section.imageOverlayOpacity??30}%)<input aria-label="Image overlay opacity" type="range" min="0" max="100" step="10" className="w-full accent-indigo-600" value={section.imageOverlayOpacity??30} onChange={e=>onChange({imageOverlayOpacity:Number(e.target.value) as PageSection['imageOverlayOpacity']})}/></label>}
      <InspectorToggle label="Foreground color" ariaLabel="Image foreground" checked={section.imageForeground??false} onChange={checked=>onChange({imageForeground:checked})}/>
      {section.imageForeground && <><label className="block text-sm text-neutral-600 dark:text-neutral-400">Foreground color<input aria-label="Image foreground color picker" type="color" value={section.imageForegroundColor??'#4f46e5'} onChange={e=>onChange({imageForegroundColor:e.target.value})}/></label><input aria-label="Image foreground color" className={inputClass} value={section.imageForegroundColor??'#4f46e5'} onChange={e=>onChange({imageForegroundColor:e.target.value})} placeholder="#4f46e5"/><label className="block text-sm text-neutral-600 dark:text-neutral-400">Foreground opacity ({section.imageForegroundOpacity??20}%)<input aria-label="Image foreground opacity" type="range" min="0" max="100" step="10" className="w-full accent-indigo-600" value={section.imageForegroundOpacity??20} onChange={e=>onChange({imageForegroundOpacity:Number(e.target.value) as PageSection['imageForegroundOpacity']})}/></label></>}
    </div></details></>}
  </div>;
}
