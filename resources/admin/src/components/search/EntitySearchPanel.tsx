import React,{useEffect,useRef,useState} from 'react';
import {searchRequest,type SearchKind,type SearchEntity} from '../../integration/searchVisibility';
import './SearchVisibility.css';

export function EntitySearchPanel({kind,id,onChanged,dirty=false}:{kind:SearchKind;id?:string|number;onChanged?:(title:string,description:string)=>void;dirty?:boolean}){
 const [data,setData]=useState<SearchEntity|null>(null),[error,setError]=useState(''),[busy,setBusy]=useState(false),[compare,setCompare]=useState(false),[advanced,setAdvanced]=useState(false);
 const [form,setForm]=useState({title:'',description:'',titleLocked:false,descriptionLocked:false,excluded:false});const active=useRef(true);const lastApplied=useRef<number|null>(null);
 const valid=!!id&&/^\d+$/.test(String(id)),path='/'+kind+'/'+id;
 async function reload(){try{const next=await searchRequest<SearchEntity>(path);if(active.current){setData(next);setForm({title:next.metadata.rawTitle??next.metadata.title,description:next.metadata.description,titleLocked:next.metadata.titleLocked,descriptionLocked:next.metadata.descriptionLocked,excluded:next.metadata.excluded});setError('')}}catch(e){if(active.current)setError((e as Error).message)}}
 useEffect(()=>{active.current=true;if(valid)void reload();return()=>{active.current=false}},[kind,id]);
 const status=data?.task?.status;
 useEffect(()=>{if(kind!=='page'&&!dirty&&data?.task?.status==='applied'&&lastApplied.current!==data.task.id){lastApplied.current=data.task.id;onChanged?.(data.metadata.rawTitle??data.metadata.title,data.metadata.description)}},[data,dirty,kind,onChanged]);
 useEffect(()=>{if(!valid||!['queued','running'].includes(status??''))return;const timer=setInterval(()=>{if(!advanced)void reload()},5000);return()=>clearInterval(timer)},[status,valid,advanced,kind,id]);
 async function run(action:'save'){if(busy||!data)return;setBusy(true);setError('');try{
  await searchRequest(path,'PUT',{...form,version:data.metadata.version});onChanged?.(form.title,form.description);
  await reload();
 }catch(e){setError((e as Error).message)}finally{setBusy(false)}}
 if(!valid)return <div className="sf-ui"><div className="sf-notice">Save this {kind} first. Search information will be generated from the saved content automatically.</div></div>;
 return <section className="sf-ui" aria-label="Search information"><div className="sf-card"><div className="sf-card-head"><h3>Search Engine {kind==='product'?'Snippet':'Listing'} Preview</h3><span className="sf-badge">{data?.metadata.source??'Loading'}</span></div><div className="sf-body">
 {error&&<div className="sf-notice" role="alert">{error} <button className="sf-btn" type="button" onClick={()=>void reload()}>Reload</button></div>}
 {data&&<><div className="sf-preview"><div className="sf-url">{data.url}</div><div className="sf-title">{data.metadata.title}</div><div className="sf-desc">{data.metadata.description}</div></div><p className="sf-muted" style={{marginTop:12}}>Automatically prepared from saved business content. Preview is illustrative; search engines choose their own snippets.</p>
 <div className="sf-row"><div><strong>AI optimization</strong><small>{status??'System generation ready'}{data.task?.error?' · '+data.task.error:''}</small></div>{data.task?.model&&<span className="sf-badge">{data.task.model}</span>}</div>
 {dirty&&<div className="sf-notice">Save your content changes first. AI uses the last saved record.</div>}
 {kind==='page'&&<p className="sf-muted">Search changes remain in this CMS draft until publication. AI completion may create a newer draft revision; reload the editor before continuing if notified.</p>}
 <div className="sf-actions">{data.task?.result&&<button type="button" className="sf-btn" onClick={()=>setCompare(!compare)}>Before / after</button>}{kind==='page'&&status==='applied'&&<button type="button" className="sf-btn" onClick={()=>{if(window.confirm('Reload this editor to fetch the saved AI draft? Unsaved editor changes will be lost.'))window.location.reload()}}>Reload saved draft</button>}</div>
 {compare&&data.task?.result&&<div className="sf-compare"><div><small>Before · saved for audit</small><div className="sf-title">{data.task.before.title}</div><p>{data.task.before.description}</p></div><div><small>AI result · source revision checked</small><div className="sf-title">{data.task.result.title}</div><p>{data.task.result.description}</p><p className="sf-muted">{data.task.result.topics.join(' · ')}</p>{data.task.usage&&<p className="sf-muted">Usage: {Object.entries(data.task.usage).map(([k,v])=>`${k}: ${v}`).join(' · ')}</p>}</div></div>}
 </>}
 </div></div><details className="sf-card" onToggle={e=>setAdvanced(e.currentTarget.open)}><summary>Advanced settings · optional manual overrides</summary><div className="sf-body">
 <label className="sf-check"><input type="checkbox" checked={form.titleLocked} onChange={e=>setForm({...form,titleLocked:e.target.checked})}/>Override search title</label>{form.titleLocked&&<label className="sf-label">Search title<input type="text" value={form.title} onChange={e=>setForm({...form,title:e.target.value})}/></label>}
 <label className="sf-check"><input type="checkbox" checked={form.descriptionLocked} onChange={e=>setForm({...form,descriptionLocked:e.target.checked})}/>Override search description</label>{form.descriptionLocked&&<label className="sf-label">Search description<textarea value={form.description} onChange={e=>setForm({...form,description:e.target.value})}/></label>}
 <label className="sf-check"><input type="checkbox" checked={form.excluded} onChange={e=>setForm({...form,excluded:e.target.checked})}/>Exclude this destination from search (does not make it private)</label><p className="sf-muted">Manual overrides stay protected until you switch them off. Domain and canonical URLs are system managed.</p><div className="sf-actions"><button type="button" disabled={busy||!data||dirty} className="sf-btn" onClick={()=>void run('save')}>Save advanced settings</button></div></div></details></section>
}
