// @vitest-environment jsdom
import React,{act} from 'react';
import {createRoot} from 'react-dom/client';
import {afterEach,expect,it,vi} from 'vitest';
import {StoreStockTransfersTab} from './StoreStockTransfersTab';

vi.mock('./loadFacilityView',()=>({loadFacilityView:async()=>({
  storeConfigs:[
    {storeId:'website',storeName:'JW Storefront',storeNickname:'JW Storefront',platform:'webstore',
      isSkuMapped:true,warehouseMode:'no_warehouse',warehouseSyncStatus:'synced',warehouses:[]},
    {storeId:'4',storeName:'Lazada Test',storeNickname:'Lazada Test',platform:'lazada',
      isSkuMapped:true,warehouseMode:'multi_warehouse',warehouseSyncStatus:'synced',
      warehouses:[{id:'5',storeId:'4',name:'my',status:'active',codeValue:'WH-5'}]},
  ],
  warehouseBalances:{'0189618':{
    'direct:website':{physicalStock:10,reservedStock:0,availableStock:10,isCounted:true},
    '5':{physicalStock:20,reservedStock:0,availableStock:20,isCounted:true},
  }},
})}));

afterEach(()=>{document.body.innerHTML='';vi.restoreAllMocks()});

it('checks a completed transfer without repeating its ledger mutation',async()=>{
  (globalThis as any).IS_REACT_ACT_ENVIRONMENT=true;
  const host=document.createElement('div');document.body.append(host);
  const root=createRoot(host);
  const onExecuteTransfer=vi.fn().mockResolvedValue({
    id:'review-1',transferNumber:'review-1',timestamp:new Date().toISOString(),masterSku:'0189618',
    productName:'Bushing',variantName:'',sourceStoreId:'4',sourceWarehouseId:'5',
    sourceStoreName:'Lazada Test',sourcePlatform:'lazada',destinationStoreId:'website',
    destinationWarehouseId:'direct:website',destinationStoreName:'JW Storefront',
    destinationPlatform:'webstore',quantity:5,operator:'Admin',reason:'Rebalance',
    ledgerStatus:'succeeded',sourcePlatformSyncStatus:'pending',destinationPlatformSyncStatus:'succeeded',
  });
  const onCheckTransferStatus=vi.fn(async record=>({...record,sourcePlatformSyncStatus:'succeeded'}));
  await act(async()=>root.render(<StoreStockTransfersTab
    initialSku="0189618" initialSourceStoreId="4" initialSourceWarehouseId="5"
    inventory={[{id:'1',variantId:120,masterSku:'0189618',productName:'Bushing',variantName:''} as any]}
    stores={[{id:'4',name:'Lazada Test',platform:'lazada',platformDisplayName:'Lazada'} as any,
      {id:'website',name:'JW Storefront',platform:'webstore',platformDisplayName:'JW Storefront'} as any]}
    storeBalances={{}} transfersList={[]} onExecuteTransfer={onExecuteTransfer}
    onCheckTransferStatus={onCheckTransferStatus} onRetryPlatformSync={vi.fn()} />));
  await act(async()=>{await Promise.resolve();await Promise.resolve()});
  const execute=[...host.querySelectorAll('button')].find(button=>button.textContent?.includes('Execute Stock Transfer'))!;
  expect(execute).toBeDefined();
  expect(execute.disabled).toBe(false);
  await act(async()=>execute.click());
  expect(host.textContent).toContain('Store Transfer Execution Pipeline');
  expect(host.textContent).toContain('Saved in JW Storefront.');
  const check=[...host.querySelectorAll('button')].find(button=>button.textContent?.trim()==='Check Status')!;
  expect(check).toBeDefined();
  await act(async()=>check.click());
  expect(onCheckTransferStatus).toHaveBeenCalledTimes(1);
  expect(onCheckTransferStatus).toHaveBeenCalledWith(expect.anything(),true);
  expect(onExecuteTransfer).toHaveBeenCalledTimes(1);
  expect(host.textContent).toContain('Provider stock confirmed.');
  await act(async()=>root.unmount());
});

it('shows an uncounted JW location as Not counted and blocks transfer',async()=>{
  (globalThis as any).IS_REACT_ACT_ENVIRONMENT=true;
  const host=document.createElement('div');document.body.append(host);
  const root=createRoot(host);
  await act(async()=>root.render(<StoreStockTransfersTab
    initialSku="UNC" initialSourceStoreId="website"
    inventory={[{id:'2',variantId:121,masterSku:'UNC',productName:'Uncounted',variantName:'',singleCurrentStock:true} as any]}
    stores={[{id:'website',name:'JW Storefront',platform:'webstore',platformDisplayName:'JW Storefront'} as any,
      {id:'4',name:'Lazada Test',platform:'lazada',platformDisplayName:'Lazada'} as any]}
    storeBalances={{UNC:{website:{physicalStock:0,reservedStock:0,availableStock:0,isCounted:false}}} as any}
    transfersList={[]} onExecuteTransfer={vi.fn()} />));
  await act(async()=>{await Promise.resolve();await Promise.resolve()});
  expect(host.textContent).toContain('Not counted');
  const execute=[...host.querySelectorAll('button')].find(button=>button.textContent?.includes('Execute Stock Transfer'))!;
  expect(execute.disabled).toBe(true);
  await act(async()=>root.unmount());
});

it('automatically refreshes pending transfer steps without provider verification or page reload',async()=>{
  (globalThis as any).IS_REACT_ACT_ENVIRONMENT=true;
  const host=document.createElement('div');document.body.append(host);
  const root=createRoot(host);
  const onExecuteTransfer=vi.fn().mockResolvedValue({
    id:'review-auto',transferNumber:'review-auto',timestamp:new Date().toISOString(),masterSku:'0189618',
    productName:'Bushing',variantName:'',sourceStoreId:'4',sourceWarehouseId:'5',
    sourceStoreName:'Lazada Test',sourcePlatform:'lazada',destinationStoreId:'website',
    destinationWarehouseId:'direct:website',destinationStoreName:'JW Storefront',
    destinationPlatform:'webstore',quantity:1,operator:'Admin',reason:'Rebalance',
    ledgerStatus:'succeeded',sourcePlatformSyncStatus:'pending',destinationPlatformSyncStatus:'succeeded',
  });
  const onCheckTransferStatus=vi.fn(async(record,verifyProvider)=>({...record,sourcePlatformSyncStatus:'succeeded'}));
  await act(async()=>root.render(<StoreStockTransfersTab
    initialSku="0189618" initialSourceStoreId="4" initialSourceWarehouseId="5"
    inventory={[{id:'1',variantId:120,masterSku:'0189618',productName:'Bushing',variantName:'',singleCurrentStock:true} as any]}
    stores={[{id:'4',name:'Lazada Test',platform:'lazada',platformDisplayName:'Lazada'} as any,
      {id:'website',name:'JW Storefront',platform:'webstore',platformDisplayName:'JW Storefront'} as any]}
    storeBalances={{}} transfersList={[]} onExecuteTransfer={onExecuteTransfer}
    onCheckTransferStatus={onCheckTransferStatus} onRetryPlatformSync={vi.fn()} />));
  await act(async()=>{await Promise.resolve();await Promise.resolve()});
  vi.useFakeTimers();
  try {
    const execute=[...host.querySelectorAll('button')].find(button=>button.textContent?.includes('Execute Stock Transfer'))!;
    await act(async()=>execute.click());
    expect(host.textContent).toContain('Not confirmed');
    await act(async()=>{await vi.advanceTimersByTimeAsync(5000)});
    expect(onCheckTransferStatus).toHaveBeenCalledTimes(1);
    expect(onCheckTransferStatus).toHaveBeenCalledWith(expect.anything(),false);
    expect(host.textContent).toContain('Provider stock confirmed.');
    await act(async()=>{await vi.advanceTimersByTimeAsync(10000)});
    expect(onCheckTransferStatus).toHaveBeenCalledTimes(1);
  } finally {
    vi.useRealTimers();
    await act(async()=>root.unmount());
  }
});
