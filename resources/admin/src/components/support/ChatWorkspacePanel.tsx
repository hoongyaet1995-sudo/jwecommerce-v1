import React,{useRef,useEffect} from 'react';
import {useAdmin} from '../../context/AdminContext';
import {SupportConversation} from '../../types/support';
import {CHANNEL_META} from './ConversationListPanel';
import {Send,ArrowLeft,CheckCircle2,RotateCcw,Info} from 'lucide-react';
interface Props {conversation:SupportConversation;onBackToList:()=>void;isContextOpen:boolean;onToggleContext:()=>void}
export const ChatWorkspacePanel:React.FC<Props>=({conversation,onBackToList})=>{
 const {sendMessageToConversation,resolveConversation,reopenConversation,supportDrafts,setSupportDraft}=useAdmin();
 const inputText=supportDrafts[conversation.id]||'';
 const streamRef=useRef<HTMLDivElement>(null);
 const channelMeta=CHANNEL_META[conversation.channel]||CHANNEL_META.web_guest,ChannelIcon=channelMeta.icon;
 useEffect(()=>{if(streamRef.current)streamRef.current.scrollTop=streamRef.current.scrollHeight},[conversation.id,conversation.messages.length]);
 const send=()=>{if(!inputText.trim()||conversation.status==='resolved')return;sendMessageToConversation(conversation.id,inputText.trim(),'text');setSupportDraft(conversation.id,'')};
 return <div id="chat-workspace-container" className="flex flex-col h-full min-h-0 overflow-hidden bg-neutral-50 dark:bg-neutral-950">
      {/* 1. Header Toolbar */}
      <div
        id="chat-workspace-header"
        className="flex-shrink-0 min-h-16 py-3 px-4 bg-white dark:bg-neutral-900 border-b border-neutral-200 dark:border-neutral-800 flex items-center justify-between gap-3 flex-wrap z-10"
      >
        {/* Left: Back (Mobile) & Customer Summary */}
        <div className="flex items-center gap-3 min-w-0">
          <button
            aria-label="Back to conversations" onClick={onBackToList}
            className="md:hidden p-1.5 -ml-1 text-neutral-500 hover:text-neutral-900 dark:hover:text-neutral-100 rounded-lg hover:bg-neutral-100 dark:hover:bg-neutral-800"
          >
            <ArrowLeft className="w-5 h-5" />
          </button>

          {/* Customer Avatar & Status */}
          <div className="relative flex-shrink-0">
            <img
              src={conversation.customer.avatar}
              alt={conversation.customer.name}
              className="w-10 h-10 rounded-full object-cover ring-2 ring-neutral-100 dark:ring-neutral-800"
            />
            <div
              className={`absolute -bottom-1 -right-1 p-0.5 rounded-full border border-white dark:border-neutral-900 ${channelMeta.bg} ${channelMeta.text}`}
              title={channelMeta.label}
            >
              <ChannelIcon className="w-3 h-3" />
            </div>
          </div>

          <div className="min-w-0">
            <div className="flex items-center gap-2 flex-wrap">
              <h2 className="text-sm font-semibold text-neutral-900 dark:text-neutral-100 truncate">
                {conversation.customer.name}
              </h2>
            </div>

            <div className="flex items-center gap-2 text-xs text-neutral-500 dark:text-neutral-400 mt-0.5"><span>{conversation.status === 'resolved' ? 'Resolved' : 'Pending'}</span><span>·</span><span>{channelMeta.label} · Sample</span></div>
          </div>
        </div>

        {/* Right: State Machine Actions (AI Mode / Human Takeover / Resolve) */}
        <div className="flex items-center gap-2 flex-shrink-0">
          {/* Resolve / Reopen Button */}
          {conversation.status !== 'resolved' ? (
            <button
              id="btn-resolve-chat"
              onClick={() => resolveConversation(conversation.id)}
              className="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium bg-emerald-50 dark:bg-emerald-950/60 hover:bg-emerald-100 dark:hover:bg-emerald-900/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 rounded-lg transition-colors"
            >
              <CheckCircle2 className="w-3.5 h-3.5" />
              <span>Resolve</span>
            </button>
          ) : (
            <button
              id="btn-reopen-chat"
              onClick={() => reopenConversation(conversation.id)}
              className="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium bg-neutral-100 dark:bg-neutral-800 text-neutral-700 dark:text-neutral-200 hover:bg-neutral-200 dark:hover:bg-neutral-700 rounded-lg transition-colors"
            >
              <RotateCcw className="w-3.5 h-3.5" />
              <span>Reopen</span>
            </button>
          )}


        </div>
      </div>


 <div id="chat-message-stream" ref={streamRef} className="flex-1 min-h-0 overflow-y-auto p-4 sm:p-6 space-y-4">
 <div className="flex items-center justify-center my-2"><div className="flex items-center gap-2 px-3 py-1 rounded-full bg-neutral-200/60 dark:bg-neutral-800/60 text-neutral-500 text-[11px]"><ChannelIcon className="w-3 h-3"/><span>Sample conversation · No messages sent externally</span></div></div>
 {conversation.messages.filter(msg=>msg.type==='text'||msg.senderRole==='system').map(msg=>{
 const customer=msg.senderRole==='customer';
 if(msg.senderRole==='system')return <div key={msg.id} className="flex justify-center my-3"><div className="flex items-center gap-1.5 px-3 py-1 rounded-md bg-neutral-100 dark:bg-neutral-800/80 text-neutral-600 dark:text-neutral-400 text-xs border border-neutral-200 dark:border-neutral-700"><Info className="w-3.5 h-3.5 text-neutral-400"/><span>{msg.content}</span></div></div>;
 return <div key={msg.id} className={'flex items-start gap-2.5 max-w-2xl '+(customer?'mr-auto':'ml-auto flex-row-reverse')}><img src={customer?conversation.customer.avatar:msg.senderAvatar||conversation.customer.avatar} alt="" className="w-8 h-8 rounded-full object-cover flex-shrink-0 ring-1 ring-neutral-200 dark:ring-neutral-800"/><div className="min-w-0 space-y-1.5"><div className={'text-[11px] text-neutral-500 '+(customer?'':'text-right')}>{customer?conversation.customer.name:'Staff · Sample'}</div><div className={'p-3 rounded-2xl border shadow-xs text-xs leading-relaxed whitespace-pre-wrap break-words '+(customer?'rounded-tl-sm bg-white dark:bg-neutral-900 border-neutral-200 dark:border-neutral-800 text-neutral-800 dark:text-neutral-200':'rounded-tr-sm bg-indigo-50 dark:bg-indigo-950/60 border-indigo-200 dark:border-indigo-800 text-indigo-900 dark:text-indigo-200')}>{msg.content}</div><div className={'text-[10px] text-neutral-400 '+(customer?'':'text-right')}>{msg.timestamp}</div></div></div>})}
 </div>
 <div id="chat-composer-container" className="flex-shrink-0 p-4 bg-white dark:bg-neutral-900 border-t border-neutral-200 dark:border-neutral-800 space-y-2.5">
 {conversation.status==='resolved'?<p className="text-xs text-neutral-500 dark:text-neutral-400">Conversation resolved. Reopen to reply.</p>:<><div className="flex items-end gap-2 p-2 rounded-xl bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-700"><textarea aria-label="Reply" rows={2} placeholder="Write a reply…" value={inputText} onChange={e=>setSupportDraft(conversation.id,e.target.value)} onKeyDown={e=>{if(e.key==='Enter'&&!e.shiftKey&&!e.nativeEvent.isComposing){e.preventDefault();send()}}} className="flex-1 min-w-0 bg-transparent border-0 resize-none text-xs text-neutral-900 dark:text-neutral-100 placeholder-neutral-400 focus:ring-0 p-1.5 max-h-32"/><button id="btn-send-chat-message" aria-label="Send sample reply" onClick={send} disabled={!inputText.trim()} className="p-2.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 disabled:opacity-40 disabled:hover:bg-indigo-600 text-white transition-colors flex-shrink-0 shadow-xs"><Send className="w-4 h-4"/></button></div><p className="text-[10px] text-neutral-400">Enter to send · Shift + Enter for a new line · Sample only</p></>}
 </div></div>;
};
