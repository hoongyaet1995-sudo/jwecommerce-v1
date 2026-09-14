import {marketplaceOperations} from './marketplaceOperations';
import {variantImportReview} from './marketplaceVariantImportReview';

export async function importReviewedCatalogProduct(enabled:boolean,
  sources:{id:string;variants:{id:string}[]}[],productId:string,
  options:Parameters<typeof variantImportReview>[1]={}) {
  if(!enabled)throw new Error('Product import is not enabled.');
  const source=sources.find(product=>product.id===productId);
  if(!source?.variants.length || source.variants.some(variant=>!/^[1-9]\d*$/.test(variant.id))) {
    throw new Error('Refresh and select a saved catalog product.');
  }
  // Reuse the single-variant policy checks without confusing whole-product ownership.
  variantImportReview(source.variants[0].id,{...options,skuOverrides:undefined,linkExistingVariantIds:undefined});
  const existingLinks:Record<string,{productId:number;variantId:number}>={};
  for(const [id,target] of Object.entries(options.linkExistingVariantIds??{})) {
    if(!source.variants.some(variant=>variant.id===id) || options.skuOverrides?.[id]!==undefined
      || !target || ![target.productId,target.variantId].every(value=>/^[1-9]\d*$/.test(value)&&Number.isSafeInteger(Number(value)))) {
      throw new Error('Select a valid existing SKU for this source variant.');
    }
    existingLinks[id]={productId:Number(target.productId),variantId:Number(target.variantId)};
  }
  const skuOverrides:Record<string,string>={};
  for(const [id,sku] of Object.entries(options.skuOverrides??{})) {
    if(!source.variants.some(variant=>variant.id===id))throw new Error('SKU override belongs to another product.');
    const review=variantImportReview(id,{skuOverrides:{[id]:sku}});
    skuOverrides[id]=review.sku!;
  }
  if(options.targetProductId!==undefined) {
    if(Object.keys(existingLinks).length) throw new Error('Batch append resolves links within the selected parent only.');
    return marketplaceOperations.importProduct(source.variants[0].id,{skuOverrides,
      productId:Number(options.targetProductId),listingIds:source.variants.map(v=>Number(v.id))});
  }
  return marketplaceOperations.importProduct(source.variants[0].id,{skuOverrides,
    ...(Object.keys(existingLinks).length ? {existingLinks} : {})});
}
