// @vitest-environment jsdom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { InventorySettingsModal } from './InventorySettingsModal';
import { storeInventoryApi } from '../../../../../integration/storeInventory';

let host:HTMLDivElement;
let root:ReturnType<typeof createRoot>;
beforeEach(()=>{(globalThis as any).IS_REACT_ACT_ENVIRONMENT=true;host=document.createElement('div');document.body.append(host);root=createRoot(host);});
afterEach(async()=>{await act(async()=>root.unmount());host.remove();vi.restoreAllMocks();});

it('shows verified Lazada warehouses inline and changes one takeover marker',async()=>{
  const facilities=vi.spyOn(storeInventoryApi,'facilities').mockResolvedValue({status:'verified',topology:'multi_warehouse',facilities:[
    {id:11,name:'Warehouse 1',provider_id:'WH-1',shared_pool_takeover_enabled:true},
    {id:12,name:'Warehouse 2',provider_id:'WH-2',shared_pool_takeover_enabled:false},
  ]});
  const toggle=vi.spyOn(storeInventoryApi,'sharedPoolFacilityTakeover').mockResolvedValue({shared_pool_takeover_enabled:true});
  const store={id:'7',platform:'lazada',name:'Lazada Test',nickname:'Lazada Test',region:'MY',connectionState:'connected',isManagedByJw:true,readStatus:'ok',syncStatus:'pending',platformDisplayName:'Lazada'} as any;
  await act(async()=>root.render(<InventorySettingsModal isOpen currentMode="shared" stores={[store]} inventory={[]}
    onClose={()=>{}} onSwitchMode={async()=>({success:true})} onToggleStoreManagement={()=>{}}/>));
  const tab=[...host.querySelectorAll('button')].find(button=>button.textContent?.includes('Store Inventory Management'))!;
  await act(async()=>tab.click());
  await act(async()=>{await Promise.resolve();await Promise.resolve();});
  expect(facilities).toHaveBeenCalledWith('7');
  expect(host.textContent).toContain('Warehouse 1');
  expect(host.textContent).toContain('Warehouse 2');
  const warehouse=host.querySelectorAll('div.rounded-lg.border.border-neutral-200.bg-white\\/40')[1];
  const button=[...warehouse.querySelectorAll('button')].find(node=>node.textContent==='Takeover')!;
  await act(async()=>{button.click();await Promise.resolve();});
  expect(toggle).toHaveBeenCalledWith('7',12,true);
  expect(host.textContent).toContain('Warehouses · 2 of 2 selected');
});
