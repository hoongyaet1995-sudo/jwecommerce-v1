import {it,expect,vi} from 'vitest';
import {exceptionView,readExceptions} from './readExceptions';
it('does not mark an unresolved historical exception excluded or invent stock quantities',()=>{
 const row=exceptionView({order_id:1,error_code:'historical_order'});
 expect(row).toMatchObject({type:'predates_activation',status:'open',isHistoricalExcluded:false,requestedQuantity:null,availableAtp:null,statusLabel:'Requires reconciliation'});
});
it('preserves unsupported exceptions instead of reporting stock depletion',()=>{
 expect(exceptionView({order_id:1,error_code:'unsupported_fulfillment'}).statusLabel).toBe('unsupported_fulfillment');
});
it('rejects failures instead of returning an empty exception list',async()=>{
 await expect(readExceptions(new AbortController().signal,vi.fn().mockResolvedValue(new Response('',{status:503})))).rejects.toThrow('503');
});
