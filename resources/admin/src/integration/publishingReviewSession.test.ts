import {afterEach,beforeEach,expect,it,vi} from 'vitest';
import {MissingPublishingRequestRecoveryError,publishLazadaWithRecovery} from './publishingReviewSession';
import {submitLazadaReview} from './submitLazadaReview';
import {marketplaceOperations} from './marketplaceOperations';
import {marketplaceRequest} from './marketplaceData';
vi.mock('./submitLazadaReview',()=>({submitLazadaReview:vi.fn()}));
vi.mock('./marketplaceOperations',()=>({marketplaceOperations:{recoverPublishingTask:vi.fn()}}));
vi.mock('./marketplaceData',()=>({marketplaceRequest:vi.fn()}));
const input={productId:4,storeId:2,sourceRevision:'a'.repeat(64),categoryId:'11',attributes:{},skuAttributes:{}};
const values=new Map<string,string>();
beforeEach(()=>{vi.resetAllMocks();values.clear();vi.stubGlobal('localStorage',{getItem:(key:string)=>values.get(key)??null,setItem:(key:string,value:string)=>values.set(key,value),removeItem:(key:string)=>values.delete(key)});});
afterEach(()=>vi.unstubAllGlobals());
it('persists the request before sending and recovers without restaging an uncertain task',async()=>{
 vi.mocked(submitLazadaReview).mockImplementation(async()=>{expect(values.size).toBe(1);return '7';});
 expect(await publishLazadaWithRecovery('2',input)).toBe('7');
 vi.mocked(marketplaceOperations.recoverPublishingTask).mockResolvedValue({id:7,productId:4,status:'uncertain'} as never);
 expect(await publishLazadaWithRecovery('2',input)).toBe('7');
 expect(submitLazadaReview).toHaveBeenCalledTimes(1);expect(marketplaceRequest).not.toHaveBeenCalled();
});
it('clears a definitively denied request so the user can correct the review',async()=>{
 vi.mocked(submitLazadaReview).mockRejectedValue(Object.assign(new Error('Review category'),{status:422}));
 await expect(publishLazadaWithRecovery('2',input)).rejects.toThrow('Review category');
 expect(values.size).toBe(0);
});
it('returns a saved queued task to the view without blocking on dispatch',async()=>{
 vi.mocked(submitLazadaReview).mockResolvedValue('7');await publishLazadaWithRecovery('2',input);
 vi.mocked(marketplaceOperations.recoverPublishingTask).mockResolvedValue({id:7,productId:4,status:'queued'} as never);
 expect(await publishLazadaWithRecovery('2',input)).toBe('7');
 expect(marketplaceRequest).not.toHaveBeenCalled();
  expect(submitLazadaReview).toHaveBeenCalledTimes(1);
});
it('uses a new request identity when a duplicate-risk recovery authorization was issued',async()=>{
 const slot='jw-marketplace-publish:2:2:4';
 values.set(slot,JSON.stringify({key:'9285db80-1111-4111-8111-123456789012',signature:'old-review'}));
 vi.mocked(marketplaceOperations.recoverPublishingTask).mockResolvedValue({id:5,productId:4,status:'uncertain'} as never);
 vi.mocked(submitLazadaReview).mockResolvedValue('6');
 const recovered={...input,recoveryAuthorizationId:'1285db80-1111-4111-8111-123456789045'};
 expect(await publishLazadaWithRecovery('2',recovered)).toBe('6');
 expect(marketplaceOperations.recoverPublishingTask).not.toHaveBeenCalled();
 expect(submitLazadaReview).toHaveBeenCalledWith(expect.objectContaining({
  recoveryAuthorizationId:recovered.recoveryAuthorizationId,
 }));
 const newRequestKey=(vi.mocked(submitLazadaReview).mock.calls[0][0] as {requestKey:string}).requestKey;
 expect(newRequestKey).not.toBe('9285db80-1111-4111-8111-123456789012');
 vi.mocked(marketplaceOperations.recoverPublishingTask).mockResolvedValue({id:6,productId:4,status:'uncertain'} as never);
 expect(await publishLazadaWithRecovery('2',recovered)).toBe('6');
 expect(marketplaceOperations.recoverPublishingTask).toHaveBeenCalledWith('2',newRequestKey);
 expect(submitLazadaReview).toHaveBeenCalledTimes(1);
});
it('lets a user replace only a server-confirmed missing request before submitting changed values',async()=>{
 const slot='jw-marketplace-publish:2:2:4';
 values.set(slot,JSON.stringify({key:'missing-request',signature:'earlier-review'}));
 vi.mocked(marketplaceOperations.recoverPublishingTask).mockRejectedValue({status:404});
 const changedInput={...input,categoryId:'12'};
 let failure:unknown;
 try {await publishLazadaWithRecovery('2',changedInput);} catch(error) {failure=error;}
 expect(failure).toBeInstanceOf(MissingPublishingRequestRecoveryError);
 expect(submitLazadaReview).not.toHaveBeenCalled();
 (failure as MissingPublishingRequestRecoveryError).startNewSubmission();
 expect(values.has(slot)).toBe(false);
 vi.mocked(submitLazadaReview).mockResolvedValue('8');
 expect(await publishLazadaWithRecovery('2',changedInput)).toBe('8');
 expect(submitLazadaReview).toHaveBeenCalledWith(expect.objectContaining({categoryId:'12'}));
 expect((vi.mocked(submitLazadaReview).mock.calls[0][0] as {requestKey:string}).requestKey).not.toBe('missing-request');
});
