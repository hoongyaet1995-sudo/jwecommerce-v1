import type {MarketplacePlatform} from './index';
import type {MappingStatus, SuggestedJwMatch} from '../../marketplace30/types';
export interface MarketplaceStoreVariant {
  id: string;
  channelSkuId: string;
  channelSellerSku: string;
  title: string;
  specOptions?: Record<string, string>;
  price: number;
  currency: string;
  stock: number;
  imageUrl?: string;

  // Mapping to JW product variant
  mappingStatus: MappingStatus;
  mappedJwProductId?: string;
  mappedJwVariantId?: string;
  mappedJwSku?: string;
  mappedJwProductName?: string;
  mappedJwVariantTitle?: string;
  mappedJwVariantImage?: string;
  mappedJwStock?: number;
  mappedJwPrice?: number;
  mappedAt?: string;
  mappedBy?: string;

  // Conflict / Invalid detail
  conflictReason?: string;

  // Suggested Smart Match (if unmapped or suggested)
  suggestedMatch?: SuggestedJwMatch;
}

export interface MarketplaceStoreProduct {
  id: string;
  connectionId: string;
  platform: MarketplacePlatform;
  shopId: string;
  shopName: string;
  channelProductId: string;
  title: string;
  imageUrl: string;
  categoryName: string;
  status: 'active' | 'inactive' | 'delisted';
  updatedAt: string;
  variants: MarketplaceStoreVariant[];
}
