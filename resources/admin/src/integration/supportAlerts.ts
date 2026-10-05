import {useCallback,useEffect,useRef,useState} from 'react';
export interface SupportAlert {id:string;title:string;description:string;conversationId:string;channel?:string|null;time:string;unread:boolean}
export function useSupportAlerts(){
 const [supportAlerts,setAlerts]=useState<SupportAlert[]>([]),[supportAlertUnread,setUnread]=useState(0),[supportAlertError,setError]=useState('');
 const revision=useRef(0);
 const request=useCallback(async(id?:string)=>{
  const version=revision.current;
  try{
   const response=await fetch('/admin/api/support/alerts'+(id?'/'+encodeURIComponent(id)+'/read':''),{method:id?'PATCH':'GET',credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json',...(id?{'X-CSRF-TOKEN':document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content||''}:{})}});
   const data=await response.json();if(!response.ok)throw new Error('Unable to load support notifications.');
   if(version===revision.current){setAlerts(previous=>JSON.stringify(previous)===JSON.stringify(data.notifications)?previous:data.notifications);setUnread(data.unreadCount);setError('');}return true;
  }catch{setError('Support notifications are unavailable. Please retry.');return false;}
 },[]);
 useEffect(()=>{let active=true,inFlight=false;let timer:ReturnType<typeof setTimeout>;const refresh=async()=>{if(document.hidden||inFlight||!active)return;inFlight=true;try{await request();}finally{inFlight=false;}};const poll=async()=>{await refresh();if(active)timer=setTimeout(poll,5000);};const wake=()=>{void refresh();};window.addEventListener('focus',wake);document.addEventListener('visibilitychange',wake);void poll();return()=>{active=false;clearTimeout(timer);revision.current++;window.removeEventListener('focus',wake);document.removeEventListener('visibilitychange',wake);};},[request]);
 const markSupportAlertRead=async(id:string)=>{revision.current++;return request(id);};
 return {supportAlerts,supportAlertUnread,supportAlertError,markSupportAlertRead};
}

