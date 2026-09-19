// @vitest-environment jsdom
import React,{act} from 'react';
import {createRoot} from 'react-dom/client';
import {expect,it,vi} from 'vitest';
import {CatalogDownload38Overlay} from './CatalogDownload38Overlay';
import type {StoreConnection} from '../../integration/marketplaceOperations';

const store:StoreConnection={id:'3',platform:'lazada',shopName:'[Official Test] Zappyworks Test',shopId:'300163725140',region:'MY',status:'connected',accountMode:'test',tokenExpiresAt:null,autoSyncStock:false,autoPullOrders:false};

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
