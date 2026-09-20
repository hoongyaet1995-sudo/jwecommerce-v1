import {readAdminBootstrap} from './adminBootstrap';

export interface MarketplaceAttributeImageReceipt {
 imageId:number;
 url:string;
 alt:string;
}

type Fetcher=(input:RequestInfo|URL,init?:RequestInit)=>Promise<Response>;
const positiveId=(value:unknown):value is number=>typeof value==='number'&&Number.isSafeInteger(value)&&value>0;

export async function uploadMarketplaceAttributeImage(productId:number,file:File,fetcher:Fetcher=window.fetch.bind(window)):Promise<MarketplaceAttributeImageReceipt>{
 if(!positiveId(productId))throw new Error('Invalid product identity.');
 const form=new FormData();
 form.append('file',file);
 const response=await fetcher(`/admin/api/marketplace/products/${productId}/attribute-images`,{
  method:'POST',credentials:'same-origin',
  headers:{Accept:'application/json','X-CSRF-TOKEN':readAdminBootstrap().csrfToken},
  body:form,
 });
 if(!response.ok)throw new Error('Unable to upload marketplace attribute image.');
 const receipt=await response.json() as Partial<MarketplaceAttributeImageReceipt>;
 if(!positiveId(receipt.imageId)||typeof receipt.url!=='string'||!receipt.url.trim()||typeof receipt.alt!=='string')throw new Error('Invalid marketplace attribute image response.');
 return receipt as MarketplaceAttributeImageReceipt;
}
