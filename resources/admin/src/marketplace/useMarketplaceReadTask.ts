import {useEffect,useRef,useState} from 'react';
import {marketplaceOperations,SyncRun} from '../integration/marketplaceOperations';

export function useMarketplaceReadTask(kind:'catalog'|'orders',onComplete:()=>void){
 const [runId,setRunId]=useState<number|null>(null);const [run,setRun]=useState<SyncRun|null>(null);
 const [error,setError]=useState('');const [submitting,setSubmitting]=useState(false);
 const [pollRevision,setPollRevision]=useState(0);
 const locked=useRef(false);const mounted=useRef(true);const completed=useRef<number|null>(null);
 const complete=useRef(onComplete);complete.current=onComplete;
 useEffect(()=>{mounted.current=true;return()=>{mounted.current=false;};},[]);
 useEffect(()=>{
  if(runId===null)return;
  let active=true;let timer:ReturnType<typeof setTimeout>|undefined;
  const poll=async()=>{
   try{const value=await (kind==='orders'?marketplaceOperations.orderRun(String(runId)):marketplaceOperations.catalogRun(String(runId)));
    if(!active)return;setRun(value);setError('');
    if(value.status==='succeeded'){
     if(completed.current!==value.id){completed.current=value.id;complete.current();}
    }else if(value.status!=='failed')timer=setTimeout(()=>void poll(),2000);
   }catch{if(active)setError('Task status could not be checked. Check status again; do not submit a duplicate task.');}
  };
  void poll();return()=>{active=false;if(timer)clearTimeout(timer);};
 },[kind,runId,pollRevision]);
 const start=async(submit:()=>Promise<{runId:number}>)=>{
  if(locked.current||runId!==null&&run?.status!=='succeeded'&&run?.status!=='failed')return;
  locked.current=true;setSubmitting(true);setError('');
  try{const result=await submit();if(mounted.current){setRun(null);setRunId(result.runId);setPollRevision(value=>value+1);}return true;}
  catch{if(mounted.current)setError('Task submission was not confirmed. Check the store before retrying.');return false;}
  finally{locked.current=false;if(mounted.current)setSubmitting(false);}
 };
 const resume=(value:SyncRun)=>{if(!Number.isSafeInteger(value.id)||value.id<=0)return;setRun(value);setRunId(value.id);setError('');setPollRevision(revision=>revision+1);};
 const reset=()=>{setRun(null);setRunId(null);setError('');};
 return {runId,run,error,submitting,start,resume,reset,checkStatus:()=>setPollRevision(value=>value+1),
  busy:submitting||runId!==null&&run?.status!=='succeeded'&&run?.status!=='failed'};
}
