// @vitest-environment jsdom
import React,{act} from 'react';
import {createRoot} from 'react-dom/client';
import {expect,it,vi} from 'vitest';
import {ShipmentPreparationPage} from './ShipmentPreparationPage';
import {INITIAL_SHIPMENT_PARCELS} from '../../data/shippingData';
globalThis.IS_REACT_ACT_ENVIRONMENT=true;
it.each(['success','failure','expired'])('quotes in Details only and preserves booking boundary (%s)',async(outcome)=>{
 const failed=outcome==='failure';
 const host=document.createElement('div');document.body.append(host);const root=createRoot(host);
 const quote=vi.fn().mockResolvedValue({id:'q1',orderId:'o1',expiresAt:new Date(Date.now()+(outcome==='expired'?-60000:60000)).toISOString(),services:[{serviceCode:'s1',serviceName:'Delivery',courierName:'Test courier',currency:'MYR',amount:8}]});
 const book=failed?vi.fn().mockRejectedValue(new Error('Result unknown')):vi.fn().mockResolvedValue({trackingNumber:'TRACK-1'});
 await act(async()=>root.render(<ShipmentPreparationPage parcel={{...INITIAL_SHIPMENT_PARCELS[0],totalWeightKg:1,dimensionsCm:{length:10,width:10,height:10}}} connected onClose={()=>{}} onQuote={quote} onBook={book} onExternal={vi.fn()} onManual={vi.fn()}/>));
 const click=async(text:string)=>act(async()=>{const button=Array.from(host.querySelectorAll('button')).find(b=>b.textContent===text||(text==='EasyParcel'&&b.textContent?.includes('Book with EasyParcel')));expect(button).toBeTruthy();button!.click()});
 await click('EasyParcel');expect(quote).not.toHaveBeenCalled();await click('Continue');expect(host.textContent).toContain('Package details');expect(book).not.toHaveBeenCalled();
 await click('Compare rates');expect(quote).toHaveBeenCalledTimes(1);await click('Test courier · Delivery · MYR 8.00');await click('Review details');if(outcome==='expired'){expect(host.textContent).toContain('current delivery quote');expect(book).not.toHaveBeenCalled();await act(async()=>root.unmount());host.remove();return}expect(book).not.toHaveBeenCalled();await click('Confirm & book');expect(book).toHaveBeenCalledTimes(1);if(failed){expect(host.textContent).toContain('check the saved result');const confirm=Array.from(host.querySelectorAll('button')).find(b=>b.textContent==='Confirm & book')!;expect(confirm.disabled).toBe(true);await act(async()=>confirm.click());expect(book).toHaveBeenCalledTimes(1)}else{expect(host.textContent).toContain('TRACK-1')};await act(async()=>root.unmount());host.remove();
});
it('opens account setup without requesting a quote when disconnected',async()=>{
 const host=document.createElement('div');document.body.append(host);const root=createRoot(host);const connect=vi.fn(),quote=vi.fn();
 await act(async()=>root.render(<ShipmentPreparationPage parcel={INITIAL_SHIPMENT_PARCELS[0]} connected={false} onConnect={connect} onClose={()=>{}} onQuote={quote} onBook={vi.fn()} onExternal={vi.fn()} onManual={vi.fn()}/>));
 const card=Array.from(host.querySelectorAll('button')).find(b=>b.textContent?.includes('Book with EasyParcel'))!;
 await act(async()=>card.click());expect(connect).toHaveBeenCalledTimes(1);expect(card.getAttribute('aria-pressed')).toBe('false');expect(quote).not.toHaveBeenCalled();
 expect(Array.from(host.querySelectorAll('button')).find(b=>b.textContent==='Continue')!.disabled).toBe(true);
 await act(async()=>root.unmount());host.remove();
});
