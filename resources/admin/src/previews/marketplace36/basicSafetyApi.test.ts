import {expect,it,vi} from 'vitest';
import {basicSafetyApi} from './basicSafetyApi';
it('uses the Basic endpoint and pins retries to the same reviewed operation',async()=>{
 const request=vi.fn().mockImplementation(async(url,options)=>{
  const payload=JSON.parse(options.body);
  return new Response(JSON.stringify({operation_key:payload.operation_key,balance_id:7,balance_version:2,safety_stock:2,previous_safety_stock:null,actor_id:3,timestamp:'2026-09-08T12:00:00Z',
   basic_wms:{physical_stock:12,owned_reserved:0,available:10,order_coverage_complete:false,platform_write_authorized:false}}));
 });
 const save=basicSafetyApi({balanceId:7,version:1,masterSku:'TEST',warehouseId:'9'},'csrf',request);
 const input={masterSku:'TEST',warehouseId:'9',newSafetyStock:2,reason:'Initial safety',expectedVersion:1,operatorName:'Ignored'};
 expect(await save(input)).toMatchObject({status:'BASIC_SAFETY_CONFIGURED',newAtp:10});
 await save(input);
 expect(request.mock.calls[0][0]).toBe('/admin/api/inventory/basic/safety');
 expect(request.mock.calls[0][1].body).toBe(request.mock.calls[1][1].body);
 expect(JSON.parse(request.mock.calls[0][1].body)).not.toHaveProperty('operatorName');
 await expect(save({...input,newSafetyStock:3})).rejects.toThrow('Reopen');
});
