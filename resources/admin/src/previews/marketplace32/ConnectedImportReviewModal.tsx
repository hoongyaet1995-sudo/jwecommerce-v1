import React from 'react';
import {JourneyProvider,type MarketplaceJourneyContext} from './context/AdminContext';
import {ImportProductReviewModal} from './components/marketplace/ImportProductReviewModal';
import {importReviewedCatalogProduct} from '../../integration/marketplaceReviewedImport';
import type {MarketplaceStoreProduct,Product} from './types';

interface Props {
  productsToImport:MarketplaceStoreProduct[];
  products:Product[];
  canImport:boolean;
  onClose:()=>void;
  onImported:()=>void;
  addToast:MarketplaceJourneyContext['addToast'];
}
export function ConnectedImportReviewModal(props:Props) {
  const unsupported=()=>{throw new Error('Operation unavailable in import review.');};
  const value:MarketplaceJourneyContext={products:props.products,categories:[],connections:[],
    marketplaceStoreProducts:props.productsToImport,addToast:props.addToast,
    setActiveSection:()=>props.onClose(),publishMasterProductToStore:unsupported,
    mapStoreVariantToJwSku:unsupported,unmapStoreVariant:unsupported,
    importStoreProductToMaster:async(productId,variantId,options)=>{
      if(variantId!==undefined)throw new Error('Use single-variant import.');
      const result=await importReviewedCatalogProduct(props.canImport,props.productsToImport,productId,options);
      try {props.onImported();}catch{
        props.addToast({type:'info',title:'Import saved',message:'Reload the catalog to see the saved product.'});
      }
      return {productId:result.productId,createdVariants:result.createdVariants??props.productsToImport.find(p=>p.id===productId)!.variants.length,
        linkedVariants:result.linkedVariants??0};
    },
  };
  return <JourneyProvider value={value}><ImportProductReviewModal isOpen onClose={props.onClose}
    productsToImport={props.productsToImport}/></JourneyProvider>;
}
