import {CustomerMemoryCard} from './CustomerMemoryCard';
import React, { useState } from 'react';
import { useAdmin } from '../../context/AdminContext';
import { SupportConversation } from '../../types/support';
import {
  User,
  Copy,
  Phone,
  Mail,
  MapPin,
  Calendar,
  DollarSign,
  ShoppingBag,
  Sparkles,
  AlertTriangle,
  FileText,
  Plus,
  Send,
  ExternalLink,
  ShieldCheck,
  Tag,
  CheckCircle2,
  TrendingUp,
  CreditCard,
  Truck,
  HeartHandshake
} from 'lucide-react';

interface CustomerContextPanelProps {
  conversation: SupportConversation;
  onClose: () => void;
}

export const CustomerContextPanel: React.FC<CustomerContextPanelProps> = ({
  conversation, onClose
}) => {
  const { addStaffNote, addToast, supportBusy } = useAdmin();
  const [newNote, setNewNote] = useState('');

  const handleAddNote = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!newNote.trim()) return;
    if(await addStaffNote(conversation.id, newNote)) setNewNote('');
  };

  const handleApplyVoucher = () => {
    addToast({
      type: 'success',
      title: 'Voucher Code Generated',
      message: 'RM 20 VIP voucher (CODE: AURORA20VIP) sent to customer inbox.'
    });
  };

  const handleGenerateInvoice = () => {
    addToast({
      type: 'info',
      title: 'SST Invoice Generated',
      message: 'Official SST 8% Tax Invoice PDF dispatched.'
    });
  };

  const handleExportTranscript = () => {
    addToast({
      type: 'info',
      title: 'Transcript Exported',
      message: 'Conversation audit log exported to CSV / PDF.'
    });
  };

  const { customer, aiSummary, internalNotes } = conversation;

  return (
    <div id="customer-context-panel" className="h-full flex flex-col p-4 space-y-4 text-xs overflow-y-auto">
      <div className="flex items-center justify-between"><h3 className="font-semibold">Customer details</h3><button onClick={onClose} aria-label="Close customer details" className="p-1.5 rounded-lg text-neutral-500 hover:bg-neutral-100 dark:hover:bg-neutral-800">✕</button></div>
      <CustomerMemoryCard key={conversation.id} conversationId={conversation.id}/>
      {/* 1. Customer Profile Card */}
      <div className="p-3.5 rounded-xl bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-700/80 space-y-3">
        <div className="flex items-center gap-3">
          <img onError={(e) => { const img=e.currentTarget; if (!img.src.endsWith("/images/support-person.svg")) img.src="/images/support-person.svg"; }}
            src={customer.avatar}
            alt={customer.name}
            className="w-12 h-12 rounded-full object-cover ring-2 ring-indigo-500/20"
          />
          <div className="flex-1 min-w-0">
            <h3 className="text-sm font-semibold text-neutral-900 dark:text-neutral-100 truncate">
              {customer.name}
            </h3>

          </div>
        </div>

        {customer.handle && <div className="text-[11px] text-neutral-500 truncate">@{customer.handle.replace(/^@/, '')}</div>}
        {customer.platformUserId && <div className="space-y-1 text-[11px] text-neutral-600 dark:text-neutral-300">
          <div className="capitalize">{conversation.channel} user ID</div>
          <div className="flex items-center gap-2">
            <span className="font-mono break-all flex-1 min-w-0">{customer.platformUserId}</span>
            <button type="button" aria-label="Copy platform user ID" className="p-1.5 rounded-lg text-neutral-500 hover:bg-neutral-100 dark:hover:bg-neutral-800" onClick={async () => {
              try { await navigator.clipboard.writeText(customer.platformUserId!); addToast({type:'success',title:'Copied',message:'Platform user ID copied.'}); }
              catch { addToast({type:'error',title:'Copy unavailable',message:'Select and copy the user ID manually.'}); }
            }}><Copy className="w-3.5 h-3.5" /></button>
          </div>
        </div>}
        {/* Contact info list */}
        <div className="space-y-1.5 pt-1 text-[11px] text-neutral-600 dark:text-neutral-300">
          {customer.phone && (
            <div className="flex items-center gap-2">
              <Phone className="w-3.5 h-3.5 text-neutral-400" />
              <span className="font-mono">{customer.phone}</span>
            </div>
          )}
          {customer.email && (
            <div className="flex items-center gap-2">
              <Mail className="w-3.5 h-3.5 text-neutral-400" />
              <span className="truncate">{customer.email}</span>
            </div>
          )}
          {customer.city && (
            <div className="flex items-center gap-2">
              <MapPin className="w-3.5 h-3.5 text-neutral-400" />
              <span>{customer.city}, {customer.country || 'Malaysia'}</span>
            </div>
          )}
          {customer.joinedDate && (
            <div className="flex items-center gap-2">
              <Calendar className="w-3.5 h-3.5 text-neutral-400" />
              <span>Customer since {customer.joinedDate}</span>
            </div>
          )}
        </div>
      </div>

      {conversation.handoffBrief && <div className="p-3.5 rounded-xl bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-700/80 space-y-3">
        <h3 className="flex items-center gap-1.5 font-semibold text-neutral-900 dark:text-neutral-100"><HeartHandshake className="w-3.5 h-3.5 text-indigo-600"/>Handoff Brief</h3>
        <p className="text-[11px] text-neutral-500">{conversation.handoffBrief.handoffStatus==='staff_handling'?'Staff handling':conversation.handoffBrief.handoffStatus==='pending'?'Awaiting staff':'Human assistance offered'} · Customer statements are requests, not verified facts.</p>
        <div className="text-[11px] text-neutral-600 dark:text-neutral-300 space-y-2">
          <div className="font-semibold">Recent customer requests</div>
          {conversation.handoffBrief.customerRequests.map((request,index)=><p key={index} className="break-words">{request}</p>)}
          <div>Reason: {conversation.handoffBrief.reason.replace(/_/g,' ')}</div>
          {conversation.handoffBrief.checks.map((check,index)=><div key={index}>{check.tool} · {check.status.replace(/_/g,' ')}</div>)}
          {conversation.handoffBrief.missingContext&&<div>Missing context: {conversation.handoffBrief.missingContext}</div>}
          {!!conversation.handoffBrief.unresolvedTopics.length&&<div>Unconfirmed topics: {conversation.handoffBrief.unresolvedTopics.map(index=>index+1).join(', ')}</div>}
          <p>Review the conversation and evidence before acting. Completed queries do not mean the issue is resolved.</p>
        </div>
      </div>}

      {conversation.messages?.some(message=>message.aiTrace) && <div className="p-3.5 rounded-xl bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-700/80 space-y-3">
        <h3 className="flex items-center gap-1.5 font-semibold text-neutral-900 dark:text-neutral-100"><Sparkles className="w-3.5 h-3.5 text-indigo-600"/>AI Reply Evidence</h3>
        <p className="text-[11px] text-neutral-500">Saved query results and references. Staff only.</p>
        {conversation.messages.filter(message=>message.aiTrace).slice(-5).reverse().map(message=>{const trace=message.aiTrace!;const labels:Record<string,string>={'product.search':'Product search','product.count':'Catalogue count','taxonomy.search':'Categories & collections','faq.search':'FAQ & policies','discount.search':'Official offers','discount.lookup_code':'Voucher check','order.lookup':'Verified order check','support.tracking':'Tracking entry',success:'Completed',no_match:'No matching source',verification_required:'Identity verification required',needs_reference:'Order reference required',unavailable:'Query unavailable',server_order:'Server verified order response',rate_limited:'Daily assistance limit',grounded:'Answered from sources',partial:'Partial answer',clarification:'Clarification requested',conversational:'Greeting / courtesy',query_unavailable:'Query unavailable',generation_unavailable:'AI unavailable',escalation_offered:'Human assistance offered',handoff_requested:'Human assistance requested'};
        return <details key={message.id} className="p-2 rounded-lg bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 space-y-2">
          <summary className="cursor-pointer text-[11px] font-semibold text-neutral-800 dark:text-neutral-200">{message.timestamp} · {labels[trace.decision]??trace.decision}</summary>
          <div className="space-y-2 text-[11px] text-neutral-600 dark:text-neutral-300">
            {trace.tools.map((tool,index)=><div key={index} className="flex items-center justify-between gap-2"><span>{labels[tool.tool]??tool.tool}</span><span>{labels[tool.status]??tool.status}</span></div>)}
            <div className="break-all">Retrieved: {trace.retrievedSourceIds.join(', ')||'No factual sources'}</div>
            <div className="break-all">Used: {trace.citedSourceIds.join(', ')||'No factual sources cited'}</div>
            {trace.clarification&&<div>Missing context: {trace.clarification}</div>}
            {trace.escalation&&<div>Handoff reason: {trace.escalation.replace(/_/g,' ')}</div>}
            {trace.coverage?.map(part=><div key={part.index}>Topic {part.index+1}: {part.status==='answered'?'Supported answer':'Unconfirmed'}</div>)}
            {!!trace.followUpCount&&<div>Additional lookup: {trace.followUpCount}</div>}
            <div>Settings version: {trace.settingsVersion}</div>
          </div>
        </details>;})}
      </div>}

      {/* 4. Internal Teammate Notes */}
      <div className="p-3.5 rounded-xl bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-700/80 space-y-3">
        <div className="flex items-center justify-between font-semibold text-neutral-900 dark:text-neutral-100">
          <div className="flex items-center gap-1.5">
            <FileText className="w-3.5 h-3.5 text-indigo-600" />
            <span>Private Staff Notes ({internalNotes.length})</span>
          </div>
        </div>

        {/* Existing notes */}
        <div className="space-y-2 max-h-36 overflow-y-auto">
          {internalNotes.length === 0 ? (
            <p className="text-[11px] text-neutral-400 italic">No internal notes yet.</p>
          ) : (
            internalNotes.map((note) => (
              <div
                key={note.id}
                className="p-2 rounded-lg bg-amber-50/70 dark:bg-amber-950/30 border border-amber-200/60 dark:border-amber-800/50 space-y-1"
              >
                <div className="flex items-center justify-between text-[10px] text-neutral-500">
                  <span className="font-semibold text-neutral-700 dark:text-neutral-300">{note.authorName}</span>
                  <span>{note.timestamp}</span>
                </div>
                <p className="text-[11px] text-neutral-800 dark:text-neutral-200">{note.content}</p>
              </div>
            ))
          )}
        </div>

        {/* Add Note Form */}
        <form onSubmit={handleAddNote} className="space-y-2 pt-1 border-t border-neutral-200 dark:border-neutral-700/60">
          <input
            type="text"
            placeholder="Add internal teammate note..." maxLength={2000} disabled={supportBusy}
            value={newNote}
            onChange={(e) => setNewNote(e.target.value)}
            className="w-full px-2.5 py-1.5 text-xs rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-neutral-900 dark:text-neutral-100 placeholder-neutral-400 focus:outline-none focus:ring-1 focus:ring-indigo-500"
          />
          <button
            type="submit"
            disabled={!newNote.trim()||supportBusy}
            className="w-full flex items-center justify-center gap-1 py-1 text-xs font-medium bg-neutral-900 hover:bg-neutral-800 disabled:opacity-40 text-white rounded-lg transition-colors dark:bg-neutral-100 dark:text-neutral-900"
          >
            <Plus className="w-3.5 h-3.5" />
            <span>Save Private Note</span>
          </button>
        </form>
      </div>

    </div>
  );
};
