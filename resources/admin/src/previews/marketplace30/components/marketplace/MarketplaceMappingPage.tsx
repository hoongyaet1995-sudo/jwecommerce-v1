import React, { useState, useMemo } from 'react';
import { useAdmin } from '../../context/ContextBridge';
import type {CatalogSummary} from '../../../../integration/marketplaceOperations';
import type {ImportResult} from '../../../../integration/marketplaceImport';
import {checkMarketplaceMappingConflict} from '../../../../integration/marketplaceMappingConflict';
import { MarketplacePlatform, MarketplaceStoreProduct, MarketplaceStoreVariant } from '../../types';
import { SubNavHeader } from '../common/SubNavHeader';
import { ProductMappingModal } from './ProductMappingModal';
import { PublishProductModal } from './PublishProductModal';
import { MarketplaceLogo } from './MarketplaceLogo';
import { Link2, Unlink, Search, RefreshCw, Sparkles, CheckCircle2, AlertTriangle, AlertCircle, Store, Boxes, ChevronRight, Check, DownloadCloud, UploadCloud, LayoutGrid, List, ChevronLeft, ChevronsLeft, ChevronsRight } from 'lucide-react';

export interface MappingPageQuery {
  platform: MarketplacePlatform | 'all'; storeId: string; q: string; status: string;
  page: number; per_page: number; view: 'grouped' | 'flat';
}
export interface MappingServerPage {
  onReviewPublishing?: (storeId?:string)=>void;
  catalogTaskStatus?: 'queued'|'running'|'failed'; onOpenCatalogTask?:()=>void;
  canFetch?:boolean;
  canImport?:boolean; importing?:boolean; onImport?:(ids:string[])=>Promise<ImportResult[]>;
  total: number; summary: CatalogSummary; loading: boolean; onQueryChange: (query: MappingPageQuery) => void;
}
export const MarketplaceMappingPage: React.FC<{serverPage?: MappingServerPage;
  renderVariantModal?: (selection:{storeProduct:MarketplaceStoreProduct;storeVariant:MarketplaceStoreVariant;intent?:'import'},onClose:()=>void)=>React.ReactNode;
}> = ({serverPage,renderVariantModal}) => {
  const {
    products = [],
    connections = [],
    marketplaceOrders = [],
    marketplaceInventory = [],
    marketplaceStoreProducts = [],
    syncLogs = [],
    mapStoreVariantToJwSku,
    unmapStoreVariant,
    batchAcceptSuggestedMappings,
    refreshStoreProductsFromChannel,
    resetStoreProductsToDefaults,
    importStoreProductToMaster,
    batchImportStoreProductsToMaster,
    setCurrentSection,
    addToast
  } = useAdmin();

  // Top Filter States - Defaults to 'unmapped' for Exception-Driven workflow!
  const [selectedPlatform, setSelectedPlatform] = useState<MarketplacePlatform | 'all'>('all');
  const [selectedConnectionId, setSelectedConnectionId] = useState<string>('all');
  const [searchQuery, setSearchQuery] = useState('');
  const [statusFilter, setStatusFilter] = useState<'unmapped' | 'suggested' | 'mapped' | 'conflict' | 'all' | 'push_candidates'>('unmapped');
  const [viewMode, setViewMode] = useState<'grouped' | 'flat'>('grouped');
  const [isRefreshing, setIsRefreshing] = useState(false);

  // Pagination State (Essential for 300+ items)
  const [currentPage, setCurrentPage] = useState(1);
  const [pageSize, setPageSize] = useState<number>(15);
  const onQueryChange = serverPage?.onQueryChange;
  React.useEffect(() => {
    onQueryChange?.({platform:selectedPlatform,storeId:selectedConnectionId,q:searchQuery,status:statusFilter,
      page:currentPage,per_page:pageSize,view:viewMode});
  }, [onQueryChange,selectedPlatform,selectedConnectionId,searchQuery,statusFilter,currentPage,pageSize,viewMode]);

  // Batch Selection State
  const [selectedProductIds, setSelectedProductIds] = useState<Set<string>>(new Set());
  React.useEffect(()=>{setSelectedProductIds(new Set());},[selectedPlatform,selectedConnectionId,searchQuery,statusFilter,currentPage,pageSize,viewMode]);

  // Modal States
  const [activeMappingModal, setActiveMappingModal] = useState<{
    storeProduct: MarketplaceStoreProduct;
    storeVariant: MarketplaceStoreVariant;
    intent?: 'import';
  } | null>(null);

  const [activePublishModal, setActivePublishModal] = useState<{
    isOpen: boolean;
    productId?: string;
    connectionId?: string;
  }>({ isOpen: false });

  // Connected count and stats
  const safeConns = connections || [];
  const safeMpInv = marketplaceInventory || [];
  const safeMpOrders = marketplaceOrders || [];
  const safeStoreProds = marketplaceStoreProducts || [];

  const connectedCount = safeConns.filter(c => c.status === 'connected').length;
  const pendingOrdersCount = safeMpOrders.filter(
    o => o.channelStatus === 'AWAITING_COLLECTION' || o.channelStatus === 'UNPAID' || !o.airwayBillGenerated
  ).length;

  // Global counts across all store products
  const totalVariants = serverPage?.summary.variants ?? safeStoreProds.reduce((acc, p) => acc + p.variants.length, 0);
  const mappedVariantsCount = serverPage?.summary.mapped ?? safeStoreProds.reduce(
    (acc, p) => acc + p.variants.filter(v => v.mappingStatus === 'mapped').length, 
    0
  );
  const unmappedVariantsCount = serverPage?.summary.unmapped ?? safeStoreProds.reduce(
    (acc, p) => acc + p.variants.filter(v => v.mappingStatus === 'unmapped').length, 
    0
  );
  const conflictVariantsCount = safeStoreProds.reduce(
    (acc, p) => acc + p.variants.filter(v => v.mappingStatus === 'conflict' || v.mappingStatus === 'invalid').length, 
    0
  );
  const readySuggestionsCount = safeStoreProds.reduce(
    (acc, p) => acc + p.variants.filter(v => v.mappingStatus === 'unmapped' && v.suggestedMatch).length, 
    0
  );

  // Available connections for filter dropdown
  const connectionOptions = useMemo(() => {
    if (selectedPlatform === 'all') return safeConns;
    return safeConns.filter(c => c.platform === selectedPlatform);
  }, [safeConns, selectedPlatform]);

  // Handle refresh
  const handleRefresh = async () => {
    setIsRefreshing(true);
    await refreshStoreProductsFromChannel(selectedConnectionId === 'all' ? undefined : selectedConnectionId);
    setIsRefreshing(false);
  };

  // Identify Master products that are unlisted on selected connection
  const unlistedMasterProducts = useMemo(() => {
    const targetConnId = selectedConnectionId === 'all' ? undefined : selectedConnectionId;
    const listedJwProductIds = new Set<string>();

    safeStoreProds.forEach(sp => {
      if (!targetConnId || sp.connectionId === targetConnId) {
        sp.variants.forEach(v => {
          if (v.mappedJwProductId) {
            listedJwProductIds.add(v.mappedJwProductId);
          }
        });
      }
    });

    return products.filter(p => !listedJwProductIds.has(p.id));
  }, [products, safeStoreProds, selectedConnectionId]);

  // Filtered store products
  const filteredProducts = useMemo(() => {
    if (serverPage) return safeStoreProds;
    return safeStoreProds.filter(prod => {
      // Platform filter
      if (selectedPlatform !== 'all' && prod.platform !== selectedPlatform) {
        return false;
      }
      // Specific connection/store filter
      if (selectedConnectionId !== 'all' && prod.connectionId !== selectedConnectionId) {
        return false;
      }

      // Variant status filter
      if (statusFilter === 'unmapped') {
        const hasUnmapped = prod.variants.some(v => v.mappingStatus === 'unmapped');
        if (!hasUnmapped) return false;
      } else if (statusFilter === 'suggested') {
        const hasSuggested = prod.variants.some(v => v.mappingStatus === 'unmapped' && v.suggestedMatch);
        if (!hasSuggested) return false;
      } else if (statusFilter === 'mapped') {
        const hasMapped = prod.variants.some(v => v.mappingStatus === 'mapped');
        if (!hasMapped) return false;
      } else if (statusFilter === 'conflict') {
        const hasConflict = prod.variants.some(v => v.mappingStatus === 'conflict' || v.mappingStatus === 'invalid');
        if (!hasConflict) return false;
      }

      // Search Query
      if (searchQuery.trim()) {
        const q = searchQuery.toLowerCase();
        const matchTitle = prod.title.toLowerCase().includes(q);
        const matchShop = prod.shopName.toLowerCase().includes(q);
        const matchChannelId = prod.channelProductId.toLowerCase().includes(q);
        const matchVariants = prod.variants.some(v => 
          v.title.toLowerCase().includes(q) ||
          v.channelSellerSku.toLowerCase().includes(q) ||
          (v.mappedJwSku && v.mappedJwSku.toLowerCase().includes(q)) ||
          (v.mappedJwProductName && v.mappedJwProductName.toLowerCase().includes(q))
        );
        if (!matchTitle && !matchShop && !matchChannelId && !matchVariants) {
          return false;
        }
      }

      return true;
    });
  }, [safeStoreProds, selectedPlatform, selectedConnectionId, statusFilter, searchQuery, serverPage]);

  // Flat variants list for flat table view
  const flatVariants = useMemo(() => {
    const list: Array<{ product: MarketplaceStoreProduct; variant: MarketplaceStoreVariant }> = [];
    filteredProducts.forEach(prod => {
      prod.variants.forEach(variant => {
        if (statusFilter === 'unmapped' && variant.mappingStatus !== 'unmapped') return;
        if (statusFilter === 'suggested' && (!variant.suggestedMatch || variant.mappingStatus !== 'unmapped')) return;
        if (statusFilter === 'mapped' && variant.mappingStatus !== 'mapped') return;
        if (statusFilter === 'conflict' && variant.mappingStatus !== 'conflict' && variant.mappingStatus !== 'invalid') return;
        list.push({ product: prod, variant });
      });
    });
    return list;
  }, [filteredProducts, statusFilter]);

  // Total count based on view mode
  const totalItemCount = serverPage?.total ?? (statusFilter === 'push_candidates'
    ? unlistedMasterProducts.length
    : (viewMode === 'grouped' ? filteredProducts.length : flatVariants.length));

  const totalPages = Math.max(1, Math.ceil(totalItemCount / pageSize));

  // Reset page if out of bounds
  React.useEffect(() => {
    if (!serverPage?.loading && currentPage > totalPages) {
      setCurrentPage(1);
    }
  }, [totalPages, currentPage, serverPage?.loading]);

  // Paginated slices
  const paginatedProducts = useMemo(() => {
    if (serverPage) return filteredProducts;
    const start = (currentPage - 1) * pageSize;
    return filteredProducts.slice(start, start + pageSize);
  }, [filteredProducts, currentPage, pageSize, serverPage]);

  const paginatedFlatVariants = useMemo(() => {
    if (serverPage) return flatVariants;
    const start = (currentPage - 1) * pageSize;
    return flatVariants.slice(start, start + pageSize);
  }, [flatVariants, currentPage, pageSize, serverPage]);

  const paginatedUnlistedMasterProducts = useMemo(() => {
    const start = (currentPage - 1) * pageSize;
    return unlistedMasterProducts.slice(start, start + pageSize);
  }, [unlistedMasterProducts, currentPage, pageSize]);

  // Selection handlers
  const handleToggleSelectAll = () => {
    if (selectedProductIds.size === paginatedProducts.length && paginatedProducts.length > 0) {
      setSelectedProductIds(new Set());
    } else {
      const next = new Set<string>();
      paginatedProducts.forEach(p => next.add(p.id));
      setSelectedProductIds(next);
    }
  };

  const handleToggleSelectProduct = (id: string) => {
    const next = new Set(selectedProductIds);
    if (next.has(id)) {
      next.delete(id);
    } else {
      next.add(id);
    }
    setSelectedProductIds(next);
  };

  // Batch actions
  const handleBatchImportSelected = async () => {
    if (selectedProductIds.size === 0) return;
    if(serverPage){
      if(!serverPage.canImport||serverPage.importing||!serverPage.onImport)return;
      const results=await serverPage.onImport(Array.from(selectedProductIds));
      setSelectedProductIds(previous=>new Set([...previous].filter(id=>!results.some(result=>result.id===id&&result.ok))));
      return;
    }
    batchImportStoreProductsToMaster(Array.from(selectedProductIds));
    setSelectedProductIds(new Set());
  };

  const handleBatchAcceptSuggestionsSelected = () => {
    if (selectedProductIds.size === 0) return;
    Array.from(selectedProductIds).forEach(id => {
      batchAcceptSuggestedMappings(id);
    });
    setSelectedProductIds(new Set());
    addToast({
      type: 'success',
      title: 'Batch Suggestions Accepted',
      message: `Accepted suggestions for ${selectedProductIds.size} product(s).`
    });
  };

  // Disconnected store warning
  const selectedConnectionObj = safeConns.find(c => c.id === selectedConnectionId);
  const isSelectedStoreDisconnected = selectedConnectionObj && selectedConnectionObj.status !== 'connected';

  return (
    <div className="space-y-6">
      {/* SubNavHeader */}
      <SubNavHeader
        hubTitle="Marketplace"
        hubSection="marketplace"
        currentPageTitle="Product & SKU Mapping"
        tabs={[
          { id: 'marketplace-auth', label: 'Connect Store', badge: serverPage ? `${connectedCount} Connected` : `${connectedCount}/4 Active`, badgeVariant: 'success' },
          { id: 'marketplace-mapping', label: 'Product & SKU Mapping', badge: unmappedVariantsCount > 0 ? `${unmappedVariantsCount} Needs Review` : 'Healthy', badgeVariant: unmappedVariantsCount > 0 ? 'warning' : 'success' },
          { id: 'marketplace-oms', label: 'Orders (OMS)', badge: pendingOrdersCount > 0 ? `${pendingOrdersCount} Pending` : undefined, badgeVariant: 'warning' },
          { id: 'marketplace-inventory', label: 'Inventory & WMS', badge: serverPage ? undefined : `${safeMpInv.length} SKUs`, badgeVariant: 'info' },
          { id: 'marketplace-sync', label: 'Webhooks & Logs', badge: serverPage ? undefined : `${syncLogs.length}`, badgeVariant: 'default' },
        ]}
      />

      {/* Header Banner & Primary Actions */}
      <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
        <div>
          <div className="flex items-center gap-2.5">
            <h1 className="text-2xl font-black tracking-tight text-neutral-900 dark:text-white">
              Product & SKU Mapping
            </h1>
            <span className="text-[11px] font-bold px-2 py-0.5 rounded-full bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800">
              {serverPage ? 'Catalog & SKU Mapping' : 'Bidirectional SKU Sync'}
            </span>
          </div>
          <p className="text-xs text-neutral-500 dark:text-neutral-400 mt-1">
            {serverPage ? `Browse fetched store listings and link variants to JW SKUs. ${serverPage.canImport ? 'Import JW drafts with zero legacy catalog stock; physical inventory awaits a count.' : 'Import is not enabled yet.'} Use Push JW Master to Store to review a product for publishing.` : 'Manage high-volume catalog bindings: auto-filter exceptions, push master products to external stores, or 1-click import channel items to JW Master.'}
          </p>
        </div>

        <div className="flex flex-wrap items-center gap-2.5">
          {/* Push Master Product to Store Button */}
          <button
            id="btn-push-master-to-store"
            type="button"
            disabled={!!serverPage && !serverPage.onReviewPublishing}
            title={serverPage ? serverPage.onReviewPublishing ? 'Review JW product details and destination requirements before publishing.' : 'Publishing is not enabled yet.' : undefined}
            onClick={() => {
              const connectionId=selectedConnectionId === 'all' ? undefined : selectedConnectionId;
              if (serverPage) {serverPage.onReviewPublishing?.(connectionId);return;}
              setActivePublishModal({ isOpen: true, connectionId });
            }}
            className="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold shadow-sm shadow-indigo-500/20 transition-all cursor-pointer"
          >
            <UploadCloud className="w-3.5 h-3.5" />
            Push JW Master to Store
          </button>

          {readySuggestionsCount > 0 && (
            <button
              id="btn-batch-accept-suggestions"
              type="button"
              onClick={() => batchAcceptSuggestedMappings()}
              className="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-violet-600 hover:bg-violet-500 text-white text-xs font-bold shadow-sm shadow-violet-500/20 transition-all cursor-pointer"
            >
              <Sparkles className="w-3.5 h-3.5" />
              Accept AI Suggestions ({readySuggestionsCount})
            </button>
          )}

          <button
            id="btn-refresh-channel-products"
            type="button"
            disabled={isRefreshing || !!serverPage&&!serverPage.canFetch}
            title={serverPage ? 'Select one connected store with catalog access' : undefined}
            onClick={handleRefresh}
            className="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl border border-neutral-200 dark:border-neutral-700 text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800 text-xs font-bold transition-colors cursor-pointer"
          >
            <RefreshCw className={`w-3.5 h-3.5 ${isRefreshing ? 'animate-spin text-indigo-600' : ''}`} />
            {isRefreshing ? 'Fetching...' : 'Fetch Store Products'}
          </button>
          {serverPage?.catalogTaskStatus && <button type="button" onClick={serverPage.onOpenCatalogTask} className="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl border border-indigo-200 dark:border-indigo-800 text-indigo-700 dark:text-indigo-300 hover:bg-indigo-50 dark:hover:bg-indigo-950/40 text-xs font-bold transition-colors cursor-pointer"><DownloadCloud className="w-3.5 h-3.5" />{serverPage.catalogTaskStatus==='failed'?'Resume download':'View download status'}</button>}
        </div>
      </div>

      {/* Disconnected Store Warning if selected */}
      {isSelectedStoreDisconnected && (
        <div className="p-4 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-900 flex items-center justify-between gap-4">
          <div className="flex items-center gap-3">
            <AlertTriangle className="w-5 h-5 text-amber-600 dark:text-amber-400 flex-shrink-0" />
            <div>
              <p className="text-xs font-bold text-amber-900 dark:text-amber-200">
                Current store "{selectedConnectionObj?.shopName}" is disconnected or authorization expired
              </p>
              <p className="text-[11px] text-amber-700 dark:text-amber-400 mt-0.5">
                Please re-authorize through Connect Store to ensure active product retrieval and automated order synchronization.
              </p>
            </div>
          </div>
          <button
            onClick={() => setCurrentSection('marketplace-auth')}
            className="px-3 py-1.5 rounded-lg bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold whitespace-nowrap cursor-pointer"
          >
            Go to Connect Store
          </button>
        </div>
      )}

      {/* Stat Cards Matrix */}
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-3.5">
        <div 
          onClick={() => setStatusFilter('unmapped')}
          className={`p-4 rounded-xl border cursor-pointer transition-all ${
            statusFilter === 'unmapped'
              ? 'bg-amber-500/10 border-amber-500/40 ring-1 ring-amber-500/30'
              : 'bg-white dark:bg-neutral-900 border-neutral-200 dark:border-neutral-800 hover:border-amber-400/50'
          }`}
        >
          <div className="flex items-center justify-between text-neutral-500 mb-1">
            <span className="text-xs font-semibold text-amber-600 dark:text-amber-400 flex items-center gap-1">
              <AlertCircle className="w-3.5 h-3.5" />
              Action Required (Unmapped)
            </span>
          </div>
          <div className="text-2xl font-black text-amber-600 dark:text-amber-400">
            {unmappedVariantsCount} <span className="text-xs font-normal text-neutral-500">Variants</span>
          </div>
          <p className="text-[11px] text-neutral-400 mt-1">
            {readySuggestionsCount} with AI SKU recommendations
          </p>
        </div>

        <div 
          onClick={() => setStatusFilter('mapped')}
          className={`p-4 rounded-xl border cursor-pointer transition-all ${
            statusFilter === 'mapped'
              ? 'bg-emerald-500/10 border-emerald-500/40 ring-1 ring-emerald-500/30'
              : 'bg-white dark:bg-neutral-900 border-neutral-200 dark:border-neutral-800 hover:border-emerald-400/50'
          }`}
        >
          <div className="flex items-center justify-between text-neutral-500 mb-1">
            <span className="text-xs font-semibold text-emerald-600 dark:text-emerald-400 flex items-center gap-1">
              <CheckCircle2 className="w-3.5 h-3.5" />
              Mapped & Healthy
            </span>
          </div>
          <div className="text-2xl font-black text-emerald-600 dark:text-emerald-400">
            {mappedVariantsCount} <span className="text-xs font-normal text-neutral-500">/ {totalVariants}</span>
          </div>
          <div className="w-full bg-neutral-100 dark:bg-neutral-800 h-1.5 rounded-full mt-2 overflow-hidden">
            <div 
              className="bg-emerald-500 h-full rounded-full transition-all duration-500"
              style={{ width: `${totalVariants > 0 ? (mappedVariantsCount / totalVariants) * 100 : 0}%` }}
            />
          </div>
        </div>

        <div 
          onClick={() => {if(!serverPage)setStatusFilter('push_candidates');}}
          className={`p-4 rounded-xl border cursor-pointer transition-all ${
            statusFilter === 'push_candidates'
              ? 'bg-indigo-500/10 border-indigo-500/40 ring-1 ring-indigo-500/30'
              : 'bg-white dark:bg-neutral-900 border-neutral-200 dark:border-neutral-800 hover:border-indigo-400/50'
          }`}
        >
          <div className="flex items-center justify-between text-neutral-500 mb-1">
            <span className="text-xs font-semibold text-indigo-600 dark:text-indigo-400 flex items-center gap-1">
              <UploadCloud className="w-3.5 h-3.5" />
              Unlisted Master SKUs
            </span>
          </div>
          <div className="text-2xl font-black text-indigo-600 dark:text-indigo-400">
            {serverPage ? '—' : unlistedMasterProducts.length} <span className="text-xs font-normal text-neutral-500">Products</span>
          </div>
          <p className="text-[11px] text-neutral-400 mt-1">
            {serverPage ? 'Review JW products for store publishing' : 'Ready to Push to Marketplace Store'}
          </p>
        </div>

        <div 
          onClick={() => setStatusFilter('all')}
          className={`p-4 rounded-xl border cursor-pointer transition-all ${
            statusFilter === 'all'
              ? 'bg-neutral-100 dark:bg-neutral-800 border-neutral-400 dark:border-neutral-600'
              : 'bg-white dark:bg-neutral-900 border-neutral-200 dark:border-neutral-800 hover:border-neutral-400'
          }`}
        >
          <div className="flex items-center justify-between text-neutral-500 mb-1">
            <span className="text-xs font-semibold text-neutral-600 dark:text-neutral-400">Total Store Catalog</span>
            <Boxes className="w-4 h-4 text-neutral-400" />
          </div>
          <div className="text-2xl font-black text-neutral-900 dark:text-white">
            {serverPage?.summary.products ?? safeStoreProds.length} <span className="text-xs font-normal text-neutral-500">Products</span>
          </div>
          <p className="text-[11px] text-neutral-400 mt-1">
            Across {totalVariants} channel variants
          </p>
        </div>
      </div>

      {/* Platform & Store Filter Bar */}
      <div className="p-4 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 space-y-3.5">
        <div className="flex flex-col md:flex-row md:items-center justify-between gap-3">
          {/* Platform Tabs */}
          <div className="flex items-center gap-1.5 overflow-x-auto pb-1 md:pb-0">
            <button
              id="filter-platform-all"
              onClick={() => { setSelectedPlatform('all'); setSelectedConnectionId('all'); setCurrentPage(1); }}
              className={`px-3 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer whitespace-nowrap ${
                selectedPlatform === 'all'
                  ? 'bg-neutral-900 text-white dark:bg-white dark:text-neutral-900'
                  : 'bg-neutral-100 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400 hover:bg-neutral-200 dark:hover:bg-neutral-700'
              }`}
            >
              All Platforms {serverPage ? '' : `(${safeStoreProds.length})`}
            </button>
            {!serverPage && (            <button
              id="filter-platform-tiktok"
              onClick={() => { setSelectedPlatform('tiktok'); setSelectedConnectionId('all'); setCurrentPage(1); }}
              className={`flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer whitespace-nowrap ${
                selectedPlatform === 'tiktok'
                  ? 'bg-black text-white dark:bg-neutral-800 dark:border dark:border-neutral-700'
                  : 'bg-neutral-100 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400 hover:bg-neutral-200'
              }`}
            >
              <MarketplaceLogo platform="tiktok" size={15} />
              <span>TikTok Shop {serverPage ? '' : `(${safeStoreProds.filter(p => p.platform === 'tiktok').length})`}</span>
            </button>)}
            <button
              id="filter-platform-shopee"
              onClick={() => { setSelectedPlatform('shopee'); setSelectedConnectionId('all'); setCurrentPage(1); }}
              className={`flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer whitespace-nowrap ${
                selectedPlatform === 'shopee'
                  ? 'bg-[#EE4D2D] text-white shadow-xs'
                  : 'bg-neutral-100 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400 hover:bg-neutral-200'
              }`}
            >
              <MarketplaceLogo platform="shopee" size={15} />
              <span>Shopee {serverPage ? '' : `(${safeStoreProds.filter(p => p.platform === 'shopee').length})`}</span>
            </button>
            <button
              id="filter-platform-lazada"
              onClick={() => { setSelectedPlatform('lazada'); setSelectedConnectionId('all'); setCurrentPage(1); }}
              className={`flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer whitespace-nowrap ${
                selectedPlatform === 'lazada'
                  ? 'bg-[#0E1268] text-white shadow-xs'
                  : 'bg-neutral-100 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400 hover:bg-neutral-200'
              }`}
            >
              <MarketplaceLogo platform="lazada" size={15} />
              <span>Lazada {serverPage ? '' : `(${safeStoreProds.filter(p => p.platform === 'lazada').length})`}</span>
            </button>
            {!serverPage && (            <button
              id="filter-platform-shopify"
              onClick={() => { setSelectedPlatform('shopify'); setSelectedConnectionId('all'); setCurrentPage(1); }}
              className={`flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer whitespace-nowrap ${
                selectedPlatform === 'shopify'
                  ? 'bg-emerald-600 text-white shadow-xs'
                  : 'bg-neutral-100 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400 hover:bg-neutral-200'
              }`}
            >
              <MarketplaceLogo platform="shopify" size={15} />
              <span>Shopify {serverPage ? '' : `(${safeStoreProds.filter(p => p.platform === 'shopify').length})`}</span>
            </button>)}
          </div>

          {/* Specific Shop Dropdown & View Mode Switcher */}
          <div className="flex items-center gap-2.5">
            <div className="flex items-center gap-1.5">
              <Store className="w-4 h-4 text-neutral-400" />
              <select
                id="select-shop-connection"
                value={selectedConnectionId}
                onChange={(e) => { setSelectedConnectionId(e.target.value); setCurrentPage(1); }}
                className="text-xs px-3 py-1.5 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800 text-neutral-900 dark:text-white font-medium focus:ring-2 focus:ring-indigo-500 cursor-pointer"
              >
                <option value="all">
                  All Connected Stores ({connectionOptions.length})
                </option>
                {connectionOptions.map(conn => (
                  <option key={conn.id} value={conn.id}>
                    {conn.shopName} ({conn.platform.toUpperCase()})
                  </option>
                ))}
              </select>
            </div>

            {/* View Mode Switcher (Grouped Cards vs Dense Table) */}
            {statusFilter !== 'push_candidates' && (
              <div className="flex items-center p-0.5 rounded-xl bg-neutral-100 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700">
                <button
                  type="button"
                  onClick={() => setViewMode('grouped')}
                  title="Grouped Product View"
                  className={`p-1.5 rounded-lg text-xs font-semibold transition-all ${
                    viewMode === 'grouped'
                      ? 'bg-white dark:bg-neutral-700 text-indigo-600 dark:text-indigo-400 shadow-xs'
                      : 'text-neutral-500 hover:text-neutral-900 dark:hover:text-white'
                  }`}
                >
                  <LayoutGrid className="w-3.5 h-3.5" />
                </button>
                <button
                  type="button"
                  onClick={() => setViewMode('flat')}
                  title="Compact Flat Table View (High Density for 300+ items)"
                  className={`p-1.5 rounded-lg text-xs font-semibold transition-all ${
                    viewMode === 'flat'
                      ? 'bg-white dark:bg-neutral-700 text-indigo-600 dark:text-indigo-400 shadow-xs'
                      : 'text-neutral-500 hover:text-neutral-900 dark:hover:text-white'
                  }`}
                >
                  <List className="w-3.5 h-3.5" />
                </button>
              </div>
            )}
          </div>
        </div>

        {/* Search & Status Pill Filters */}
        <div className="flex flex-col md:flex-row md:items-center justify-between gap-3 pt-2 border-t border-neutral-100 dark:border-neutral-800">
          <div className="relative flex-1 max-w-md">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-neutral-400" />
            <input
              id="input-search-mapping-products"
              type="text"
              value={searchQuery}
              onChange={(e) => { setSearchQuery(e.target.value); setCurrentPage(1); }}
              placeholder="Search store product, external SKU, or mapped JW SKU..."
              className="w-full pl-9 pr-4 py-2 text-xs rounded-xl bg-neutral-50 dark:bg-neutral-800/80 border border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white placeholder-neutral-400 focus:outline-none focus:ring-2 focus:ring-indigo-500"
            />
          </div>

          {/* Status filter tabs */}
          <div className="flex items-center gap-1.5 overflow-x-auto">
            <button
              onClick={() => { setStatusFilter('unmapped'); setCurrentPage(1); }}
              className={`px-3 py-1 rounded-lg text-xs font-semibold whitespace-nowrap transition-colors cursor-pointer ${
                statusFilter === 'unmapped'
                  ? 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 font-bold ring-1 ring-amber-500/30'
                  : 'text-neutral-500 hover:text-amber-600'
              }`}
            >
              Action Required ({unmappedVariantsCount})
            </button>
            <button
              disabled={!!serverPage}
              onClick={() => { setStatusFilter('suggested'); setCurrentPage(1); }}
              className={`px-3 py-1 rounded-lg text-xs font-semibold whitespace-nowrap transition-colors cursor-pointer ${
                statusFilter === 'suggested'
                  ? 'bg-violet-100 text-violet-800 dark:bg-violet-950/60 dark:text-violet-300 font-bold'
                  : 'text-neutral-500 hover:text-violet-600'
              }`}
            >
              AI Suggestions ({readySuggestionsCount})
            </button>
            <button
              onClick={() => { setStatusFilter('mapped'); setCurrentPage(1); }}
              className={`px-3 py-1 rounded-lg text-xs font-semibold whitespace-nowrap transition-colors cursor-pointer ${
                statusFilter === 'mapped'
                  ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 font-bold'
                  : 'text-neutral-500 hover:text-emerald-600'
              }`}
            >
              Mapped ({mappedVariantsCount})
            </button>
            <button
              disabled={!!serverPage}
              onClick={() => { setStatusFilter('push_candidates'); setCurrentPage(1); }}
              className={`px-3 py-1 rounded-lg text-xs font-semibold whitespace-nowrap transition-colors cursor-pointer ${
                statusFilter === 'push_candidates'
                  ? 'bg-indigo-100 text-indigo-800 dark:bg-indigo-950/60 dark:text-indigo-300 font-bold'
                  : 'text-neutral-500 hover:text-indigo-600'
              }`}
            >
              Push to Store ({serverPage ? '—' : unlistedMasterProducts.length})
            </button>
            <button
              onClick={() => { setStatusFilter('all'); setCurrentPage(1); }}
              className={`px-3 py-1 rounded-lg text-xs font-semibold whitespace-nowrap transition-colors cursor-pointer ${
                statusFilter === 'all'
                  ? 'bg-neutral-800 text-white dark:bg-neutral-200 dark:text-neutral-900'
                  : 'text-neutral-500 hover:text-neutral-900 dark:hover:text-white'
              }`}
            >
              All Listings
            </button>
          </div>
        </div>
      </div>

      {/* Sticky Batch Operations Action Bar if items selected */}
      {selectedProductIds.size > 0 && (
        <div className="sticky top-4 z-20 p-3.5 rounded-2xl bg-indigo-950/95 dark:bg-indigo-900/90 text-white border border-indigo-700/80 shadow-xl backdrop-blur-md flex flex-col sm:flex-row items-center justify-between gap-3 animate-in fade-in slide-in-from-top-2">
          <div className="flex items-center gap-3">
            <span className="px-2.5 py-1 rounded-lg bg-indigo-800/80 text-indigo-200 text-xs font-bold font-mono">
              {selectedProductIds.size} Selected
            </span>
            <span className="text-xs text-indigo-100 font-medium">
              Apply bulk operations to selected store items:
            </span>
          </div>

          <div className="flex items-center gap-2">
            <button
              type="button"
              disabled={!!serverPage}
              onClick={handleBatchAcceptSuggestionsSelected}
              className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-violet-600 hover:bg-violet-500 text-white text-xs font-bold transition-colors cursor-pointer"
            >
              <Sparkles className="w-3.5 h-3.5" />
              Accept Suggestions
            </button>

            <button
              type="button"
              disabled={!!serverPage&&(!serverPage.canImport||serverPage.importing)}
              onClick={handleBatchImportSelected}
              className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold transition-colors cursor-pointer"
            >
              <DownloadCloud className="w-3.5 h-3.5" />
              Batch Import to JW Master
            </button>

            <button
              type="button"
              onClick={() => setSelectedProductIds(new Set())}
              className="px-2.5 py-1.5 rounded-xl bg-indigo-900/60 hover:bg-indigo-800 text-indigo-200 text-xs transition-colors cursor-pointer"
            >
              Clear Selection
            </button>
          </div>
        </div>
      )}

      {/* Main Content Area */}
      {statusFilter === 'push_candidates' ? (
        /* ========================================================================= */
        /* TAB: PUSH TO STORE (Unlisted JW Master Products)                         */
        /* ========================================================================= */
        <div className="space-y-4">
          <div className="p-4 rounded-2xl bg-indigo-50/50 dark:bg-indigo-950/20 border border-indigo-200 dark:border-indigo-900/50 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
              <h3 className="text-sm font-bold text-neutral-900 dark:text-white flex items-center gap-2">
                <UploadCloud className="w-4 h-4 text-indigo-600 dark:text-indigo-400" />
                Unlisted JW Master Products ({unlistedMasterProducts.length})
              </h3>
              <p className="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">
                These products exist in your JW Ecommerce catalog but have not yet been published to{' '}
                <strong>{selectedConnectionId === 'all' ? 'one or more connected stores' : selectedConnectionObj?.shopName}</strong>.
              </p>
            </div>
            <span className="text-xs text-indigo-600 dark:text-indigo-400 font-semibold shrink-0">
              Target: {selectedConnectionId === 'all' ? 'All Channels' : selectedConnectionObj?.shopName}
            </span>
          </div>

          {unlistedMasterProducts.length === 0 ? (
            <div className="p-12 text-center rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800">
              <CheckCircle2 className="w-12 h-12 mx-auto text-emerald-500 mb-3" />
              <h3 className="text-sm font-bold text-neutral-900 dark:text-white">
                All JW Master Products are listed!
              </h3>
              <p className="text-xs text-neutral-500 dark:text-neutral-400 mt-1">
                Every product in your JW Ecommerce catalog has an active binding on the selected channel.
              </p>
            </div>
          ) : (
            <div className="rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 overflow-hidden shadow-sm">
              <table className="w-full text-left text-xs">
                <thead className="bg-neutral-50 dark:bg-neutral-800/60 text-neutral-500 font-semibold border-b border-neutral-200 dark:border-neutral-800">
                  <tr>
                    <th className="py-3 px-4">Master Product</th>
                    <th className="py-3 px-3">Brand & Category</th>
                    <th className="py-3 px-3">SKU Count</th>
                    <th className="py-3 px-3">Base Price</th>
                    <th className="py-3 px-3">Available ATP Stock</th>
                    <th className="py-3 px-4 text-right">Action</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800">
                  {paginatedUnlistedMasterProducts.map(p => (
                    <tr key={p.id} className="hover:bg-neutral-50/70 dark:hover:bg-neutral-800/30 transition-colors">
                      <td className="py-3 px-4">
                        <div className="flex items-center gap-3">
                          <img
                            src={p.images[0] || 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=80'}
                            alt={p.title}
                            className="w-10 h-10 rounded-lg object-cover border border-neutral-200 dark:border-neutral-700 shrink-0"
                          />
                          <div>
                            <div className="font-bold text-neutral-900 dark:text-white">{p.title}</div>
                            <div className="text-[11px] text-neutral-400 font-mono">
                              SKU: {p.variants[0]?.sku || 'JW-ITEM'}
                            </div>
                          </div>
                        </div>
                      </td>
                      <td className="py-3 px-3 text-neutral-600 dark:text-neutral-400">
                        {p.brand || 'JW Audio'}
                      </td>
                      <td className="py-3 px-3">
                        <span className="px-2 py-0.5 rounded-full bg-neutral-100 dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300 font-medium">
                          {p.variants.length} variant(s)
                        </span>
                      </td>
                      <td className="py-3 px-3 font-semibold text-neutral-900 dark:text-white">
                        RM {p.basePrice.toFixed(2)}
                      </td>
                      <td className="py-3 px-3">
                        <span className="font-bold text-emerald-600 dark:text-emerald-400">
                          {p.totalInventory} units
                        </span>
                      </td>
                      <td className="py-3 px-4 text-right">
                        <button
                          type="button"
                          onClick={() => setActivePublishModal({
                            isOpen: true,
                            productId: p.id,
                            connectionId: selectedConnectionId === 'all' ? undefined : selectedConnectionId
                          })}
                          className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold transition-all shadow-xs cursor-pointer"
                        >
                          <UploadCloud className="w-3.5 h-3.5" />
                          Publish to Store
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      ) : viewMode === 'flat' ? (
        /* ========================================================================= */
        /* VIEW MODE: FLAT COMPACT TABLE (High Density for 300+ items)              */
        /* ========================================================================= */
        <div className="space-y-4">
          <div className="rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 overflow-hidden shadow-sm">
            <div className="p-3 bg-neutral-50/80 dark:bg-neutral-800/40 border-b border-neutral-200 dark:border-neutral-800 flex items-center justify-between text-xs text-neutral-500 font-medium">
              <span>Showing flat variant rows ({flatVariants.length} total)</span>
              <span>Click Link or Import to resolve mappings</span>
            </div>
            <table className="w-full text-left text-xs">
              <thead className="bg-neutral-50 dark:bg-neutral-800/60 text-neutral-500 font-semibold border-b border-neutral-200 dark:border-neutral-800">
                <tr>
                  <th className="py-2.5 px-4">Channel Listing & Variant</th>
                  <th className="py-2.5 px-3">Store Platform</th>
                  <th className="py-2.5 px-3">Channel Price & Stock</th>
                  <th className="py-2.5 px-3">Status</th>
                  <th className="py-2.5 px-4">Mapped JW Master SKU</th>
                  <th className="py-2.5 px-4 text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800">
                {paginatedFlatVariants.length === 0 ? (
                  <tr>
                    <td colSpan={6} className="py-10 text-center text-neutral-400">
                      No matching variants found.
                    </td>
                  </tr>
                ) : (
                  paginatedFlatVariants.map(({ product, variant }) => {
                    const isMapped = variant.mappingStatus === 'mapped';
                    const isUnmapped = variant.mappingStatus === 'unmapped';
                    const isConflict = variant.mappingStatus === 'conflict' || variant.mappingStatus === 'invalid';

                    return (
                      <tr key={variant.id} className="hover:bg-neutral-50/70 dark:hover:bg-neutral-800/30 transition-colors">
                        <td className="py-3 px-4">
                          <div className="flex items-center gap-2.5">
                            <img
                              src={variant.imageUrl || product.imageUrl}
                              alt={variant.title}
                              className="w-9 h-9 rounded-lg object-cover border border-neutral-200 dark:border-neutral-700 shrink-0"
                            />
                            <div>
                              <div className="font-bold text-neutral-900 dark:text-white">
                                {variant.title}
                              </div>
                              <div className="text-[11px] text-neutral-500 truncate max-w-xs">
                                {product.title}
                              </div>
                              <div className="font-mono text-[10px] text-neutral-400 mt-0.5">
                                SKU: {variant.channelSellerSku}
                              </div>
                            </div>
                          </div>
                        </td>
                        <td className="py-3 px-3">
                          <MarketplaceLogo platform={product.platform} variant="badge" />
                          <div className="text-[10px] text-neutral-400 mt-1 truncate max-w-[120px]">
                            {product.shopName}
                          </div>
                        </td>
                        <td className="py-3 px-3">
                          <div className="font-semibold text-neutral-900 dark:text-white">
                            {variant.currency} {variant.price?.toFixed(2) ?? '—'}
                          </div>
                          <div className="text-[11px] text-neutral-500">
                            Stock: <strong>{variant.stock ?? '—'}</strong> units
                          </div>
                        </td>
                        <td className="py-3 px-3 whitespace-nowrap">
                          {isMapped && (
                            <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                              <CheckCircle2 className="w-3 h-3 text-emerald-500" />
                              Mapped
                            </span>
                          )}
                          {isUnmapped && (
                            <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                              <AlertCircle className="w-3 h-3 text-amber-500" />
                              Unmapped
                            </span>
                          )}
                          {isConflict && (
                            <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-50 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800">
                              <AlertTriangle className="w-3 h-3 text-rose-500" />
                              Conflict
                            </span>
                          )}
                        </td>
                        <td className="py-3 px-4">
                          {isMapped ? (
                            <div>
                              <div className="font-bold text-neutral-900 dark:text-white">
                                {variant.mappedJwProductName} ({variant.mappedJwVariantTitle})
                              </div>
                              <div className="font-mono text-indigo-600 dark:text-indigo-400 font-bold text-[11px]">
                                {variant.mappedJwSku}
                              </div>
                            </div>
                          ) : variant.suggestedMatch ? (
                            <div className="flex items-center gap-2">
                              <span className="text-[11px] text-indigo-600 dark:text-indigo-400 font-medium">
                                Suggest: <strong>{variant.suggestedMatch.sku}</strong> ({variant.suggestedMatch.confidenceScore}%)
                              </span>
                              <button
                                type="button"
                                onClick={() => mapStoreVariantToJwSku(product.id, variant.id, variant.suggestedMatch!.productId, variant.suggestedMatch!.variantId)}
                                className="px-2 py-0.5 rounded bg-indigo-600 text-white text-[10px] font-bold hover:bg-indigo-700 cursor-pointer"
                              >
                                Accept
                              </button>
                            </div>
                          ) : (
                            <span className="text-neutral-400 italic text-[11px]">Not linked</span>
                          )}
                        </td>
                        <td className="py-3 px-4 text-right whitespace-nowrap">
                          <div className="inline-flex items-center gap-1.5 justify-end">
                            {isMapped ? (
                              <>
                                <button
                                  type="button"
                                  onClick={() => setActiveMappingModal({ storeProduct: product, storeVariant: variant })}
                                  className="px-2.5 py-1 rounded-lg border border-neutral-200 dark:border-neutral-700 text-neutral-700 dark:text-neutral-300 text-xs font-semibold hover:bg-neutral-100 dark:hover:bg-neutral-800 cursor-pointer"
                                >
                                  Edit
                                </button>
                                <button
                                  type="button"
                                  onClick={() => unmapStoreVariant(product.id, variant.id)}
                                  className="p-1 rounded-lg text-neutral-400 hover:text-rose-600 cursor-pointer"
                                >
                                  <Unlink className="w-3.5 h-3.5" />
                                </button>
                              </>
                            ) : (
                              <>
                                <button
                                  type="button"
                                  onClick={() => setActiveMappingModal({ storeProduct: product, storeVariant: variant })}
                                  className="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold cursor-pointer"
                                >
                                  <Link2 className="w-3.5 h-3.5" />
                                  Link
                                </button>
                                <button
                                  type="button"
                                  disabled={!!serverPage && (!serverPage.canImport || !renderVariantModal)}
                                  onClick={() => serverPage ? setActiveMappingModal({storeProduct:product,storeVariant:variant,intent:'import'}) : importStoreProductToMaster(product.id, variant.id)}
                                  title="Import this variant into JW Master Catalog and auto-link"
                                  className="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold cursor-pointer"
                                >
                                  <DownloadCloud className="w-3.5 h-3.5" />
                                  Import
                                </button>
                              </>
                            )}
                          </div>
                        </td>
                      </tr>
                    );
                  })
                )}
              </tbody>
            </table>
          </div>
        </div>
      ) : (
        /* ========================================================================= */
        /* VIEW MODE: GROUPED CARDS (With Batch Checkboxes & 1-click product import) */
        /* ========================================================================= */
        <div className="space-y-4">
          {/* Select All on Page bar */}
          {paginatedProducts.length > 0 && (
            <div className="flex items-center justify-between px-2 text-xs text-neutral-500">
              <label className="flex items-center gap-2 cursor-pointer font-medium hover:text-neutral-900 dark:hover:text-white">
                <input
                  type="checkbox"
                  checked={selectedProductIds.size === paginatedProducts.length && paginatedProducts.length > 0}
                  onChange={handleToggleSelectAll}
                  className="rounded border-neutral-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer"
                />
                <span>Select all {paginatedProducts.length} products on this page</span>
              </label>

              <span className="text-[11px] text-neutral-400">
                Defaulting to Action Required. Switch filters to view mapped items.
              </span>
            </div>
          )}

          {paginatedProducts.length === 0 ? (
            <div className="p-12 text-center rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800">
              <Boxes className="w-12 h-12 mx-auto text-neutral-300 dark:text-neutral-700 mb-3" />
              <h3 className="text-sm font-bold text-neutral-900 dark:text-white">
                No matching store products found
              </h3>
              <p className="text-xs text-neutral-500 dark:text-neutral-400 mt-1 max-w-sm mx-auto">
                No store products or variants match your active filter criteria. Try changing filters or clearing search.
              </p>
              <button
                onClick={() => {
                  setSelectedPlatform('all');
                  setSelectedConnectionId('all');
                  setStatusFilter('all');
                  setSearchQuery('');
                  setCurrentPage(1);
                }}
                className="mt-4 px-4 py-2 rounded-xl bg-neutral-100 dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300 text-xs font-bold hover:bg-neutral-200 transition-colors cursor-pointer"
              >
                Reset All Filters
              </button>
            </div>
          ) : (
            paginatedProducts.map(prod => {
              const mappedCount = prod.variants.filter(v => v.mappingStatus === 'mapped').length;
              const totalCount = prod.variants.length;
              const isFullyMapped = mappedCount === totalCount && totalCount > 0;
              const hasUnmapped = prod.variants.some(v => v.mappingStatus === 'unmapped');
              const isSelected = selectedProductIds.has(prod.id);

              const platformBadge = {
                tiktok: { label: 'TikTok Shop', class: 'bg-black text-white dark:bg-neutral-800 dark:border-neutral-700' },
                shopee: { label: 'Shopee', class: 'bg-orange-500 text-white' },
                lazada: { label: 'Lazada', class: 'bg-blue-600 text-white' },
                shopify: { label: 'Shopify', class: 'bg-emerald-600 text-white' },
                direct: { label: 'Direct', class: 'bg-neutral-700 text-white' }
              }[prod.platform];

              return (
                <div
                  key={prod.id}
                  id={`store-product-${prod.id}`}
                  className={`rounded-2xl bg-white dark:bg-neutral-900 border transition-all shadow-sm overflow-hidden ${
                    isSelected
                      ? 'border-indigo-500/60 ring-1 ring-indigo-500/30'
                      : 'border-neutral-200 dark:border-neutral-800'
                  }`}
                >
                  {/* Product Header */}
                  <div className="p-4 bg-neutral-50/60 dark:bg-neutral-800/40 border-b border-neutral-200 dark:border-neutral-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div className="flex items-start gap-3">
                      {/* Checkbox */}
                      <input
                        type="checkbox"
                        checked={isSelected}
                        onChange={() => handleToggleSelectProduct(prod.id)}
                        className="mt-1 rounded border-neutral-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer"
                      />

                      <img
                        src={prod.imageUrl}
                        alt={prod.title}
                        className="w-12 h-12 rounded-xl object-cover border border-neutral-200 dark:border-neutral-700 flex-shrink-0"
                      />
                      <div>
                        <div className="flex items-center gap-2 flex-wrap">
                          <MarketplaceLogo platform={prod.platform} variant="badge" />
                          <span className="text-xs text-neutral-500 dark:text-neutral-400 font-medium">
                            Store: {prod.shopName}
                          </span>
                          <span className="font-mono text-[10px] text-neutral-400">
                            ID: {prod.channelProductId}
                          </span>
                        </div>
                        <h3 className="text-sm font-bold text-neutral-900 dark:text-white mt-1">
                          {prod.title}
                        </h3>
                        <p className="text-[11px] text-neutral-400 mt-0.5">
                          Category: {prod.categoryName} · Updated at {new Date(prod.updatedAt).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                        </p>
                      </div>
                    </div>

                    {/* Mapping Progress Indicator & 1-click Import Product to Master */}
                    <div className="flex items-center gap-3 sm:self-center flex-wrap">
                      {hasUnmapped && (
                        <button
                          type="button"
                          disabled={!!serverPage&&(!serverPage.canImport||serverPage.importing)}
                          onClick={() => serverPage ? void serverPage.onImport?.([prod.id]) : importStoreProductToMaster(prod.id)}
                          className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold transition-all shadow-xs cursor-pointer"
                        >
                          <DownloadCloud className="w-3.5 h-3.5" />
                          {serverPage?.importing ? 'Importing…' : 'Import Product to JW Master'}
                        </button>
                      )}

                      <div className="text-right">
                        <div className="text-xs font-bold text-neutral-700 dark:text-neutral-300">
                          {mappedCount} / {totalCount} Variants Mapped
                        </div>
                        <div className="w-24 bg-neutral-200 dark:bg-neutral-700 h-1.5 rounded-full mt-1 overflow-hidden">
                          <div
                            className={`h-full rounded-full transition-all ${
                              isFullyMapped ? 'bg-emerald-500' : 'bg-amber-500'
                            }`}
                            style={{ width: `${totalCount > 0 ? (mappedCount / totalCount) * 100 : 0}%` }}
                          />
                        </div>
                      </div>

                      {isFullyMapped ? (
                        <span className="p-1.5 rounded-full bg-emerald-100 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400">
                          <Check className="w-4 h-4" />
                        </span>
                      ) : (
                        <span className="p-1.5 rounded-full bg-amber-100 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400">
                          <AlertCircle className="w-4 h-4" />
                        </span>
                      )}
                    </div>
                  </div>

                  {/* Variants Table */}
                  <div className="overflow-x-auto">
                    <table className="w-full text-left text-xs">
                      <thead className="bg-neutral-50 dark:bg-neutral-800/60 text-neutral-500 font-semibold border-b border-neutral-200 dark:border-neutral-800">
                        <tr>
                          <th className="py-2.5 px-4">Channel Product Variant</th>
                          <th className="py-2.5 px-3">Price & Stock</th>
                          <th className="py-2.5 px-3">Mapping Status</th>
                          <th className="py-2.5 px-4">Mapped JW Master Variant (Local SKU)</th>
                          <th className="py-2.5 px-4 text-right">Actions</th>
                        </tr>
                      </thead>
                      <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800/80">
                        {prod.variants.map(variant => {
                          const isMapped = variant.mappingStatus === 'mapped';
                          const isConflict = variant.mappingStatus === 'conflict' || variant.mappingStatus === 'invalid';
                          const isUnmapped = variant.mappingStatus === 'unmapped';

                          return (
                            <tr 
                              key={variant.id}
                              className="hover:bg-neutral-50/70 dark:hover:bg-neutral-800/30 transition-colors"
                            >
                              {/* External Variant Specs */}
                              <td className="py-3 px-4">
                                <div className="flex items-center gap-2.5">
                                  <img
                                    src={variant.imageUrl || prod.imageUrl}
                                    alt={variant.title}
                                    className="w-9 h-9 rounded-lg object-cover border border-neutral-200 dark:border-neutral-700 flex-shrink-0"
                                  />
                                  <div>
                                    <div className="font-bold text-neutral-900 dark:text-white">
                                      {variant.title}
                                    </div>
                                    <div className="flex items-center gap-2 mt-0.5">
                                      <span className="font-mono text-[10px] text-neutral-500">
                                        SKU: {variant.channelSellerSku}
                                      </span>
                                    </div>
                                  </div>
                                </div>
                              </td>

                              {/* Price & Stock */}
                              <td className="py-3 px-3">
                                <div className="font-semibold text-neutral-900 dark:text-white">
                                  {variant.currency} {variant.price?.toFixed(2) ?? '—'}
                                </div>
                                <div className="text-[11px] text-neutral-500">
                                  Channel Stock: <strong className="text-neutral-700 dark:text-neutral-300">{variant.stock ?? '—'}</strong> units
                                </div>
                              </td>

                              {/* Mapping Status */}
                              <td className="py-3 px-3 whitespace-nowrap">
                                {isMapped && (
                                  <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                                    <CheckCircle2 className="w-3 h-3 text-emerald-500" />
                                    Mapped
                                  </span>
                                )}
                                {isUnmapped && (
                                  <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                                    <AlertCircle className="w-3 h-3 text-amber-500" />
                                    Unmapped
                                  </span>
                                )}
                                {isConflict && (
                                  <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-rose-50 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800">
                                    <AlertTriangle className="w-3 h-3 text-rose-500" />
                                    Conflict
                                  </span>
                                )}
                              </td>

                              {/* Mapped JW Variant or Smart Suggestion */}
                              <td className="py-3 px-4">
                                {isMapped && (
                                  <div className="flex items-center gap-2.5">
                                    {variant.mappedJwVariantImage && (
                                      <img
                                        src={variant.mappedJwVariantImage}
                                        alt={variant.mappedJwVariantTitle}
                                        className="w-8 h-8 rounded-lg object-cover border border-neutral-200 dark:border-neutral-700 flex-shrink-0"
                                      />
                                    )}
                                    <div>
                                      <div className="font-bold text-neutral-900 dark:text-white flex items-center gap-1.5">
                                        <span>{variant.mappedJwProductName}</span>
                                        <span className="text-neutral-400">·</span>
                                        <span className="text-neutral-600 dark:text-neutral-300">{variant.mappedJwVariantTitle}</span>
                                      </div>
                                      <div className="flex items-center gap-3 text-[11px] text-neutral-500 mt-0.5">
                                        <span className="font-mono text-indigo-600 dark:text-indigo-400 font-bold">
                                          {variant.mappedJwSku}
                                        </span>
                                        <span>JW Legacy Stock: <strong>{variant.mappedJwStock ?? '--'}</strong> units</span>
                                        <span>Price: RM {variant.mappedJwPrice?.toFixed(2) ?? '--'}</span>
                                      </div>
                                    </div>
                                  </div>
                                )}

                                {isUnmapped && variant.suggestedMatch && (
                                  <div className="p-2.5 rounded-xl bg-indigo-50/70 dark:bg-indigo-950/40 border border-indigo-200 dark:border-indigo-900/60">
                                    <div className="flex items-center justify-between gap-2">
                                      <div className="flex items-center gap-1.5">
                                        <Sparkles className="w-3.5 h-3.5 text-indigo-600 dark:text-indigo-400" />
                                        <span className="font-bold text-indigo-900 dark:text-indigo-300 text-xs">
                                          Suggested: {variant.suggestedMatch.sku} ({variant.suggestedMatch.variantTitle})
                                        </span>
                                        <span className="text-[10px] px-1.5 py-0.2 rounded bg-indigo-100 dark:bg-indigo-900 text-indigo-700 dark:text-indigo-300 font-bold">
                                          {variant.suggestedMatch.confidenceScore}% Match
                                        </span>
                                      </div>
                                      <button
                                        id={`btn-accept-suggestion-${variant.id}`}
                                        type="button"
                                        onClick={() => {
                                          if (variant.suggestedMatch) {
                                            mapStoreVariantToJwSku(
                                              prod.id,
                                              variant.id,
                                              variant.suggestedMatch.productId,
                                              variant.suggestedMatch.variantId
                                            );
                                          }
                                        }}
                                        className="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-[11px] font-bold shadow-xs transition-colors cursor-pointer"
                                      >
                                        <Check className="w-3 h-3" />
                                        Accept
                                      </button>
                                    </div>
                                    <p className="text-[11px] text-neutral-600 dark:text-neutral-400 mt-1">
                                      {variant.suggestedMatch.matchReason}
                                    </p>
                                  </div>
                                )}

                                {isUnmapped && !variant.suggestedMatch && (
                                  <span className="text-neutral-400 italic">
                                    Unlinked. Click Link to match existing SKU or Import as new Master SKU.
                                  </span>
                                )}

                                {isConflict && (
                                  <div className="p-2 rounded-lg bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900 text-rose-700 dark:text-rose-300 text-[11px]">
                                    <strong>Mapping Issue: </strong>{variant.conflictReason || 'Linked master SKU no longer exists. Please re-assign.'}
                                  </div>
                                )}
                              </td>

                              {/* Actions Column */}
                              <td className="py-3 px-4 text-right whitespace-nowrap">
                                <div className="inline-flex items-center gap-1.5 justify-end">
                                  {isMapped && (
                                    <>
                                      <button
                                        id={`btn-edit-mapping-${variant.id}`}
                                        type="button"
                                        onClick={() => setActiveMappingModal({ storeProduct: prod, storeVariant: variant })}
                                        className="px-2.5 py-1.5 rounded-lg border border-neutral-200 dark:border-neutral-700 hover:bg-neutral-100 dark:hover:bg-neutral-800 text-neutral-700 dark:text-neutral-300 text-xs font-semibold transition-colors cursor-pointer"
                                      >
                                        Change Link
                                      </button>
                                      <button
                                        id={`btn-unlink-${variant.id}`}
                                        type="button"
                                        onClick={() => unmapStoreVariant(prod.id, variant.id)}
                                        title="Unlink variant"
                                        className="p-1.5 rounded-lg text-neutral-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition-colors cursor-pointer"
                                      >
                                        <Unlink className="w-3.5 h-3.5" />
                                      </button>
                                    </>
                                  )}

                                  {isUnmapped && (
                                    <>
                                      <button
                                        id={`btn-link-spec-${variant.id}`}
                                        type="button"
                                        onClick={() => setActiveMappingModal({ storeProduct: prod, storeVariant: variant })}
                                        className="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold transition-all shadow-sm cursor-pointer"
                                      >
                                        <Link2 className="w-3.5 h-3.5" />
                                        Link Variant
                                      </button>
                                      <button
                                        id={`btn-import-spec-${variant.id}`}
                                        type="button"
                                        disabled={!!serverPage && (!serverPage.canImport || !renderVariantModal)}
                                        onClick={() => serverPage ? setActiveMappingModal({storeProduct:prod,storeVariant:variant,intent:'import'}) : importStoreProductToMaster(prod.id, variant.id)}
                                        title="Create this variant as a JW Master SKU and auto-link"
                                        className="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition-all shadow-sm cursor-pointer"
                                      >
                                        <DownloadCloud className="w-3.5 h-3.5" />
                                        Import
                                      </button>
                                    </>
                                  )}

                                  {isConflict && (
                                    <button
                                      id={`btn-fix-conflict-${variant.id}`}
                                      type="button"
                                      onClick={() => setActiveMappingModal({ storeProduct: prod, storeVariant: variant })}
                                      className="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold transition-all shadow-sm cursor-pointer"
                                    >
                                      <AlertTriangle className="w-3.5 h-3.5" />
                                      Fix Link
                                    </button>
                                  )}
                                </div>
                              </td>
                            </tr>
                          );
                        })}
                      </tbody>
                    </table>
                  </div>
                </div>
              );
            })
          )}
        </div>
      )}

      {/* Pagination Footer (Crucial for 300+ items catalog performance) */}
      {totalItemCount > 0 && (
        <div className="p-4 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 flex flex-col sm:flex-row sm:items-center justify-between gap-4 text-xs">
          <div className="flex items-center gap-3 text-neutral-500">
            <span>
              Showing{' '}
              <strong className="text-neutral-900 dark:text-white">
                {(currentPage - 1) * pageSize + 1}
              </strong>{' '}
              –{' '}
              <strong className="text-neutral-900 dark:text-white">
                {Math.min(currentPage * pageSize, totalItemCount)}
              </strong>{' '}
              of <strong className="text-neutral-900 dark:text-white">{totalItemCount}</strong> items
            </span>

            <div className="flex items-center gap-1.5 border-l border-neutral-200 dark:border-neutral-800 pl-3">
              <span>Per page:</span>
              <select
                value={pageSize}
                onChange={e => {
                  setPageSize(Number(e.target.value));
                  setCurrentPage(1);
                }}
                className="px-2 py-1 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800 text-neutral-900 dark:text-white font-medium cursor-pointer"
              >
                <option value={10}>10</option>
                <option value={15}>15</option>
                <option value={30}>30</option>
                <option value={50}>50</option>
                <option value={100}>100</option>
              </select>
            </div>

            <span className="hidden xl:inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-neutral-100 dark:bg-neutral-800 font-mono text-[10px] text-neutral-500 border border-neutral-200 dark:border-neutral-700/60">
              {serverPage ? '' : `Laravel API: /api/v1/marketplace/products?page=${currentPage}&per_page=${pageSize}`}
            </span>
          </div>

          <div className="flex items-center gap-1 self-center sm:self-auto">
            <button
              type="button"
              disabled={currentPage === 1}
              onClick={() => setCurrentPage(1)}
              className="p-1.5 rounded-lg border border-neutral-200 dark:border-neutral-700 disabled:opacity-30 hover:bg-neutral-100 dark:hover:bg-neutral-800 text-neutral-600 dark:text-neutral-400 cursor-pointer disabled:cursor-not-allowed"
            >
              <ChevronsLeft className="w-4 h-4" />
            </button>
            <button
              type="button"
              disabled={currentPage === 1}
              onClick={() => setCurrentPage(p => Math.max(1, p - 1))}
              className="p-1.5 rounded-lg border border-neutral-200 dark:border-neutral-700 disabled:opacity-30 hover:bg-neutral-100 dark:hover:bg-neutral-800 text-neutral-600 dark:text-neutral-400 cursor-pointer disabled:cursor-not-allowed"
            >
              <ChevronLeft className="w-4 h-4" />
            </button>

            <div className="px-3 py-1 font-semibold text-neutral-700 dark:text-neutral-300">
              Page {currentPage} of {totalPages}
            </div>

            <button
              type="button"
              disabled={currentPage === totalPages}
              onClick={() => setCurrentPage(p => Math.min(totalPages, p + 1))}
              className="p-1.5 rounded-lg border border-neutral-200 dark:border-neutral-700 disabled:opacity-30 hover:bg-neutral-100 dark:hover:bg-neutral-800 text-neutral-600 dark:text-neutral-400 cursor-pointer disabled:cursor-not-allowed"
            >
              <ChevronRight className="w-4 h-4" />
            </button>
            <button
              type="button"
              disabled={currentPage === totalPages}
              onClick={() => setCurrentPage(totalPages)}
              className="p-1.5 rounded-lg border border-neutral-200 dark:border-neutral-700 disabled:opacity-30 hover:bg-neutral-100 dark:hover:bg-neutral-800 text-neutral-600 dark:text-neutral-400 cursor-pointer disabled:cursor-not-allowed"
            >
              <ChevronsRight className="w-4 h-4" />
            </button>
          </div>
        </div>
      )}

      {/* Product Mapping Modal */}
      {activeMappingModal && (renderVariantModal ? renderVariantModal(activeMappingModal,()=>setActiveMappingModal(null)) : (
        <ProductMappingModal
          isOpen={Boolean(activeMappingModal)}
          onClose={() => setActiveMappingModal(null)}
          storeProduct={activeMappingModal.storeProduct}
          storeVariant={activeMappingModal.storeVariant}
          checkConflict={serverPage ? checkMarketplaceMappingConflict : undefined}
        />
      ))}

      {/* Publish Master Product to Store Modal */}
      <PublishProductModal
        isOpen={activePublishModal.isOpen}
        onClose={() => setActivePublishModal({ isOpen: false })}
        preselectedProductId={activePublishModal.productId}
        preselectedConnectionId={activePublishModal.connectionId}
      />
    </div>
  );
};
