import {useCallback,useEffect,useRef,useState} from 'react';

export interface WhatsAppConnection {
  id:string;pageId:string;name:string;displayPhoneNumber?:string|null;state:'connected'|'authorization_expired'|'permission_required'|'disconnected'|'unknown';
  checkReason?:string|null;checkedAt:string|null;permissions:string[];messagingReady:boolean;messagingError?:string|null;
  inboundEnabled?:boolean;inboundRoutingReady?:boolean;inboundVerifiedAt?:string|null;lastInboundAt?:string|null;
}
interface WhatsAppAccounts {configured:boolean;canManage:boolean;oauthPermitted?:boolean;oauthReason?:string;pages:{id:string;name:string;display_phone_number?:string|null}[];accounts:WhatsAppConnection[]}

export function useWhatsAppAccounts(active:boolean) {
  const [data,setData]=useState<WhatsAppAccounts|null>(null);
  const [loading,setLoading]=useState(false),[error,setError]=useState('');
  const sequence=useRef(0);
  const refresh=useCallback(async(retryMessaging=false)=>{
    const current=++sequence.current;setLoading(true);setError('');
    try{
      const response=await fetch('/admin/api/social/whatsapp',{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'}});
      if(!response.ok)throw new Error('Could not load WhatsApp connections. Please try again.');
      const result:WhatsAppAccounts=await response.json();
      if(retryMessaging && result.canManage){
        const csrf=document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content||'';
        for(const account of result.accounts){
          if(account.state==='disconnected')continue;
          const checked=await fetch('/admin/api/social/whatsapp/'+encodeURIComponent(account.id)+'/check',{method:'POST',credentials:'same-origin',headers:{Accept:'application/json','X-CSRF-TOKEN':csrf}});
          const body=await checked.json();
          if(!checked.ok)throw new Error(body.message||'Could not check this WhatsApp account.');
          result.accounts=result.accounts.map(item=>item.id===account.id?body.account:item);
        }
      }
      if(current===sequence.current)setData(result);
    }catch(e){if(current===sequence.current)setError(e instanceof Error?e.message:'Could not load connections.');}
    finally{if(current===sequence.current)setLoading(false);}
  },[]);
  useEffect(()=>{if(active)void refresh();return()=>{sequence.current++;};},[active,refresh]);
  const select=async(pageIds:string[])=>{
    const current=++sequence.current;setLoading(true);setError('');
    try{
      const csrf=document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content||'';
      const response=await fetch('/admin/api/social/whatsapp/select',{method:'POST',credentials:'same-origin',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrf},body:JSON.stringify({page_ids:pageIds})});
      const result=await response.json();
      if(!response.ok)throw new Error(result.message||'Could not save the selected WhatsApp accounts.');
      if(current===sequence.current)setData(result);
    }catch(e){if(current===sequence.current)setError(e instanceof Error?e.message:'Could not connect WhatsApp.');}
    finally{if(current===sequence.current)setLoading(false);}
  };
  const manage=async(id:string,action:'check'|'disconnect'|'delete'|'inbound')=>{
    const current=++sequence.current;setLoading(true);setError('');
    try{
      const csrf=document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content||'';
      const response=await fetch('/admin/api/social/whatsapp/'+encodeURIComponent(id)+(action==='delete'?'':'/'+action),{method:action==='delete'?'DELETE':'POST',credentials:'same-origin',headers:{Accept:'application/json','X-CSRF-TOKEN':csrf}});
      const result=await response.json();
      if(!response.ok)throw new Error(result.message||'Could not update this connection. Please try again.');
      if(current===sequence.current){
        if(action==='check'||action==='inbound')setData(previous=>previous?{...previous,accounts:previous.accounts.map(account=>account.id===id?result.account:account)}:previous);
        else setData(result);
      }
      return true;
    }catch(e){if(current===sequence.current)setError(e instanceof Error?e.message:'Could not update connection.');return false;}
    finally{if(current===sequence.current)setLoading(false);}
  };
  return {data,loading,error,refresh,select,manage};

}

export const whatsappOAuthNotice:Record<string,string>={
  invalid_state:'This authorization expired or could not be verified. Connect WhatsApp again.',
  cancelled:'WhatsApp authorization was cancelled. No WhatsApp account was connected.',
  permission_required:'WhatsApp account or messaging permission was not granted. Connect again and allow access.',
  business_permission_required:'Business access was not granted. Connect WhatsApp again and allow access to your WhatsApp Business Account.',
  no_pages:'Meta returned no authorized WhatsApp phone numbers. Check your WhatsApp Business Account and reconnect.',
  provider_error:'Meta could not complete authorization. Please try again.',
  authorization_expired:'WhatsApp authorization expired. Connect WhatsApp again.',
  hub_not_permitted:'Social Hub has not permitted WhatsApp OAuth for this website. Ask your administrator to enable authorization.',
  hub_not_configured:'Configure and enable this website’s Social Hub installation before authorizing WhatsApp.',
  hub_unavailable:'Social Hub could not verify authorization. Please try again; no WhatsApp account was connected.',
  page_not_authorized:'The selected WhatsApp account was not authorized. Connect WhatsApp again.',
  not_configured:'WhatsApp customer authorization requires administrator configuration of Meta Embedded Signup. Existing sender migration is separate.',
};
