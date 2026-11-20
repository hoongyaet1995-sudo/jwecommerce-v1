import {LayoutDiagramDropdown} from './LayoutDiagramDropdown';
import React from 'react';
import type {PageSection} from '../../types';
export function PaymentSettings({mode,section,onChange}:{mode:'content'|'design';section:PageSection;onChange:(section:PageSection)=>void}){
 if(section.type!=='payment_methods')return null;
 if(mode==='content')return <div className="py-4 space-y-3"><p className="text-xs text-neutral-500">Payment methods update automatically from saved store settings. Test and incomplete channels appear in the editor; customer pages show live options.</p><a href="/admin/settings/payment" className="text-xs text-indigo-500">Manage payment methods ↗</a><label className="block text-xs text-neutral-500">Payment note<textarea className="mt-2 w-full p-3 rounded-xl border border-neutral-300 dark:border-neutral-700 bg-transparent" value={section.paymentNote??''} onChange={e=>onChange({...section,paymentNote:e.target.value})}/></label></div>;
 return <fieldset className="py-4"><legend className="text-xs text-neutral-500">Payment layout</legend><LayoutDiagramDropdown label="Payment layout" value={section.paymentLayout??'cards'}
 choices={(['cards','list'] as const).map(layout=>({value:layout,label:layout==='cards'?'Cards':'List',diagram:<span aria-hidden="true" className={`h-16 bg-neutral-200 dark:bg-neutral-800 rounded p-2 ${layout==='cards'?'flex gap-1':'space-y-1'}`}>{[1,2,3].map(i=><span key={i} className={`block bg-neutral-400/70 rounded ${layout==='cards'?'flex-1':'h-3 w-full'}`}/>)}</span>}))}
 onChange={(layout)=>onChange({...section,paymentLayout:layout})}/></fieldset>;
}
