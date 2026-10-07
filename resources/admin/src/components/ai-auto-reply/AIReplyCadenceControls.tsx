import React from 'react';
import {Timer} from 'lucide-react';
import {AISectionHeader} from './AISectionHeader';
import {AISettingToggle} from './AISettingInputs';
import type {ReplyCadenceConfig} from '../../types/aiAutoReply';

export function AIReplyCadenceControls({value,onChange}:{value?:ReplyCadenceConfig;onChange:(value:ReplyCadenceConfig)=>void}){
 const c={enabled:false,collectionSeconds:2,intervalSeconds:1,maxSegments:3,...value};
 const fields=[{key:'collectionSeconds' as const,label:'Collect consecutive messages',min:0,max:3,unit:'seconds'},
 {key:'intervalSeconds' as const,label:'Pause between reply segments',min:0,max:3,unit:'seconds'},
 {key:'maxSegments' as const,label:'Maximum reply segments',min:1,max:3,unit:'messages'}];
 return <section id="ai-reply-rhythm-card" className="bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-xl p-5 shadow-xs">
  <AISectionHeader icon={Timer} title="Reply Rhythm" description="Let customers finish their thought, then deliver clear replies at a comfortable pace."/>
  <label className="flex items-center justify-between gap-4 text-sm mb-5"><span className="font-semibold text-neutral-900 dark:text-white">Enable reply rhythm</span><AISettingToggle aria-label="Enable reply rhythm" checked={c.enabled} onChange={e=>onChange({...c,enabled:e.target.checked})}/></label>
  <div className="grid grid-cols-1 md:grid-cols-3 gap-4">{fields.map(f=><label key={f.key} className="p-4 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/50">
   <span className="block text-xs font-semibold text-neutral-900 dark:text-white mb-2">{f.label}</span>
   <span className="block text-xs text-indigo-600 dark:text-indigo-300 mb-3">{c[f.key]} {f.unit}</span>
   <input type="range" aria-label={f.label} min={f.min} max={f.max} step={1} value={c[f.key]} onChange={e=>onChange({...c,[f.key]:Number(e.target.value)})} className="w-full accent-indigo-600"/>
  </label>)}</div>
  <p className="text-xs text-neutral-500 dark:text-neutral-400 mt-4">Short answers stay in one message. Continuous input is collected for at most 5 seconds. Staff takeover or pausing AI stops remaining replies. Verification and human handoff messages are sent together.</p>
 </section>;
}
