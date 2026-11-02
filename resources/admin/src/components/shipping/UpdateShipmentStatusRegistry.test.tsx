// @vitest-environment jsdom
import React, {act} from 'react';
import {createRoot} from 'react-dom/client';
import {afterEach,it,expect,vi} from 'vitest';
import {UpdateShipmentStatusModal} from './UpdateShipmentStatusModal';
import type {ShipmentParcel} from '../../types/shipping';
(globalThis as any).IS_REACT_ACT_ENVIRONMENT = true;
afterEach(()=>{vi.unstubAllGlobals();document.body.innerHTML='';});
it('uses database labels for permitted shipment transitions without a shipment mutation',async()=>{
 const fetch=vi.fn().mockResolvedValue({ok:true,json:async()=>({data:[{code:'delivered',name:'Received by customer',description:'Confirmed delivery.'}]})});vi.stubGlobal('fetch',fetch);
 const onUpdateStatus=vi.fn();const props={isOpen:true,parcel:{id:'saved-1',status:'in_transit',source:'manual',orderNumber:'JW-1',customerName:'Customer'} as ShipmentParcel,onClose:vi.fn(),onUpdateStatus};
 const host=document.createElement('div');document.body.append(host);const root=createRoot(host);
 await act(async()=>{root.render(<UpdateShipmentStatusModal {...props}/>);});
 expect(document.body.textContent).toContain('Received by customer');expect(onUpdateStatus).not.toHaveBeenCalled();
 await act(async()=>{root.render(<UpdateShipmentStatusModal {...props} isOpen={false}/>);});
 await act(async()=>{root.render(<UpdateShipmentStatusModal {...props}/>);});
 expect(document.body.textContent).toContain('Received by customer');expect(onUpdateStatus).not.toHaveBeenCalled();
 await act(async()=>root.unmount());
});
