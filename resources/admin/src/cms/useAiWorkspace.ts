import {useEffect,useRef,useState} from 'react';
import {readAdminBootstrap} from '../integration/adminBootstrap';
export function useAiWorkspace<T>(page:string,signature:string,state:T,restore:(state:T)=>void){
 const [ready,setReady]=useState(false),[error,setError]=useState('');const version=useRef(0),active=useRef(true),latest=useRef(state),timer=useRef<ReturnType<typeof setTimeout>|undefined>(undefined);const queue=useRef<Promise<unknown>>(Promise.resolve());latest.current=state;
 const lastSaved=useRef('');
 const url='/admin/api/cms/ai-workspaces/'+encodeURIComponent(page);
 const request=async(method:string,body?:unknown)=>{const r=await fetch(url,{method,credentials:'same-origin',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':readAdminBootstrap().csrfToken},...(body?{body:JSON.stringify(body)}:{})});const json=await r.json().catch(()=>null);if(!r.ok)throw new Error(json?.message||'Workspace could not be saved. Keep this window open and retry.');return json;};
 useEffect(()=>{active.current=true;void request('GET').then(data=>{if(!active.current)return;version.current=data.version;if(data.data&&data.data.signature!==signature)throw new Error('The saved CMS source changed. Discard this AI workspace and reopen before continuing.');if(data.data){lastSaved.current=JSON.stringify(data.data.state);restore(data.data.state);}setReady(true);}).catch(e=>{if(active.current)setError(e.message);});return()=>{active.current=false;clearTimeout(timer.current);};},[url,signature]);
 const flush=(next:T=latest.current)=>{clearTimeout(timer.current);const job=queue.current.catch(()=>{}).then(async()=>{if(!active.current||!ready)throw new Error(error||'Workspace recovery is not ready. Reopen the generator.');if(JSON.stringify(next)===lastSaved.current)return;const data=await request('PUT',{version:version.current,signature,state:next});version.current=data.version;lastSaved.current=JSON.stringify(next);setError('');});queue.current=job;return job;};
 useEffect(()=>{if(!ready||!active.current)return;timer.current=setTimeout(()=>{void flush().catch(e=>{if(active.current)setError(e.message);});},700);return()=>clearTimeout(timer.current);},[state,ready]);
 const discard=async()=>{clearTimeout(timer.current);await queue.current.catch(()=>{});const data=await request('DELETE',{version:version.current});version.current=data.version;active.current=false;setError('');};
 return{ready,error,flush,discard};
}
