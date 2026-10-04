import React from 'react';
import {ArrowLeft, Facebook} from 'lucide-react';
import {useAdmin} from '../../context/AdminContext';
import {useFacebookAccounts,facebookOAuthNotice} from '../../integration/facebookAccounts';

export const FacebookAccountPage:React.FC=()=>{
  const {setCurrentSection}=useAdmin();
  const facebook=useFacebookAccounts(true);
  const callbackResult=new URLSearchParams(window.location.search).get('facebook');
  return <div className="space-y-6">
    <button type="button" onClick={()=>setCurrentSection('support-overview')} className="inline-flex items-center gap-1.5 text-xs text-neutral-500 dark:text-neutral-400 hover:text-indigo-600 dark:hover:text-indigo-400"><ArrowLeft className="w-4 h-4"/>Back to Customer Support</button>
    <div className="p-5 sm:p-6 rounded-2xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900">
      <div className="flex items-center gap-3"><span className="w-11 h-11 rounded-xl flex items-center justify-center text-white shrink-0 bg-blue-600"><Facebook className="w-5 h-5"/></span><div><h2 className="text-lg font-bold text-neutral-900 dark:text-white">Facebook Account Management</h2><p className="mt-1 text-sm text-neutral-500 dark:text-neutral-400">Manage your authorized Facebook Pages and connection status.</p></div></div>
    </div>
    <section aria-label="Facebook connection" className="p-4 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-neutral-900 dark:text-neutral-100 space-y-3">
              <div className="flex items-center justify-between gap-3"><h3 className="text-sm font-semibold">Facebook Pages</h3><button type="button" disabled={facebook.loading} onClick={()=>void facebook.refresh()} className="text-xs text-indigo-600 dark:text-indigo-400 disabled:opacity-50">Check connection</button></div>
              {callbackResult&&facebookOAuthNotice[callbackResult]&&<p role="status" className="text-xs text-amber-700 dark:text-amber-300">{facebookOAuthNotice[callbackResult]}</p>}
              {facebook.error&&<p role="alert" className="text-xs text-red-600 dark:text-red-400">{facebook.error}</p>}
              {facebook.loading&&<p role="status" className="text-xs text-neutral-500">Verifying with Meta…</p>}
              {facebook.data?.pages.map(page=><button key={page.id} disabled={facebook.loading} onClick={()=>void facebook.select(page.id)} className="w-full flex items-center justify-between gap-3 text-left p-3 rounded-xl border border-neutral-200 dark:border-neutral-700 hover:border-indigo-500 disabled:opacity-50"><span><span className="block text-sm font-semibold">{page.name}</span><span className="text-xs text-neutral-500">Page ID: {page.id}</span></span><span className="text-xs text-indigo-600 dark:text-indigo-400">Connect Page →</span></button>)}
              {facebook.data?.accounts.map(account=><div key={account.id} className="p-3 rounded-xl bg-neutral-50 dark:bg-neutral-800 space-y-1"><div className="flex items-center justify-between gap-2"><span className="text-sm font-semibold">{account.name}</span><span className={'text-xs '+(account.state==='connected'?'text-emerald-600 dark:text-emerald-400':'text-amber-700 dark:text-amber-300')}>{account.state==='connected'?'Connected':account.state==='authorization_expired'?'Reconnect required':'Unable to verify'}</span></div><p className="text-xs text-neutral-500">Page ID: {account.pageId}</p><p className="text-xs text-neutral-500">{account.checkedAt?'Last verified: '+new Date(account.checkedAt).toLocaleString():'Not yet verified'}</p><p className="text-xs text-neutral-500">Page authorization only · Messaging not enabled</p></div>)}
              {facebook.data&&!facebook.data.pages.length&&!facebook.data.accounts.length&&<p className="text-xs text-neutral-500 dark:text-neutral-400">No Facebook Pages connected to this store.</p>}
              {facebook.data?.canManage&&facebook.data.configured&&<a href="/admin/social/facebook/authorize" className="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700"><Facebook className="w-4 h-4"/>Continue with Facebook</a>}
              {facebook.data&&!facebook.data.configured&&<p className="text-xs text-amber-700 dark:text-amber-300">Server credentials and an HTTPS OAuth callback are required. Ask your administrator to complete setup.</p>}
              {facebook.data&&!facebook.data.canManage&&<p className="text-xs text-neutral-500">A store administrator must authorize new Pages. Existing connections are shared with this store’s staff.</p>}
            </section>
  </div>;
};