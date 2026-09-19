import {expect,it,vi} from 'vitest';
import {readSandboxHistory} from './readSandboxHistory';
it('keeps uncertain platform outcomes distinct from physical stock movements',async()=>{
 const row={id:'a',product_variant_id:17,store_id:3,actor_id:2,status:'uncertain',before_quantity:82,observed_quantity:null,reason:'Test',snapshot:{platform:'lazada',platform_location_id:'WH'},updated_at:'2026-09-08 12:00:00'};
 const request=vi.fn().mockResolvedValue({ok:true,json:async()=>({data:[row],total:1,last_page:1,current_page:1})});
 const result=await readSandboxHistory('17',new AbortController().signal,request);
 expect(result[0]).toMatchObject({change:0,action:'Sandbox lazada store #3 · uncertain · Platform 82 → Unconfirmed',timestamp:'2026-09-08T12:00:00Z'});
 row.product_variant_id=18;
 await expect(readSandboxHistory('17',new AbortController().signal,request)).rejects.toThrow('incomplete');
});
it('shows original readback and subsequent matched observation separately',async()=>{
 const row={id:'attempt',product_variant_id:17,store_id:1,actor_id:2,status:'confirmed',before_quantity:97,observed_quantity:97,reason:'Test',snapshot:{platform:'shopee',platform_location_id:'MYZ'},updated_at:'2026-09-13 15:55:15',rechecks:[{attempt_id:'attempt',actor_id:2,outcome:'matched',observed_quantity:20,created_at:'2026-09-13 15:55:15'}]};
 const request=vi.fn().mockResolvedValue({ok:true,json:async()=>({data:[row],total:1,last_page:1,current_page:1})});
 const result=await readSandboxHistory('17',new AbortController().signal,request);
 expect(result).toHaveLength(2);expect(result[0].action).toContain('97 → 97');expect(result[1].action).toContain('Recheck matched · Platform 20');expect(result[0].attemptId).toBeUndefined();
});
