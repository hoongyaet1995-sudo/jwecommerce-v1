// @vitest-environment jsdom
import React,{act} from 'react';
import {createRoot} from 'react-dom/client';
import {expect,it,vi} from 'vitest';
import {OrderActivationControl} from './OrderActivationControl';
it('requires explicit channel and review before sending activation',async()=>{
 (globalThis as any).IS_REACT_ACT_ENVIRONMENT=true;
 const host=document.createElement('div');const root=createRoot(host);const send=vi.fn();
 vi.stubGlobal('fetch',send);
 try {
  await act(async()=>root.render(<OrderActivationControl balanceId={1} sku="QA" channels={[{id:'website',name:'JW'}]}/>));
  expect(host.querySelector('select')!.value).toBe('');
  expect(host.querySelector<HTMLButtonElement>('button')!.disabled).toBe(true);
  expect(send).not.toHaveBeenCalled();
  expect(host.textContent).toContain('after activation');
  const select=host.querySelector('select')!;
  await act(async()=>{select.value='website';select.dispatchEvent(new Event('change',{bubbles:true}));});
  const reason=host.querySelector('input')!;
  await act(async()=>{Object.getOwnPropertyDescriptor(HTMLInputElement.prototype,'value')!.set!.call(reason,'QA approved');reason.dispatchEvent(new Event('input',{bubbles:true}));});
  await act(async()=>host.querySelector<HTMLButtonElement>('button')!.click());
  expect(send).not.toHaveBeenCalled();
  expect(host.textContent).toContain('Confirm JW uses QA');
  send.mockResolvedValue({ok:true,json:async()=>({data:{channel_key:'website',inventory_balance_id:1,orders_created_after:'2026-09-14 01:00:00'}})});
  await act(async()=>Array.from(host.querySelectorAll('button')).find(button=>button.textContent==='Confirm activation')!.click());
  expect(send).toHaveBeenCalledTimes(1);
  expect(JSON.parse(send.mock.calls[0][1].body)).toEqual({channel_key:'website',balance_id:1,reason:'QA approved'});
  expect(host.textContent).toContain('Enabled for orders created after 2026-09-14');
 } finally {await act(async()=>root.unmount());vi.unstubAllGlobals();}
});
