import {expect,it,vi} from 'vitest';
import {readSafetyHistory} from './readSafetyHistory';
it('displays safety audit without treating it as a physical stock delta',async()=>{
 const row={operation_key:'key',product_variant_id:17,pool_kind:'shared',warehouse_id:null,
 receipt:{operation_key:'key',previous_safety_stock:null,safety_stock:2,actor_id:2,reason:'Initial buffer',timestamp:'2026-09-08T12:00:00Z'}};
 const request=vi.fn().mockResolvedValue({ok:true,json:async()=>({data:[row],total:1,current_page:1,last_page:1})});
 expect(await readSafetyHistory('17',new AbortController().signal,request)).toEqual([{action:'Shared pool · Safety Unknown → 2',change:0,reason:'Initial buffer',user:'Employee #2',timestamp:'2026-09-08T12:00:00Z'}]);
 row.product_variant_id=18;
 await expect(readSafetyHistory('17',new AbortController().signal,request)).rejects.toThrow('incomplete');
});
