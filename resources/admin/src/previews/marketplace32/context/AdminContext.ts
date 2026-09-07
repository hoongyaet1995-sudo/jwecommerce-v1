import {createContext, useContext} from 'react';
import type {Product, Category, MarketplaceConnection, MarketplaceStoreProduct,
  ImportProductOptions, PublishListingOptions} from '../types';

// An isolated value/event boundary: no demo state, persistence or network side effects.
export interface MarketplaceJourneyContext {
  products: Product[];
  categories: Category[];
  connections: MarketplaceConnection[];
  marketplaceStoreProducts: MarketplaceStoreProduct[];
  setActiveSection: (section: string) => void;
  addToast: (toast: {type: string; title: string; message: string}) => void;
  importStoreProductToMaster: (productId: string, variantId?: string, options?: ImportProductOptions) => void | Promise<void | {productId:number|null;createdVariants:number;linkedVariants:number}>;
  publishMasterProductToStore: (productId: string, connectionId: string, options?: PublishListingOptions) => void;
  mapStoreVariantToJwSku: (productId: string, variantId: string, jwProductId: string, jwVariantId: string) => void | Promise<void>;
  unmapStoreVariant: (productId: string, variantId: string) => void | Promise<void>;
}
const MissingProvider = 'ZIP 32 journey provider required';
// Exporting the context separately allows host adapters to supply explicit operations.
const JourneyContext = createContext<MarketplaceJourneyContext | null>(null);
export const JourneyProvider = JourneyContext.Provider;
export function useAdmin(): MarketplaceJourneyContext {
  const value = useContext(JourneyContext);
  if (!value) throw new Error(MissingProvider);
  return value;
}
