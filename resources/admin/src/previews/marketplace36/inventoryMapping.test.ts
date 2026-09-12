// @vitest-environment jsdom
import {expect,it,vi,afterEach} from 'vitest';
import {saveInventoryMapping,loadMappingCatalog} from './inventoryMapping';
afterEach(()=>{vi.unstubAllGlobals();document.head.innerHTML='';});
it('saves listing identity with session CSRF without Admin Host bootstrap',async()=>{
 document.head.innerHTML='<meta name="csrf-token" content="test-csrf">';
 const request=vi.fn().mockResolvedValue(new Response(JSON.stringify({saved:true})));vi.stubGlobal('fetch',request);
 await saveInventoryMapping({id:42,platform:'lazada',jw_variant_id:null} as any,102,51);
 expect(request.mock.calls[0][0]).toBe('/admin/api/marketplace/listings/42/mapping');
 expect(JSON.parse(request.mock.calls[0][1].body)).toEqual({productId:51,variantId:102,expectedVariantId:null});
 expect(request.mock.calls[0][1].headers['X-CSRF-TOKEN']).toBe('test-csrf');
});
it('rejects another mapped SKU before dispatch',async()=>{
 const request=vi.fn();vi.stubGlobal('fetch',request);
 await expect(saveInventoryMapping({id:42,platform:'lazada',jw_variant_id:7} as any,102,51)).rejects.toThrow('another JW SKU');
 expect(request).not.toHaveBeenCalled();
});
it('keeps identical seller SKUs in different stores as distinct choices',async()=>{
 vi.stubGlobal('fetch',vi.fn().mockResolvedValue(new Response(JSON.stringify({current_page:1,last_page:1,total:2,data:[
  {id:1,store_id:3,platform:'lazada',observed_data:{sku:'SAME'}},
  {id:2,store_id:4,platform:'lazada',observed_data:{sku:'SAME'}},
 ]}))));
 expect((await loadMappingCatalog()).map(row=>row.id)).toEqual([1,2]);
});
