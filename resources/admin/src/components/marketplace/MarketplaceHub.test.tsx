// @vitest-environment jsdom
import React, {act} from 'react';
import {createRoot} from 'react-dom/client';
import {it,expect,vi} from 'vitest';
import {MarketplaceHub} from './MarketplaceHub';
const state=vi.hoisted(()=>({addToast:vi.fn(),syncAllMarketplaces:vi.fn(),setCurrentSection:vi.fn()}));
vi.mock('../../context/AdminContext',()=>({useAdmin:()=>state}));
it('reports unavailable synchronization without claiming or simulating a channel update',async()=>{
 (globalThis as typeof globalThis & {IS_REACT_ACT_ENVIRONMENT:boolean}).IS_REACT_ACT_ENVIRONMENT=true;
 const host=document.createElement('div');document.body.append(host);const root=createRoot(host);
 try{
  await act(async()=>root.render(<MarketplaceHub/>));
  const button=Array.from(host.querySelectorAll('button')).find(item=>item.textContent?.includes('Sync All Channels'))!;
  await act(async()=>button.click());
  expect(state.addToast).toHaveBeenCalledWith({type:'info',title:'Unavailable',message:'This operation is not enabled. No changes were made.'});
  expect(state.syncAllMarketplaces).not.toHaveBeenCalled();
  expect(host.textContent).not.toContain('Broadcasting...');
 }finally{await act(async()=>root.unmount());host.remove();}
});
