import React, {useState} from 'react';
import {Plus, X} from 'lucide-react';

export const AISettingToggle: React.FC<React.InputHTMLAttributes<HTMLInputElement>> = ({className, ...props}) => (
  <span className="relative inline-flex w-9 h-5 shrink-0 align-middle">
    <input {...props} type="checkbox" role="switch" className="peer absolute inset-0 w-full h-full opacity-0 z-10 cursor-pointer disabled:cursor-not-allowed" />
    <span aria-hidden="true" className="w-9 h-5 rounded-full bg-neutral-300 dark:bg-neutral-700 peer-checked:bg-teal-500 peer-focus-visible:ring-2 peer-focus-visible:ring-indigo-500 peer-focus-visible:ring-offset-2 peer-disabled:opacity-40 transition-colors" />
    <span aria-hidden="true" className="pointer-events-none absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full peer-checked:translate-x-4 peer-disabled:opacity-40 transition-transform" />
  </span>
);

export const AIKeywordInput: React.FC<{label:string; values:string[]; onChange:(values:string[])=>void; maxLength:number}> = ({label,values,onChange,maxLength}) => {
  const [draft,setDraft]=useState('');
  const add=()=>{
    const value=draft.trim();
    if(!value || values.length>=30)return;
    if(!values.some(item=>item.toLocaleLowerCase()===value.toLocaleLowerCase()))onChange([...values,value]);
    setDraft('');
  };
  return <div className="mb-4">
    <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-2">{label}</label>
    <div className="flex flex-wrap items-center gap-2 mb-3">{values.map(value=><span key={value} className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-medium bg-indigo-50 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 break-all">
      {value}<button type="button" aria-label={`Remove ${value}`} onClick={()=>onChange(values.filter(item=>item!==value))} className="hover:text-indigo-900 dark:hover:text-white"><X className="w-3 h-3" /></button>
    </span>)}</div>
    <div className="flex items-center gap-2 max-w-md">
      <input aria-label={label} value={draft} maxLength={maxLength} disabled={values.length>=30} onChange={e=>setDraft(e.target.value)} onKeyDown={e=>{if(e.key==='Enter'){e.preventDefault();add();}}} placeholder="Add a phrase" className="min-w-0 flex-1 text-xs px-3 py-2 bg-neutral-50 dark:bg-neutral-800 border border-neutral-300 dark:border-neutral-700 rounded-lg text-neutral-900 dark:text-white focus:outline-hidden focus:ring-1 focus:ring-indigo-500" />
      <button type="button" onClick={add} disabled={!draft.trim()||values.length>=30} className="px-3 py-2 bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 text-xs font-semibold rounded-lg hover:opacity-90 transition-opacity flex items-center gap-1 disabled:opacity-40"><Plus className="w-3.5 h-3.5" />Add</button>
    </div>
    {values.length>=30&&<p className="text-xs text-neutral-500 mt-2">Maximum 30 phrases. Remove a phrase to add another.</p>}
  </div>;
};
