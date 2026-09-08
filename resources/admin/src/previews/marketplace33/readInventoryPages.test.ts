import {describe,it,expect,vi} from 'vitest';
import {readInventoryPages} from './readInventoryPages';
const page=(current_page:number,last_page:number,total:number,ids:number[])=>new Response(JSON.stringify({current_page,last_page,total,data:ids.map(id=>({id}))}));
describe('inventory preview complete reads',()=>{
 it('reads subsequent pages using fixed same-origin URLs',async()=>{
  const request=vi.fn().mockResolvedValueOnce(page(1,2,3,[1,2])).mockResolvedValueOnce(page(2,2,3,[3]));
  expect((await readInventoryPages(new AbortController().signal,request)).map(r=>r.id)).toEqual([1,2,3]);
  expect(request.mock.calls[1][0]).toBe('/admin/api/marketplace/inventory?per_page=100&page=2');
 });
 it('rejects a partial read when the next request fails',async()=>{
  const request=vi.fn().mockResolvedValueOnce(page(1,2,3,[1,2])).mockResolvedValueOnce(new Response('',{status:503}));
  await expect(readInventoryPages(new AbortController().signal,request)).rejects.toThrow('503');
 });
 it('rejects shifted pages with duplicate SKUs',async()=>{
  const request=vi.fn().mockResolvedValueOnce(page(1,2,3,[1,2])).mockResolvedValueOnce(page(2,2,3,[2]));
  await expect(readInventoryPages(new AbortController().signal,request)).rejects.toThrow('changed');
 });
 it('rejects inventory beyond the bounded preview limit',async()=>{
  const request=vi.fn().mockResolvedValue(page(1,101,10001,[1]));
  await expect(readInventoryPages(new AbortController().signal,request)).rejects.toThrow('limit');
  expect(request).toHaveBeenCalledTimes(1);
 });
});
