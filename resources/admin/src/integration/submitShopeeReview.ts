import {marketplaceRequest} from './marketplaceData';
import {readPublishingSource} from './marketplacePublishing';

export interface ShopeeReviewSubmission {
 productId:number;storeId:number;requestKey:string;sourceRevision:string;categoryId:string;
 variantImageChoices?:Record<string,number>;
 sizeChartImageId?:number;
 brandId:number|null;condition:'NEW'|'USED';weightKg:number;lengthCm:number;widthCm:number;heightCm:number;
 attributes:unknown[];logistics:{logistic_id:number;is_free?:boolean;size_id?:string|number;shipping_fee?:number}[];
}
interface Receipt {id:number;storeId:number;productId:number;status:string}

export async function submitShopeeReview(input:ShopeeReviewSubmission):Promise<string>{
 if(!Number.isSafeInteger(input.productId)||input.productId<=0||!Number.isSafeInteger(input.storeId)||input.storeId<=0
  ||!/^[a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12}$/i.test(input.requestKey)||!/^[a-f0-9]{64}$/.test(input.sourceRevision)
  ||!/^[1-9][0-9]*$/.test(input.categoryId)||!Number.isSafeInteger(Number(input.categoryId))
  ||!['NEW','USED'].includes(input.condition)||!Number.isFinite(input.weightKg)||input.weightKg<=0
  ||![input.lengthCm,input.widthCm,input.heightCm].every(value=>Number.isSafeInteger(value)&&value>0)
 ||!Array.isArray(input.attributes)||!Array.isArray(input.logistics)||!input.logistics.length
  ||(input.sizeChartImageId!==undefined&&(!Number.isSafeInteger(input.sizeChartImageId)||input.sizeChartImageId<=0))
  ||(input.brandId!==null&&(!Number.isSafeInteger(input.brandId)||input.brandId<0)))throw new Error('Review the Shopee category, package and shipping selection.');
 const source=await readPublishingSource(input.productId);
 if(source.revision!==input.sourceRevision)throw Object.assign(new Error('The JW product changed after review. Reopen it before publishing.'),{status:409});
 // Server staging owns the product fields, images, variant structure, prices and initial stock.
 const payload={...(input.variantImageChoices?{jw_variant_image_choices:input.variantImageChoices}:{}),
  ...(input.sizeChartImageId?{jw_size_chart_image_id:input.sizeChartImageId}:{}),category_id:Number(input.categoryId),item_status:'UNLIST',condition:input.condition,
  weight:input.weightKg,dimension:{package_length:input.lengthCm,package_width:input.widthCm,package_height:input.heightCm},
  ...(input.brandId===null?{}:{brand:{brand_id:input.brandId}}),attribute_list:input.attributes,logistic_info:input.logistics};
 let receipt:Receipt;
 try{receipt=await marketplaceRequest<Receipt>(`/stores/${input.storeId}/publishing-tasks`,'POST',{
  product_id:input.productId,request_key:input.requestKey,source_revision:source.revision,payload});}
 catch(error){
  const status=error&&typeof error==='object'&&'status' in error?error.status:undefined;
  if(typeof status==='number'&&status>=400&&status<500)throw error;
  receipt=await marketplaceRequest<Receipt>(`/stores/${input.storeId}/publishing-request/${input.requestKey}`);
 }
 if(!receipt||!Number.isSafeInteger(receipt.id)||receipt.id<=0||receipt.productId!==input.productId||receipt.storeId!==input.storeId
  ||!['queued','processing','succeeded','rejected','uncertain'].includes(receipt.status))throw new Error('The saved publishing task could not be verified.');
 if(receipt.status==='queued'){
  try{await marketplaceRequest(`/publishing-tasks/${receipt.id}/dispatch`,'POST');}
  catch{/* The saved task owns the result, including a partially created Shopee parent. */}
 }
 return String(receipt.id);
}
