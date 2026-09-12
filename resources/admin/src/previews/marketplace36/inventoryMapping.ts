import type {CatalogVariant,Page} from '../../integration/marketplaceOperations';

export async function mappingRequest<T>(path:string,method='GET',body?:object):Promise<T>{
 const csrf=document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
 if(method!=='GET'&&!csrf)throw new Error('Reload the page to restore the authenticated session.');
 const response=await fetch(`/admin/api/marketplace${path}`,{method,credentials:'same-origin',
  headers:{Accept:'application/json','Content-Type':'application/json',...(csrf?{'X-CSRF-TOKEN':csrf}:{})},
  ...(body?{body:JSON.stringify(body)}:{})});
 const result=await response.json();
 if(!response.ok)throw new Error(result.errors?Object.values(result.errors).flat().join(' '):result.message||'Mapping request failed.');
 return result;
}

export async function loadMappingCatalog():Promise<CatalogVariant[]>{
 const rows:CatalogVariant[]=[];let total:number|undefined;
 for(let page=1;page<=100;page++){
  const result=await mappingRequest<Page<CatalogVariant>>(`/catalog?page=${page}`);
  if(result.current_page!==page||!Array.isArray(result.data)||!Number.isSafeInteger(result.total)
   ||!Number.isSafeInteger(result.last_page)||result.last_page<page||result.last_page>100
   ||(total!==undefined&&total!==result.total))throw new Error('Catalog changed. Reopen mapping to reload.');
  total=result.total;rows.push(...result.data);if(page===result.last_page)break;
 }
 if(rows.length!==total||new Set(rows.map(row=>row.id)).size!==rows.length)throw new Error('Catalog response is incomplete.');
 return rows.filter(row=>['shopee','lazada'].includes(row.platform));
}

export async function saveInventoryMapping(listing:CatalogVariant,variantId:number,productId:number){
 if(![listing.id,variantId,productId].every(id=>Number.isSafeInteger(id)&&id>0)||!['shopee','lazada'].includes(listing.platform))throw new Error('Select a valid Shopee or Lazada catalog variant.');
 if(listing.jw_variant_id!==null&&listing.jw_variant_id!==variantId)throw new Error('This platform variant is already mapped to another JW SKU.');
 const result=await mappingRequest<{saved:boolean}>(`/listings/${listing.id}/mapping`,'PUT',{productId,variantId,expectedVariantId:listing.jw_variant_id});
 if(result.saved!==true)throw new Error('Mapping save could not be verified. Reopen to check the persisted mapping.');
}
