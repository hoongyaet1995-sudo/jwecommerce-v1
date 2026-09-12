import {expect,it,vi} from 'vitest';
import {initialCountApi} from './initialCountApi';
it('provisions only after explicit count review and never seeds stock in provisioning',async()=>{
 const review={id:'12345678-1234-1234-1234-123456789012',physical_count:12,physical_before:null,actor_id:2,atp:null};
 const request=vi.fn().mockResolvedValueOnce(new Response(JSON.stringify({balance_id:9,variant_id:17})))
  .mockResolvedValueOnce(new Response(JSON.stringify(review)))
  .mockResolvedValueOnce(new Response(JSON.stringify({review_id:review.id,physical_after:12,actor_id:2})));
 const api=initialCountApi(17,'warehouse',3,'csrf',request);
 expect(request).not.toHaveBeenCalled();
 await api.confirm(await api.review(12,'Initial shelf count'));
 expect(JSON.parse(request.mock.calls[0][1].body)).toEqual({variant_id:17,pool_kind:'warehouse',warehouse_id:3});
 expect(request.mock.calls).toHaveLength(3);
});
