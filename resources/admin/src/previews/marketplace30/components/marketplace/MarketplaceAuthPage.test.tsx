// @vitest-environment jsdom
import React, {act} from 'react';
import {createRoot} from 'react-dom/client';
import {renderToStaticMarkup} from 'react-dom/server';
import {expect,it,vi} from 'vitest';
import {PreviewContext} from '../../context/ContextBridge';
import {MarketplaceAuthPage} from './MarketplaceAuthPage';

it('does not describe official test credentials as completed seller OAuth',()=>{
 const value={connections:[{id:'3',platform:'lazada',shopName:'Test',shopId:'3',status:'connected',credentialSource:'official_test',testLeaseExpiresAt:'2026-09-08T10:00:00Z',tokenExpiresAt:'',lastSyncAt:''}],marketplaceOrders:[],marketplaceInventory:[],marketplaceStoreProducts:[],syncLogs:[],setCurrentSection:vi.fn(),currentSection:'marketplace-auth'};
 const html=renderToStaticMarkup(<PreviewContext.Provider value={value as unknown as React.ContextType<typeof PreviewContext>}><MarketplaceAuthPage operational/></PreviewContext.Provider>);
 expect(html).toContain('Seller OAuth not verified');
 expect(html).toContain('Local Test Access Validity');
 expect(html).not.toContain('Official authorization');
 expect(html).not.toContain('Authorized &amp; Active');
});

it('shows truthful operational availability without fake metrics or developer configuration',()=>{
 const value={connections:[{id:'1',platform:'shopee',shopName:'Verified',shopId:'1',status:'connected',tokenExpiresAt:'2026-09-06T14:00:00Z',lastSyncAt:''}],marketplaceOrders:[],marketplaceInventory:[],marketplaceStoreProducts:[],syncLogs:[],
   setCurrentSection:vi.fn(),currentSection:'marketplace-auth'};
 const html=renderToStaticMarkup(<PreviewContext.Provider value={value as unknown as React.ContextType<typeof PreviewContext>}>
   <MarketplaceAuthPage operational oauthAvailability={{shopee:true}}/>
 </PreviewContext.Provider>);
 expect(html).not.toContain('96.8%');
 expect(html).not.toContain('200 OK');
 expect(html).not.toContain('api.yourdomain.com');
 expect(html).not.toContain('OAuth 2.0 Handshake Active');
 expect(html).not.toContain('whsec_auto_generated');
 expect(html).not.toContain('days remaining');
 expect(html).toContain('Not enabled');
});

it('shows the connected store account mode and publishing permission',()=>{
 const value={connections:[{id:'3',platform:'lazada',shopName:'Lazada MY',shopId:'300927968304',status:'connected',accountMode:'production',capabilities:{catalogRead:true,productPublish:false,orderReceive:false,inventoryUpdate:false,fulfillmentWrite:false},tokenExpiresAt:'2026-09-06T14:00:00Z',lastSyncAt:''}],marketplaceOrders:[],marketplaceInventory:[],marketplaceStoreProducts:[],syncLogs:[],setCurrentSection:vi.fn(),currentSection:'marketplace-auth'};
 const html=renderToStaticMarkup(<PreviewContext.Provider value={value as unknown as React.ContextType<typeof PreviewContext>}><MarketplaceAuthPage operational/></PreviewContext.Provider>);
 expect(html).toContain('Production account');
 expect(html).toContain('Publishing disabled');
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

it('offers test-token renewal instead of seller OAuth for a disconnected official Lazada test store',()=>{
 const value={connections:[{id:'4',platform:'lazada',shopName:'[Official Test] Zappyworks Test',shopId:'300163725140',status:'reauthorization_required',credentialSource:'official_test',accountMode:'test',testLeaseExpiresAt:'',tokenExpiresAt:'',lastSyncAt:''}],marketplaceOrders:[],marketplaceInventory:[],marketplaceStoreProducts:[],syncLogs:[],setCurrentSection:vi.fn(),currentSection:'marketplace-auth'};
 const html=renderToStaticMarkup(<PreviewContext.Provider value={value as unknown as React.ContextType<typeof PreviewContext>}><MarketplaceAuthPage operational/></PreviewContext.Provider>);
 expect(html).toContain('Renew Test Access');
 expect(html).not.toContain('Re-authorize &amp; Resume Sync');
});

it('lets a super admin manage product publishing on a connected production store',async()=>{
 vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT',true);
 const update=vi.fn().mockResolvedValue(undefined);
 const container=document.createElement('div');const root=createRoot(container);
 const value={connections:[{id:'3',platform:'lazada',shopName:'Lazada MY',shopId:'300927968304',status:'connected',accountMode:'production',capabilities:{catalogRead:true,productPublish:false,orderReceive:false,inventoryUpdate:false,fulfillmentWrite:false},tokenExpiresAt:'',lastSyncAt:''}],marketplaceOrders:[],marketplaceInventory:[],marketplaceStoreProducts:[],syncLogs:[],setCurrentSection:vi.fn(),currentSection:'marketplace-auth',addToast:vi.fn()};
 await act(async()=>root.render(<PreviewContext.Provider value={value as unknown as React.ContextType<typeof PreviewContext>}><MarketplaceAuthPage operational canManagePublishing onSetProductPublishing={update}/></PreviewContext.Provider>));
 const manage=Array.from(container.querySelectorAll('button')).find(button=>button.textContent?.includes('Enable Publishing'))!;
 expect(manage).toBeTruthy();
 await act(async()=>manage.click());
 expect(container.textContent).toContain('Enable Product Publishing');
 const reason=container.querySelector('textarea')!;
 await act(async()=>{(reason as HTMLTextAreaElement&{_valueTracker?:{setValue(value:string):void}})._valueTracker?.setValue('');reason.value='Merchant approved production publishing';reason.dispatchEvent(new Event('input',{bubbles:true}));});
 await act(async()=>container.querySelector('form')!.dispatchEvent(new Event('submit',{bubbles:true,cancelable:true})));
 expect(update).toHaveBeenCalledWith('3',true,'Merchant approved production publishing');
 await act(async()=>root.unmount());vi.unstubAllGlobals();
});

it('does not expose publishing management to ordinary staff',()=>{
 const value={connections:[{id:'3',platform:'lazada',shopName:'Lazada MY',shopId:'300927968304',status:'connected',accountMode:'production',capabilities:{catalogRead:true,productPublish:false,orderReceive:false,inventoryUpdate:false,fulfillmentWrite:false},tokenExpiresAt:'',lastSyncAt:''}],marketplaceOrders:[],marketplaceInventory:[],marketplaceStoreProducts:[],syncLogs:[],setCurrentSection:vi.fn(),currentSection:'marketplace-auth'};
 const html=renderToStaticMarkup(<PreviewContext.Provider value={value as unknown as React.ContextType<typeof PreviewContext>}><MarketplaceAuthPage operational/></PreviewContext.Provider>);
 expect(html).not.toContain('Enable Publishing');
});
