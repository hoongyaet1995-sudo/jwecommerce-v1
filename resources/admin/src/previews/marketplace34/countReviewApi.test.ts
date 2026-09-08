import {expect,it,vi} from 'vitest';
import {countReviewApi} from './countReviewApi';
const review={id:'12345678-1234-1234-1234-123456789012',physical_count:0,physical_before:null,actor_id:2,atp:null} as const;
it('sends only count intent then confirms the server review without employee or quantity overrides',async()=>{
 const request=vi.fn().mockResolvedValueOnce(new Response(JSON.stringify(review),{status:201}))
  .mockResolvedValueOnce(new Response(JSON.stringify({review_id:review.id,physical_after:0,actor_id:2})));
 const api=countReviewApi(3,'csrf',request);
 await api.confirm(await api.review(0,'Empty'));
 expect(JSON.parse(request.mock.calls[0][1].body)).toEqual({balance_id:3,physical_count:0,reason:'Empty'});
 expect(JSON.parse(request.mock.calls[1][1].body)).toEqual({acknowledged:true});
 expect(request.mock.calls[0][1].headers['X-CSRF-TOKEN']).toBe('csrf');
});
it('surfaces server conflicts and rejects a mismatched confirmation',async()=>{
 const conflict=vi.fn().mockResolvedValue(new Response(JSON.stringify({errors:{inventory:['Inventory changed.']}}),{status:422}));
 await expect(countReviewApi(3,'csrf',conflict).review(0,'Empty')).rejects.toThrow('Inventory changed');
 const mismatch=vi.fn().mockResolvedValue(new Response(JSON.stringify({review_id:review.id,physical_after:99,actor_id:2})));
 await expect(countReviewApi(3,'csrf',mismatch).confirm(review)).rejects.toThrow('could not be verified');
});
