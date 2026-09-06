// @vitest-environment jsdom
import React,{act} from 'react';
import {createRoot} from 'react-dom/client';
import {expect,it,vi} from 'vitest';
import {PreviewContext} from '../../context/ContextBridge';
import {MarketplaceOMSPage} from './MarketplaceOMSPage';
it('does not expose simulator or claim an AWB was printed in operational mode',async()=>{
 vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT',true);
 const container=document.createElement('div');const root=createRoot(container);
 const context={marketplaceOrders:[{id:'1',channel:'shopee',channelStatus:'READY_TO_SHIP',orderNumber:'REAL-ID',channelOrderId:'REAL-ID',items:[],totalAmount:10,currency:'MYR',customerName:'',airwayBillGenerated:false}],connections:[],marketplaceInventory:[],marketplaceStoreProducts:[],syncLogs:[]};
 try{await act(async()=>root.render(<PreviewContext.Provider value={context as unknown as React.ContextType<typeof PreviewContext>}><MarketplaceOMSPage operational/></PreviewContext.Provider>));
 expect(container.textContent).not.toContain('Simulate Inbound Order');
 expect(container.textContent).not.toContain('AWB Printed');
 expect(container.querySelector<HTMLButtonElement>('[title="Generate Official Airway Bill (AWB)"]')!.disabled).toBe(true);
 }finally{await act(async()=>root.unmount());vi.unstubAllGlobals();}
});
