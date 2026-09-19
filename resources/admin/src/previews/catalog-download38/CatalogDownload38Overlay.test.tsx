// @vitest-environment jsdom
import React,{act} from 'react';
import {createRoot} from 'react-dom/client';
import {expect,it,vi} from 'vitest';
import {CatalogDownload38Overlay,catalogTaskFor} from './CatalogDownload38Overlay';
import type {StoreConnection} from '../../integration/marketplaceOperations';

const store:StoreConnection={id:'3',platform:'lazada',shopName:'[Official Test] Zappyworks Test',shopId:'300163725140',region:'MY',status:'connected',accountMode:'test',tokenExpiresAt:null,autoSyncStock:false,autoPullOrders:false};

it('selects the download store inside the modal, starts only on confirmation, and reopens progress without resubmitting',async()=>{
 vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT',true);
 localStorage.setItem('jw.catalog-download.confirmation.3','1');
 const other={...store,id:'4',shopName:'Other test store'};
 const submit=vi.fn();
 const host=document.createElement('div'),root=createRoot(host);
 function Harness(){
  const [selected,setSelected]=React.useState(store),[requested,setRequested]=React.useState(true),[run,setRun]=React.useState<any>(null);
  return <CatalogDownload38Overlay stores={[store,other]} selectedStore={selected} run={run} requestFetch={requested} onRequestConsumed={()=>setRequested(false)} onSelectStore={id=>setSelected(id===store.id?store:other)} onStart={async()=>{submit(selected.id);setRun({id:22,store_id:Number(selected.id),status:'running',pages_completed:2,items_processed:100,total_products:200});}} onRetry={vi.fn()} onCheck={vi.fn()}/>;
 }
 const click=async(text:string)=>{const button=[...host.querySelectorAll('button')].find(button=>button.textContent?.includes(text));expect(button).toBeTruthy();await act(async()=>button!.click());};
 await act(async()=>root.render(<Harness/>));
 expect(host.textContent).toContain('Start Background Download');
 await click('Change Store');await click('Other test store');
 expect(submit).not.toHaveBeenCalled();
 await click('Start Background Download');
 expect(submit).toHaveBeenCalledExactlyOnceWith('4');
 expect(host.textContent).toContain('Background Catalog Download');
 await click('Continue working');
 expect(host.querySelector('#catalog-download-drawer')).toBeNull();
 await click('Catalog download: 50%');
 expect(host.querySelector('#catalog-download-drawer')).not.toBeNull();
 expect(submit).toHaveBeenCalledTimes(1);
 expect(host.textContent).not.toContain('Interactive State Tester');
 await act(async()=>root.unmount());vi.unstubAllGlobals();
});

it('never relabels another store task or creates a phantom task',()=>{
 expect(catalogTaskFor(store,{} as any)).toBeNull();
 expect(catalogTaskFor(store,{id:1,store_id:4,status:'running'} as any)).toBeNull();
});

it('renders completed progress without invented image or unmapped counts',async()=>{
 vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT',true);
 const host=document.createElement('div'),root=createRoot(host);
 await act(async()=>root.render(<CatalogDownload38Overlay stores={[store]} selectedStore={store} run={{id:91,store_id:3,status:'succeeded',pages_completed:4,items_processed:40,total_products:40,error_code:null,finished_at:null}} requestFetch={false} onRequestConsumed={vi.fn()} requestOpen onOpenRequestConsumed={vi.fn()} onStart={vi.fn()} onRetry={vi.fn()} onCheck={vi.fn()} onSelectStore={vi.fn()}/>));
 expect(host.textContent).toContain('Review Unmapped Products');
 expect(host.textContent).not.toContain('Review Unmapped Products (0)');
 await act(async()=>root.unmount());vi.unstubAllGlobals();
});

it('requires an explicit source-faithful confirmation before starting a catalog task',async()=>{
  vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT',true);
  localStorage.clear();
  const onStart=vi.fn().mockResolvedValue(undefined),onConsumed=vi.fn();
  const host=document.createElement('div'),root=createRoot(host);
  await act(async()=>root.render(<CatalogDownload38Overlay stores={[store]} selectedStore={store} run={null} requestFetch onRequestConsumed={onConsumed} onStart={onStart} onRetry={vi.fn()} onCheck={vi.fn()} onSelectStore={vi.fn()}/>));
  expect(host.textContent).toContain('Fetch Store Products');
  expect(onStart).not.toHaveBeenCalled();
  const start=[...host.querySelectorAll('button')].find(button=>button.textContent?.includes('Start Background Download'));
  expect(start).toBeTruthy();
  await act(async()=>start?.dispatchEvent(new MouseEvent('click',{bubbles:true})));
  expect(onStart).toHaveBeenCalledTimes(1);
  expect(onConsumed).toHaveBeenCalledTimes(1);
  await act(async()=>root.unmount());
  vi.unstubAllGlobals();
});

it('opens the source progress drawer when staff requests the active task status',async()=>{
  vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT',true);
  localStorage.clear();
  const consumed=vi.fn();
  const host=document.createElement('div'),root=createRoot(host);
  await act(async()=>root.render(<CatalogDownload38Overlay stores={[store]} selectedStore={store} run={{id:91,store_id:3,status:'running',pages_completed:4,items_processed:40,variants_processed:184,total_products:1398,error_code:null,finished_at:null}} requestFetch={false} onRequestConsumed={vi.fn()} requestOpen onOpenRequestConsumed={consumed} onStart={vi.fn()} onRetry={vi.fn()} onCheck={vi.fn()} onSelectStore={vi.fn()}/>));
  expect(host.textContent).toContain('Background Catalog Download');
  expect(consumed).toHaveBeenCalledTimes(1);
  await act(async()=>root.unmount());
  vi.unstubAllGlobals();
});
