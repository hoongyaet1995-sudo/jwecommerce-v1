import {useCallback,useEffect,useRef,useState} from 'react';

export interface FacebookConnection {
  id:string;pageId:string;name:string;state:'connected'|'authorization_expired'|'permission_required'|'disconnected'|'unknown';
  checkReason?:string|null;checkedAt:string|null;permissions:string[];messagingReady:boolean;messagingError?:string|null;
  inboundEnabled?:boolean;inboundRoutingReady?:boolean;inboundVerifiedAt?:string|null;lastInboundAt?:string|null;
}
interface FacebookAccounts {configured:boolean;canManage:boolean;pages:{id:string;name:string}[];accounts:FacebookConnection[]}

export function useFacebookAccounts(active:boolean) {
  const [data,setData]=useState<FacebookAccounts|null>(null);
  const [loading,setLoading]=useState(false),[error,setError]=useState('');
  const sequence=useRef(0);
  const refresh=useCallback(async(retryMessaging=false)=>{
    const current=++sequence.current;setLoading(true);setError('');
    try{
      const response=await fetch('/admin/api/social/facebook',{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'}});
      if(!response.ok)throw new Error('Could not load Facebook connections. Please try again.');
      const result:FacebookAccounts=await response.json();
      if(retryMessaging && result.canManage){
        const csrf=document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content||'';
        for(const account of result.accounts){
          if(account.state==='disconnected')continue;
          const checked=await fetch('/admin/api/social/facebook/'+encodeURIComponent(account.id)+'/check',{method:'POST',credentials:'same-origin',headers:{Accept:'application/json','X-CSRF-TOKEN':csrf}});
          const body=await checked.json();
          if(!checked.ok)throw new Error(body.message||'Could not check this Page.');
          result.accounts=result.accounts.map(item=>item.id===account.id?body.account:item);
        }
      }
      if(current===sequence.current)setData(result);
    }catch(e){if(current===sequence.current)setError(e instanceof Error?e.message:'Could not load connections.');}
    finally{if(current===sequence.current)setLoading(false);}
  },[]);
  useEffect(()=>{if(active)void refresh();return()=>{sequence.current++;};},[active,refresh]);
  const select=async(pageId:string)=>{
    const current=++sequence.current;setLoading(true);setError('');
    try{
      const csrf=document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content||'';
      const response=await fetch('/admin/api/social/facebook/select',{method:'POST',credentials:'same-origin',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrf},body:JSON.stringify({page_id:pageId})});
      const result=await response.json();
      if(!response.ok)throw new Error(result.message||'Could not save the selected Page.');
      if(current===sequence.current)setData(result);
    }catch(e){if(current===sequence.current)setError(e instanceof Error?e.message:'Could not connect Facebook.');}
    finally{if(current===sequence.current)setLoading(false);}
  };
  const manage=async(id:string,action:'check'|'disconnect'|'delete'|'inbound')=>{
    const current=++sequence.current;setLoading(true);setError('');
    try{
      const csrf=document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content||'';
      const response=await fetch('/admin/api/social/facebook/'+encodeURIComponent(id)+(action==='delete'?'':'/'+action),{method:action==='delete'?'DELETE':'POST',credentials:'same-origin',headers:{Accept:'application/json','X-CSRF-TOKEN':csrf}});
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

export const facebookOAuthNotice:Record<string,string>={
  invalid_state:'This authorization expired or could not be verified. Connect Facebook again.',
  cancelled:'Facebook authorization was cancelled. No Page was connected.',
  permission_required:'Page list permission was not granted. Connect again and allow Page access.',
  business_permission_required:'Business access was not granted and Meta returned no Pages. Connect Facebook again, allow Business access, and select the Business Portfolio and Pages you manage.',
  no_pages:'Meta returned no accessible Pages. Check that the Facebook account has Page access in the correct Business Portfolio and that the Page is selected in the authorization settings, then reconnect.',
  provider_error:'Meta could not complete authorization. Please try again.',
  authorization_expired:'Facebook authorization expired. Connect Facebook again.',
  hub_not_permitted:'Social Hub has not permitted Facebook OAuth for this website. Ask your administrator to enable authorization.',
  hub_not_configured:'Configure and enable this website’s Social Hub installation before authorizing Facebook.',
  hub_unavailable:'Social Hub could not verify authorization. Please try again; no Page was connected.',
  page_not_authorized:'The selected Page was not authorized. Connect Facebook again.',
  not_configured:'Facebook OAuth requires server credentials and an HTTPS callback before connecting.',
};
