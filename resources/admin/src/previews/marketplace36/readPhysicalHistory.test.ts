import {expect,it,vi} from 'vitest';
import {readPhysicalHistory} from './readPhysicalHistory';
it('loads all pages and preserves unknown initial counts',async()=>{
 const row={id:1,product_variant_id:17,pool_kind:'warehouse',warehouse_id:9,physical_before:null,physical_after:20,physical_delta:null,reason:'Opening count',actor_id:2,created_at:'2026-09-08 11:00:00'};
 const request=vi.fn().mockResolvedValueOnce(new Response(JSON.stringify({data:[row],current_page:1,last_page:2,total:2})))
  .mockResolvedValueOnce(new Response(JSON.stringify({data:[{...row,id:2,physical_before:20,physical_after:25,physical_delta:5}],current_page:2,last_page:2,total:2})));
 const result=await readPhysicalHistory('17',new AbortController().signal,request);
 expect(request).toHaveBeenCalledTimes(2);
 expect(result[0]).toMatchObject({action:'Warehouse #9 · Unknown → 20',change:null,user:'Employee #2',timestamp:'2026-09-08T11:00:00Z'});
 expect(result[1].change).toBe(5);
});
it('rejects mixed SKU history instead of displaying another product ledger',async()=>{
 const request=vi.fn().mockResolvedValue(new Response(JSON.stringify({data:[{id:1,product_variant_id:99}],current_page:1,last_page:1,total:1})));
 await expect(readPhysicalHistory('17',new AbortController().signal,request)).rejects.toThrow('incomplete');
});
