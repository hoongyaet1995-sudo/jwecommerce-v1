// @vitest-environment jsdom
import React,{act} from 'react';
import {createRoot} from 'react-dom/client';
import {expect,it,vi} from 'vitest';
import {StoreFulfillmentBindingModal} from './components/marketplace/StoreFulfillmentBindingModal';
it('shows an explicit empty choice and saves only a selected warehouse',async()=>{
 (globalThis as any).IS_REACT_ACT_ENVIRONMENT=true;
 const host=document.createElement('div'); const root=createRoot(host);const save=vi.fn().mockResolvedValue({success:true});
 try {
  await act(async()=>root.render(<StoreFulfillmentBindingModal isOpen binding={{id:'website',platform:'webstore',storeName:'JW',boundWarehouseId:''} as any} warehouses={[{id:'1',name:'Test',code:'QA',address:'',state:''} as any]} onClose={()=>{}} onSave={save}/>));
  const select=host.querySelector('select')!;
  expect(select.value).toBe('');
  expect(select.selectedOptions[0].textContent).toContain('Select');
  const enabled=host.querySelector<HTMLInputElement>('input[type="checkbox"]')!;
  expect(enabled).not.toBeNull();
  expect(enabled.checked).toBe(false);
  await act(async()=>enabled.click());
  await act(async()=>{select.value='1';select.dispatchEvent(new Event('change',{bubbles:true}));});
  await act(async()=>{host.querySelector('form')!.dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}));});
  expect(save).toHaveBeenCalledWith(expect.objectContaining({boundWarehouseId:'1',routeEnabled:true}));
 } finally {await act(async()=>root.unmount());}
});
