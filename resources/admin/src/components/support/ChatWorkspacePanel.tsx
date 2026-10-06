import React,{useRef,useEffect,useState} from 'react';
import {useAdmin} from '../../context/AdminContext';
import {SupportConversation,MessageAttachment} from '../../types/support';
import {SupportActionConfirmation} from './SupportActionConfirmation';
import {CHANNEL_META} from './ConversationListPanel';
import {Send,ArrowLeft,CheckCircle2,RotateCcw,Info,Bot,UserCheck,Archive,ArchiveRestore,ChevronDown,Paperclip,X,Music,FileText} from 'lucide-react';
import {uploadSupportMedia,mediaSize} from './supportMediaUpload';
import {WhatsAppAudioRecorder} from './WhatsAppAudioRecorder';
import {SupportMessageImages} from './SupportMessageImages';
import {imageValidation,imageFromClipboard} from './supportImages';
interface Props {conversation:SupportConversation;onBackToList:()=>void;isContextOpen:boolean;onToggleContext:()=>void}
export const ChatWorkspacePanel:React.FC<Props>=({conversation,onBackToList})=>{
 const {sendMessageToConversation,resolveConversation,reopenConversation,supportDrafts,setSupportDraft,supportBusy,takeOverConversation,handBackToAI,archiveConversation}=useAdmin();
 type Action='takeover'|'ai'|'archive'|'restore'|'resolve'|'reopen';
 const [confirmation,setConfirmation]=useState<{action:Action;id:string}|null>(null);
 const confirming=useRef(false);
 const [actionsOpen,setActionsOpen]=useState(false);
 const actionsRef=useRef<HTMLDivElement>(null),actionsButton=useRef<HTMLButtonElement>(null);
 useEffect(()=>{setActionsOpen(false);},[conversation.id,conversation.status,conversation.isArchived]);
 useEffect(()=>{
  if(!actionsOpen)return;
  actionsRef.current?.querySelector<HTMLButtonElement>('[role="menuitem"]')?.focus();
  const outside=(e:PointerEvent)=>{if(!actionsRef.current?.contains(e.target as Node))setActionsOpen(false);};
  document.addEventListener('pointerdown',outside);return()=>document.removeEventListener('pointerdown',outside);
 },[actionsOpen]);
 const chooseAction=(action:Action)=>{setActionsOpen(false);actionsButton.current?.focus();setConfirmation({action,id:conversation.id});};
 useEffect(()=>setConfirmation(null),[conversation.id,conversation.status,conversation.isArchived]);
 const actionDetails:Record<Action,{title:string;description:string}>={
  takeover:{title:'Take Over',description:'You will take over this conversation. Automatic AI replies will pause.'},
  ai:{title:'Hand to AI',description:'Return this conversation to AI handling. New customer messages may receive automatic replies.'},
  archive:{title:'Archive',description:'Move this conversation to Archived. Its message history will be retained.'},
  restore:{title:'Restore',description:'Return this conversation to the active inbox.'},
  resolve:{title:'Resolve',description:'Mark this conversation as resolved. Reopen it before sending another reply.'},
  reopen:{title:'Reopen',description:'Reopen this conversation and return it to the active inbox.'},
 };
 const confirmAction=async()=>{
  if(!confirmation||confirming.current||supportBusy||confirmation.id!==conversation.id)return;
  confirming.current=true;const {action,id}=confirmation;
  try{setConfirmation(null);switch(action){
   case 'takeover':await takeOverConversation(id);break;
   case 'ai':await handBackToAI(id);break;
   case 'archive':await archiveConversation(id,true);break;
   case 'restore':await archiveConversation(id,false);break;
   case 'resolve':await resolveConversation(id);break;
   case 'reopen':await reopenConversation(id);break;
  }}finally{confirming.current=false;}
 };
 const [imageDrafts,setImageDrafts]=useState<Record<string,MessageAttachment>>({});
 const [recordings,setRecordings]=useState<Record<string,boolean>>({});
 const [uploading,setUploading]=useState<Record<string,boolean>>({});
 const [imageErrors,setImageErrors]=useState<Record<string,string>>({});
 const [progress,setProgress]=useState<Record<string,number>>({}),[failedFiles,setFailedFiles]=useState<Record<string,File>>({});
 const uploads=useRef<Record<string,AbortController>>({});
 useEffect(()=>()=>{Object.values(uploads.current).forEach(c=>c.abort());uploads.current={};},[]);
 const documentInput=useRef<HTMLInputElement>(null);
 const fileInput=useRef<HTMLInputElement>(null),audioInput=useRef<HTMLInputElement>(null);
 const attachment=imageDrafts[conversation.id],uploadBusy=!!uploading[conversation.id],recordBusy=!!recordings[conversation.id];
 const imagesAllowed=conversation.mediaCapabilities?.images??['web_guest','facebook','instagram','whatsapp'].includes(conversation.channel);
 const documentsAllowed=conversation.mediaCapabilities?.documents??['web_guest','facebook','whatsapp'].includes(conversation.channel);
 const upload=async(file:File)=>{
  const id=conversation.id,kind=file.type.startsWith('image/')?'image':file.type==='application/pdf'||file.name.toLowerCase().endsWith('.pdf')?'document':'audio';
  const error=kind==='audio'?(conversation.channel!=='whatsapp'?'Audio is available only for WhatsApp.':file.size>16777216?'Use an audio file up to 16 MB.':''):kind==='document'?(!documentsAllowed?'Documents are unavailable for this channel.':file.size>10485760?'Use a PDF up to 10 MB.':''):imageValidation(file);
  if(error){setImageErrors(v=>({...v,[id]:error}));return;}
  if(uploads.current[id]||recordings[id]||supportBusy)return;
  const controller=new AbortController();uploads.current[id]=controller;setFailedFiles(v=>({...v,[id]:file}));setUploading(v=>({...v,[id]:true}));setProgress(v=>({...v,[id]:0}));setImageErrors(v=>({...v,[id]:''}));
  try{const a=await uploadSupportMedia(`/admin/api/support/conversations/${id}/attachments`,file,kind,p=>setProgress(v=>({...v,[id]:p})),controller.signal);if(!controller.signal.aborted){setImageDrafts(v=>({...v,[id]:a}));setFailedFiles(v=>{const n={...v};delete n[id];return n;});}}
  catch(e){setImageErrors(v=>({...v,[id]:e instanceof Error?e.message:'Attachment upload failed.'}));}
  finally{if(uploads.current[id]===controller){delete uploads.current[id];setUploading(v=>({...v,[id]:false}));}}
 };
 const removeImage=()=>setImageDrafts(v=>{const next={...v};delete next[conversation.id];return next;});
 const inputText=supportDrafts[conversation.id]||'';
 const [clock,setClock]=useState(Date.now());
 useEffect(()=>{if(!['facebook','instagram','whatsapp'].includes(conversation.channel)||!conversation.replyWindowExpiresAt)return;const timer=setInterval(()=>setClock(Date.now()),5000);return()=>clearInterval(timer);},[conversation.channel,conversation.replyWindowExpiresAt]);
 const windowExpired=['facebook','instagram','whatsapp'].includes(conversation.channel)&&!!conversation.replyWindowExpiresAt&&new Date(conversation.replyWindowExpiresAt).getTime()<=clock;
 const blocked=conversation.canReply===false||windowExpired;
 const streamRef=useRef<HTMLDivElement>(null);
 const followLatest=useRef(true),previousConversation=useRef('');
 const channelMeta=CHANNEL_META[conversation.channel]||CHANNEL_META.web_guest,ChannelIcon=channelMeta.icon;
 useEffect(()=>{const stream=streamRef.current;if(!stream)return;const switched=previousConversation.current!==conversation.id;previousConversation.current=conversation.id;if(switched||followLatest.current){stream.scrollTop=stream.scrollHeight;followLatest.current=true;}},[conversation.id,conversation.messages.length]);
 const send=async()=>{if(blocked||(!inputText.trim()&&!attachment)||conversation.status==='resolved'||supportBusy||uploadBusy||recordBusy)return;if(attachment?.type==='audio'&&inputText.trim()){setImageErrors(v=>({...v,[conversation.id]:'Send audio and text as separate messages.'}));return;}if(attachment&&inputText.trim()&&['facebook','instagram'].includes(conversation.channel)){setImageErrors(v=>({...v,[conversation.id]:'Send the image and text as separate messages on this channel.'}));return;}if(attachment&&conversation.channel==='whatsapp'&&inputText.trim().length>1024){setImageErrors(v=>({...v,[conversation.id]:'WhatsApp image captions can contain up to 1024 characters.'}));return;}const draft=inputText,id=conversation.id;if(await sendMessageToConversation(id,draft.trim(),attachment?attachment.type:'text',attachment?[attachment]:undefined)){setSupportDraft(id,'');setImageDrafts(v=>{const next={...v};delete next[id];return next;});}};
 return <div id="chat-workspace-container" className="flex flex-col h-full min-h-0 overflow-hidden bg-neutral-50 dark:bg-neutral-950">
      {confirmation && <SupportActionConfirmation title={actionDetails[confirmation.action].title} description={actionDetails[confirmation.action].description} customerName={conversation.customer.name} pending={supportBusy} onCancel={()=>setConfirmation(null)} onConfirm={confirmAction}/>}
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

        {/* Status and contextual actions */}
        <div className="flex items-center gap-2 flex-shrink-0">
          <span className="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium rounded-lg bg-indigo-50 dark:bg-indigo-950 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800">
            {conversation.status==='human_mode'?<UserCheck className="w-3.5 h-3.5"/>:<Bot className="w-3.5 h-3.5"/>}
            {conversation.status==='human_mode'?'Human Specialist':conversation.status==='resolved'?'Resolved':conversation.status==='waiting_human'?'Waiting for human':'AI Active'}
          </span>
          <div ref={actionsRef} className="relative" onKeyDown={e=>{
            if(e.key==='Escape'){e.preventDefault();setActionsOpen(false);actionsButton.current?.focus();}
            if(actionsOpen&&['ArrowDown','ArrowUp','Home','End'].includes(e.key)){
              const items=Array.from(actionsRef.current?.querySelectorAll<HTMLButtonElement>('[role="menuitem"]')||[]);if(!items.length)return;
              e.preventDefault();const index=items.indexOf(document.activeElement as HTMLButtonElement);
              items[e.key==='Home'?0:e.key==='End'?items.length-1:(index+(e.key==='ArrowDown'?1:-1)+items.length)%items.length].focus();
            }
          }}>
            <button ref={actionsButton} id="btn-support-actions" type="button" aria-haspopup="menu" aria-expanded={actionsOpen} aria-controls="support-actions-menu" disabled={supportBusy} onClick={()=>setActionsOpen(!actionsOpen)} className="h-8 flex items-center justify-center gap-2 px-3 text-xs font-medium rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-700 dark:text-neutral-200 hover:bg-neutral-50 dark:hover:bg-neutral-700 transition-colors disabled:opacity-50">Actions<ChevronDown className="w-3.5 h-3.5"/></button>
            {actionsOpen && <div id="support-actions-menu" role="menu" aria-label="Conversation actions" className="absolute right-0 top-full mt-2 w-48 p-1.5 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 shadow-xl z-50">
              {(conversation.status==='open'||conversation.status==='waiting_human')&&<button role="menuitem" id="btn-take-over-chat" onClick={()=>chooseAction('takeover')} className="w-full flex items-center gap-2 px-3 py-2 text-xs text-neutral-700 dark:text-neutral-200 rounded-lg hover:bg-neutral-100 dark:hover:bg-neutral-800 focus:bg-neutral-100 dark:focus:bg-neutral-800"><UserCheck className="w-4 h-4 text-indigo-500"/>Take Over</button>}
              {conversation.status==='human_mode'&&<button role="menuitem" id="btn-hand-back-ai" onClick={()=>chooseAction('ai')} className="w-full flex items-center gap-2 px-3 py-2 text-xs text-neutral-700 dark:text-neutral-200 rounded-lg hover:bg-neutral-100 dark:hover:bg-neutral-800 focus:bg-neutral-100 dark:focus:bg-neutral-800"><Bot className="w-4 h-4 text-indigo-500"/>Hand to AI</button>}
              <button role="menuitem" id={conversation.status==='resolved'?'btn-reopen-chat':'btn-resolve-chat'} onClick={()=>chooseAction(conversation.status==='resolved'?'reopen':'resolve')} className="w-full flex items-center gap-2 px-3 py-2 text-xs text-neutral-700 dark:text-neutral-200 rounded-lg hover:bg-neutral-100 dark:hover:bg-neutral-800 focus:bg-neutral-100 dark:focus:bg-neutral-800">{conversation.status==='resolved'?<RotateCcw className="w-4 h-4 text-emerald-500"/>:<CheckCircle2 className="w-4 h-4 text-emerald-500"/>}{conversation.status==='resolved'?'Reopen':'Resolve'}</button>
              <div className="my-1 border-t border-neutral-200 dark:border-neutral-800"/>
              <button role="menuitem" aria-label={conversation.isArchived?'Restore conversation':'Archive conversation'} onClick={()=>chooseAction(conversation.isArchived?'restore':'archive')} className="w-full flex items-center gap-2 px-3 py-2 text-xs text-neutral-500 dark:text-neutral-400 rounded-lg hover:bg-neutral-100 dark:hover:bg-neutral-800 focus:bg-neutral-100 dark:focus:bg-neutral-800">{conversation.isArchived?<ArchiveRestore className="w-4 h-4"/>:<Archive className="w-4 h-4"/>}{conversation.isArchived?'Restore':'Archive'}</button>
            </div>}
          </div>
        </div>
      </div>


 <div id="chat-message-stream" ref={streamRef} onScroll={()=>{const stream=streamRef.current;if(stream)followLatest.current=stream.scrollHeight-stream.scrollTop-stream.clientHeight<80;}} className="flex-1 min-h-0 overflow-y-auto p-4 sm:p-6 space-y-4">
 <div className="flex items-center justify-center my-2"><div className="flex items-center gap-2 px-3 py-1 rounded-full bg-neutral-200/60 dark:bg-neutral-800/60 text-neutral-500 text-[11px]"><ChannelIcon className="w-3 h-3"/><span>{conversation.channel==='whatsapp'?'WhatsApp conversation':conversation.channel==='instagram'?'Instagram conversation':conversation.channel==='facebook'?'Facebook Messenger conversation':'Storefront conversation'}</span></div></div>
 {conversation.messages.filter(msg=>msg.type==='text'||msg.type==='image'||msg.type==='audio'||msg.type==='document'||msg.senderRole==='system').map(msg=>{
 const customer=msg.senderRole==='customer';
 if(msg.senderRole==='system')return <div key={msg.id} className="flex justify-center my-3"><div className="flex items-center gap-1.5 px-3 py-1 rounded-md bg-neutral-100 dark:bg-neutral-800/80 text-neutral-600 dark:text-neutral-400 text-xs border border-neutral-200 dark:border-neutral-700"><Info className="w-3.5 h-3.5 text-neutral-400"/><span>{msg.content}</span></div></div>;
 return <div key={msg.id} className={'flex items-start gap-2.5 max-w-2xl '+(customer?'mr-auto':'ml-auto flex-row-reverse')}><img onError={(e) => { const img=e.currentTarget; if (!img.src.endsWith("/images/support-person.svg")) img.src="/images/support-person.svg"; }} src={customer?conversation.customer.avatar:msg.senderAvatar||conversation.customer.avatar} alt="" className="w-8 h-8 rounded-full object-cover flex-shrink-0 ring-1 ring-neutral-200 dark:ring-neutral-800"/><div className="min-w-0 space-y-1.5"><div className={'text-[11px] text-neutral-500 '+(customer?'':'text-right')}>{customer?conversation.customer.name:(msg.senderName || 'Support')}</div><div className={'p-3 rounded-2xl border shadow-xs text-xs leading-relaxed whitespace-pre-wrap break-words '+(customer?'rounded-tl-sm bg-white dark:bg-neutral-900 border-neutral-200 dark:border-neutral-800 text-neutral-800 dark:text-neutral-200':'rounded-tr-sm bg-indigo-50 dark:bg-indigo-950/60 border-indigo-200 dark:border-indigo-800 text-indigo-900 dark:text-indigo-200')}><SupportMessageImages attachments={msg.attachments}/>{msg.content}</div><div className={'text-[10px] text-neutral-400 '+(customer?'':'text-right')}>{msg.timestamp}{['facebook','instagram','whatsapp'].includes(conversation.channel)&&!customer&&<span className={msg.deliveryStatus==='failed'?' text-red-500':''}> {'\u00b7'} {msg.deliveryStatus==='read'?'Read':msg.deliveryStatus==='delivered'?'Delivered':msg.deliveryStatus==='accepted'?'Accepted by Meta':msg.deliveryStatus==='sent'?(conversation.channel==='whatsapp'?'Sent':'Accepted by Meta'):msg.deliveryStatus==='unknown'?'Unconfirmed':msg.deliveryStatus==='failed'?'Failed':'Sending…'}</span>}{msg.sendError&&<span className="block text-red-500">{msg.sendError}</span>}</div></div></div>})}
 </div>
 <div id="chat-composer-container" className="flex-shrink-0 p-4 bg-white dark:bg-neutral-900 border-t border-neutral-200 dark:border-neutral-800 space-y-2.5">
 {imageErrors[conversation.id]&&<p role="alert" className="text-xs text-red-500">{imageErrors[conversation.id]}</p>}{uploadBusy&&<div className="flex items-center gap-3 text-xs text-neutral-500"><span role="status">{progress[conversation.id]===100?'Processing attachment...':`Uploading attachment ${progress[conversation.id]||0}%`}</span><button type="button" onClick={()=>uploads.current[conversation.id]?.abort()} className="underline">Cancel upload</button></div>}{!uploadBusy&&failedFiles[conversation.id]&&<button type="button" disabled={supportBusy||recordBusy} onClick={()=>void upload(failedFiles[conversation.id])} className="text-xs underline text-indigo-600 dark:text-indigo-400">Retry upload</button>}{attachment&&<div className="flex items-center gap-3 rounded-lg p-2 border border-neutral-200 dark:border-neutral-700">{attachment.type==='document'?<FileText className="w-8 h-8 flex-shrink-0 text-indigo-500"/>:attachment.type==='audio'?<audio controls preload="metadata" src={attachment.url} aria-label="Audio preview" className="max-w-full w-64"/>:<img src={attachment.url} alt="Attachment preview" className="w-14 h-14 object-cover rounded-lg"/>}<span className="text-xs truncate text-neutral-700 dark:text-neutral-200">{attachment.name} {mediaSize(attachment.sizeBytes)}</span><button type="button" aria-label={attachment.type==='document'?'Remove document':attachment.type==='audio'?'Remove audio':'Remove image'} disabled={supportBusy||uploadBusy||recordBusy} onClick={removeImage} className="ml-auto p-2 text-neutral-500"><X className="w-4 h-4"/></button></div>}{blocked?<p className="text-xs text-neutral-500 dark:text-neutral-400">{windowExpired?'The 24-hour Messenger reply window has ended. Wait for a new customer message.':conversation.replyBlockedReason||'Reconnect this Page to enable replies.'}</p>:conversation.status==='resolved'?<p className="text-xs text-neutral-500 dark:text-neutral-400">Conversation resolved. Reopen to reply.</p>:<><div className="flex items-end gap-2 p-2 rounded-xl bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-700">{imagesAllowed&&<><input ref={fileInput} type="file" accept="image/jpeg,image/png,image/webp" hidden onChange={e=>{const file=e.target.files?.[0];if(file)void upload(file);e.target.value='';}}/><button type="button" aria-label="Attach image" disabled={supportBusy||uploadBusy||recordBusy} onClick={()=>fileInput.current?.click()} className="p-2.5 rounded-lg text-neutral-500 hover:bg-neutral-200 dark:hover:bg-neutral-700 disabled:opacity-40"><Paperclip className="w-4 h-4"/></button></>}{documentsAllowed&&<><input ref={documentInput} type="file" accept="application/pdf,.pdf" hidden onChange={e=>{const f=e.target.files?.[0];if(f)void upload(f);e.target.value='';}}/><button type="button" aria-label="Attach PDF" disabled={supportBusy||uploadBusy||recordBusy} onClick={()=>documentInput.current?.click()} className="p-2.5 rounded-lg text-neutral-500 hover:bg-neutral-200 dark:hover:bg-neutral-700 disabled:opacity-40"><FileText className="w-4 h-4"/></button></>}{conversation.channel==='whatsapp'&&<><input ref={audioInput} type="file" accept="audio/*,.ogg,.opus,.m4a,.mp3,.amr,.aac,.wav,.webm" hidden onChange={e=>{const file=e.target.files?.[0];if(file)void upload(file);e.target.value='';}}/><button type="button" aria-label="Attach audio" disabled={supportBusy||uploadBusy||recordBusy} onClick={()=>audioInput.current?.click()} className="p-2.5 rounded-lg text-neutral-500 hover:bg-neutral-200 dark:hover:bg-neutral-700 disabled:opacity-40"><Music className="w-4 h-4"/></button><WhatsAppAudioRecorder conversationId={conversation.id} disabled={supportBusy||uploadBusy||!!attachment} onBusyChange={busy=>setRecordings(v=>({...v,[conversation.id]:busy}))} onFile={file=>void upload(file)} onError={message=>setImageErrors(v=>({...v,[conversation.id]:message}))}/></>}<textarea onPaste={e=>{const file=imageFromClipboard(e.clipboardData.items);if(file&&imagesAllowed){e.preventDefault();void upload(file);}}} aria-label="Reply" rows={2} maxLength={2000} disabled={supportBusy} placeholder="Write a reply…" value={inputText} onChange={e=>setSupportDraft(conversation.id,e.target.value)} onKeyDown={e=>{if(e.key==='Enter'&&!e.shiftKey&&!e.nativeEvent.isComposing){e.preventDefault();send()}}} className="flex-1 min-w-0 bg-transparent border-0 resize-none text-xs text-neutral-900 dark:text-neutral-100 placeholder-neutral-400 focus:ring-0 p-1.5 max-h-32"/><button id="btn-send-chat-message" aria-label="Send reply" onClick={send} disabled={(!inputText.trim()&&!attachment)||supportBusy||uploadBusy||recordBusy} className="p-2.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 disabled:opacity-40 disabled:hover:bg-indigo-600 text-white transition-colors flex-shrink-0 shadow-xs"><Send className="w-4 h-4"/></button></div><p className="text-[10px] text-neutral-400">Enter to send · Shift + Enter for a new line</p></>}
 </div></div>;
};

