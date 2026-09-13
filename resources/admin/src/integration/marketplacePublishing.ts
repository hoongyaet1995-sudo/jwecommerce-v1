import {marketplaceRequest} from './marketplaceData';

export interface PublishingSource {
  product_id:number; title:string; description:string|null; revision:string; brand?:string|null;
  variants:{id:number;sku:string;price_minor:number;initial_quantity:0;title?:string;options?:unknown;
    weight_grams?:number|null;package_length_cm?:number|null;package_width_cm?:number|null;package_height_cm?:number|null}[];
  images:{id:number;path:string}[];
}
export interface PublishingImageReceipt {
  id:number;storeId:number;productId:number;imageId:number;status:'succeeded';providerIdentity:string;
}
const positiveId=(value:unknown):value is number=>typeof value==='number'&&Number.isSafeInteger(value)&&value>0;
function requireIds(...ids:number[]):void {
  if(!ids.every(positiveId))throw new Error('Invalid publishing identity.');
}

export async function readPublishingSource(productId:number):Promise<PublishingSource> {
  requireIds(productId);
  const source=await marketplaceRequest<PublishingSource>(`/products/${productId}/publishing-source`);
  if(!source||source.product_id!==productId||typeof source.title!=='string'||!source.title.trim()
    ||typeof source.revision!=='string'||!/^[a-f0-9]{64}$/.test(source.revision)
    ||!Array.isArray(source.variants)||!source.variants.length
    ||!source.variants.every(v=>v&&positiveId(v.id)&&typeof v.sku==='string'&&v.sku.trim()&&positiveId(v.price_minor)&&v.initial_quantity===0)
    ||new Set(source.variants.map(v=>v.id)).size!==source.variants.length
    ||new Set(source.variants.map(v=>v.sku)).size!==source.variants.length
    ||!Array.isArray(source.images)||!source.images.every(i=>i&&positiveId(i.id)&&typeof i.path==='string')
    ||new Set(source.images.map(i=>i.id)).size!==source.images.length)throw new Error('Invalid publishing source response.');
  return source;
}

export async function preparePublishingImage(storeId:number,productId:number,imageId:number):Promise<PublishingImageReceipt> {
  requireIds(storeId,productId,imageId);
  const receipt=await marketplaceRequest<PublishingImageReceipt>(`/stores/${storeId}/products/${productId}/images/${imageId}/prepare`,'POST');
  if(!receipt||!positiveId(receipt.id)||receipt.storeId!==storeId||receipt.productId!==productId||receipt.imageId!==imageId
    ||receipt.status!=='succeeded'||typeof receipt.providerIdentity!=='string'||!receipt.providerIdentity.trim())throw new Error('Invalid publishing image receipt.');
  return receipt;
}
