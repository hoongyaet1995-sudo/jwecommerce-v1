import type {CatalogProduct} from './marketplaceOperations';
import type {MarketplaceStoreProduct} from '../previews/marketplace30/types';

export function catalogProductView(source: CatalogProduct, shopId: string): MarketplaceStoreProduct {
 const first=source.variants[0]?.observed_data;
 return {id:`${source.store_id}:${source.external_product_id}`,connectionId:String(source.store_id),platform:source.platform,
  shopId,shopName:source.shop_name,channelProductId:source.external_product_id,title:first?.productTitle||source.external_product_id,
  imageUrl:first?.productImage||'',categoryName:'',status:first?.status==='NORMAL'?'active':'unknown',
  updatedAt:source.variants[0]?.fetched_at||'',variants:source.variants.map(variant=>{
    const data=variant.observed_data;
    const price=data.price===null||data.price===undefined||data.price===''?null:Number(data.price);
    return {id:String(variant.id),channelSkuId:variant.external_variant_id,channelSellerSku:data.sku||'',title:data.title||variant.external_variant_id,
      price:price!==null&&Number.isFinite(price)?price:null,stock:data.stock??null,currency:data.currency||'',mappingStatus:variant.jw_variant_id===null?'unmapped':'mapped',
      mappedJwProductId:variant.jw_product_id==null?undefined:String(variant.jw_product_id),mappedJwVariantId:variant.jw_variant_id==null?undefined:String(variant.jw_variant_id),
      mappedJwSku:variant.jw_sku??undefined,mappedJwProductName:variant.jw_product_title??undefined,mappedJwVariantTitle:variant.jw_variant_title??undefined,mappedAt:variant.mapped_at??undefined};
  })};
}
