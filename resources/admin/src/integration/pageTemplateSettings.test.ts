// @vitest-environment jsdom
import {afterEach,expect,it,vi} from 'vitest';
import {loadPageTemplateSettings} from './pageTemplateSettings';
const bootstrap:any={commerceSettingsUrl:'/admin/api/commerce-settings'};
const profile={shopName:'Saved shop',tagline:'Saved tagline',currency:'MYR'};
const commerce={currencies:[{code:'MYR',isEnabled:true,isDefault:true}],shippingRates:[{id:'saved-rate'}]};
const response=(data:any,status=200)=>({ok:status<400,status,json:async()=>({data})}) as Response;
afterEach(()=>vi.unstubAllGlobals());
it('reports each failed persisted source and only reads again on an explicit retry',async()=>{
 let failed=true;
 const fetcher=vi.fn(async(url:string)=>url.includes('shop-branding')?response(profile,failed?500:200):response(commerce));vi.stubGlobal('fetch',fetcher);
 const first=await loadPageTemplateSettings(bootstrap);expect(first.error).toContain('Shop profile');expect(first.error).toContain('500');expect(first.context).toBeUndefined();expect(fetcher).toHaveBeenCalledTimes(2);
 failed=false;const second=await loadPageTemplateSettings(bootstrap);expect(second.error).toBeUndefined();expect(second.context).toEqual({shopName:'Saved shop',tagline:'Saved tagline',currency:'MYR',shippingRates:commerce.shippingRates});expect(fetcher).toHaveBeenCalledTimes(4);
 for(const [,options]of fetcher.mock.calls as any){expect(options.method).toBeUndefined();expect(options.credentials).toBe('same-origin');}
});
it.each([401,403,419,503])('retains safe HTTP diagnostics for %i without exposing response bodies',async status=>{
 vi.stubGlobal('fetch',vi.fn(async()=>({...response(null,status),json:async()=>{throw Error('PRIVATE PASSWORD SQL');}})));
 const result=await loadPageTemplateSettings(bootstrap);expect(result.error).toContain(String(status));expect(result.error).toContain('Shop profile');expect(result.error).toContain('Commerce settings');expect(result.error).not.toContain('PRIVATE');expect(result.context).toBeUndefined();
});
it('classifies network and malformed responses without substituting demo settings',async()=>{
 vi.stubGlobal('fetch',vi.fn(async(url:string)=>{if(url.includes('shop-branding'))throw Error('Network secret');return response({});}));
 const result=await loadPageTemplateSettings(bootstrap);expect(result.error).toContain('could not be reached');expect(result.error).toContain('unexpected response');expect(result.error).not.toContain('secret');expect(result.context).toBeUndefined();
});
