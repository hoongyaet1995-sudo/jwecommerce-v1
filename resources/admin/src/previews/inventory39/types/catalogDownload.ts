import { MarketplacePlatform } from './index';

export type CatalogDownloadStatus = 
  | 'queued'
  | 'preparing'
  | 'downloading'
  | 'paused'
  | 'failed'
  | 'completed';

export interface CatalogDownloadErrorDetails {
  reason: string;
  technicalCode?: string;
  httpStatus?: number;
  failedPage?: number;
  recoverable: boolean;
  preservedProductsCount: number;
  rawResponseSample?: string;
}

export interface CatalogDownloadTask {
  id: string;
  connectionId: string;
  platform: MarketplacePlatform;
  shopName: string;
  shopNickname?: string;
  shopId: string;
  region: string;
  accountType: string;
  status: CatalogDownloadStatus;
  statusMessage: string;
  downloadedProducts: number;
  totalProducts: number | null; // null if confirming size
  variantsCaptured: number;
  imagesCaptured: number;
  currentPage: number;
  totalPages: number | null;
  pageSize: number;
  startedAt: string; // ISO string
  updatedAt: string; // ISO string
  completedAt?: string; // ISO string
  elapsedSeconds: number;
  savedPageCursor?: string;
  unmappedVariantsCount: number;
  errorDetails?: CatalogDownloadErrorDetails;
  isRestoredFromSavedProgress?: boolean;
  workerId: string;
}

export type CatalogSimulationPreset = 
  | 'running_determinate'
  | 'running_indeterminate'
  | 'restored_session'
  | 'failed_paused'
  | 'completed';
