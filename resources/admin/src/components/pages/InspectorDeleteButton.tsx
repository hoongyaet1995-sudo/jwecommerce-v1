import React from 'react';
import {Trash2} from 'lucide-react';
export function InspectorDeleteButton({label='Delete item',className='',...props}:React.ButtonHTMLAttributes<HTMLButtonElement>&{label?:string}){
 return <button {...props} type="button" aria-label={props['aria-label']??label} title={props.title??label} className={`inline-flex items-center justify-center p-1.5 rounded-md text-neutral-400 hover:text-rose-500 hover:bg-rose-500/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-500 ${className}`}><Trash2 aria-hidden="true" className="w-3.5 h-3.5"/></button>;
}
