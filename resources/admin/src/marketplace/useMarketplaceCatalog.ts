import {useCallback,useEffect,useState} from 'react';
import {marketplaceOperations, type CatalogProduct, type CatalogSummary} from '../integration/marketplaceOperations';
import type {MappingPageQuery} from '../previews/marketplace30/components/marketplace/MarketplaceMappingPage';

const emptySummary:CatalogSummary={products:0,variants:0,mapped:0,unmapped:0};

/** The server owns pagination; old filter responses must never replace the current page. */
export function useMarketplaceCatalog(query:MappingPageQuery) {
 const {platform,storeId,q,status,page,per_page,view}=query;
 const [revision,setRevision]=useState(0);
 const reload=useCallback(()=>setRevision(value=>value+1),[]);
 const [state,setState]=useState({products:[] as CatalogProduct[],total:0,summary:emptySummary,loading:true,error:''});
 useEffect(()=>{
  let active=true;
  if(platform==='direct'){
   setState({products:[],total:0,summary:emptySummary,loading:false,error:'This channel does not have a marketplace catalog.'});
   return()=>{active=false;};
  }
  setState(previous=>({...previous,products:[],loading:true,error:''}));
  const filters={platform:platform==='all'?undefined:platform,storeId:storeId==='all'?undefined:storeId,q,status:status==='all'?undefined:status,page,per_page};
  const request=view==='grouped'?marketplaceOperations.catalogProducts(filters):marketplaceOperations.catalog(filters).then(result=>{
   const grouped=new Map<string,CatalogProduct>();
   for(const variant of result.data){
    const key=`${variant.store_id}:${variant.external_product_id}`;
    let product=grouped.get(key);
    if(!product){product={store_id:variant.store_id,external_product_id:variant.external_product_id,platform:variant.platform,shop_name:variant.shop_name,first_listing_id:variant.id,variants:[]};grouped.set(key,product);}
    product.variants.push(variant);
   }
   // Keep variant pagination totals, not the number of grouped parent products.
   return {...result,data:[...grouped.values()]};
  });
  Promise.all([request,marketplaceOperations.catalogSummary(filters)]).then(([result,summary])=>{
   if(active)setState({products:result.data,total:result.total,summary,loading:false,error:''});
  }).catch(()=>{
   if(active)setState({products:[],total:0,summary:emptySummary,loading:false,error:'The store catalog could not be loaded. Please retry.'});
  });
  return()=>{active=false;};
 },[platform,storeId,q,status,page,per_page,view,revision]);
 return {...state,reload};
}
