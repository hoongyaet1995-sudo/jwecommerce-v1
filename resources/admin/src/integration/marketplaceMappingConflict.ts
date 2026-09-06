import {marketplaceOperations} from './marketplaceOperations';
import type {MappingConflict} from '../previews/marketplace30/components/marketplace/ProductMappingModal';

export async function checkMarketplaceMappingConflict(listingId:string,variantId:string):Promise<MappingConflict|null>{
 const id=Number(variantId);
 if(!Number.isSafeInteger(id)||id<=0)throw new Error('Invalid SKU identity.');
 const result=await marketplaceOperations.mappingContext(listingId,[id]);
 const candidate=result.data.find(row=>row.jw_variant_id===id);
 // A missing candidate is not proof that the SKU is eligible.
 if(!candidate)throw new Error('SKU eligibility could not be verified.');
 const conflict=candidate.same_store_conflict;
 return conflict?{storeProductTitle:conflict.product_title||conflict.external_product_id,
  storeVariantTitle:conflict.variant_title||conflict.external_variant_id,channelSku:conflict.sku||'',variantId:String(conflict.listing_id)}:null;
}
