import React from 'react';

interface InspectorSwitchProps {
  label:string;
  checked:boolean;
  onChange:(checked:boolean)=>void;
  disabled?:boolean;
}

export function InspectorSwitch({label,checked,onChange,disabled=false}:InspectorSwitchProps) {
  return <button type="button" role="switch" aria-label={label} aria-checked={checked} disabled={disabled} onClick={()=>onChange(!checked)} className={`w-10 h-6 shrink-0 rounded-full p-[3px] flex items-center border transition-colors duration-150 cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-neutral-900 disabled:opacity-40 disabled:cursor-not-allowed ${checked?'bg-indigo-600 border-indigo-600':'bg-neutral-200 border-neutral-300 dark:bg-neutral-700 dark:border-neutral-600'}`}>
    <span aria-hidden="true" className={`w-4 h-4 rounded-full bg-white shadow-sm transition-transform duration-150 motion-reduce:transition-none ${checked?'translate-x-4':'translate-x-0'}`}/>
  </button>;
}

export function InspectorToggle({label,checked,onChange,disabled,description,ariaLabel}:{label:string;description?:string;ariaLabel?:string}&Omit<InspectorSwitchProps,'label'>) {
  return <div className="flex items-center justify-between gap-3 py-1">
    <div className="min-w-0"><span className="block text-xs font-medium text-neutral-700 dark:text-neutral-300">{label}</span>{description && <span className="block mt-0.5 text-[10px] leading-relaxed text-neutral-400">{description}</span>}</div>
    <InspectorSwitch label={ariaLabel??label} checked={checked} onChange={onChange} disabled={disabled}/>
  </div>;
}
