import {afterEach,expect,it,vi} from 'vitest';
import {marketplaceOperations} from './marketplaceOperations';
import {importCatalogProducts} from './marketplaceImport';

afterEach(()=>vi.restoreAllMocks());
it('imports each selected product once using its saved listing and retains individual failures',async()=>{
 const api=vi.spyOn(marketplaceOperations,'importProduct')
  .mockResolvedValueOnce({productId:11,status:'draft'}).mockRejectedValueOnce(new Error('private backend detail'));
 const result=await importCatalogProducts(['1:100','1:100','1:200'],[
  {id:'1:100',listingId:'7'},{id:'1:200',listingId:'8'},
 ]);
 expect(api.mock.calls).toEqual([['7'],['8']]);
 expect(result).toEqual([{id:'1:100',ok:true,productId:11,status:'draft'},{id:'1:200',ok:false}]);
});
it('does not guess a listing for a missing selection or report malformed success',async()=>{
 const api=vi.spyOn(marketplaceOperations,'importProduct').mockResolvedValue({productId:0,status:'draft'});
 expect(await importCatalogProducts(['missing','1:100'],[{id:'1:100',listingId:'7'}]))
  .toEqual([{id:'missing',ok:false},{id:'1:100',ok:false}]);
 expect(api.mock.calls).toEqual([['7']]);
});
