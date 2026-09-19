// @vitest-environment jsdom
import React,{act} from 'react';
import {createRoot} from 'react-dom/client';
import {expect,it,vi} from 'vitest';
import {FetchStoreProductsConfirmModal38} from './FetchStoreProductsConfirmModal38';
it('keeps source confirmation content while excluding developer controls',async()=>{vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT',true);const host=document.createElement('div'),root=createRoot(host);await act(async()=>root.render(<FetchStoreProductsConfirmModal38 open connections={[]} onClose={vi.fn()} onConfirmStart={vi.fn()} connection={{id:'4',platform:'lazada',shopName:'[Official Test] Zappyworks Test',shopId:'300163725140',region:'MY',status:'connected',tokenExpiresAt:null,autoSyncStock:false,autoPullOrders:false}}/>));expect(host.textContent).toContain('What will be fetched');expect(host.textContent).not.toContain('Interactive State Tester:');await act(async()=>root.unmount());vi.unstubAllGlobals();});