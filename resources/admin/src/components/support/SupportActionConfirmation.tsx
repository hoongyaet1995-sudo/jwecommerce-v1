import React, {useEffect,useRef} from 'react';
interface Props {title:string;description:string;customerName:string;pending:boolean;onCancel:()=>void;onConfirm:()=>void}
export const SupportActionConfirmation:React.FC<Props>=({title,description,customerName,pending,onCancel,onConfirm})=>{
 const panel=useRef<HTMLDivElement>(null),cancel=useRef<HTMLButtonElement>(null);
 useEffect(()=>{const previous=document.activeElement as HTMLElement;cancel.current?.focus();return()=>previous?.focus();},[]);
 return <div className="fixed inset-0 z-100 flex items-center justify-center p-4 bg-black/75 backdrop-blur-md" onClick={()=>{if(!pending)onCancel();}}>
  <div ref={panel} role="dialog" aria-modal="true" aria-labelledby="support-confirm-title" aria-describedby="support-confirm-description" className="w-full max-w-lg bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 rounded-2xl p-6 shadow-2xl text-neutral-900 dark:text-white space-y-4" onClick={e=>e.stopPropagation()} onKeyDown={e=>{
   if(e.key==='Escape'&&!pending){e.preventDefault();onCancel();}
   if(e.key==='Tab'){const buttons=panel.current?.querySelectorAll<HTMLButtonElement>('button:not(:disabled)');if(!buttons?.length)return;const first=buttons[0],last=buttons[buttons.length-1];if(e.shiftKey&&document.activeElement===first){e.preventDefault();last.focus();}else if(!e.shiftKey&&document.activeElement===last){e.preventDefault();first.focus();}}
  }}>
   <h3 id="support-confirm-title" className="text-base font-semibold">{title}?</h3>
   <p className="text-sm font-medium truncate">{customerName}</p>
   <p id="support-confirm-description" className="text-xs leading-relaxed text-neutral-600 dark:text-neutral-300">{description}</p>
   <div className="flex items-center justify-end gap-2">
    <button ref={cancel} data-support-confirm-cancel type="button" disabled={pending} onClick={onCancel} className="h-8 px-3 text-xs rounded-lg border border-neutral-200 dark:border-neutral-700 hover:bg-neutral-100 dark:hover:bg-neutral-800 disabled:opacity-50">Cancel</button>
    <button data-support-confirm-submit type="button" disabled={pending} onClick={onConfirm} className="h-8 px-3 text-xs font-medium rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white disabled:opacity-50">{pending?'Processing…':title}</button>
   </div>
  </div>
 </div>;
};
