import {useSyncExternalStore} from 'react';
export type ShippingView = 'overview'|'fulfillment'|'deliveries'|'settings'|'rates'|'couriers'|'tracking'|'routing'|'reconciliation';
let current: ShippingView = 'overview';
const listeners = new Set<()=>void>();
export function navigateShipping(view: ShippingView){current=view;listeners.forEach(listener=>listener());}
export function useShippingView(){return useSyncExternalStore(listener=>{listeners.add(listener);return()=>{listeners.delete(listener)}},()=>current,()=> 'overview' as ShippingView);}
