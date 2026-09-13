import {beforeEach,expect,it,vi} from 'vitest';
import {marketplaceRequest} from './marketplaceData';
import {submitLazadaReview} from './submitLazadaReview';
vi.mock('./marketplaceData',()=>({marketplaceRequest:vi.fn()}));
const source={product_id:4,title:'Saved',description:null,revision:'a'.repeat(64),variants:[{id:8,sku:'JW',price_minor:1599,initial_quantity:0}],images:[]};
const input={productId:4,storeId:2,requestKey:'1285db80-1111-4111-8111-123456789045',sourceRevision:source.revision,categoryId:'10000420',attributes:{brand:'No Brand'},skuAttributes:{JW:{package_weight:'0.2'}}};
const task={id:7,storeId:2,productId:4,status:'queued',externalItemId:null,createdAt:'now',updatedAt:'now'};
beforeEach(()=>vi.resetAllMocks());
it('stages the reviewed source and returns the saved task even when dispatch response is lost',async()=>{
 vi.mocked(marketplaceRequest).mockImplementation(async(path)=>{
  if(path.includes('publishing-source'))return source;
  if(path.endsWith('/publishing-tasks'))return task;
  throw new Error('Network unavailable');
 });
 expect(await submitLazadaReview(input)).toBe('7');
 const stage=vi.mocked(marketplaceRequest).mock.calls.find(call=>call[0].endsWith('/publishing-tasks'))!;
 expect(stage[2]).toMatchObject({product_id:4,source_revision:source.revision,request_key:input.requestKey,
  payload:{Request:{Product:{PrimaryCategory:'10000420',Skus:{Sku:[{SellerSku:'JW',quantity:'0',price:'15.99',package_weight:'0.2'}]}}}}});
 expect(marketplaceRequest).toHaveBeenCalledTimes(3);
});
it('recovers an uncertain staging response without another stage request',async()=>{
 vi.mocked(marketplaceRequest).mockImplementation(async(path)=>{
  if(path.includes('publishing-source'))return source;
  if(path.endsWith('/publishing-tasks'))throw new Error('Response lost');
  if(path.includes('/publishing-request/'))return task;
  return {...task,status:'succeeded',externalItemId:'123'};
 });
 expect(await submitLazadaReview(input)).toBe('7');
 expect(vi.mocked(marketplaceRequest).mock.calls.filter(call=>call[0].endsWith('/publishing-tasks'))).toHaveLength(1);
});
it('rejects changed source and foreign task receipts before dispatch',async()=>{
 vi.mocked(marketplaceRequest).mockResolvedValue({...source,revision:'b'.repeat(64)});
 await expect(submitLazadaReview(input)).rejects.toThrow('changed');
 expect(marketplaceRequest).toHaveBeenCalledTimes(1);
 vi.mocked(marketplaceRequest).mockReset().mockImplementation(async(path)=>path.includes('publishing-source')?source:{...task,productId:99});
 await expect(submitLazadaReview(input)).rejects.toThrow('task');
 expect(vi.mocked(marketplaceRequest).mock.calls.some(call=>call[0].endsWith('/dispatch'))).toBe(false);
});
