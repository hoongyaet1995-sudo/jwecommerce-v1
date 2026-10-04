import { useAdminCurrency } from '../../context/useAdminCurrency';
import React, { useEffect, useRef, useState } from 'react';
import { useAdmin } from '../../context/AdminContext';
import {useFacebookAccounts,facebookOAuthNotice} from '../../integration/facebookAccounts';
import {
  ShoppingBag,
  Users,
  ArrowRight,
  TrendingUp,
  CreditCard,
  Truck,
  ShieldCheck,
  UserCheck,
  Award,
  DollarSign, Facebook, Instagram, Music2, MessageCircle, X, Settings, Info
} from 'lucide-react';

export const SupportOverviewHub: React.FC = () => {
  const { setCurrentSection } = useAdmin();
  const callbackResult=new URLSearchParams(window.location.search).get('facebook');
  const [channelsOpen,setChannelsOpen]=useState(Boolean(callbackResult));
  const [selectedChannel,setSelectedChannel]=useState<string|null>(callbackResult?'Facebook':null);
  const facebook=useFacebookAccounts(channelsOpen);
  const dialogRef=useRef<HTMLDialogElement>(null);
  useEffect(()=>{const dialog=dialogRef.current;if(channelsOpen&&!dialog?.open)dialog?.showModal();else if(!channelsOpen&&dialog?.open)dialog.close();},[channelsOpen]);
  const channels=[
    {name:'Facebook',icon:Facebook,color:'bg-blue-600',description:'Connect your Facebook Page to receive Messenger conversations.'},
    {name:'Instagram',icon:Instagram,color:'bg-gradient-to-br from-pink-500 to-purple-600',description:'Connect an Instagram professional account for direct messages.'},
    {name:'TikTok',icon:Music2,color:'bg-neutral-900 dark:bg-neutral-700',description:'Messaging availability and account requirements will be confirmed during setup.'},
    {name:'WhatsApp',icon:MessageCircle,color:'bg-emerald-600',description:'Connect a WhatsApp Business account for customer messages.'},
  ];

  const cards = [
    {id:'support' as const,title:'Customer Support',shortName:'Support Inbox',description:'Manage storefront conversations, respond to customers, take over from AI, and resolve support requests.',icon:Users,iconBg:'from-amber-500 to-orange-600',badge:'Storefront',badgeColor:'bg-amber-500/10 text-amber-700 dark:text-amber-300 border-amber-500/20',highlights:['AI and human conversations','Human handoff and follow-up','Resolved and archived conversations'],actionLabel:'Open Customer Support'},
    {id:'support-overview' as const,title:'Connect Multichannel Account',shortName:'Messaging Channels',description:'Connect Facebook, Instagram, TikTok and WhatsApp messaging accounts. Channel integrations will be configured in a later phase.',icon:ShoppingBag,iconBg:'from-orange-500 to-rose-600',badge:'Not connected',badgeColor:'bg-orange-500/10 text-orange-700 dark:text-orange-300 border-orange-500/20',highlights:['Facebook Messenger','Instagram Direct Messages','TikTok and WhatsApp'],actionLabel:'Coming soon'},
  ];
  return (
    <div className="space-y-6">
      {/* Banner */}
      <div className="relative overflow-hidden rounded-2xl bg-gradient-to-r from-neutral-900 via-amber-950 to-neutral-900 border border-neutral-800 p-6 sm:p-8 text-white shadow-lg">
        <div className="relative z-10 space-y-2 max-w-2xl">
          <div className="flex items-center gap-2">
            <span className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-500/20 text-amber-300 border border-amber-500/30">
              <ShoppingBag className="w-3.5 h-3.5 text-amber-400" />
              Customer Support
            </span>
            <span className="text-xs text-neutral-400">Customer Care & Messaging</span>
          </div>
          <h1 className="text-2xl sm:text-3xl font-bold tracking-tight text-white">
            Customer Support
          </h1>
          <p className="text-sm text-neutral-300 leading-relaxed">
            Manage customer conversations and prepare your multichannel messaging connections.
          </p>
        </div>

        {/* Quick KPI Bar */}
        <div className="mt-6 pt-6 border-t border-neutral-800 grid grid-cols-3 gap-4">
          <div className="p-3 rounded-xl bg-white/5 border border-white/5">
            <p className="text-[11px] font-medium text-neutral-400">Support Inbox</p>
            <p className="text-xl font-bold text-white mt-0.5">Storefront</p>
            <span className="text-[10px] text-amber-400">AI & human support</span>
          </div>
          <div className="p-3 rounded-xl bg-white/5 border border-white/5">
            <p className="text-[11px] font-medium text-neutral-400">Conversation Workflow</p>
            <p className="text-xl font-bold text-white mt-0.5">Ready</p>
            <span className="text-[10px] text-orange-400">Take over, resolve & archive</span>
          </div>
          <div className="p-3 rounded-xl bg-white/5 border border-white/5">
            <p className="text-[11px] font-medium text-neutral-400">Multichannel Accounts</p>
            <p className="text-xl font-bold text-white mt-0.5">{facebook.data?.accounts.some(a=>a.state==='connected')?'Facebook connected':'Check connections'}</p>
            <span className="text-[10px] text-emerald-400">Select a channel to view status</span>
          </div>
        </div>
      </div>

      {/* Feature Cards Grid */}
      <div className="space-y-4">
        <div className="flex items-center justify-between">
          <h2 className="text-lg font-bold text-neutral-900 dark:text-white">Support Modules</h2>
          <span className="text-xs text-neutral-500">Select a section to manage</span>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
          {cards.map((card) => {
            const Icon = card.icon;
            return (
              <div
                key={card.id}
                role="button" tabIndex={0}
                onKeyDown={e=>{if(e.key==='Enter'||e.key===' '){e.preventDefault();if(card.id==='support')setCurrentSection(card.id);else{setSelectedChannel(null);setChannelsOpen(true);}}}}
                onClick={() => { if(card.id==='support') setCurrentSection(card.id); else {setSelectedChannel(null);setChannelsOpen(true);} }}
                className="group relative flex flex-col justify-between p-6 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 hover:border-amber-300 dark:hover:border-amber-700/60 transition-all duration-200 shadow-xs hover:shadow-md cursor-pointer"
              >
                <div>
                  <div className="flex items-start justify-between gap-3">
                    <div
                      className={`w-11 h-11 rounded-xl bg-gradient-to-tr ${card.iconBg} flex items-center justify-center text-white shadow-md group-hover:scale-105 transition-transform`}
                    >
                      <Icon className="w-5 h-5" />
                    </div>
                    <span className={`text-[11px] font-semibold px-2 py-0.5 rounded-full border ${card.badgeColor}`}>
                      {card.id==='support-overview' && facebook.data?.accounts.some(a=>a.state==='connected')?'Facebook connected':card.badge}
                    </span>
                  </div>

                  <span className="inline-block mt-3 text-[10px] uppercase tracking-wider font-semibold text-neutral-400">
                    {card.shortName}
                  </span>
                  <h3 className="text-base font-bold text-neutral-900 dark:text-white group-hover:text-amber-600 dark:group-hover:text-amber-400 transition-colors">
                    {card.title}
                  </h3>

                  <p className="mt-2.5 text-xs text-neutral-600 dark:text-neutral-400 leading-relaxed">
                    {card.description}
                  </p>

                  <div className="mt-4 pt-4 border-t border-neutral-100 dark:border-neutral-800/80 space-y-1.5">
                    {card.highlights.map((h, i) => (
                      <div key={i} className="flex items-center gap-1.5 text-[11px] text-neutral-600 dark:text-neutral-400">
                        <div className="w-1.5 h-1.5 rounded-full bg-amber-500 shrink-0" />
                        <span className="truncate">{h}</span>
                      </div>
                    ))}
                  </div>
                </div>

                <div className="mt-6 pt-4 border-t border-neutral-100 dark:border-neutral-800 flex items-center justify-between text-xs font-bold text-amber-600 dark:text-amber-400 group-hover:translate-x-0.5 transition-transform">
                  <span>{card.id==='support' ? card.actionLabel : 'Choose a channel'}</span>
                  <div className="w-7 h-7 rounded-lg bg-amber-50 dark:bg-amber-500/10 flex items-center justify-center group-hover:bg-amber-600 group-hover:text-white transition-colors">
                    <ArrowRight className="w-4 h-4" />
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      </div>
      <dialog ref={dialogRef} aria-labelledby="channel-dialog-title" onCancel={()=>setChannelsOpen(false)} onClose={()=>setChannelsOpen(false)} onClick={e=>{if(e.target===e.currentTarget)setChannelsOpen(false);}} className="fixed inset-0 m-auto w-[calc(100%-32px)] max-w-3xl max-h-[85dvh] overflow-y-auto p-0 rounded-2xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-neutral-900 dark:text-neutral-100 shadow-2xl backdrop:bg-black/50 backdrop:backdrop-blur-sm">
        <div onClick={e=>e.stopPropagation()}>
          <header className="flex items-start justify-between gap-4 p-5 sm:p-6 border-b border-neutral-100 dark:border-neutral-800">
            <div><h2 id="channel-dialog-title" className="text-lg font-bold">Connect Multichannel Account</h2><p className="mt-1 text-sm text-neutral-500 dark:text-neutral-400">Choose a messaging channel for your customer support inbox.</p></div>
            <button type="button" aria-label="Close channel selection" onClick={()=>setChannelsOpen(false)} className="p-1.5 rounded-lg text-neutral-500 hover:bg-neutral-100 dark:hover:bg-neutral-800"><X className="w-5 h-5"/></button>
          </header>
          <div className="p-5 sm:p-6 space-y-5">
            <div className="flex gap-3 p-4 rounded-xl border border-amber-200 dark:border-amber-800/60 bg-amber-50 dark:bg-amber-950/30 text-amber-900 dark:text-amber-200"><Info className="w-4 h-4 shrink-0 mt-0.5"/><div><p className="text-sm font-semibold">Connect a messaging account</p><p className="mt-1 text-xs leading-relaxed">Authorize your Facebook Pages and verify their connection. Message delivery will be enabled in a later phase. Other channels still require setup.</p></div></div>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
              {channels.map(channel=>{const Icon=channel.icon;return <button key={channel.name} type="button" aria-pressed={selectedChannel===channel.name} onClick={()=>setSelectedChannel(channel.name)} className={'flex items-center gap-3 text-left p-4 sm:p-5 rounded-xl border transition-colors '+(selectedChannel===channel.name?'border-indigo-500 bg-indigo-50 dark:bg-indigo-950/30':'border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/40 hover:border-indigo-400')}><span className={'w-11 h-11 rounded-xl flex items-center justify-center text-white shrink-0 '+channel.color}><Icon className="w-5 h-5"/></span><span className="flex-1 min-w-0"><span className="flex items-center justify-between gap-2 flex-wrap"><span className="text-sm font-semibold">{channel.name}</span><span className="text-[10px] font-semibold rounded-full px-2 py-1 bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-300">Setup required</span></span><span className="block mt-2 text-xs leading-relaxed text-neutral-500 dark:text-neutral-400">{channel.description}</span></span><Settings className="w-4 h-4 shrink-0 text-neutral-400"/></button>})}
            </div>
            {selectedChannel&&selectedChannel!=='Facebook'&&<p role="status" className="text-xs text-neutral-500 dark:text-neutral-400">{selectedChannel} selected. Connection setup is not available yet.</p>}
            {selectedChannel==='Facebook'&&<section aria-label="Facebook connection" className="p-4 rounded-xl border border-neutral-200 dark:border-neutral-700 space-y-3">
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
            </section>}
          </div>
        </div>
      </dialog>
    </div>
  );
};


