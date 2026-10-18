import React, {useEffect, useState} from 'react';
import {searchRequest} from '../../integration/searchVisibility';
import {useAdmin} from '../../context/AdminContext';
import './SearchVisibility.css';

type Settings = {version:number; mode:'off'|'auto'|'review'; preview:boolean};
type Refresh = {id:number; total:number; completed:number; skipped:number; failed:number; active:boolean; status:string};
type State = {settings:Settings; origin:string; brand:string; ready:boolean; model:string|null; counts:Record<string,number>; refresh:Refresh|null};

export function SearchVisibilitySettings() {
 const [data,setData] = useState<State|null>(null);
 const [error,setError] = useState(''), [notice,setNotice] = useState(''), [busy,setBusy] = useState(false);
 const [refresh,setRefresh] = useState<Refresh|null>(null);
 const [resume,setResume] = useState(0);
 const {setCurrentSection} = useAdmin();
 async function reload() {
  try {const next = await searchRequest<State>('/settings'); setData(next); setRefresh(next.refresh); setError('');}
  catch(e) {setError((e as Error).message);}
 }
 useEffect(()=>{void reload()},[]);
 useEffect(()=>{
  if(!refresh?.active) return;
  let active=true;
  let timer:ReturnType<typeof setTimeout>;
  const advance=async()=>{
   try {
    const next=await searchRequest<Refresh>(`/refresh/${refresh.id}/advance`,'POST');
    if(!active)return;
    setRefresh(next);setError('');
    if(next.active)timer=setTimeout(()=>void advance(),1000);
   } catch(e){if(active)setError(`${(e as Error).message} Click Resume SEO to continue.`)}
  };
  timer=setTimeout(()=>void advance(),1000);
  return ()=>{active=false;clearTimeout(timer)};
 },[refresh?.id,refresh?.active,resume]);
 async function save() {
  if(!data||busy)return;
  if(data.settings.preview&&!window.confirm('Request no indexing across the public website? This does not make it private.'))return;
  setBusy(true);
  try {await searchRequest('/settings','PUT',data.settings); await reload(); setNotice('Search preferences saved.');}
  catch(e){setError((e as Error).message)}finally{setBusy(false)}
 }
 async function refreshSeo() {
  if(busy)return;
  if(refresh?.active){setError('');setResume(value=>value+1);return;}
  setBusy(true);setError('');setNotice('');
  try {setRefresh(await searchRequest<Refresh>('/refresh','POST'));}
  catch(e){setError((e as Error).message)}finally{setBusy(false)}
 }
 return <div className="sf-ui">
  <div className="sf-page-head"><div><h2>Your content. Automatically prepared for search.</h2><p className="sf-muted">One shared foundation across every industry theme.</p></div>
   <div className="sf-actions" style={{marginTop:0}}>
    <button type="button" className="sf-btn sf-primary" disabled={busy||!data||(refresh?.active&&!error)} onClick={()=>void refreshSeo()}>{refresh?.active?(error?'Resume SEO':'Refreshing SEO…'):'Refresh SEO'}</button>
    <button type="button" className="sf-btn" disabled={busy||!data} onClick={()=>void save()}>Save preferences</button>
   </div>
  </div>
  <p className="sf-muted" style={{marginBottom:20}}>Refresh search information for all products, pages, collections and categories. Updates run only when you click Refresh SEO. Keep this page open until the refresh finishes. Saving content does not call AI. Manual overrides stay protected. CMS changes stay in drafts until publication.</p>
  {error&&<div role="alert" className="sf-notice">{error}</div>}
  {notice&&<div role="status" className="sf-notice">{notice}</div>}
  {refresh&&<div role="status" className="sf-notice">{refresh.active?(`Refreshing SEO: ${refresh.completed} of ${refresh.total} records ready. Keep this page open. If interrupted, return here to continue.`):refresh.failed?`SEO refresh finished. ${refresh.failed} records could not be updated; their previous information was kept.`:`SEO refreshed across ${refresh.total} records. Manual overrides were preserved. CMS updates are saved as drafts.`}</div>}
  {data?<div className="sf-grid"><div>
   <section className="sf-card"><div className="sf-card-head"><h3>Connected website</h3></div><div className="sf-body"><div className="sf-row"><div><strong>Website name</strong><small>{data.brand}</small></div><span className="sf-badge">From shop settings</span></div><div className="sf-row"><div><strong>Preferred domain</strong><small>{data.origin}</small></div><span className="sf-badge">From deployment</span></div><p className="sf-muted">Domain changes belong to deployment management, not routine SEO editing.</p></div></section>
   <section className="sf-card"><div className="sf-card-head"><h3>How should AI help?</h3><span className="sf-badge">All themes</span></div><div className="sf-body">{[['auto','AI-assisted refresh · recommended','Validate and apply updates only when you click Refresh SEO.'],['off','System automation only','Generate metadata from authored content without LLM requests.']].map(([key,title,help])=><label key={key} className={'sf-option '+((data.settings.mode===key||(key==='auto'&&data.settings.mode==='review'))?'selected':'')}><input type="radio" name="search-mode" checked={data.settings.mode===key||(key==='auto'&&data.settings.mode==='review')} onChange={()=>setData({...data,settings:{...data.settings,mode:key as Settings['mode']}})}/><div><strong>{title}</strong><small>{help}</small></div></label>)}</div></section>
   <section className="sf-card"><div className="sf-card-head"><h3>Already handled for you</h3></div><div className="sf-body">{[['Search titles & descriptions','Base metadata works without AI.'],['Canonical URLs & sitemap','Only active, eligible public content is included.'],['Private records & drafts','Access controls remain in force; private destinations use noindex.'],['Manual overrides','Protected per field until explicitly cleared.']].map(([title,help])=><div className="sf-row" key={title}><div><strong>{title}</strong><small>{help}</small></div><span className="sf-badge">Automatic</span></div>)}</div></section>
   <details className="sf-card"><summary>Advanced settings · search policy</summary><div className="sf-body"><label className="sf-check"><input type="checkbox" checked={data.settings.preview} onChange={e=>setData({...data,settings:{...data.settings,preview:e.target.checked}})}/>Request no indexing for this website</label><p className="sf-muted">AI refreshes have no daily task limit. System automation only does not make LLM requests.</p></div></details>
  </div><aside>
   <section className="sf-card"><div className="sf-card-head"><h3>AI connection</h3></div><div className="sf-body"><span className="sf-badge">{data.ready?'Search enabled':'Not connected for search'}</span><p style={{marginTop:12}}>{data.model??'System metadata stays available.'}</p><div className="sf-actions"><button type="button" className="sf-btn" onClick={()=>setCurrentSection('integration-ai-connect')}>Open AI Connect</button></div></div></section>
   <section className="sf-card"><div className="sf-card-head"><h3>Content coverage</h3></div><div className="sf-body">{Object.entries(data.counts).map(([kind,count])=><div key={kind} className="sf-row"><span>{kind}</span><strong>{count}</strong></div>)}<p className="sf-muted">One refresh covers all four content types.</p></div></section>
  </aside></div>:<p className="sf-muted">Loading search settings…</p>}
 </div>;
}
