import {beforeEach,expect,it,vi} from 'vitest';
import {marketplaceRequest} from './marketplaceData';
import {submitShopeeReview} from './submitShopeeReview';
vi.mock('./marketplaceData',()=>({marketplaceRequest:vi.fn()}));
const source={product_id:4,title:'Saved',description:null,revision:'a'.repeat(64),variants:[{id:8,sku:'JW',price_minor:1599,initial_quantity:0}],images:[]};
const input={productId:4,storeId:2,requestKey:'1285db80-1111-4111-8111-123456789045',sourceRevision:source.revision,categoryId:'101',
 brandId:42,condition:'NEW' as const,weightKg:0.5,lengthCm:10,widthCm:10,heightCm:10,attributes:[],logistics:[{logistic_id:24000,is_free:false}]};
const task={id:7,storeId:2,productId:4,status:'queued'};
beforeEach(()=>vi.resetAllMocks());
it('stages reviewed Shopee metadata and recovers a lost receipt without restaging',async()=>{
 vi.mocked(marketplaceRequest).mockImplementation(async(path)=>{
  if(path.includes('publishing-source'))return source;
  if(path.endsWith('/publishing-tasks'))throw new Error('Lost response');
  if(path.includes('/publishing-request/'))return task;
  throw new Error('Lost dispatch response');
 });
 expect(await submitShopeeReview(input)).toBe('7');
 const stages=vi.mocked(marketplaceRequest).mock.calls.filter(call=>call[0].endsWith('/publishing-tasks'));
 expect(stages).toHaveLength(1);
 expect(stages[0][2]).toMatchObject({payload:{category_id:101,item_status:'UNLIST',condition:'NEW',brand:{brand_id:42},
  dimension:{package_length:10,package_width:10,package_height:10},logistic_info:input.logistics}});
 expect((stages[0][2] as {payload:object}).payload).not.toHaveProperty('jw_variations');
});
it('rejects changed source before creating a task',async()=>{
 vi.mocked(marketplaceRequest).mockResolvedValue({...source,revision:'b'.repeat(64)});
 await expect(submitShopeeReview(input)).rejects.toThrow('changed');
 expect(marketplaceRequest).toHaveBeenCalledTimes(1);
});
