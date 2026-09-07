import {describe, expect, it} from 'vitest';
import {variantImportReview} from './marketplaceVariantImportReview';

describe('ZIP32 reviewed variant import contract', () => {
  it('converts only the selected variant SKU and explicit destination', () => {
    expect(variantImportReview('variant-12', {zeroPhysicalStock:true, createDraftStatus:'draft',
      skuOverrides:{'variant-12':' JW-CODE '}})).toEqual({newProduct:true, sku:'JW-CODE'});
    expect(variantImportReview('variant-12', {targetProductId:'51'})).toEqual({productId:51});
  });
  it('rejects unsafe or unsupported source options instead of silently discarding them', () => {
    for (const options of [{zeroPhysicalStock:false}, {createDraftStatus:'active'}, {targetProductId:'51oops'},
      {targetProductId:'0'}, {targetProductId:'9007199254740992'},
      {skuOverrides:{other:'OTHER'}}, {skuOverrides:{'variant-12':' '}},
      {linkExistingVariantIds:{'variant-12':{productId:'51',variantId:'92'}}}]) {
      expect(()=>variantImportReview('variant-12', options as never)).toThrow();
    }
  });
});
