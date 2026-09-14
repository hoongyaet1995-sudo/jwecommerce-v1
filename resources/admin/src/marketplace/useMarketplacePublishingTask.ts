import {useEffect,useState} from 'react';
import {marketplaceOperations,type PublishingTask} from '../integration/marketplaceOperations';

/** Read-only lifecycle: uncertainty must never call a write retry. */
export function useMarketplacePublishingTask(taskId:string|null,enabled:boolean) {
  const [snapshot,setSnapshot]=useState<{id:string;task:PublishingTask}|null>(null);
  const [error,setError]=useState('');
  const [revision,setRevision]=useState(0);
  useEffect(()=>{
    if(!enabled || !taskId)return;
    let active=true;
    let timer:ReturnType<typeof setTimeout>|undefined;
    setError('');
    const poll=async()=>{
      try{
        const task=await marketplaceOperations.publishingTask(taskId);
        if(!active)return;
        setSnapshot({id:taskId,task});setError('');
        if(task.status==='queued'||task.status==='processing'||(task.status==='succeeded'&&task.mappingComplete===false))timer=setTimeout(()=>void poll(),2000);
      }catch{
        if(active)setError('Saved task status could not be checked. Do not submit again; check this task later.');
      }
    };
    void poll();
    return()=>{active=false;if(timer)clearTimeout(timer);};
  },[taskId,enabled,revision]);
  return {task:snapshot?.id===taskId?snapshot.task:null,error,
    checkStatus:()=>setRevision(value=>value+1)};
}
