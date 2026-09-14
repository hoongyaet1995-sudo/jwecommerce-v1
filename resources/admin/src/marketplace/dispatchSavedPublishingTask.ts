import {marketplaceRequest} from '../integration/marketplaceData';
import {marketplaceOperations} from '../integration/marketplaceOperations';

const active=new Map<string,Promise<void>>();
/** Explicit execution only. Polling and reopening never submit writes. */
export function dispatchSavedPublishingTask(id:string):Promise<void>{
 if(!/^[1-9][0-9]*$/.test(id))return Promise.reject(new Error('Invalid saved task identity.'));
 const existing=active.get(id);if(existing)return existing;
 const pending=(async()=>{
  const task=await marketplaceOperations.publishingTask(id);
  if(task.status!=='queued')return;
  await marketplaceRequest(`/publishing-tasks/${id}/dispatch`,'POST');
 })().finally(()=>active.delete(id));
 active.set(id,pending);return pending;
}
