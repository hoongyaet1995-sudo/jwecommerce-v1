import React,{useState,useEffect,useRef} from 'react';
import {createRoot} from 'react-dom/client';
import {consumeOneClickNavigation,isOneClickPending} from './optimization-core.mjs';
import './optimization.css';

function OptimizationApp({host}){
 const [run,setRun]=useState(null),[ready,setReady]=useState(false),[active,setActive]=useState(false),[busy,setBusy]=useState(false),[error,setError]=useState('');
 const sending=useRef(false),requestId=useRef(null);
 const parent=host.closest('.ws-editing-assistant'),key='one-click-optimization:'+host.dataset.film;
 const pinned=new URLSearchParams(location.search).get('optimization_run');
 const statusUrl=host.dataset.status+(pinned?'?run='+encodeURIComponent(pinned):'');
 const send=async(url,body)=>{
  const response=await fetch(url,{method:body?'POST':'GET',headers:{Accept:'application/json',...(body?{'Content-Type':'application/json','X-CSRF-TOKEN':host.dataset.csrf}:{})},...(body?{body:JSON.stringify(body)}:{})});
  let data;try{data=await response.json();}catch{throw Error('暂时无法读取，请刷新并检查登录状态。');}
  if(response.status>=500)throw Error('暂未完成，请重新读取进度；原版保留。');
  if(!response.ok)throw Error(Object.values(data.errors||{}).flat()[0]||data.message||'暂未完成，请重新读取进度。');
  return data.run;
 };
 const receive=r=>{
  setRun(r);setReady(true);
  const url=consumeOneClickNavigation(r,sessionStorage,key+':active');if(url)location.assign(url);
 };
 const read=async()=>{setError('');try{receive(await send(run?host.dataset.status+'?run='+run.id:statusUrl));}catch(e){setError(e.message);}};
 useEffect(()=>{let stopped=false;send(statusUrl).then(r=>{if(stopped)return;setRun(r);setReady(true);if(r?.one_click&&(sessionStorage.getItem(key+':active')||['queued','applying','rendering'].includes(r.one_click.status))){setActive(true);receive(r);}}).catch(e=>{if(!stopped){setError(e.message);setActive(true);}});return()=>{stopped=true;};},[]);
 const click=run?.one_click,pending=isOneClickPending(run,active);
 useEffect(()=>{if(!pending||busy)return;let stopped=false,inflight=false;const timer=setInterval(async()=>{if(inflight)return;inflight=true;try{const r=await send(run?host.dataset.status+'?run='+run.id:statusUrl);if(!stopped){setError('');receive(r);}}catch(e){if(!stopped)setError(e.message);}finally{inflight=false;}},5000);return()=>{stopped=true;clearInterval(timer);};},[pending,busy,run?.id]);
 useEffect(()=>{parent?.classList.toggle('optimization-active',active);return()=>parent?.classList.remove('optimization-active');},[active]);
 const start=async()=>{
  if(sending.current||!ready)return;
  setActive(true);setError('');
  if(click){sessionStorage.setItem(key+':active','1');await read();return;}
  sending.current=true;setBusy(true);sessionStorage.setItem(key+':active','1');
  try{
   requestId.current=requestId.current||sessionStorage.getItem(key+':request')||crypto.randomUUID();sessionStorage.setItem(key+':request',requestId.current);
   const r=await send(host.dataset.oneClick,{request_id:requestId.current,base_hash:host.dataset.hash,...((pinned||run?.id)?{run_id:pinned||run.id}:{})});receive(r);
  }catch(e){setError(e.message+' 请求结果需要核对，请重新读取进度；不会自动重新分析。');}finally{sending.current=false;setBusy(false);}
 };
 const talk=()=>{setActive(false);sessionStorage.removeItem(key+':active');};
 const failed=click?.status==='failed'||['failed','unknown'].includes(run?.status);
 const unchanged=click?.status==='unchanged';
 const stage=busy?'正在检查画面':click?.status==='applying'||(run?.status==='completed'&&pending)?'正在优化':click?.status==='rendering'?'正在生成新版':({queued:'等待后台检查',sampling:'正在检查画面',analysis:'正在分析画面与配方',validating:'正在核对优化方案'})[run?.stage]||'正在读取优化进度';
 return <div className="optimization-ui"><div className="opt-mode-switch"><button className={active?'active':''} onClick={start} disabled={busy||!ready}>一键优化 <i className="bi bi-stars"/></button><button className={!active?'active':''} onClick={talk}>项目对话 · Luna</button></div>{!active?<div className="opt-intro compact-intro"><h3>让 AI 完成检查与优化</h3><p>自动检查画面、选择可靠的优化并制作新版。你只需看看结果，原版始终保留。</p><button className="opt-primary" disabled={busy||!ready} onClick={start}>{click?.status==='completed'?'查看 AI 优化版':click?'查看优化状态':'一键优化'} <i className="bi bi-arrow-right"/></button><small>点击即开始检查与本地制作；已有检查会复用，新检查可能产生分析费用。</small></div>:<div className="opt-assistant"><div className="opt-assistant-header"><div><h3><i className="bi bi-stars"/> 一键优化</h3><p>AI 负责检查与制作，你只需查看新版。</p></div><span className="opt-badge">原版保留</span></div><div className="opt-intro" role="status" aria-live="polite"><div className="opt-spark"><i className={`bi bi-${failed?'exclamation-circle':unchanged?'check-circle':'hourglass-split'}`}/></div><h3>{failed?'这次优化没有完成':unchanged?'本次保留当前版本':click?.status==='completed'?'AI 优化版已完成':stage}</h3><p>{failed?(click?.error||run?.error||'原版保留，没有自动重新分析。'):unchanged?'没有发现能够可靠自动执行的改动，保留当前版本。':'后台会继续处理，完成后自动显示 AI 优化版。可以刷新或切回项目对话。'}</p>{click?.url&&<a className="opt-primary" href={click.url}>查看 AI 优化版 <i className="bi bi-arrow-right"/></a>}<small>完整口播、人物与商品事实保留，不会自动发布。没有可靠依据的改动会跳过。</small></div></div>}{error&&<p className="opt-error" role="alert">{error}<button className="opt-link" onClick={read}>重新读取进度</button></p>}</div>;
}
export function mountOptimization(){const host=document.querySelector('[data-film-optimization]');if(host)createRoot(host).render(<OptimizationApp host={host}/>);}
