import {it,expect,vi} from 'vitest';
import {readMovementHistory} from './readMovementHistory';
it('projects real movements without inventing physical stock or employee names',async()=>{
 const request=vi.fn().mockResolvedValue(new Response(JSON.stringify({current_page:1,last_page:1,total:1,data:[{id:1,product_variant_id:92,created_at:'2026-09-07',available_delta:-2,available_after:8,stock_version:1,actor_id:null,reason:'Checkout'}]})));
 const rows=await readMovementHistory('92',new AbortController().signal,request);
 expect(rows[0]).toMatchObject({change:-2,resultingStock:null,user:'Actor not recorded',action:'Available stock adjustment'});
 expect(rows[0].reason).toContain('Available after: 8');
});
it('rejects an unexpected SKU instead of showing its ledger',async()=>{
 const request=vi.fn().mockResolvedValue(new Response(JSON.stringify({current_page:1,last_page:1,total:1,data:[{id:1,product_variant_id:93}]})));
 await expect(readMovementHistory('92',new AbortController().signal,request)).rejects.toThrow('identity');
});
