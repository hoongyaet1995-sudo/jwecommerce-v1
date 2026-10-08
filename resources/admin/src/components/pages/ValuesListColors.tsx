import React from 'react';
import type {PageSection} from '../../types';
import {richTextHasDarkBackground} from '../../cms/richTextPresets';

function swatchValue(value:string|undefined,fallback:string) {
  if(value && /^#[0-9a-f]{6}$/i.test(value)) return value;
  if(value && /^#[0-9a-f]{3}$/i.test(value)) return '#'+value.slice(1).split('').map(character=>character+character).join('');
  return fallback;
}

export function ValuesListColors({section,onChange}:{section:PageSection;onChange:(patch:Partial<PageSection>)=>void}) {
  const dark=richTextHasDarkBackground(section);
  const controls=[
    {field:'valuesNumberColor' as const,label:'Number color',description:'Automatic value numbering',fallback:dark?'#737373':'#d4d4d4'},
    ...(section.valuesShowDividers===false?[]:[{field:'valuesDividerColor' as const,label:'Divider color',description:'Lines between value rows',fallback:dark?'#333333':'#e5e5e5'}]),
  ];
  return <>{controls.map(control=><div key={control.field} className="flex items-center justify-between gap-2">
    <div><span className="text-xs font-medium text-neutral-700 dark:text-neutral-300 block">{control.label}</span><span className="text-[10px] text-neutral-400">{control.description}</span></div>
    <div className="flex items-center gap-2">
      <input type="color" aria-label={`${control.label} picker`} value={swatchValue(section[control.field],control.fallback)} onChange={event=>onChange({[control.field]:event.target.value})} className="w-7 h-7 rounded-lg border border-neutral-300 dark:border-neutral-600 cursor-pointer bg-transparent"/>
      <input type="text" aria-label={`${control.label} Hex`} value={section[control.field]??''} placeholder={control.fallback} onChange={event=>onChange({[control.field]:event.target.value})} className="w-20 px-2 py-1 text-[11px] font-mono rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-neutral-900 dark:text-white"/>
    </div>
  </div>)}</>;
}
