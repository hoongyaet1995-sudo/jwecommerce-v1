// @vitest-environment jsdom
import {it,expect,vi,afterEach} from 'vitest';import {loadShopBranding} from './shopBrandingData';import {fetchCommerceSettings} from './commerceSettingsData';
afterEach(()=>vi.unstubAllGlobals());
it('coalesces concurrent settings reads but keeps settled reads fresh',async()=>{let resolve!:()=>void;const wait=new Promise<void>(r=>resolve=r);const fetch=vi.fn(async()=>{await wait;return new Response(JSON.stringify({data:{shopName:'Keep brand',shippingRates:[],currencies:[]}}),{status:200})});vi.stubGlobal('fetch',fetch);const bootstrap={commerceSettingsUrl:'/admin/api/commerce-settings'} as any;const a=loadShopBranding(),b=loadShopBranding(),c=fetchCommerceSettings(bootstrap),d=fetchCommerceSettings(bootstrap);const started=fetch.mock.calls.length;resolve();await Promise.all([a,b,c,d]);expect(started).toBe(2);await loadShopBranding();await fetchCommerceSettings(bootstrap);expect(fetch).toHaveBeenCalledTimes(4);});
it('failed settings reads allow an explicit fresh retry',async()=>{const fetch=vi.fn().mockResolvedValueOnce(new Response('{}',{status:503})).mockResolvedValueOnce(new Response('{"data":{"shopName":"Recovered"}}',{status:200}));vi.stubGlobal('fetch',fetch);await expect(loadShopBranding()).rejects.toThrow();await expect(loadShopBranding()).resolves.toEqual({shopName:'Recovered'});expect(fetch).toHaveBeenCalledTimes(2);});

it('invalidation isolates old reads and does not erase a newer in-flight request',async()=>{
 const {inFlightSettingsRead,invalidateSettingsRead}=await import('./inFlightSettingsRead');
 let finishOld!:(v:string)=>void,finishNew!:(v:string)=>void;
 const old=inFlightSettingsRead('race',()=>new Promise<string>(r=>finishOld=r));
 invalidateSettingsRead('race');
 const next=inFlightSettingsRead('race',()=>new Promise<string>(r=>finishNew=r));
 finishOld('old');await old;
 const loader=vi.fn(async()=>'incorrect');
 expect(inFlightSettingsRead('race',loader)).toBe(next);
 expect(loader).not.toHaveBeenCalled();
 finishNew('new');expect(await next).toBe('new');
 expect(await inFlightSettingsRead('race',async()=>'fresh')).toBe('fresh');
 expect(await inFlightSettingsRead('other-store',async()=>'separate')).toBe('separate');
});
