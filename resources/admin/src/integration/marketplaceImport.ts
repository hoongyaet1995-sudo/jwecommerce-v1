import {marketplaceOperations} from './marketplaceOperations';
export type ImportResult = {id:string;ok:false}|{id:string;ok:true;productId:number;status:string};
export async function importCatalogProducts(ids:string[],sources:{id:string;listingId:string}[]):Promise<ImportResult[]> {
 const results:ImportResult[]=[];
 for(const id of new Set(ids)) {
  const source=sources.find(item=>item.id===id);
  if(!source){results.push({id,ok:false});continue;}
  try {
   const response=await marketplaceOperations.importProduct(source.listingId);
   if(!Number.isSafeInteger(response.productId)||response.productId<=0||typeof response.status!=='string'||!response.status) throw new Error('Invalid import result');
   results.push({id,ok:true,productId:response.productId,status:response.status});
  } catch {results.push({id,ok:false});}
 }
 return results;
}
