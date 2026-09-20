import {marketplaceRequest} from './marketplaceData';
import {readPublishingSource} from './marketplacePublishing';

export interface LazadaReviewSubmission {
 productId:number;storeId:number;requestKey:string;sourceRevision:string;categoryId:string;
 sizeChartImageId?:number;
 attributeImageIds?:Record<string,number>;
 attributes:Record<string,string>;skuAttributes:Record<string,Record<string,string>>;
}
interface Receipt {id:number;storeId:number;productId:number;status:string}
function checkedReceipt(value:Receipt,input:LazadaReviewSubmission):Receipt {
 if(!value||!Number.isSafeInteger(value.id)||value.id<=0||value.storeId!==input.storeId||value.productId!==input.productId
  ||!['queued','processing','succeeded','rejected','uncertain'].includes(value.status))throw new Error('The saved publishing task could not be verified.');
 return value;
}

/** A single reviewed attempt. Receipt recovery never stages a second task. */
export async function submitLazadaReview(input:LazadaReviewSubmission):Promise<string>{
 if(!Number.isSafeInteger(input.storeId)||input.storeId<=0||!Number.isSafeInteger(input.productId)||input.productId<=0
  ||!/^[a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12}$/i.test(input.requestKey)
  ||!/^[a-f0-9]{64}$/.test(input.sourceRevision)||!/^[1-9][0-9]*$/.test(input.categoryId)
 ||(input.sizeChartImageId!==undefined&&(!Number.isSafeInteger(input.sizeChartImageId)||input.sizeChartImageId<=0))
 ||(input.attributeImageIds!==undefined&&(!input.attributeImageIds||Array.isArray(input.attributeImageIds)||Object.entries(input.attributeImageIds).some(([key,id])=>!/^lazada:(?:product:[A-Za-z_][A-Za-z0-9_]*|size-chart:[A-Za-z_][A-Za-z0-9_]*|variant:.+:[A-Za-z_][A-Za-z0-9_]*)$/.test(key)||!Number.isSafeInteger(id)||id<=0))))throw new Error('Review the product, store and category before publishing.');
 const source=await readPublishingSource(input.productId);
 if(source.revision!==input.sourceRevision)throw Object.assign(new Error('The JW product changed after review. Reopen it before publishing.'),{status:409});
 const payload={...(input.sizeChartImageId?{jw_size_chart_image_id:input.sizeChartImageId}:{}),...(input.attributeImageIds&&Object.keys(input.attributeImageIds).length?{jw_attribute_image_ids:input.attributeImageIds}:{}),Request:{Product:{PrimaryCategory:input.categoryId,Attributes:input.attributes,
  Skus:{Sku:source.variants.map(variant=>({...input.skuAttributes[variant.sku],SellerSku:variant.sku,
   price:(variant.price_minor/100).toFixed(2),quantity:'0'}))}}}};
 let receipt:Receipt;
 try{
  receipt=await marketplaceRequest<Receipt>(`/stores/${input.storeId}/publishing-tasks`,'POST',{
   product_id:input.productId,request_key:input.requestKey,source_revision:source.revision,payload});
 }catch(error){
  // Validation/permission responses are definitive; keep their actionable message.
  const status=error&&typeof error==='object'&&'status' in error?error.status:undefined;
  if(typeof status==='number'&&status>=400&&status<500)throw error;
  receipt=await marketplaceRequest<Receipt>(`/stores/${input.storeId}/publishing-request/${input.requestKey}`);
 }
 receipt=checkedReceipt(receipt,input);
 if(receipt.status==='queued'){
  try{await marketplaceRequest(`/publishing-tasks/${receipt.id}/dispatch`,'POST');}
  catch{/* The persisted task, including uncertain/rejected states, owns the result. */}
 }
 return String(receipt.id);
}
