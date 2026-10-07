import React from 'react';
import {Paperclip, ShieldCheck} from 'lucide-react';
import {AISectionHeader} from './AISectionHeader';
import type {AttachmentAnalysisConfig} from '../../types/aiAutoReply';
import {DEFAULT_AI_AUTO_REPLY_CONFIG} from '../../data/defaultAIAutoReply';
import {AISettingToggle} from './AISettingInputs';

export function AIAttachmentControls({mode,value,readiness,onChange}:{mode:'capabilities'|'limits';value?:AttachmentAnalysisConfig;readiness?:{image:boolean;document:boolean;audio:boolean};onChange:(value:AttachmentAnalysisConfig)=>void}){
 const config={...DEFAULT_AI_AUTO_REPLY_CONFIG.attachmentAnalysis!,...value};
 return <section className="bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-xl p-5 shadow-xs">
  <AISectionHeader icon={mode==='capabilities'?Paperclip:ShieldCheck} title={mode==='capabilities'?'Customer Attachment Understanding':'Attachment Analysis Boundaries'} description={mode==='capabilities'?'Choose which customer attachments AI can read. Master and channel switches still apply; staff takeover stops automatic replies.':'Attachments are customer-provided information. They never verify identity, payment or refund eligibility.'}/>
  {mode==='capabilities'?<div className="grid grid-cols-1 md:grid-cols-3 gap-3">{([['image','Image understanding','Objects, readable text and visible damage'],['document','PDF analysis','Text and scanned pages within your page limit'],['audio','Voice transcription','WhatsApp voice messages; text replies only']] as const).map(([key,label,help])=><label key={key} className="p-3 rounded-lg bg-neutral-50 dark:bg-neutral-800/50 border border-neutral-200 dark:border-neutral-700 flex items-start justify-between gap-3 text-xs">
   <span><span className="block font-semibold text-neutral-900 dark:text-white">{label}</span><span className="block mt-1 text-neutral-500">{help}</span>{!readiness?.[key]&&<span className="block mt-1 text-amber-600 dark:text-amber-400">Unavailable with current AI connection</span>}</span>
   <AISettingToggle aria-label={label} checked={config[key]} disabled={!readiness?.[key]} onChange={e=>onChange({...config,[key]:e.target.checked})}/>
  </label>)}</div>:<div className="grid grid-cols-1 md:grid-cols-3 gap-3">{([['pdfPages','Maximum PDF pages',1,20],['audioSeconds','Maximum voice seconds',15,300],['retentionDays','Analysis retention days',7,90]] as const).map(([key,label,min,max])=><label key={key} className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">{label}<input aria-label={label} type="number" min={min} max={max} value={config[key]} onChange={e=>onChange({...config,[key]:Math.max(min,Math.min(max,Number(e.target.value)||min))})} className="mt-2 w-full text-xs p-3 bg-neutral-50 dark:bg-neutral-800 border border-neutral-300 dark:border-neutral-700 rounded-lg text-neutral-900 dark:text-white"/></label>)}
   <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300 md:col-span-3">Voice transcription model<select aria-label="Voice transcription model" value={config.transcriptionModel} onChange={e=>onChange({...config,transcriptionModel:e.target.value as AttachmentAnalysisConfig['transcriptionModel']})} className="mt-2 w-full text-xs p-3 bg-neutral-50 dark:bg-neutral-800 border border-neutral-300 dark:border-neutral-700 rounded-lg text-neutral-900 dark:text-white"><option value="gpt-4o-mini-transcribe">GPT-4o mini transcribe</option><option value="gpt-4o-transcribe">GPT-4o transcribe</option></select></label>
  </div>}
  <p className="text-xs text-neutral-500 mt-3">{mode==='limits'?'Limits apply to AI analysis. Files remain available to staff; retention removes derived analysis only.':'Analysis starts on new incoming attachments after saving. It does not scan old conversations.'}</p>
 </section>;
}
