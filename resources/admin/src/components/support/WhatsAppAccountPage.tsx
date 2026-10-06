import React, {useEffect,useRef,useState} from 'react';
import {ArrowLeft, MessageCircle as WhatsApp, Plus, RefreshCw, Info, Ellipsis, X, Check} from 'lucide-react';
import {useAdmin} from '../../context/AdminContext';
import {useWhatsAppAccounts, whatsappOAuthNotice} from '../../integration/whatsappAccounts';

export const WhatsAppAccountPage:React.FC=()=>{
  const {setCurrentSection,addToast}=useAdmin();
  const whatsapp=useWhatsAppAccounts(true);
  const [tab,setTab]=useState<'accounts'|'guide'>('accounts');
  const confirmationDialog=useRef<HTMLDialogElement>(null);
  const [confirmAction,setConfirmAction]=useState<{id:string;name:string;action:'disconnect'|'delete'}|null>(null);
  useEffect(()=>{if(confirmAction)confirmationDialog.current?.showModal();},[confirmAction]);
  const pageDialog=useRef<HTMLDialogElement>(null);
  const [selectedaccounts,setSelectedaccounts]=useState<string[]>([]);
  const pendingaccounts=whatsapp.data?.pages.length||0;
  useEffect(()=>{if(pendingaccounts&&whatsapp.data?.canManage)pageDialog.current?.showModal();else {pageDialog.current?.close();setSelectedaccounts([]);}},[pendingaccounts,whatsapp.data?.canManage]);
  const [expanded,setExpanded]=useState<string|null>(null);
  const callbackResult=new URLSearchParams(window.location.search).get('whatsapp');
  const callbackNotified=useRef(false);
  useEffect(()=>{
    if(!callbackResult||!whatsappOAuthNotice[callbackResult]||callbackNotified.current)return;
    callbackNotified.current=true;
    const type=callbackResult==='cancelled'?'info':['provider_error','hub_unavailable'].includes(callbackResult)?'error':'warning';
    addToast({type,title:callbackResult==='cancelled'?'WhatsApp authorization cancelled':type==='error'?'WhatsApp authorization failed':'WhatsApp authorization requires attention',message:whatsappOAuthNotice[callbackResult]});
    const url=new URL(window.location.href);
    url.searchParams.delete('whatsapp');
    window.history.replaceState(window.history.state,'',url.pathname+url.search+url.hash);
  },[callbackResult,addToast]);
  const previousError=useRef('');
  useEffect(()=>{
    if(whatsapp.error&&previousError.current!==whatsapp.error)addToast({type:'error',title:'WhatsApp connection failed',message:whatsapp.error});
    previousError.current=whatsapp.error;
  },[whatsapp.error,addToast]);
  const setupNotified=useRef(false);
  useEffect(()=>{
    if(whatsapp.data&&!whatsapp.data.configured&&!setupNotified.current){
      setupNotified.current=true;
      if(!callbackNotified.current)addToast({type:'warning',title:'WhatsApp setup required',message:whatsappOAuthNotice.not_configured});
    }
  },[whatsapp.data,addToast]);
  const canConnect=whatsapp.data?.canManage&&whatsapp.data.configured&&whatsapp.data.oauthPermitted;
  const menuItemClass='flex w-full items-center px-3 py-2.5 rounded-md text-left text-sm whitespace-nowrap hover:bg-neutral-100 dark:hover:bg-neutral-800 focus-visible:outline-none focus-visible:bg-neutral-100 dark:focus-visible:bg-neutral-800';
  const actionClass='inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-neutral-200 dark:border-neutral-700 text-xs font-medium text-emerald-600 dark:text-emerald-400 hover:bg-neutral-50 dark:hover:bg-neutral-800';
  return <div className="space-y-6 text-neutral-900 dark:text-neutral-100">
    <button type="button" onClick={()=>setCurrentSection('support-overview')} className="inline-flex items-center gap-1.5 text-xs text-neutral-500 dark:text-neutral-400 hover:text-emerald-600 dark:hover:text-emerald-400"><ArrowLeft className="w-4 h-4"/>Back to Customer Support</button>
    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div className="flex items-center gap-4"><span className="w-12 h-12 rounded-full flex items-center justify-center text-white shrink-0 bg-gradient-to-br from-emerald-500 to-emerald-600"><WhatsApp className="w-7 h-7"/></span><div><h2 className="text-2xl font-bold tracking-tight">WhatsApp Messages</h2><p className="mt-1 text-sm text-neutral-500 dark:text-neutral-400">Manage WhatsApp accounts authorized to this store.</p></div></div>
      {!canConnect&&whatsapp.data?.canManage&&<button disabled className="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg bg-gradient-to-br from-emerald-500 to-emerald-600 text-white text-sm font-semibold opacity-40"><Plus className="w-4 h-4"/>Connect WhatsApp</button>}{canConnect&&<a href="/admin/social/whatsapp/authorize" className="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg bg-gradient-to-br from-emerald-500 to-emerald-600 text-white text-sm font-semibold hover:from-emerald-600 hover:to-emerald-700"><Plus className="w-4 h-4"/>Connect WhatsApp</a>}
    </div>
    {whatsapp.data&&<p className="text-xs text-neutral-500 dark:text-neutral-400">{whatsapp.data.oauthPermitted?'Social Hub has permitted WhatsApp OAuth.':whatsapp.data.oauthReason||'Social Hub has not permitted WhatsApp OAuth.'}</p>}
    <div className="flex gap-6 border-b border-neutral-200 dark:border-neutral-800" role="tablist" aria-label="WhatsApp management">
      {(['accounts','guide'] as const).map(value=><button key={value} type="button" role="tab" aria-selected={tab===value} onClick={()=>setTab(value)} className={'pb-3 text-sm font-medium border-b-2 '+(tab===value?'border-emerald-500 text-emerald-600 dark:text-emerald-400':'border-transparent text-neutral-500 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white')}>{value==='accounts'?'Accounts':'Connection guide'}</button>)}
    </div>
    {whatsapp.data&&!whatsapp.data.canManage&&<p className="text-sm text-neutral-500 dark:text-neutral-400">A store administrator must authorize new accounts. Existing connections are shared with this store’s staff.</p>}
    {tab==='accounts'?<section aria-label="WhatsApp accounts" className="space-y-4">
      <div className="flex justify-end"><button type="button" disabled={whatsapp.loading} onClick={()=>void whatsapp.refresh(true)} className="inline-flex items-center gap-2 text-xs font-medium text-neutral-500 dark:text-neutral-400 hover:text-emerald-600 dark:hover:text-emerald-400 disabled:opacity-50"><RefreshCw className={'w-3.5 h-3.5 '+(whatsapp.loading?'animate-spin':'')}/>{whatsapp.loading?'Checking connections…':'Check connections'}</button></div>
      {!!pendingaccounts&&whatsapp.data?.canManage&&<button type="button" onClick={()=>pageDialog.current?.showModal()} className={actionClass}>Select an authorized account</button>}

      <div className="overflow-x-auto rounded-xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900">
        <table className="w-full text-left text-sm min-w-[760px]"><thead className="bg-neutral-50 dark:bg-neutral-800/60 text-xs text-neutral-500 dark:text-neutral-400"><tr>{['WhatsApp account','Authorization','Messaging','Last checked','Actions'].map(label=><th key={label} scope="col" className="px-5 py-3.5 font-medium">{label}</th>)}</tr></thead>
          <tbody className="divide-y divide-neutral-200 dark:divide-neutral-800">{whatsapp.data?.accounts.map(account=><React.Fragment key={account.id}>
            <tr><td className="px-5 py-5"><div className="flex items-center gap-3"><span className="w-10 h-10 rounded-full bg-neutral-100 dark:bg-neutral-800 flex items-center justify-center font-semibold shrink-0">{account.name.slice(0,1).toUpperCase()}</span><div><div className="font-semibold">{account.name}</div><div className="mt-1 text-xs text-neutral-500 dark:text-neutral-400">Phone number ID: {account.pageId}</div></div></div></td>
              <td className="px-5 py-5"><span className={'inline-flex items-center gap-2 whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-medium '+(account.state==='connected'?'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400':'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300')}><span className={'w-1.5 h-1.5 rounded-full '+(account.state==='connected'?'bg-emerald-500':'bg-amber-500')}/>{account.state==='connected'?'Connected':account.state==='authorization_expired'?'Reconnect required':account.state==='permission_required'?'Permission required':account.state==='disconnected'?'Disconnected':'Unable to verify'}</span></td>
              <td className="px-5 py-5"><span className="inline-flex items-center gap-2 whitespace-nowrap rounded-full px-2.5 py-1 text-xs bg-neutral-100 dark:bg-neutral-800 text-neutral-500 dark:text-neutral-400"><span className="w-1.5 h-1.5 rounded-full bg-neutral-400"/>{account.messagingReady?'Ready to chat':account.messagingError?'Action required':account.inboundEnabled?'Verification required':'Not enabled'}</span></td>
              <td className="px-5 py-5 text-xs whitespace-nowrap text-neutral-500 dark:text-neutral-400">{account.checkedAt?new Date(account.checkedAt).toLocaleString():'Not yet verified'}</td>
              <td className="px-5 py-5"><WhatsAppActions name={account.name} disabled={whatsapp.loading}><button type="button" role="menuitem" className={menuItemClass} onClick={()=>setExpanded(expanded===account.id?null:account.id)}>Connection details</button>{whatsapp.data?.canManage&&account.state!=='disconnected'&&<button type="button" role="menuitem" className={menuItemClass} onClick={()=>void whatsapp.manage(account.id,'check')}>Check this account</button>}{whatsapp.data?.canManage&&<><a role="menuitem" aria-disabled={!canConnect} href={canConnect?'/admin/social/whatsapp/authorize':undefined} className={menuItemClass+(!canConnect?' opacity-40 pointer-events-none':'')}>{account.state==='disconnected'?'Reconnect':'Review authorization'}</a><div className="my-1 border-t border-neutral-200 dark:border-neutral-700"/><button type="button" role="menuitem" className={menuItemClass+' text-red-600 dark:text-red-400'} onClick={()=>setConfirmAction({id:account.id,name:account.name,action:account.state==='disconnected'?'delete':'disconnect'})}>{account.state==='disconnected'?'Delete saved connection':'Disconnect'}</button></>}</WhatsAppActions>{(account.messagingError||account.checkReason)&&<p className="mt-2 max-w-56 text-xs text-neutral-500 dark:text-neutral-400">{account.messagingError||account.checkReason}</p>}</td></tr>
            {expanded===account.id&&<tr><td colSpan={5} className="px-5 py-4 bg-neutral-50 dark:bg-neutral-800/40"><div className="flex flex-wrap items-start justify-between gap-4"><div className="space-y-2"><p className="text-sm font-semibold">{account.name} · Connection details</p><p className="text-xs text-neutral-500 dark:text-neutral-400">{!account.inboundRoutingReady&&<>WhatsApp webhook routing is awaiting administrator setup.<br/></>}Granted permissions: {account.permissions.length?account.permissions.join(', '):'No permissions reported'}</p><p className="text-xs text-neutral-500 dark:text-neutral-400">{account.messagingReady?'Messaging subscription verified. Staff can take over and reply within 24 hours of the customer’s latest message.':account.messagingError||'This account is shared with this store’s staff. Messaging is not ready.'}</p></div>{canConnect&&<a href="/admin/social/whatsapp/authorize" className="text-xs font-medium text-emerald-600 dark:text-emerald-400">Review WhatsApp authorization →</a>}</div></td></tr>}
          </React.Fragment>)}</tbody>
        </table>
        {!whatsapp.data?.accounts.length&&<div className="py-14 px-6 text-center"><WhatsApp className="w-8 h-8 mx-auto text-neutral-400 mb-3"/><p className="font-semibold">{whatsapp.loading?'Loading WhatsApp accounts…':'No WhatsApp accounts connected yet'}</p><p className="mt-2 text-sm text-neutral-500 dark:text-neutral-400">{whatsapp.loading?'Your saved connections will appear here.':'Connect WhatsApp, then select an account to manage it here.'}</p></div>}
      </div>
      <p className="flex items-start gap-2 text-xs text-neutral-500 dark:text-neutral-400"><Info className="w-4 h-4 shrink-0"/>Messaging requires verified Hub routing and a WhatsApp message subscription.</p>
    </section>:<section aria-label="Connection guide" className="max-w-3xl space-y-6 pb-6">
      <h3 className="text-lg font-semibold">Connect your WhatsApp accounts</h3>
      <ol className="space-y-5">{[['Connect WhatsApp','Connect WhatsApp becomes available once native customer authorization is configured. Meta Embedded Signup must first be configured by your administrator for customer-owned WhatsApp Business accounts.'],['Authorize account access','Review the permissions and select the accounts in Meta. Return to JW to choose one or more authorized accounts to connect.'],['Verify the connection','Existing sender migration is configured by your developer. Messaging becomes available after Hub routing and the WhatsApp message subscription are verified.']].map(([title,description],index)=><li key={title} className="flex gap-4"><span className="flex w-8 h-8 shrink-0 items-center justify-center rounded-full bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 text-sm font-semibold">{index+1}</span><div><h4 className="text-sm font-semibold">{title}</h4><p className="mt-1 text-sm leading-6 text-neutral-500 dark:text-neutral-400">{description}</p></div></li>)}</ol>
      <p className="flex items-start gap-2 text-sm text-neutral-500 dark:text-neutral-400"><Info className="w-4 h-4 shrink-0 mt-0.5"/>If Meta requests more permissions, use Review authorization and grant access to this account. Replies use Meta’s standard 24-hour messaging window.</p>
    </section>}
    <dialog ref={confirmationDialog} aria-labelledby="whatsapp-confirm-title" onCancel={event=>{if(whatsapp.loading)event.preventDefault();else setConfirmAction(null);}} className="m-auto w-[calc(100%-2rem)] max-w-md rounded-2xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-neutral-900 dark:text-neutral-100 p-6 shadow-2xl backdrop:bg-black/60 backdrop:backdrop-blur-sm"><h3 id="whatsapp-confirm-title" className="text-lg font-semibold">{confirmAction?.action==='delete'?'Delete saved connection?':'Disconnect this account?'}</h3><p className="mt-3 text-sm font-semibold">{confirmAction?.name}</p><p className="mt-2 text-sm text-neutral-500 dark:text-neutral-400">{confirmAction?.action==='delete'?'This permanently removes the disconnected record from this store. You can connect the account again through WhatsApp.':'This clears the token saved by this Ecommerce store. The account record remains available to reconnect. Other JW projects and your WhatsApp account are unaffected.'}</p><div className="mt-6 flex justify-end gap-3"><button type="button" disabled={whatsapp.loading} onClick={()=>{confirmationDialog.current?.close();setConfirmAction(null);}} className={actionClass}>Cancel</button><button type="button" disabled={whatsapp.loading} onClick={async()=>{if(confirmAction&&await whatsapp.manage(confirmAction.id,confirmAction.action)){confirmationDialog.current?.close();setConfirmAction(null);}}} className="px-4 py-2 rounded-lg bg-red-600 text-white text-sm font-semibold disabled:opacity-50">{whatsapp.loading?'Processing…':confirmAction?.action==='delete'?'Delete':'Disconnect'}</button></div></dialog>
    <dialog ref={pageDialog} aria-labelledby="whatsapp-page-title" aria-describedby="whatsapp-page-help" onCancel={event=>{if(whatsapp.loading)event.preventDefault();}} className="m-auto w-[calc(100%-2rem)] max-w-2xl max-h-[85vh] overflow-y-auto rounded-2xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-neutral-900 dark:text-neutral-100 p-0 shadow-2xl backdrop:bg-black/60 backdrop:backdrop-blur-sm">
      <div className="flex items-start justify-between gap-4 p-6 border-b border-neutral-200 dark:border-neutral-800"><div><h3 id="whatsapp-page-title" className="text-lg font-semibold">Select WhatsApp accounts</h3><p id="whatsapp-page-help" className="mt-2 text-sm text-neutral-500 dark:text-neutral-400">Select one or more accounts to connect. Each account will have its own connection.</p></div><button type="button" aria-label="Close account selection" disabled={whatsapp.loading} onClick={()=>pageDialog.current?.close()} className="p-1 rounded-lg text-neutral-500 hover:bg-neutral-100 dark:hover:bg-neutral-800 disabled:opacity-50"><X className="w-5 h-5"/></button></div>
      <div className="p-6 space-y-4"><div className="grid sm:grid-cols-2 gap-3" role="group" aria-label="Authorized WhatsApp accounts">{whatsapp.data?.pages.map(page=><button type="button" key={page.id} aria-pressed={selectedaccounts.includes(page.id)} disabled={whatsapp.loading} onClick={()=>setSelectedaccounts(previous=>previous.includes(page.id)?previous.filter(id=>id!==page.id):[...previous,page.id])} className={'flex items-start gap-3 text-left p-4 rounded-xl border transition-colors disabled:opacity-50 '+(selectedaccounts.includes(page.id)?'border-emerald-500 bg-emerald-50 dark:bg-emerald-950/30':'border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 hover:border-emerald-400')}><span className="w-10 h-10 rounded-lg bg-gradient-to-br from-emerald-500 to-emerald-600 text-white flex items-center justify-center shrink-0"><WhatsApp className="w-5 h-5"/></span><span className="min-w-0 flex-1"><span className="block text-sm font-semibold break-words">{page.name}</span><span className="block mt-1 text-xs text-neutral-500 dark:text-neutral-400 break-all">Account ID: {page.id}</span></span>{selectedaccounts.includes(page.id)&&<Check className="w-4 h-4 text-emerald-600 dark:text-emerald-400 shrink-0"/>}</button>)}</div></div>
      <div className="flex justify-end gap-3 p-6 border-t border-neutral-200 dark:border-neutral-800"><button type="button" disabled={whatsapp.loading} onClick={()=>pageDialog.current?.close()} className={actionClass}>Cancel</button><button type="button" disabled={!selectedaccounts.length||whatsapp.loading} onClick={()=>{if(selectedaccounts.length)void whatsapp.select(selectedaccounts);}} className="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg bg-gradient-to-br from-emerald-500 to-emerald-600 text-white text-sm font-semibold hover:from-emerald-600 hover:to-emerald-700 disabled:opacity-50">{whatsapp.loading?'Connecting…':`Connect selected accounts (${selectedaccounts.length})`}</button></div>
    </dialog>
  </div>;
};

function WhatsAppActions({name,disabled,children}:{name:string;disabled:boolean;children:React.ReactNode}) {
  const trigger=useRef<HTMLButtonElement>(null);
  const popup=useRef<HTMLDivElement>(null);
  const [open,setOpen]=useState(false);
  const close=()=>popup.current?.hidePopover();
  useEffect(()=>{
    const dismiss=()=>popup.current?.hidePopover();
    window.addEventListener('resize',dismiss);
    window.addEventListener('scroll',dismiss,true);
    return ()=>{window.removeEventListener('resize',dismiss);window.removeEventListener('scroll',dismiss,true);};
  },[]);
  return <><button ref={trigger} type="button" aria-label={'Actions for '+name} aria-haspopup="menu" aria-expanded={open} disabled={disabled} onClick={()=>{
    const menu=popup.current,button=trigger.current;if(!menu||!button)return;
    if(menu.matches(':popover-open')){menu.hidePopover();return;}
    menu.showPopover();
    const anchor=button.getBoundingClientRect(),box=menu.getBoundingClientRect();
    menu.style.left=Math.max(8,Math.min(anchor.right-box.width,window.innerWidth-box.width-8))+'px';
    menu.style.top=(anchor.bottom+box.height+8<=window.innerHeight?anchor.bottom+6:Math.max(8,anchor.top-box.height-6))+'px';
    menu.querySelector<HTMLElement>('[role="menuitem"]')?.focus();
  }} className="inline-flex w-9 h-9 items-center justify-center rounded-lg text-neutral-500 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 disabled:opacity-50"><Ellipsis className="w-5 h-5"/></button>
    <div ref={popup} popover="auto" role="menu" aria-label={'Actions for '+name} onToggle={()=>setOpen(popup.current?.matches(':popover-open')||false)} onClick={event=>{if((event.target as HTMLElement).closest('[role="menuitem"]'))close();}} onKeyDown={event=>{
      if(!['ArrowDown','ArrowUp','Home','End'].includes(event.key))return;
      event.preventDefault();const items=Array.from(popup.current?.querySelectorAll<HTMLElement>('[role="menuitem"]')||[]);
      const index=items.indexOf(document.activeElement as HTMLElement);
      const next=event.key==='Home'?0:event.key==='End'?items.length-1:(index+(event.key==='ArrowDown'?1:-1)+items.length)%items.length;
      items[next]?.focus();
    }} className="fixed m-0 w-56 max-w-[calc(100vw-1rem)] rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-neutral-900 dark:text-neutral-100 p-1.5 shadow-xl">{children}</div>
  </>;
}
