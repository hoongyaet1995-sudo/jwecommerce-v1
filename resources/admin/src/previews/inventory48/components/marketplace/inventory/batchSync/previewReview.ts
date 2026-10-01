import type {Review, ReviewTarget} from './types';

// Display only: no review API, eligibility check, batch identity or submission key.
export function previewReview(items:any[], stores:any[], mode:'shared'|'independent'):Review {
  const targets:ReviewTarget[]=[];
  for(const item of items){
    const balances=item.batchPreviewBalances??[];
    const shared=balances.filter((row:any)=>row.poolKind==='shared');
    const canonical=shared.length===1?shared[0]:undefined;
    const mapped=stores.filter(store=>['shopee','lazada'].includes(store.platform)&&item.mappedStoreIds?.includes(String(store.id)));
    const destinations=mapped.length?mapped:[{id:null,name:'Destination',platform:'unknown'}];
    for(const store of destinations){
      const locations=mode==='independent'?balances.filter((row:any)=>store.id!==null&&String(row.storeId)===String(store.id)&&['store','facility'].includes(row.poolKind)):[];
      for(const [index,location] of (locations.length?locations:[undefined]).entries()){
        const balance=mode==='shared'?canonical:location;
        const quantity=balance?.counted===true&&balance?.notApplicable!==true&&Number.isSafeInteger(balance.quantity)&&balance.quantity>=0?balance.quantity:null;
        const firstCount=balance?.counted===false&&balance?.notApplicable!==true;
        targets.push({schemaVersion:1,rowKey:`ui-preview:${item.variantId}:${store.id??'unknown'}:${location?.facilityId??index}`,variantId:item.variantId,
          platform:store.platform,storeId:Number.isSafeInteger(Number(store.id))&&Number(store.id)>0?Number(store.id):null,facilityId:location?.facilityId??null,
          mode:mode==='shared'?'shared_pool':'independent_stores',configurationVersion:0,balanceId:balance?.balanceId??null,balanceVersion:balance?.balanceVersion??null,quantity,
          listingId:null,providerShopId:null,externalProductId:null,externalVariantId:null,providerLocationId:null,topologyFingerprint:'',policyFingerprint:'',providerAddressKey:null,
          disposition:'skipped',reasonCode:firstCount?'first_count_required':'batch_not_connected',
          display:{sku:item.masterSku,name:item.productName,image:item.imageUrl??'',store:store.name,facility:location?.poolKind==='facility'&&location.providerFacilityId?`Warehouse ${location.providerFacilityId} (not verified)`: 'Destination not verified'},
        });
      }
    }
  }
  return {id:'ui-preview',actorId:0,kind:'initial',batchId:null,variantIds:items.map(item=>item.variantId),itemIds:[],mode:mode==='shared'?'shared_pool':'independent_stores',configurationVersion:0,targets,digest:'',expiresAt:'',counts:{skus:items.length,validDestinations:0,skippedRows:targets.length}};
}
