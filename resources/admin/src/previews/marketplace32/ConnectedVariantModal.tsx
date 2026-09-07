import React from 'react';
import {ProductMappingModal} from './components/marketplace/ProductMappingModal';
import {JourneyProvider, type MarketplaceJourneyContext} from './context/AdminContext';
import {importCatalogVariant} from '../../integration/marketplaceVariantImport';
import {marketplaceRequest} from '../../integration/marketplaceData';
import type {MarketplaceStoreProduct, Product, Category} from './types';

interface Props {
  isOpen: boolean;
  canImport: boolean;
  storeProduct: MarketplaceStoreProduct;
  variantId: string;
  products: Product[];
  categories: Category[];
  onClose: () => void;
  onImported: () => void;
  addToast: MarketplaceJourneyContext['addToast'];
}

/** No fixture imports. The host must supply the currently loaded server catalog. */
export function ConnectedVariantModal(props: Props) {
  const variant=props.storeProduct.variants.find(item=>item.id===props.variantId);
  if (!variant) return null;
  const unsupported=()=>{throw new Error('This operation is not enabled in the import acceptance workspace.');};
  const refresh=()=>{
    try {props.onImported();} catch {
      props.addToast({type:'info',title:'Change saved',message:'The change succeeded. Reload the catalog to see the saved result.'});
    }
  };
  const persist=async(productId:string,listingId:string,method:'PUT'|'DELETE',data?:{productId:number;variantId:number})=>{
    if (productId!==props.storeProduct.id || listingId!==props.variantId || !/^[1-9]\d*$/.test(listingId)) {
      throw new Error('Refresh the catalog and select a saved variant.');
    }
    await marketplaceRequest(`/listings/${encodeURIComponent(listingId)}/mapping`,method,data);
    refresh();
  };
  const context: MarketplaceJourneyContext={
    products:props.products,categories:props.categories,connections:[],marketplaceStoreProducts:[props.storeProduct],
    addToast:props.addToast,setActiveSection:unsupported,publishMasterProductToStore:unsupported,
    mapStoreVariantToJwSku:async(productId,listingId,jwProductId,jwVariantId)=>{
      const target=props.products.find(product=>product.id===jwProductId);
      if (!target?.variants.some(item=>item.id===jwVariantId) || !/^[1-9]\d*$/.test(jwProductId)
        || !/^[1-9]\d*$/.test(jwVariantId) || !Number.isSafeInteger(Number(jwProductId)) || !Number.isSafeInteger(Number(jwVariantId))) {
        throw new Error('Select a saved JW product variant.');
      }
      await persist(productId,listingId,'PUT',{productId:Number(jwProductId),variantId:Number(jwVariantId)});
    },
    unmapStoreVariant:(productId,listingId)=>persist(productId,listingId,'DELETE'),
    importStoreProductToMaster:async(productId,variantId,options)=>{
      if (!variantId) throw new Error('Select one variant to import.');
      await importCatalogVariant(props.canImport,[props.storeProduct],productId,variantId,options);
      // The write already succeeded: a host refresh error must not encourage duplicate creation.
      refresh();
    },
  };
  return <JourneyProvider value={context}><ProductMappingModal isOpen={props.isOpen}
    onClose={props.onClose} storeProduct={props.storeProduct} storeVariant={variant}/></JourneyProvider>;
}
