import React from 'react';
import {ScanText} from 'lucide-react';
import {useAdmin} from '../../context/AdminContext';
import {AISettingToggle} from './AISettingInputs';
export const AIPageContextControls:React.FC=()=>{
 const {aiAutoReplyConfig,updateAIAutoReplyConfig}=useAdmin();
 const policy={enabled:false,cart:false,enquiries:false,quotations:false,account:false,...aiAutoReplyConfig.knowledgeScope.pageContext};
 const enabled=policy.enabled;
 const change=(next:typeof policy)=>updateAIAutoReplyConfig(prev=>({...prev,knowledgeScope:{...prev.knowledgeScope,pageContext:next}}));
 return <section id="page-context-scope-card" className="bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-xl p-5 shadow-xs">
  <div className="flex items-center justify-between gap-4"><div className="flex items-center gap-3"><ScanText className="w-5 h-5 text-indigo-500"/><h3 className="text-sm font-semibold">Current Page Context (当前页面上下文)</h3></div><AISettingToggle aria-label="Use current page context" checked={enabled} onChange={e=>change({...policy,enabled:e.target.checked})}/></div>
  <p className="text-xs text-neutral-500 mt-3 leading-relaxed">Understand which public CMS page, category, collection or product the customer is viewing, including the selected product variant and listing filters. Published data is resolved by the server and existing FAQ / Product Knowledge permissions still apply.</p>
  <p className="text-xs text-neutral-500 mt-2">Private scopes below require the same customer session, login or record ownership as the page. Order fields also follow Order Access Boundaries. Street addresses, passwords and payment credentials are excluded. Read only: no changes, acceptance, orders or payments. Save Configuration to apply.</p>
  <div className="grid md:grid-cols-2 gap-3 mt-4">{([['cart','Cart & Checkout'],['enquiries','Owned Enquiries'],['quotations','Sent Quotations'],['account','My Account & Orders']] as const).map(([key,label])=><label key={key} className="flex items-center gap-3 p-3 rounded-lg border border-neutral-200 dark:border-neutral-800 text-xs"><AISettingToggle aria-label={'Page context: '+label} checked={policy[key]} disabled={!enabled} onChange={e=>change({...policy,[key]:e.target.checked})}/>{label}</label>)}</div>
 </section>;
};
