import {useCallback,useSyncExternalStore} from 'react';
export type PricingBilling='monthly'|'yearly';
type Store={value:PricingBilling;listeners:Set<()=>void>};
const stores=new Map<string,Store>();
function storeFor(key:string,initial:PricingBilling){let store=stores.get(key);if(!store){store={value:initial,listeners:new Set()};stores.set(key,store);}return store;}
export function pricingBillingKey(pageKey:string,sourceId:string,initial:PricingBilling){return JSON.stringify([pageKey,sourceId,initial]);}
export function publishPricingBilling(key:string,value:PricingBilling,initial:PricingBilling='monthly'){const store=storeFor(key,initial);store.value=value;for(const listener of store.listeners)listener();}
export function readPricingBilling(key:string,initial:PricingBilling='monthly'){return storeFor(key,initial).value;}
/** Same page and source share visitor state. Authored default is separately saved in Content. */
export function usePricingBilling(pageKey:string,sourceId:string,initial:PricingBilling){const key=pricingBillingKey(pageKey,sourceId,initial);const subscribe=useCallback((listener:()=>void)=>{const store=storeFor(key,initial);store.listeners.add(listener);return()=>{store.listeners.delete(listener);if(!store.listeners.size)stores.delete(key);};},[key,initial]);const value=useSyncExternalStore(subscribe,()=>readPricingBilling(key,initial),()=>initial);return [value,(next:PricingBilling)=>publishPricingBilling(key,next,initial)] as const;}
