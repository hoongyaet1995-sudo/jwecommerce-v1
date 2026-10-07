import React,{useState} from 'react';
import type {MessageAttachment} from '../../types/support';

export function SupportAttachmentAnalysis({analysis}:{analysis:MessageAttachment['analysis']}){
 const [busy,setBusy]=useState(false),[error,setError]=useState('');
 if(!analysis)return null;
 const retry=async()=>{
  if(!analysis.retryUrl||busy)return;
  setBusy(true);setError('');
  try{const response=await fetch(analysis.retryUrl,{method:'POST',credentials:'same-origin',headers:{Accept:'application/json','X-CSRF-TOKEN':document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content||''}});if(!response.ok)throw new Error((await response.json()).message||'Analysis retry unavailable.');}
  catch(e){setError(e instanceof Error?e.message:'Analysis retry unavailable.');}
  finally{setBusy(false);}
 };
 return <details className="mt-2 rounded-lg border border-neutral-200 dark:border-neutral-700 p-2 text-xs">
  <summary className="cursor-pointer font-semibold">AI attachment notes · {analysis.status}</summary>
  <p className="mt-2 text-neutral-500">Customer-provided content; identity and business facts remain unverified.</p>
  {analysis.text&&<p className="mt-2 whitespace-pre-wrap break-words">{analysis.text}</p>}
  {analysis.limitations.map((limitation,index)=><p key={index} className="mt-1 text-neutral-500">{limitation}</p>)}
  {analysis.needsConfirmation&&<p className="mt-2 text-amber-600 dark:text-amber-400">Confirm unclear content with the customer.</p>}
  {analysis.failureCode&&<p className="mt-2 text-neutral-500">Analysis unavailable: {analysis.failureCode.replaceAll('_',' ')}.</p>}
  {analysis.retryUrl&&<button type="button" disabled={busy} onClick={()=>void retry()} className="mt-2 underline disabled:opacity-50">{busy?'Retry requested…':'Retry analysis'}</button>}
  {error&&<p role="alert" className="mt-2 text-red-500">{error}</p>}
 </details>;
}
