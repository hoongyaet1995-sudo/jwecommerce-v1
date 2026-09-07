import {it,expect,vi} from 'vitest';
import {marketplaceOperations} from './marketplaceOperations';
import {importReviewedCatalogProduct} from './marketplaceReviewedImport';

it('submits reviewed source product using persisted listing ids and rejects invalid review',async()=>{
  const api=vi.spyOn(marketplaceOperations,'importProduct').mockResolvedValue({productId:51,status:'draft'});
  const sources=[{id:'1:100',variants:[{id:'12'},{id:'13'}]}];
  try {
    await expect(importReviewedCatalogProduct(true,sources,'1:100',{skuOverrides:{'12':' NEW '}})).resolves.toEqual({productId:51,status:'draft'});
    expect(api).toHaveBeenCalledWith('12',{skuOverrides:{'12':'NEW'}});
    api.mockClear();
    for(const options of [{zeroPhysicalStock:false},{createDraftStatus:'active'},{targetProductId:'9'},
      {skuOverrides:{'14':'OTHER'}},{linkExistingVariantIds:{'14':{productId:'51',variantId:'92'}}}]) {
      await expect(importReviewedCatalogProduct(true,sources,'1:100',options as never)).rejects.toThrow();
    }
    await expect(importReviewedCatalogProduct(false,sources,'1:100',{})).rejects.toThrow();
    expect(api).not.toHaveBeenCalled();
  }finally{api.mockRestore();}
});

it('sends explicit existing links as numeric identities without creating duplicate SKUs',async()=>{
  const api=vi.spyOn(marketplaceOperations,'importProduct').mockResolvedValue({productId:52,status:'draft'});
  try {
    const sources=[{id:'1:100',variants:[{id:'12'},{id:'13'}]}];
    await importReviewedCatalogProduct(true,sources,'1:100',{linkExistingVariantIds:{'12':{productId:'51',variantId:'92'}},skuOverrides:{'13':' NEW '}});
    expect(api).toHaveBeenCalledWith('12',{skuOverrides:{'13':'NEW'},existingLinks:{'12':{productId:51,variantId:92}}});
    api.mockClear();
    await expect(importReviewedCatalogProduct(true,sources,'1:100',{linkExistingVariantIds:{'12':{productId:'51',variantId:'92'}},skuOverrides:{'12':'DUP'}})).rejects.toThrow();
    expect(api).not.toHaveBeenCalled();
  }finally{api.mockRestore();}
});
