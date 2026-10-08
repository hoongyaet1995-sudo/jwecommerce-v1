import React from 'react';
import type {PageSection} from '../../types';
import {InspectorToggle} from './InspectorToggle';
export {ValuesListEditor} from './ValuesListEditor';
const inputClass='w-full px-3 py-2 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-neutral-900 dark:text-neutral-100 text-sm';
export function ValuesListSettings({section,onChange}:{section:PageSection;onChange:(patch:Partial<PageSection>)=>void}) {
  return <div className="space-y-3 pb-4 border-b border-neutral-100 dark:border-neutral-800">
    <label className="text-[11px] font-bold uppercase tracking-wider text-neutral-500 dark:text-neutral-400 block">Values List layout</label>
    <p className="text-xs text-neutral-500 dark:text-neutral-400">Numbers follow the value order automatically. Desktop uses three columns; mobile stacks each title and description.</p>
    <InspectorToggle label="Show section heading" checked={section.valuesShowHeading??false} onChange={checked=>onChange({valuesShowHeading:checked})}/>
    <label className="block text-xs text-neutral-500">Row spacing<select aria-label="Row spacing" className={`${inputClass} mt-1`} value={section.valuesRowSpacing??'normal'} onChange={event=>onChange({valuesRowSpacing:event.target.value as PageSection['valuesRowSpacing']})}><option value="compact">Compact</option><option value="normal">Normal</option><option value="spacious">Spacious</option></select></label>
    <InspectorToggle label="Show dividers" checked={section.valuesShowDividers??true} onChange={checked=>onChange({valuesShowDividers:checked})}/>
  </div>;
}
