// @vitest-environment jsdom
import React, {act} from 'react';
import {createRoot} from 'react-dom/client';
import {renderToStaticMarkup} from 'react-dom/server';
import {expect,it,vi} from 'vitest';
import {PreviewContext} from '../../context/ContextBridge';
import {MarketplaceAuthPage} from './MarketplaceAuthPage';

it('shows truthful operational availability without fake metrics or developer configuration',()=>{
 const value={connections:[{id:'1',platform:'shopee',shopName:'Verified',shopId:'1',status:'connected',tokenExpiresAt:'',lastSyncAt:''}],marketplaceOrders:[],marketplaceInventory:[],marketplaceStoreProducts:[],syncLogs:[],
   setCurrentSection:vi.fn(),currentSection:'marketplace-auth'};
 const html=renderToStaticMarkup(<PreviewContext.Provider value={value as unknown as React.ContextType<typeof PreviewContext>}>
   <MarketplaceAuthPage operational oauthAvailability={{shopee:true}}/>
 </PreviewContext.Provider>);
 expect(html).not.toContain('96.8%');
 expect(html).not.toContain('200 OK');
 expect(html).not.toContain('api.yourdomain.com');
 expect(html).not.toContain('OAuth 2.0 Handshake Active');
 expect(html).not.toContain('whsec_auto_generated');
 expect(html).toContain('Not enabled');
});

it('opens the official callback result then clears it for a new connection',async()=>{
 vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT',true);
 const container=document.createElement('div');const root=createRoot(container);
 const value={connections:[],marketplaceOrders:[],marketplaceInventory:[],marketplaceStoreProducts:[],syncLogs:[],setCurrentSection:vi.fn(),currentSection:'marketplace-auth'};
 await act(async()=>root.render(<PreviewContext.Provider value={value as unknown as React.ContextType<typeof PreviewContext>}>
   <MarketplaceAuthPage operational oauthAvailability={{shopee:true}} authorizationResult={{status:'success',store:{id:'1',platform:'shopee',shopId:'227482706',shopName:'Verified Sandbox'}}}/>
 </PreviewContext.Provider>));
 expect(container.textContent).toContain('Store Connected Successfully!');
 expect(container.textContent).toContain('227482706');
 const complete=Array.from(container.querySelectorAll('button')).find(b=>b.textContent?.includes('Complete & Return'))!;
 await act(async()=>complete.click());
 const connect=Array.from(container.querySelectorAll('button')).find(b=>b.textContent?.trim()==='Connect Store')!;
 await act(async()=>connect.click());
 expect(container.textContent).not.toContain('Store Connected Successfully!');
 expect(container.textContent).toContain('Proceed to Official Authorization');
 await act(async()=>root.unmount());vi.unstubAllGlobals();
});
