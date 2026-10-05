import {useCallback,useEffect,useRef,useState} from 'react';

export interface InstagramConnection {
  id:string;pageId:string;name:string;state:'connected'|'authorization_expired'|'permission_required'|'disconnected'|'unknown';
  checkReason?:string|null;checkedAt:string|null;permissions:string[];messagingReady:boolean;messagingError?:string|null;
  inboundEnabled?:boolean;inboundRoutingReady?:boolean;inboundVerifiedAt?:string|null;lastInboundAt?:string|null;
}
interface InstagramAccounts {configured:boolean;canManage:boolean;oauthPermitted?:boolean;oauthReason?:string;pages:{id:string;name:string}[];accounts:InstagramConnection[]}

export function useInstagramAccounts(active:boolean) {
  const [data,setData]=useState<InstagramAccounts|null>(null);
  const [loading,setLoading]=useState(false),[error,setError]=useState('');
  const sequence=useRef(0);
  const refresh=useCallback(async(retryMessaging=false)=>{
    const current=++sequence.current;setLoading(true);setError('');
    try{
      const response=await fetch('/admin/api/social/instagram',{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'}});
      if(!response.ok)throw new Error('Could not load Instagram connections. Please try again.');
      const result:InstagramAccounts=await response.json();
      if(retryMessaging && result.canManage){
        const csrf=document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content||'';
        for(const account of result.accounts){
          if(account.state==='disconnected')continue;
          const checked=await fetch('/admin/api/social/instagram/'+encodeURIComponent(account.id)+'/check',{method:'POST',credentials:'same-origin',headers:{Accept:'application/json','X-CSRF-TOKEN':csrf}});
          const body=await checked.json();
          if(!checked.ok)throw new Error(body.message||'Could not check this Instagram account.');
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
      const response=await fetch('/admin/api/social/instagram/select',{method:'POST',credentials:'same-origin',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrf},body:JSON.stringify({page_ids:pageIds})});
      const result=await response.json();
      if(!response.ok)throw new Error(result.message||'Could not save the selected Instagram accounts.');
      if(current===sequence.current)setData(result);
    }catch(e){if(current===sequence.current)setError(e instanceof Error?e.message:'Could not connect Instagram.');}
    finally{if(current===sequence.current)setLoading(false);}
  };
  const manage=async(id:string,action:'check'|'disconnect'|'delete'|'inbound')=>{
    const current=++sequence.current;setLoading(true);setError('');
    try{
      const csrf=document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content||'';
      const response=await fetch('/admin/api/social/instagram/'+encodeURIComponent(id)+(action==='delete'?'':'/'+action),{method:action==='delete'?'DELETE':'POST',credentials:'same-origin',headers:{Accept:'application/json','X-CSRF-TOKEN':csrf}});
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

export const instagramOAuthNotice:Record<string,string>={
  invalid_state:'This authorization expired or could not be verified. Connect Instagram again.',
  cancelled:'Instagram authorization was cancelled. No Instagram account was connected.',
  permission_required:'Instagram account or messaging permission was not granted. Connect again and allow access.',
  business_permission_required:'Business access was not granted and Meta returned no Pages. Connect Instagram again, allow Business access, and select the Business Portfolio and Pages you manage.',
  no_pages:'Meta returned no linked Instagram professional accounts. Verify the Instagram account is Business or Creator and linked to a Facebook Page you manage, then reconnect.',
  provider_error:'Meta could not complete authorization. Please try again.',
  authorization_expired:'Instagram authorization expired. Connect Instagram again.',
  hub_not_permitted:'Social Hub has not permitted Instagram OAuth for this website. Ask your administrator to enable authorization.',
  hub_not_configured:'Configure and enable this website’s Social Hub installation before authorizing Instagram.',
  hub_unavailable:'Social Hub could not verify authorization. Please try again; no Instagram account was connected.',
  page_not_authorized:'The selected Instagram account was not authorized. Connect Instagram again.',
  not_configured:'Instagram OAuth requires server credentials and an HTTPS callback before connecting.',
};
