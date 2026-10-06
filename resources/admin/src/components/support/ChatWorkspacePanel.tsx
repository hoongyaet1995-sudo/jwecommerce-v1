import React,{useRef,useEffect,useState} from 'react';
import {useAdmin} from '../../context/AdminContext';
import {SupportConversation} from '../../types/support';
import {CHANNEL_META} from './ConversationListPanel';
import {Send,ArrowLeft,CheckCircle2,RotateCcw,Info,Bot,UserCheck,Archive,ArchiveRestore} from 'lucide-react';
interface Props {conversation:SupportConversation;onBackToList:()=>void;isContextOpen:boolean;onToggleContext:()=>void}
export const ChatWorkspacePanel:React.FC<Props>=({conversation,onBackToList})=>{
 const {sendMessageToConversation,resolveConversation,reopenConversation,supportDrafts,setSupportDraft,supportBusy,takeOverConversation,handBackToAI,archiveConversation}=useAdmin();
 const inputText=supportDrafts[conversation.id]||'';
 const [clock,setClock]=useState(Date.now());
 useEffect(()=>{if(!['facebook','instagram','whatsapp'].includes(conversation.channel)||!conversation.replyWindowExpiresAt)return;const timer=setInterval(()=>setClock(Date.now()),5000);return()=>clearInterval(timer);},[conversation.channel,conversation.replyWindowExpiresAt]);
 const windowExpired=['facebook','instagram','whatsapp'].includes(conversation.channel)&&!!conversation.replyWindowExpiresAt&&new Date(conversation.replyWindowExpiresAt).getTime()<=clock;
 const blocked=conversation.canReply===false||windowExpired;
 const streamRef=useRef<HTMLDivElement>(null);
 const followLatest=useRef(true),previousConversation=useRef('');
 const channelMeta=CHANNEL_META[conversation.channel]||CHANNEL_META.web_guest,ChannelIcon=channelMeta.icon;
 useEffect(()=>{const stream=streamRef.current;if(!stream)return;const switched=previousConversation.current!==conversation.id;previousConversation.current=conversation.id;if(switched||followLatest.current){stream.scrollTop=stream.scrollHeight;followLatest.current=true;}},[conversation.id,conversation.messages.length]);
 const send=async()=>{if(blocked||!inputText.trim()||conversation.status==='resolved'||supportBusy)return;const draft=inputText;if(await sendMessageToConversation(conversation.id,draft.trim(),'text'))setSupportDraft(conversation.id,'')};
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
            <img onError={(e) => { const img=e.currentTarget; if (!img.src.endsWith("/images/support-person.svg")) img.src="/images/support-person.svg"; }}
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

            <div className="flex items-center gap-2 text-xs text-neutral-500 dark:text-neutral-400 mt-0.5"><span>{conversation.status === 'resolved' ? 'Resolved' : conversation.status === 'waiting_human' ? 'Waiting for human' : conversation.status === 'human_mode' ? 'Human handling' : 'AI handling'}</span><span>·</span><span>{channelMeta.label}{conversation.pageName ? ` · ${conversation.pageName}` : ''}</span></div>
          </div>
        </div>

        {/* Right: State Machine Actions (AI Mode / Human Takeover / Resolve) */}
        <div className="flex items-center gap-2 flex-shrink-0">

{(conversation.status === 'open' || conversation.status === 'waiting_human') && (
            <div className="flex items-center gap-1.5">
              <span className="hidden lg:inline-flex items-center gap-1 px-2 py-1 text-xs font-medium rounded-lg bg-indigo-50 dark:bg-indigo-950 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800">
                <Bot className="w-3.5 h-3.5" />
                {conversation.status === 'waiting_human' ? 'Waiting for human' : 'AI Active'}
              </span>

              <button
                id="btn-take-over-chat"
                disabled={supportBusy} onClick={() => takeOverConversation(conversation.id)}
                className="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition-colors shadow-xs"
              >
                <UserCheck className="w-3.5 h-3.5" />
                <span>Take Over</span>
              </button>
            </div>
          )}

          {conversation.status === 'human_mode' && (
            <div className="flex items-center gap-1.5">
              <span className="hidden lg:inline-flex items-center gap-1 px-2 py-1 text-xs font-medium rounded-lg bg-blue-50 dark:bg-blue-950 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800">
                <UserCheck className="w-3.5 h-3.5" />
                {'Human Specialist'}
              </span>

              <button
                id="btn-hand-back-ai"
                disabled={supportBusy} onClick={() => handBackToAI(conversation.id)}
                className="flex items-center gap-1.5 px-2.5 py-1.5 text-xs font-medium bg-white dark:bg-neutral-800 text-neutral-700 dark:text-neutral-200 border border-neutral-200 dark:border-neutral-700 hover:bg-neutral-50 dark:hover:bg-neutral-700 rounded-lg transition-colors"
                title="Return conversation to automated AI handling"
              >
                <Bot className="w-3.5 h-3.5 text-indigo-500" />
                <span className="hidden sm:inline">Hand to AI</span>
              </button>
            </div>
          )}

          
          <button type="button" aria-label={conversation.isArchived?"Restore conversation":"Archive conversation"} title={conversation.isArchived?"Restore conversation":"Archive conversation"} disabled={supportBusy} onClick={()=>archiveConversation(conversation.id,!conversation.isArchived)} className="p-2 rounded-lg border border-neutral-200 dark:border-neutral-700 text-neutral-500 hover:text-indigo-500">{conversation.isArchived?<ArchiveRestore className="w-4 h-4"/>:<Archive className="w-4 h-4"/>}</button>
          {/* Resolve / Reopen Button */}
          {conversation.status !== 'resolved' ? (
            <button
              id="btn-resolve-chat" disabled={supportBusy}
              onClick={() => resolveConversation(conversation.id)}
              className="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium bg-emerald-50 dark:bg-emerald-950/60 hover:bg-emerald-100 dark:hover:bg-emerald-900/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 rounded-lg transition-colors"
            >
              <CheckCircle2 className="w-3.5 h-3.5" />
              <span>Resolve</span>
            </button>
          ) : (
            <button
              id="btn-reopen-chat" disabled={supportBusy}
              onClick={() => reopenConversation(conversation.id)}
              className="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium bg-neutral-100 dark:bg-neutral-800 text-neutral-700 dark:text-neutral-200 hover:bg-neutral-200 dark:hover:bg-neutral-700 rounded-lg transition-colors"
            >
              <RotateCcw className="w-3.5 h-3.5" />
              <span>Reopen</span>
            </button>
          )}


        </div>
      </div>


 <div id="chat-message-stream" ref={streamRef} onScroll={()=>{const stream=streamRef.current;if(stream)followLatest.current=stream.scrollHeight-stream.scrollTop-stream.clientHeight<80;}} className="flex-1 min-h-0 overflow-y-auto p-4 sm:p-6 space-y-4">
 <div className="flex items-center justify-center my-2"><div className="flex items-center gap-2 px-3 py-1 rounded-full bg-neutral-200/60 dark:bg-neutral-800/60 text-neutral-500 text-[11px]"><ChannelIcon className="w-3 h-3"/><span>{conversation.channel==='whatsapp'?'WhatsApp conversation':conversation.channel==='instagram'?'Instagram conversation':conversation.channel==='facebook'?'Facebook Messenger conversation':'Storefront conversation'}</span></div></div>
 {conversation.messages.filter(msg=>msg.type==='text'||msg.senderRole==='system').map(msg=>{
 const customer=msg.senderRole==='customer';
 if(msg.senderRole==='system')return <div key={msg.id} className="flex justify-center my-3"><div className="flex items-center gap-1.5 px-3 py-1 rounded-md bg-neutral-100 dark:bg-neutral-800/80 text-neutral-600 dark:text-neutral-400 text-xs border border-neutral-200 dark:border-neutral-700"><Info className="w-3.5 h-3.5 text-neutral-400"/><span>{msg.content}</span></div></div>;
 return <div key={msg.id} className={'flex items-start gap-2.5 max-w-2xl '+(customer?'mr-auto':'ml-auto flex-row-reverse')}><img onError={(e) => { const img=e.currentTarget; if (!img.src.endsWith("/images/support-person.svg")) img.src="/images/support-person.svg"; }} src={customer?conversation.customer.avatar:msg.senderAvatar||conversation.customer.avatar} alt="" className="w-8 h-8 rounded-full object-cover flex-shrink-0 ring-1 ring-neutral-200 dark:ring-neutral-800"/><div className="min-w-0 space-y-1.5"><div className={'text-[11px] text-neutral-500 '+(customer?'':'text-right')}>{customer?conversation.customer.name:(msg.senderName || 'Support')}</div><div className={'p-3 rounded-2xl border shadow-xs text-xs leading-relaxed whitespace-pre-wrap break-words '+(customer?'rounded-tl-sm bg-white dark:bg-neutral-900 border-neutral-200 dark:border-neutral-800 text-neutral-800 dark:text-neutral-200':'rounded-tr-sm bg-indigo-50 dark:bg-indigo-950/60 border-indigo-200 dark:border-indigo-800 text-indigo-900 dark:text-indigo-200')}>{msg.content}</div><div className={'text-[10px] text-neutral-400 '+(customer?'':'text-right')}>{msg.timestamp}{['facebook','instagram','whatsapp'].includes(conversation.channel)&&!customer&&<span className={msg.deliveryStatus==='failed'?' text-red-500':''}> {'\u00b7'} {msg.deliveryStatus==='read'?'Read':msg.deliveryStatus==='delivered'?'Delivered':msg.deliveryStatus==='accepted'?'Accepted by Meta':msg.deliveryStatus==='sent'?(conversation.channel==='whatsapp'?'Sent':'Accepted by Meta'):msg.deliveryStatus==='unknown'?'Unconfirmed':msg.deliveryStatus==='failed'?'Failed':'Sending…'}</span>}{msg.sendError&&<span className="block text-red-500">{msg.sendError}</span>}</div></div></div>})}
 </div>
 <div id="chat-composer-container" className="flex-shrink-0 p-4 bg-white dark:bg-neutral-900 border-t border-neutral-200 dark:border-neutral-800 space-y-2.5">
 {blocked?<p className="text-xs text-neutral-500 dark:text-neutral-400">{windowExpired?'The 24-hour Messenger reply window has ended. Wait for a new customer message.':conversation.replyBlockedReason||'Reconnect this Page to enable replies.'}</p>:conversation.status==='resolved'?<p className="text-xs text-neutral-500 dark:text-neutral-400">Conversation resolved. Reopen to reply.</p>:<><div className="flex items-end gap-2 p-2 rounded-xl bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-700"><textarea aria-label="Reply" rows={2} maxLength={2000} disabled={supportBusy} placeholder="Write a reply…" value={inputText} onChange={e=>setSupportDraft(conversation.id,e.target.value)} onKeyDown={e=>{if(e.key==='Enter'&&!e.shiftKey&&!e.nativeEvent.isComposing){e.preventDefault();send()}}} className="flex-1 min-w-0 bg-transparent border-0 resize-none text-xs text-neutral-900 dark:text-neutral-100 placeholder-neutral-400 focus:ring-0 p-1.5 max-h-32"/><button id="btn-send-chat-message" aria-label="Send reply" onClick={send} disabled={!inputText.trim()||supportBusy} className="p-2.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 disabled:opacity-40 disabled:hover:bg-indigo-600 text-white transition-colors flex-shrink-0 shadow-xs"><Send className="w-4 h-4"/></button></div><p className="text-[10px] text-neutral-400">Enter to send · Shift + Enter for a new line</p></>}
 </div></div>;
};

