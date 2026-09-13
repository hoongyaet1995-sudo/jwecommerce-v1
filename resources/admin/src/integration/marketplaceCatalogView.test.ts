import {expect,it} from 'vitest';
import {catalogProductView} from './marketplaceCatalogView';
import type {CatalogProduct} from './marketplaceOperations';

it('preserves missing observations and persisted mapping identities without suggestions',()=>{
 const source={store_id:7,external_product_id:'100',platform:'shopee',shop_name:'Shop',first_listing_id:9,variants:[{
  id:9,store_id:7,external_product_id:'100',external_variant_id:'200',jw_product_id:3,jw_variant_id:4,jw_sku:'LOCAL',jw_product_title:'JW item',jw_variant_title:'Blue',jw_price_minor:1234,jw_legacy_stock:0,mapped_at:null,
  fetched_at:'2026-09-06T10:00:00Z',observed_data:{productTitle:'Channel item',title:'Blue',sku:'CHANNEL',price:null,stock:null,status:'NORMAL'}}]} as CatalogProduct;
 const result=catalogProductView(source,'456');
 expect(result.id).toBe('7:100');expect(result.shopId).toBe('456');
 expect(result.variants[0]).toMatchObject({id:'9',mappedJwProductId:'3',mappedJwVariantId:'4',mappingStatus:'mapped',price:null,stock:null});
 expect(result.variants[0].suggestedMatch).toBeUndefined();
 expect(result.variants[0].mappedJwPrice).toBe(12.34);
 expect(result.variants[0].mappedJwStock).toBe(0);
 expect(result.title).toBe('Channel item');
});
