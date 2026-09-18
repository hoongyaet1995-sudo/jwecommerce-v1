import {marketplaceOperations} from './marketplaceOperations';
import {marketplaceRequest} from './marketplaceData';
import {submitLazadaReview,type LazadaReviewSubmission} from './submitLazadaReview';
import {submitShopeeReview,type ShopeeReviewSubmission} from './submitShopeeReview';

/**
 * The API has confirmed that the browser's prior request key is no longer a
 * saved publishing task.  The caller may offer a deliberate recovery action;
 * requests which still exist (including uncertain ones) never reach here.
 */
export class MissingPublishingRequestRecoveryError extends Error {
  constructor(private readonly slot:string) {
    super('The earlier publishing request was not found on the server. Confirm that no listing was created, then start a new submission.');
    this.name='MissingPublishingRequestRecoveryError';
  }

  startNewSubmission():void {
    localStorage.removeItem(this.slot);
  }
}

export async function publishLazadaWithRecovery(actorId:string,input:Omit<LazadaReviewSubmission,'requestKey'>):Promise<string>{
 return publishWithRecovery(actorId,input,submitLazadaReview);
}

export async function publishShopeeWithRecovery(actorId:string,input:Omit<ShopeeReviewSubmission,'requestKey'>):Promise<string>{
 return publishWithRecovery(actorId,input,submitShopeeReview);
}

async function publishWithRecovery<T extends {productId:number;storeId:number}>(actorId:string,input:T,submit:(input:T&{requestKey:string})=>Promise<string>):Promise<string>{
 if(!/^[1-9][0-9]*$/.test(actorId))throw new Error('Reload the administrator session before publishing.');
 const slot=`jw-marketplace-publish:${actorId}:${input.storeId}:${input.productId}`;
 const signature=JSON.stringify(input);const saved=localStorage.getItem(slot);
 let pending:{key:string;signature:string};
 if(saved){
  pending=JSON.parse(saved);
  if(!pending||typeof pending.key!=='string'||typeof pending.signature!=='string')throw new Error('The saved publishing request needs review.');
  try{
   const task=await marketplaceOperations.recoverPublishingTask(String(input.storeId),pending.key);
   if(task.productId!==input.productId)throw new Error('Saved task belongs to another product.');
   if(task.status==='rejected'&&!task.externalItemId){
    pending={key:crypto.randomUUID(),signature};localStorage.setItem(slot,JSON.stringify(pending));
   }else{
    if(task.status==='queued'&&pending.signature===signature){
     try{await marketplaceRequest(`/publishing-tasks/${task.id}/dispatch`,'POST');}catch{/* Read the original task outcome. */}
    }
    return String(task.id);
   }
  }catch(error){
   if(!error||typeof error!=='object'||!('status' in error)||error.status!==404)throw error;
   if(pending.signature!==signature)throw new MissingPublishingRequestRecoveryError(slot);
  }
 }else{
  pending={key:crypto.randomUUID(),signature};
  localStorage.setItem(slot,JSON.stringify(pending));
 }
 try{return await submit({...input,requestKey:pending.key});}
 catch(error){
  if(error&&typeof error==='object'&&'status' in error&&typeof error.status==='number'&&error.status>=400&&error.status<500&&error.status!==404)localStorage.removeItem(slot);
  throw error;
 }
}
