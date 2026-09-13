import {afterEach,beforeEach,expect,it,vi} from 'vitest';
import {publishLazadaWithRecovery} from './publishingReviewSession';
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
it('resumes a saved queued task without creating a new one',async()=>{
 vi.mocked(submitLazadaReview).mockResolvedValue('7');await publishLazadaWithRecovery('2',input);
 vi.mocked(marketplaceOperations.recoverPublishingTask).mockResolvedValue({id:7,productId:4,status:'queued'} as never);
 expect(await publishLazadaWithRecovery('2',input)).toBe('7');
 expect(marketplaceRequest).toHaveBeenCalledWith('/publishing-tasks/7/dispatch','POST');
 expect(submitLazadaReview).toHaveBeenCalledTimes(1);
});
