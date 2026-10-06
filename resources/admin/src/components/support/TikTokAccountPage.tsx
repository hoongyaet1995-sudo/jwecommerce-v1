import React,{useState} from 'react';
import {ArrowLeft,Music2,Plus} from 'lucide-react';
import {useAdmin} from '../../context/AdminContext';

export function TikTokAccountPage(){
  const {setCurrentSection}=useAdmin();
  const [tab,setTab]=useState<'accounts'|'guide'>('accounts');
  return <div className="space-y-6 text-neutral-900 dark:text-neutral-100">
    <button type="button" onClick={()=>setCurrentSection('support-overview')} className="inline-flex items-center gap-1.5 text-xs text-neutral-500 dark:text-neutral-400"><ArrowLeft className="w-4 h-4"/>Back to Customer Support</button>
    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div className="flex items-center gap-4"><span className="w-12 h-12 rounded-full flex items-center justify-center text-white shrink-0 bg-neutral-900 dark:bg-neutral-700"><Music2 className="w-7 h-7"/></span><div><h2 className="text-2xl font-bold tracking-tight">TikTok Direct Messages</h2><p className="mt-1 text-sm text-neutral-500 dark:text-neutral-400">Manage TikTok Business Accounts authorized to this store.</p></div></div>
      <button disabled className="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg bg-neutral-900 dark:bg-neutral-700 text-white text-sm font-semibold opacity-40"><Plus className="w-4 h-4"/>Connect TikTok</button>
    </div>
    <p role="status" className="text-xs text-neutral-500 dark:text-neutral-400">等待 Tiktok 授权 · Business Messaging access must be enabled before accounts can connect.</p>
    <div className="flex gap-6 border-b border-neutral-200 dark:border-neutral-800" role="tablist" aria-label="TikTok management">
      {(['accounts','guide'] as const).map(value=><button key={value} type="button" role="tab" aria-selected={tab===value} onClick={()=>setTab(value)} className={'pb-3 text-sm font-medium border-b-2 '+(tab===value?'border-indigo-500 text-indigo-600 dark:text-indigo-400':'border-transparent text-neutral-500 dark:text-neutral-400')}>{value==='accounts'?'Accounts':'Connection guide'}</button>)}
    </div>
    {tab==='accounts'?<div className="overflow-x-auto rounded-xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900">
      <table className="w-full text-left text-sm min-w-[760px]"><thead className="bg-neutral-50 dark:bg-neutral-800/60 text-xs text-neutral-500 dark:text-neutral-400"><tr>{['TikTok account','Authorization','Messaging','Last checked','Actions'].map(label=><th key={label} scope="col" className="px-5 py-3.5 font-medium">{label}</th>)}</tr></thead></table>
      <div className="py-14 px-6 text-center"><Music2 className="w-8 h-8 mx-auto text-neutral-400 mb-3"/><p className="font-semibold">TikTok messaging is not available yet</p><p className="mt-2 text-sm text-neutral-500 dark:text-neutral-400">Your administrator is preparing Business Messaging access. No accounts have been connected.</p></div>
    </div>:<div className="rounded-xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 p-6 space-y-4 text-sm"><p className="font-semibold">Connect after Business Messaging is enabled</p><p className="text-neutral-500 dark:text-neutral-400">Your administrator completes the TikTok application and Social Hub setup. You then authorize your own TikTok Business Account from this page.</p><p className="text-neutral-500 dark:text-neutral-400">Social Hub checks OAuth permission and handles callbacks and webhook forwarding. This store manages tokens, customer conversations, staff replies, AI assistance and message notifications.</p><p className="text-neutral-500 dark:text-neutral-400">TikTok Login Kit, video publishing and TikTok Shop access do not enable direct messaging. Real receipt and reply delivery must be verified before messaging is shown as ready.</p></div>}
  </div>;
}
