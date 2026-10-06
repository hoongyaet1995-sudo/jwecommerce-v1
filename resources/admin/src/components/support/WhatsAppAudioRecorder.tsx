import React,{useEffect,useRef,useState} from 'react';
import {Mic,Square,X} from 'lucide-react';
interface Props{conversationId:string;disabled:boolean;onFile:(file:File)=>void;onError:(message:string)=>void;onBusyChange?:(busy:boolean)=>void}
export function WhatsAppAudioRecorder({conversationId,disabled,onFile,onError,onBusyChange}:Props){
 const [recording,setRecording]=useState(false),[requesting,setRequesting]=useState(false),[seconds,setSeconds]=useState(0);
 const stream=useRef<MediaStream|null>(null),recorder=useRef<MediaRecorder|null>(null),chunks=useRef<Blob[]>([]),cancelled=useRef(false),generation=useRef(0),pending=useRef(false),timer=useRef<ReturnType<typeof setInterval>|null>(null);
 const cleanup=()=>{if(timer.current)clearInterval(timer.current);timer.current=null;stream.current?.getTracks().forEach(t=>t.stop());stream.current=null;};
 const cancel=()=>{cancelled.current=true;generation.current++;pending.current=false;const r=recorder.current;if(r&&r.state!=='inactive')r.stop();recorder.current=null;cleanup();setRecording(false);setRequesting(false);setSeconds(0);chunks.current=[];onBusyChange?.(false);};
 useEffect(()=>{cancel();return()=>{cancelled.current=true;generation.current++;const r=recorder.current;if(r&&r.state!=='inactive')r.stop();cleanup();onBusyChange?.(false);};},[conversationId]);
 const stop=()=>{pending.current=true;setRequesting(true);const r=recorder.current;if(r&&r.state!=='inactive')r.stop();cleanup();setRecording(false);};
 const start=async()=>{
  if(disabled||pending.current||recording)return;
  const ticket=++generation.current;pending.current=true;setRequesting(true);onBusyChange?.(true);
  try{
   if(!navigator.mediaDevices?.getUserMedia||typeof MediaRecorder==='undefined')throw new Error('Recording is unavailable in this browser. Upload an audio file instead.');
   const media=await navigator.mediaDevices.getUserMedia({audio:{channelCount:1},video:false});
   if(ticket!==generation.current){media.getTracks().forEach(t=>t.stop());return;}
   stream.current=media;cancelled.current=false;chunks.current=[];
   const mime=['audio/webm;codecs=opus','audio/ogg;codecs=opus','audio/mp4'].find(t=>MediaRecorder.isTypeSupported(t));
   const r=new MediaRecorder(media,mime?{mimeType:mime}:undefined);recorder.current=r;
   r.ondataavailable=e=>{if(cancelled.current||ticket!==generation.current)return;if(e.data.size)chunks.current.push(e.data);if(chunks.current.reduce((n,b)=>n+b.size,0)>16*1024*1024){cancel();onError('Recording exceeds 16 MB. Record a shorter message.');}};
   r.onerror=()=>{if(ticket!==generation.current)return;cancel();onError('Recording failed. Please try again.');};
   r.onstop=()=>{if(cancelled.current||ticket!==generation.current)return;cleanup();pending.current=false;setRequesting(false);onBusyChange?.(false);const blob=new Blob(chunks.current,{type:r.mimeType});chunks.current=[];if(!blob.size){onError('Recording is empty. Please try again.');return;}onFile(new File([blob],'recording.'+(r.mimeType.includes('mp4')?'m4a':r.mimeType.includes('ogg')?'ogg':'webm'),{type:r.mimeType}));};
   setSeconds(0);setRecording(true);r.start(1000);let elapsed=0;timer.current=setInterval(()=>{setSeconds(++elapsed);if(elapsed>=300)stop();},1000);
  }catch(e){if(ticket===generation.current){cleanup();onBusyChange?.(false);}if(ticket===generation.current)onError(e instanceof Error?e.message:'Microphone access failed.');}
  finally{if(ticket===generation.current){pending.current=false;setRequesting(false);}}
 };
 return recording?<div className="flex items-center gap-2 text-xs text-red-500" role="status"><span>{Math.floor(seconds/60)}:{String(seconds%60).padStart(2,'0')}</span><button type="button" aria-label="Stop recording and preview" onClick={stop} className="p-2.5 rounded-lg hover:bg-neutral-200 dark:hover:bg-neutral-700"><Square className="w-4 h-4"/></button><button type="button" aria-label="Cancel recording" onClick={cancel} className="p-2.5 rounded-lg hover:bg-neutral-200 dark:hover:bg-neutral-700"><X className="w-4 h-4"/></button></div>:requesting?<div className="flex items-center gap-2 text-xs text-neutral-500" role="status"><span>{recorder.current?'Preparing audio…':'Waiting for microphone…'}</span><button type="button" aria-label="Cancel recording request" onClick={cancel} className="p-2.5 rounded-lg hover:bg-neutral-200 dark:hover:bg-neutral-700"><X className="w-4 h-4"/></button></div>:<button type="button" aria-label="Record voice message" disabled={disabled||requesting} onClick={start} className="p-2.5 rounded-lg text-neutral-500 hover:bg-neutral-200 dark:hover:bg-neutral-700 disabled:opacity-40"><Mic className="w-4 h-4"/></button>;
}
