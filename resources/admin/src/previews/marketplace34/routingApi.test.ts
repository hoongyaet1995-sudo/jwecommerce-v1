import {describe,it,expect,vi} from 'vitest';
import {routingSaver} from './routingApi';

describe('routing saver',()=>{
  it('preserves loaded store, version and disabled state while saving source fields',async()=>{
    const request=vi.fn().mockResolvedValue(new Response(JSON.stringify({channel_key:'marketplace:2',warehouse_id:3,version:5}),{status:200}));
    const result=await routingSaver('csrf',{channelKey:'marketplace:2',version:4,enabled:false},request)({
      id:'other',boundWarehouseId:'3',platformLocationId:'LOC',platformLocationName:'Main',fulfillmentPriority:2});
    expect(result.success).toBe(true);
    expect(JSON.parse(request.mock.calls[0][1].body)).toEqual({channel_key:'marketplace:2',version:4,is_enabled:false,
      warehouse_id:3,platform_location_id:'LOC',platform_location_name:'Main',fulfillment_priority:2});
  });
  it('does not accept a receipt for another store',async()=>{
    const request=vi.fn().mockResolvedValue(new Response(JSON.stringify({channel_key:'marketplace:99',warehouse_id:3,version:0}),{status:200}));
    expect((await routingSaver('csrf',{channelKey:'website',version:null,enabled:false},request)({boundWarehouseId:'3'})).success).toBe(false);
  });
});
