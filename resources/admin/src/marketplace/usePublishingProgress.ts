import {useEffect,useState} from 'react';
/** Presentation follows confirmed milestones; timers only delay, never invent success. */
export function usePublishingProgress(active:boolean,saved:boolean,published:boolean,mapped:boolean,failed:boolean){
 const [step,setStep]=useState(0);
 useEffect(()=>{
  if(!active){setStep(0);return;}
  if(failed)return;
  const ready=step===0?saved:step===1?published:step===2?mapped:step===3;
  if(!ready||step>=4)return;
  const timer=setTimeout(()=>setStep(s=>s+1),step===3?400:600);
  return()=>clearTimeout(timer);
 },[active,saved,published,mapped,failed,step]);
 return step;
}
