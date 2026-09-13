import {afterEach,expect,it,vi} from 'vitest';
import {marketplaceRequest,MarketplaceRequestError} from './marketplaceData';
vi.mock('./adminBootstrap',()=>({readAdminBootstrap:()=>({csrfToken:'test-csrf'})}));
afterEach(()=>vi.unstubAllGlobals());

it('preserves recovery identity and no-retry guidance on uncertain writes',async()=>{
 vi.stubGlobal('fetch',vi.fn().mockResolvedValue(new Response(JSON.stringify({code:'publishing_result_uncertain',taskId:42,retryAllowed:false,message:'Check the saved task.'}),{status:409})));
 try{await marketplaceRequest('/publishing-tasks/42/dispatch','POST');throw new Error('Expected rejection');}
 catch(error){expect(error).toBeInstanceOf(MarketplaceRequestError);expect(error).toMatchObject({status:409,code:'publishing_result_uncertain',taskId:42,retryAllowed:false,message:'Check the saved task.'});}
 expect(fetch).toHaveBeenCalledTimes(1);
});

it('handles a non-JSON gateway failure without attempting a second write',async()=>{
 vi.stubGlobal('fetch',vi.fn().mockResolvedValue(new Response('<html>Bad gateway</html>',{status:502})));
 await expect(marketplaceRequest('/publishing-tasks/42/dispatch','POST')).rejects.toMatchObject({status:502,message:'Marketplace request failed. Check the saved operation status before retrying.'});
 expect(fetch).toHaveBeenCalledTimes(1);
});

it('does not accept malformed recovery identities or retry flags',async()=>{
 vi.stubGlobal('fetch',vi.fn().mockResolvedValue(new Response(JSON.stringify({taskId:'42',retryAllowed:'true',errors:{images:['Prepare images.']}}),{status:422})));
 await expect(marketplaceRequest('/images','POST')).rejects.toMatchObject({taskId:undefined,retryAllowed:undefined,message:'Prepare images.'});
});
