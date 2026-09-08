// @vitest-environment jsdom
import React,{act} from 'react';
import {createRoot} from 'react-dom/client';
import {expect,it,vi} from 'vitest';
import {PhysicalCountModal} from './PhysicalCountModal';

it('opens the source modal but blocks review when the SKU has no configured balance',async()=>{
 (globalThis as any).IS_REACT_ACT_ENVIRONMENT=true;
 const host=document.createElement('div');document.body.append(host);
 const root=createRoot(host);
 const request=vi.fn().mockResolvedValue(new Response(JSON.stringify({data:[],total:0,current_page:1,last_page:1})));
 vi.stubGlobal('fetch',request);
 try {
  await act(async()=>root.render(<PhysicalCountModal isOpen item={{id:'17',masterSku:'SKU',productName:'Product'}} onClose={()=>{}}/>));
  expect(request.mock.calls[0][0]).toBe('/admin/api/inventory/balances?variantId=17&page=1');
  expect(host.textContent).toContain('No physical balance is configured');
  expect(host.textContent).toContain('Warehouse Stock Operation');
  const review=Array.from(host.querySelectorAll('button')).find(b=>b.textContent?.includes('Proceed to Review'));
  expect(review?.disabled).toBe(true);
  expect(host.querySelector('input[type="number"]')?.getAttribute('value')).toBe('');
 } finally {await act(async()=>root.unmount());host.remove();vi.unstubAllGlobals();}
});
