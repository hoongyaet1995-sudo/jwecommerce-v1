import {it, expect, vi} from 'vitest';
import {marketplaceOperations} from './marketplaceOperations';
import {importCatalogVariant} from './marketplaceVariantImport';

it('imports only a saved variant belonging to the selected catalog product', async()=>{
  const api=vi.spyOn(marketplaceOperations,'importVariant').mockResolvedValue({productId:51,variantId:92});
  const sources=[{id:'1:100',variants:[{id:'12'}]}];
  try {
    await expect(importCatalogVariant(false,sources,'1:100','12',{})).rejects.toThrow();
    await expect(importCatalogVariant(true,sources,'2:100','12',{})).rejects.toThrow();
    await expect(importCatalogVariant(true,sources,'1:100','13',{})).rejects.toThrow();
    expect(api).not.toHaveBeenCalled();
    await expect(importCatalogVariant(true,sources,'1:100','12',{skuOverrides:{'12':'JW-NEW'}}))
      .resolves.toEqual({productId:51,variantId:92});
    expect(api).toHaveBeenCalledWith('12',{newProduct:true,sku:'JW-NEW'});
    api.mockRejectedValueOnce(new Error('Server conflict'));
    await expect(importCatalogVariant(true,sources,'1:100','12',{})).rejects.toThrow('Server conflict');
  } finally {api.mockRestore();}
});
