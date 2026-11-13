import React from 'react';
import type {PageSection} from '../../types';
import {imageTargets,imageTargetSection,updateImageTarget,type ImageTarget} from '../../cms/imageTargets';
import {SectionImageSettings} from './SectionImageSettings';
export function ImageTargetInspector({section,selected,onSelect,onChange,assetControl,mode='all'}:{section:PageSection;mode?:'all'|'content'|'design';selected?:string;onSelect?:(id:string)=>void;onChange:(section:PageSection)=>void;assetControl:(target:ImageTarget)=>React.ReactNode}) {
  const targets=imageTargets(section);if(!targets.length) return null;
  const target=targets.find(target=>target.id===selected)??targets[0];
  return <div data-image-target-inspector>
    {targets.length>1 && <div className="space-y-3 py-4 border-b border-neutral-100 dark:border-neutral-800"><h4 className="text-[11px] font-bold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Images ({targets.length})</h4>
      <div className="grid grid-cols-2 gap-2">{targets.map(item=><button type="button" key={item.id} aria-label={`Edit ${item.label}`} aria-pressed={item.id===target.id} onClick={()=>onSelect?.(item.id)} className={`p-2 rounded-lg border text-left ${item.id===target.id?'border-indigo-500 bg-indigo-500/10':'border-neutral-200 dark:border-neutral-700'}`}>
        {item.url?<img src={item.url} alt="" referrerPolicy="no-referrer" className="w-full h-16 object-cover rounded"/>:<span className="block h-16 bg-neutral-100 dark:bg-neutral-800 rounded"/>}<span className="block text-xs mt-2 text-neutral-600 dark:text-neutral-300">{item.label}</span>
      </button>)}</div><p className="text-xs text-neutral-500 dark:text-neutral-400">Select a canvas image or thumbnail. Settings apply only to the selected image.</p>
    </div>}
    <p className="py-3 text-sm font-semibold text-neutral-600 dark:text-neutral-300">Editing: {target.label}</p>
    <SectionImageSettings fixedAppearance={section.type==='cta_strip'&&['illustrated-panel-right','illustrated-panel','editorial-illustrated'].includes(section.ctaPreset??'')} mode={mode} key={target.id} section={imageTargetSection(section,target)} geometryLocked={target.geometryLocked} onChange={patch=>onChange(updateImageTarget(section,target.id,patch))} assetControl={assetControl(target)}/>
  </div>;
}
