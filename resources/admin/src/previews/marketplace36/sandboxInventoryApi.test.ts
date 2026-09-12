import {expect,it,vi} from 'vitest';
import {sandboxPush,type SandboxStockReview} from './sandboxInventoryApi';
const review:SandboxStockReview={platform:'lazada',store_id:3,inventory_balance_id:10,listing_variant_id:83,desired_quantity:19,balance_version:5,route_version:0,configuration_version:4,external_product_id:'123',external_variant_id:'456',external_shop_id:'300163725140',platform_location_id:'TEST',order_coverage_complete:false};
it('dispatches only the displayed snapshot and requires matching independent readback',async()=>{
 let id='';const request=vi.fn(async(_url,options:any)=>{
  const data=JSON.parse(options.body);if(data.operation_key)id=data.operation_key;
  return {ok:true,json:async()=>({id,store_id:3,inventory_balance_id:10,snapshot:review,status:data.operation_key?'staged':'confirmed',observed_quantity:19})} as Response;
 });
 const push=sandboxPush(review,'csrf',request);
 await expect(push()).resolves.toMatchObject({status:'confirmed',observed_quantity:19});
 expect(request).toHaveBeenCalledTimes(2);
 expect(JSON.parse(request.mock.calls[0][1].body)).not.toHaveProperty('quantity');
});
it('does not dispatch a changed balance review or accept uncertain provider success',async()=>{
 const request=vi.fn(async(_url,options:any)=>({ok:true,json:async()=>({id:JSON.parse(options.body).operation_key,store_id:3,inventory_balance_id:10,snapshot:{...review,desired_quantity:20},status:'staged'})} as Response));
 await expect(sandboxPush(review,'csrf',request)()).rejects.toThrow('target changed');
 expect(request).toHaveBeenCalledTimes(1);
});
