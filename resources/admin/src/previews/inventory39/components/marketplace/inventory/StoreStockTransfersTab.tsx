import React, { useState, useMemo, useRef, useEffect } from 'react';
import {
  ArrowRightLeft,
  Store,
  Boxes,
  CheckCircle2,
  AlertTriangle,
  RefreshCw,
  Search,
  Filter,
  ShieldCheck,
  Calendar,
  User,
  Info,
  ChevronRight,
  ChevronLeft,
  Sparkles,
  Layers,
  ArrowRight,
  X,
  ChevronsUpDown,
  Check,
  Package,
  SlidersHorizontal,
  Plus
} from 'lucide-react';
import { MarketplaceInventoryItem } from '../../../types';
import { InventoryStoreChannel, StoreStockAccountBalance, StoreStockTransferRecord } from './inventoryTypes';
import { MarketplaceLogo } from '../MarketplaceLogo';

interface StoreStockTransfersTabProps {
  inventory: MarketplaceInventoryItem[];
  stores: InventoryStoreChannel[];
  storeBalances: Record<string, Record<string, StoreStockAccountBalance>>;
  transfersList: StoreStockTransferRecord[];
  onExecuteTransfer: (transfer: Omit<StoreStockTransferRecord, 'id' | 'transferNumber' | 'timestamp' | 'ledgerStatus' | 'sourcePlatformSyncStatus' | 'destinationPlatformSyncStatus'>) => Promise<StoreStockTransferRecord>;
  onRetryPlatformSync: (transferId: string, target: 'source' | 'destination') => Promise<void>;
}

export const StoreStockTransfersTab: React.FC<StoreStockTransfersTabProps> = ({
  inventory,
  stores,
  storeBalances,
  transfersList,
  onExecuteTransfer,
  onRetryPlatformSync
}) => {
  // Form State
  const [selectedSku, setSelectedSku] = useState<string>(inventory[0]?.masterSku || '');
  const [sourceStoreId, setSourceStoreId] = useState<string>('');
  const [destinationStoreId, setDestinationStoreId] = useState<string>('');
  const [quantityInput, setQuantityInput] = useState<string>('1');
  const [reason, setReason] = useState<string>('Store Stock Rebalance');
  const [notes, setNotes] = useState<string>('');
  const [operator, setOperator] = useState<string>('Alexander (Admin)');

  // Quick Combobox Search State
  const [skuSearchQuery, setSkuSearchQuery] = useState<string>('');
  const [isSkuDropdownOpen, setIsSkuDropdownOpen] = useState<boolean>(false);
  const comboboxRef = useRef<HTMLDivElement>(null);

  // Catalog Browser Modal State (for 1000+ products / 5000+ SKUs)
  const [isCatalogModalOpen, setIsCatalogModalOpen] = useState<boolean>(false);
  const [modalSearch, setModalSearch] = useState<string>('');
  const [modalCategoryFilter, setModalCategoryFilter] = useState<string>('all');
  const [modalStockFilter, setModalStockFilter] = useState<'all' | 'in_stock' | 'has_source_stock'>('all');
  const [modalPage, setModalPage] = useState<number>(1);
  const ITEMS_PER_PAGE = 8;

  // Execution Modal / Status
  const [activeTransferExecution, setActiveTransferExecution] = useState<{
    isOpen: boolean;
    stage: 'preparing' | 'ledger_done' | 'source_syncing' | 'dest_syncing' | 'completed' | 'has_platform_error';
    record?: StoreStockTransferRecord;
  }>({
    isOpen: false,
    stage: 'preparing'
  });

  // Transfer history filter
  const [historySearch, setHistorySearch] = useState<string>('');

  const safeInventory = inventory || [];
  const safeStores = stores || [];

  // Keep the selected route valid when connected stores load or change.
  useEffect(() => {
    const activeStores = safeStores.filter(store => !store.isComingSoon);
    if (!activeStores.length) return;

    const nextSourceId = activeStores.some(store => store.id === sourceStoreId)
      ? sourceStoreId
      : activeStores[0].id;
    if (nextSourceId !== sourceStoreId) setSourceStoreId(nextSourceId);

    if (!activeStores.some(store => store.id === destinationStoreId) || destinationStoreId === nextSourceId) {
      setDestinationStoreId((activeStores.find(store => store.id !== nextSourceId) || activeStores[0]).id);
    }
  }, [safeStores, sourceStoreId, destinationStoreId]);

  useEffect(() => {
    if (safeInventory.length && !safeInventory.some(item => item.masterSku === selectedSku)) {
      setSelectedSku(safeInventory[0].masterSku);
    }
  }, [safeInventory, selectedSku]);

  // Close combobox when clicking outside.
  useEffect(() => {
    const handleClickOutside = (e: MouseEvent) => {
      if (comboboxRef.current && !comboboxRef.current.contains(e.target as Node)) {
        setIsSkuDropdownOpen(false);
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  // Target item
  const selectedItem = useMemo(() => {
    return safeInventory.find(i => i.masterSku === selectedSku) || safeInventory[0];
  }, [safeInventory, selectedSku]);

  // Source & Destination store balances
  const sourceBalance = useMemo(() => {
    if (!selectedSku || !storeBalances || !storeBalances[selectedSku]) return null;
    return storeBalances[selectedSku][sourceStoreId] || null;
  }, [selectedSku, storeBalances, sourceStoreId]);

  const destBalance = useMemo(() => {
    if (!selectedSku || !storeBalances || !storeBalances[selectedSku]) return null;
    return storeBalances[selectedSku][destinationStoreId] || null;
  }, [selectedSku, storeBalances, destinationStoreId]);

  const unavailableStore = {
    id: '', platform: 'webstore', platformDisplayName: 'Unavailable', name: 'Unavailable', nickname: 'Unavailable',
    region: '', connectionState: 'disconnected', isManagedByJw: false, readStatus: 'failed', syncStatus: 'disabled'
  } as InventoryStoreChannel;
  const sourceStore = safeStores.find(s => s.id === sourceStoreId) || safeStores[0] || unavailableStore;
  const destStore = safeStores.find(s => s.id === destinationStoreId) || safeStores.find(s => s.id !== sourceStore.id) || unavailableStore;

  const sourceAvailable = sourceBalance?.availableStock ?? 0;
  const destAvailable = destBalance?.availableStock ?? 0;

  const numericQty = Number(quantityInput);
  const isValidQty = !isNaN(numericQty) && Number.isInteger(numericQty) && numericQty > 0 && numericQty <= sourceAvailable;
  const isSameStore = sourceStoreId === destinationStoreId;

  // Before / After calculations for visual reference
  const sourceAfterPhysical = (sourceBalance?.physicalStock ?? 0) - (isValidQty ? numericQty : 0);
  const sourceAfterAvailable = sourceAvailable - (isValidQty ? numericQty : 0);
  const destAfterPhysical = (destBalance?.physicalStock ?? 0) + (isValidQty ? numericQty : 0);
  const destAfterAvailable = destAvailable + (isValidQty ? numericQty : 0);

  // Combobox Filtered SKUs (Instant search, top 25 matches)
  const comboboxFilteredItems = useMemo(() => {
    if (!skuSearchQuery.trim()) {
      return safeInventory.slice(0, 20);
    }
    const q = skuSearchQuery.toLowerCase().trim();
    return safeInventory.filter(item => {
      return (
        item.masterSku.toLowerCase().includes(q) ||
        item.productName.toLowerCase().includes(q) ||
        (item.variantName && item.variantName.toLowerCase().includes(q)) ||
        (item.barcode && item.barcode.toLowerCase().includes(q))
      );
    }).slice(0, 30);
  }, [safeInventory, skuSearchQuery]);

  // Extract categories for catalog modal filter
  const availableCategories = useMemo(() => {
    const cats = new Set<string>();
    safeInventory.forEach(item => {
      // Derive category from SKU prefix or warehouse location or default groups
      const prefix = item.masterSku.split('-')[0] || 'General';
      cats.add(prefix);
    });
    return Array.from(cats);
  }, [safeInventory]);

  // Catalog Modal Filtered Items (handles 1,000+ products and 5,000+ SKUs)
  const modalFilteredItems = useMemo(() => {
    return safeInventory.filter(item => {
      // Search match
      if (modalSearch.trim()) {
        const q = modalSearch.toLowerCase().trim();
        const match =
          item.masterSku.toLowerCase().includes(q) ||
          item.productName.toLowerCase().includes(q) ||
          (item.variantName && item.variantName.toLowerCase().includes(q)) ||
          (item.barcode && item.barcode.toLowerCase().includes(q));
        if (!match) return false;
      }

      // Category match
      if (modalCategoryFilter !== 'all') {
        const prefix = item.masterSku.split('-')[0] || 'General';
        if (prefix !== modalCategoryFilter) return false;
      }

      // Stock status filter
      if (modalStockFilter === 'in_stock') {
        const totalAvail = Object.values(storeBalances[item.masterSku] || {}).reduce(
          (sum, b) => sum + (b.availableStock ?? 0),
          0
        );
        if (totalAvail <= 0) return false;
      } else if (modalStockFilter === 'has_source_stock') {
        const srcAvail = storeBalances[item.masterSku]?.[sourceStoreId]?.availableStock ?? 0;
        if (srcAvail <= 0) return false;
      }

      return true;
    });
  }, [safeInventory, modalSearch, modalCategoryFilter, modalStockFilter, storeBalances, sourceStoreId]);

  // Pagination for catalog modal
  const totalPages = Math.max(1, Math.ceil(modalFilteredItems.length / ITEMS_PER_PAGE));
  const paginatedModalItems = useMemo(() => {
    const start = (modalPage - 1) * ITEMS_PER_PAGE;
    return modalFilteredItems.slice(start, start + ITEMS_PER_PAGE);
  }, [modalFilteredItems, modalPage]);

  const handleSelectSku = (sku: string) => {
    setSelectedSku(sku);
    setIsSkuDropdownOpen(false);
    setIsCatalogModalOpen(false);
    setQuantityInput('1');
  };

  const handleStartTransfer = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!isValidQty || isSameStore || !selectedItem) return;

    setActiveTransferExecution({
      isOpen: true,
      stage: 'preparing'
    });

    try {
      // Step 1: Ledger transfer
      setActiveTransferExecution(prev => ({ ...prev, stage: 'ledger_done' }));
      
      const res = await onExecuteTransfer({
        masterSku: selectedItem.masterSku,
        productName: selectedItem.productName,
        variantName: selectedItem.variantName,
        imageUrl: selectedItem.imageUrl,
        sourceStoreId,
        sourceStoreName: sourceStore.name,
        sourcePlatform: sourceStore.platform as any,
        destinationStoreId,
        destinationStoreName: destStore.name,
        destinationPlatform: destStore.platform as any,
        quantity: numericQty,
        operator,
        reason,
        notes: notes.trim() || undefined
      });

      const hasError = res.sourcePlatformSyncStatus === 'failed' || res.destinationPlatformSyncStatus === 'failed';
      const isPending = ['pending', 'syncing'].includes(res.sourcePlatformSyncStatus)
        || ['pending', 'syncing'].includes(res.destinationPlatformSyncStatus);
      setActiveTransferExecution({
        isOpen: true,
        stage: hasError ? 'has_platform_error' : isPending ? 'dest_syncing' : 'completed',
        record: res
      });

    } catch (err) {
      const message = err instanceof Error ? err.message : 'The reviewed transfer could not be created.';
      setActiveTransferExecution({
        isOpen: true,
        stage: 'has_platform_error',
        record: {
          masterSku: selectedItem.masterSku, productName: selectedItem.productName, variantName: selectedItem.variantName,
          imageUrl: selectedItem.imageUrl, sourceStoreId, sourceStoreName: sourceStore.name,
          sourcePlatform: sourceStore.platform as any, destinationStoreId, destinationStoreName: destStore.name,
          destinationPlatform: destStore.platform as any, quantity: numericQty, operator, reason,
          notes: notes.trim() || undefined, id: 'not-created', transferNumber: 'Not created', timestamp: new Date().toISOString(),
          ledgerStatus: 'failed', sourcePlatformSyncStatus: 'failed', destinationPlatformSyncStatus: 'failed',
          sourceFailureReason: message, destinationFailureReason: message
        }
      });
    }
  };

  // Filtered history list
  const filteredHistory = useMemo(() => {
    return transfersList.filter(t => {
      if (!historySearch.trim()) return true;
      const q = historySearch.toLowerCase();
      return (
        t.transferNumber.toLowerCase().includes(q) ||
        t.masterSku.toLowerCase().includes(q) ||
        t.productName.toLowerCase().includes(q) ||
        t.sourceStoreName.toLowerCase().includes(q) ||
        t.destinationStoreName.toLowerCase().includes(q) ||
        t.operator.toLowerCase().includes(q)
      );
    });
  }, [transfersList, historySearch]);

  return (
    <div id="store-stock-transfers-tab" className="space-y-8 animate-in fade-in duration-150">
      {/* Informational Policy Banner */}
      <div className="p-4 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs flex items-start gap-3.5">
        <div className="p-2.5 rounded-xl bg-neutral-100 dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300 border border-neutral-200 dark:border-neutral-700 shrink-0">
          <ArrowRightLeft className="w-4 h-4" />
        </div>
        <div className="text-xs space-y-1">
          <div className="font-bold text-neutral-900 dark:text-white flex items-center gap-2">
            <span>Store Stock Transfer Policy (店铺库存调配规则)</span>
            <span className="text-[10px] font-bold px-1.5 py-0.5 rounded bg-neutral-100 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 text-neutral-700 dark:text-neutral-300">
              Independent Mode Only
            </span>
          </div>
          <p className="text-neutral-600 dark:text-neutral-400 leading-relaxed">
            Store transfers shift sellable stock allocations between specific stores' JW ledgers. This represents business allocation, not physical freight transportation. 
            Transfers are strictly constrained by the source store's <strong>Available Stock</strong>. Units locked in <strong>Reserved Stock</strong> (pending customer orders) cannot be transferred. Total company stock is conserved.
          </p>
        </div>
      </div>

      {/* Full Width 100% Transfer Form Container */}
      <div className="w-full">
        <div className="p-5 sm:p-6 lg:p-7 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs space-y-6">
          {/* Form Header */}
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-neutral-100 dark:border-neutral-800 pb-4">
            <div className="space-y-1">
              <h3 className="text-base font-bold text-neutral-900 dark:text-white flex items-center gap-2.5">
                <Store className="w-5 h-5 text-neutral-500" />
                <span>Initiate Store Stock Transfer (新建店铺调配)</span>
              </h3>
              <p className="text-xs text-neutral-500 dark:text-neutral-400">
                Instantly reallocate available units between store ledgers and synchronize updated channel inventory in real time.
              </p>
            </div>
            <div className="flex items-center gap-2 shrink-0">
              <span className="text-[11px] font-mono px-2.5 py-1 rounded-lg bg-neutral-100 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 text-neutral-700 dark:text-neutral-300 font-semibold">
                Strict Conservation Enforced
              </span>
            </div>
          </div>

          <form onSubmit={handleStartTransfer} className="space-y-6">
            {/* ================= SECTION 1: ENTERPRISE SKU & PRODUCT SELECTION (Scales for 1000+ Products / 5000+ SKUs) ================= */}
            <div className="space-y-3">
              <div className="flex items-center justify-between">
                <label className="text-xs font-bold text-neutral-900 dark:text-white flex items-center gap-2">
                  <span>1. Select Master SKU / Product (选择调配商品)</span>
                  <span className="text-neutral-400 font-normal">
                    ({safeInventory.length.toLocaleString()} total SKUs indexed)
                  </span>
                </label>
                <button
                  type="button"
                  onClick={() => {
                    setModalSearch('');
                    setModalPage(1);
                    setIsCatalogModalOpen(true);
                  }}
                  className="px-3 py-1.5 rounded-xl bg-neutral-100 dark:bg-neutral-800 hover:bg-neutral-200 dark:hover:bg-neutral-700 text-neutral-800 dark:text-neutral-200 text-xs font-bold border border-neutral-200 dark:border-neutral-700 transition flex items-center gap-1.5 cursor-pointer"
                >
                  <Package className="w-3.5 h-3.5 text-neutral-500" />
                  <span>Browse Full Catalog ({safeInventory.length.toLocaleString()})</span>
                </button>
              </div>

              {/* Combobox Search Bar */}
              <div ref={comboboxRef} className="relative w-full">
                <div className="relative">
                  <Search className="w-4 h-4 absolute left-3.5 top-3 text-neutral-400 pointer-events-none" />
                  <input
                    type="text"
                    placeholder="Search by Master SKU, Product Name, Variant, or Barcode (e.g. AUR-PINE, Headphones, ANC)..."
                    value={skuSearchQuery}
                    onFocus={() => setIsSkuDropdownOpen(true)}
                    onChange={e => {
                      setSkuSearchQuery(e.target.value);
                      setIsSkuDropdownOpen(true);
                    }}
                    className="w-full pl-10 pr-24 py-2.5 text-xs rounded-xl border border-neutral-300 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/80 text-neutral-900 dark:text-white placeholder:text-neutral-400 focus:outline-hidden focus:ring-2 focus:ring-neutral-400"
                  />
                  <div className="absolute right-2.5 top-2 flex items-center gap-1">
                    {skuSearchQuery && (
                      <button
                        type="button"
                        onClick={() => {
                          setSkuSearchQuery('');
                          setIsSkuDropdownOpen(false);
                        }}
                        className="p-1 rounded-md text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200 cursor-pointer"
                      >
                        <X className="w-3.5 h-3.5" />
                      </button>
                    )}
                    <button
                      type="button"
                      onClick={() => setIsSkuDropdownOpen(prev => !prev)}
                      className="p-1 rounded-md text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200 cursor-pointer"
                    >
                      <ChevronsUpDown className="w-4 h-4" />
                    </button>
                  </div>
                </div>

                {/* Instant Search Suggestions Dropdown */}
                {isSkuDropdownOpen && (
                  <div className="absolute z-30 left-0 right-0 mt-1.5 max-h-80 overflow-y-auto rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xl divide-y divide-neutral-100 dark:divide-neutral-800">
                    <div className="p-2.5 bg-neutral-50/80 dark:bg-neutral-800/50 flex items-center justify-between text-[11px] text-neutral-500">
                      <span>Found {comboboxFilteredItems.length} matching SKU(s)</span>
                      <button
                        type="button"
                        onClick={() => {
                          setIsSkuDropdownOpen(false);
                          setModalSearch(skuSearchQuery);
                          setIsCatalogModalOpen(true);
                        }}
                        className="text-neutral-900 dark:text-white font-bold hover:underline cursor-pointer"
                      >
                        Open Catalog Grid &rarr;
                      </button>
                    </div>

                    {comboboxFilteredItems.length === 0 ? (
                      <div className="p-6 text-center text-xs text-neutral-500 space-y-2">
                        <Package className="w-6 h-6 mx-auto text-neutral-400" />
                        <div>No SKUs found matching &ldquo;{skuSearchQuery}&rdquo;</div>
                        <button
                          type="button"
                          onClick={() => {
                            setIsSkuDropdownOpen(false);
                            setIsCatalogModalOpen(true);
                          }}
                          className="text-xs font-bold text-neutral-900 dark:text-white underline cursor-pointer"
                        >
                          Browse complete catalog with advanced filters
                        </button>
                      </div>
                    ) : (
                      comboboxFilteredItems.map(item => {
                        const isCurrent = item.masterSku === selectedSku;
                        const balances = storeBalances[item.masterSku] || {};
                        const totalAvail = Object.values(balances).reduce((acc, b) => acc + (b.availableStock ?? 0), 0);

                        return (
                          <div
                            key={item.id}
                            onClick={() => handleSelectSku(item.masterSku)}
                            className={`p-3 flex items-center justify-between gap-3 hover:bg-neutral-50 dark:hover:bg-neutral-800/60 cursor-pointer transition-colors ${
                              isCurrent ? 'bg-neutral-100/70 dark:bg-neutral-800/80' : ''
                            }`}
                          >
                            <div className="flex items-center gap-3 min-w-0">
                              <div className="w-10 h-10 rounded-lg bg-neutral-100 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 overflow-hidden shrink-0 flex items-center justify-center">
                                {item.imageUrl ? (
                                  <img
                                    src={item.imageUrl}
                                    alt={item.productName}
                                    className="w-full h-full object-cover"
                                    loading="lazy"
                                    decoding="async"
                                    referrerPolicy="no-referrer"
                                  />
                                ) : (
                                  <Package className="w-5 h-5 text-neutral-400" />
                                )}
                              </div>
                              <div className="min-w-0">
                                <div className="flex items-center gap-2">
                                  <span className="font-mono font-bold text-xs text-neutral-900 dark:text-white">
                                    {item.masterSku}
                                  </span>
                                  {isCurrent && (
                                    <span className="text-[10px] font-bold px-1.5 py-0.2 rounded bg-neutral-900 text-white dark:bg-white dark:text-neutral-900">
                                      Active
                                    </span>
                                  )}
                                </div>
                                <div className="text-xs text-neutral-700 dark:text-neutral-300 truncate max-w-md">
                                  {item.productName}
                                </div>
                                {item.variantName && (
                                  <div className="text-[11px] text-neutral-400 truncate">
                                    Variant: {item.variantName}
                                  </div>
                                )}
                              </div>
                            </div>

                            <div className="text-right shrink-0">
                              <div className="text-xs font-mono font-bold text-neutral-900 dark:text-white">
                                Total Avail: {totalAvail}
                              </div>
                              <div className="text-[10px] text-neutral-400">
                                Physical: {item.totalPhysicalStock ?? 0}
                              </div>
                            </div>
                          </div>
                        );
                      })
                    )}
                  </div>
                )}
              </div>

              {/* Active Selected Product Card with Store Stock Distribution */}
              {selectedItem && (
                <div className="p-4 rounded-2xl bg-neutral-50 dark:bg-neutral-800/50 border border-neutral-200 dark:border-neutral-700/80 space-y-3">
                  <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div className="flex items-center gap-3.5">
                      <div className="w-14 h-14 rounded-xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 overflow-hidden shrink-0 flex items-center justify-center shadow-xs">
                        {selectedItem.imageUrl ? (
                          <img
                            src={selectedItem.imageUrl}
                            alt={selectedItem.productName}
                            className="w-full h-full object-cover"
                            loading="lazy"
                            decoding="async"
                            referrerPolicy="no-referrer"
                          />
                        ) : (
                          <Package className="w-7 h-7 text-neutral-400" />
                        )}
                      </div>
                      <div className="space-y-0.5">
                        <div className="flex items-center gap-2">
                          <span className="font-mono font-bold text-sm text-neutral-900 dark:text-white">
                            {selectedItem.masterSku}
                          </span>
                          <span className="text-[10px] font-bold px-2 py-0.5 rounded-full bg-neutral-200 dark:bg-neutral-700 text-neutral-800 dark:text-neutral-200">
                            Selected SKU
                          </span>
                        </div>
                        <h4 className="text-xs font-semibold text-neutral-900 dark:text-white">
                          {selectedItem.productName}
                        </h4>
                        <div className="flex flex-wrap items-center gap-2 text-[11px] text-neutral-500">
                          {selectedItem.variantName && <span>Variant: <strong>{selectedItem.variantName}</strong></span>}
                          {selectedItem.barcode && <span>• Barcode: <strong className="font-mono">{selectedItem.barcode}</strong></span>}
                          {selectedItem.warehouseLocation && <span>• Bin: <strong className="font-mono">{selectedItem.warehouseLocation}</strong></span>}
                        </div>
                      </div>
                    </div>

                    <div className="flex items-center gap-2 self-start sm:self-center">
                      <button
                        type="button"
                        onClick={() => setIsCatalogModalOpen(true)}
                        className="px-3 py-1.5 rounded-xl border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-900 text-neutral-800 dark:text-neutral-200 text-xs font-semibold hover:bg-neutral-100 dark:hover:bg-neutral-800 transition cursor-pointer"
                      >
                        Change Product
                      </button>
                    </div>
                  </div>

                  {/* Stock Distribution across all connected stores */}
                  <div className="border-t border-neutral-200/80 dark:border-neutral-700/60 pt-3">
                    <div className="text-[11px] font-bold text-neutral-500 uppercase tracking-wider mb-2">
                      Available Stock Across Connected Stores:
                    </div>
                    <div className="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                      {safeStores.filter(s => !s.isComingSoon).map(st => {
                        const bal = storeBalances[selectedItem.masterSku]?.[st.id];
                        const avail = bal?.availableStock ?? 0;
                        const phys = bal?.physicalStock ?? 0;
                        const isSource = st.id === sourceStoreId;
                        const isDest = st.id === destinationStoreId;

                        return (
                          <div
                            key={st.id}
                            onClick={() => {
                              if (avail > 0 && !isSource) {
                                setSourceStoreId(st.id);
                              }
                            }}
                            className={`p-2.5 rounded-xl border transition-all cursor-pointer ${
                              isSource
                                ? 'bg-white dark:bg-neutral-900 border-neutral-900 dark:border-white shadow-xs ring-1 ring-neutral-900 dark:ring-white'
                                : isDest
                                ? 'bg-white/70 dark:bg-neutral-900/70 border-neutral-400 dark:border-neutral-600'
                                : 'bg-white/50 dark:bg-neutral-900/40 border-neutral-200 dark:border-neutral-700 hover:border-neutral-400'
                            }`}
                          >
                            <div className="flex items-center justify-between text-xs mb-1">
                              <div className="flex items-center gap-1.5 truncate">
                                <MarketplaceLogo platform={st.platform as any} size="xs" />
                                <span className="font-semibold text-neutral-900 dark:text-white truncate">
                                  {st.nickname || st.name}
                                </span>
                              </div>
                              {isSource && (
                                <span className="text-[9px] font-bold px-1.5 py-0.2 rounded bg-neutral-900 text-white dark:bg-white dark:text-neutral-900">
                                  Source
                                </span>
                              )}
                              {isDest && (
                                <span className="text-[9px] font-bold px-1.5 py-0.2 rounded bg-neutral-200 dark:bg-neutral-700 text-neutral-800 dark:text-neutral-200">
                                  Dest
                                </span>
                              )}
                            </div>
                            <div className="flex items-baseline justify-between pt-0.5">
                              <span className="text-[10px] text-neutral-400">Available</span>
                              <span className={`font-mono text-xs font-bold ${avail > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-neutral-400'}`}>
                                {avail} Units
                              </span>
                            </div>
                            <div className="flex items-baseline justify-between text-[10px] text-neutral-400">
                              <span>Physical</span>
                              <span className="font-mono">{phys}</span>
                            </div>
                          </div>
                        );
                      })}
                    </div>
                  </div>
                </div>
              )}
            </div>

            {/* ================= SECTION 2: TRANSFER ROUTING & QUANTITY CONFIGURATION (100% Width) ================= */}
            <div className="space-y-3">
              <label className="text-xs font-bold text-neutral-900 dark:text-white">
                2. Configure Transfer Route & Quantity (设置调配路径与数量)
              </label>

              <div className="grid grid-cols-1 lg:grid-cols-12 gap-4 items-stretch">
                {/* 1. Source Store Card (From) */}
                <div className="lg:col-span-5 p-4 rounded-2xl bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-700 space-y-3">
                  <div className="flex items-center justify-between">
                    <label className="text-xs font-bold text-neutral-900 dark:text-white flex items-center gap-1.5">
                      <MarketplaceLogo platform={sourceStore.platform as any} size="xs" />
                      <span>From Store (调出店铺 - Source) *</span>
                    </label>
                    <span className="text-[11px] font-mono font-bold px-2 py-0.5 rounded-md bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white">
                      Avail: {sourceAvailable}
                    </span>
                  </div>

                  <select
                    value={sourceStoreId}
                    onChange={e => {
                      setSourceStoreId(e.target.value);
                      setQuantityInput('1');
                    }}
                    className="w-full px-3 py-2 text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-neutral-900 dark:text-white font-medium cursor-pointer"
                  >
                    {safeStores.filter(s => !s.isComingSoon).map(s => (
                      <option key={s.id} value={s.id}>
                        {s.nickname || s.name} ({s.platformDisplayName})
                      </option>
                    ))}
                  </select>

                  <div className="grid grid-cols-3 gap-2 text-center text-xs pt-1">
                    <div className="p-2 rounded-xl bg-white dark:bg-neutral-900 border border-neutral-200/60 dark:border-neutral-700/60">
                      <span className="text-[10px] text-neutral-400 block">Physical</span>
                      <span className="font-mono font-bold text-neutral-800 dark:text-neutral-200">
                        {sourceBalance?.physicalStock ?? 0}
                      </span>
                    </div>
                    <div className="p-2 rounded-xl bg-white dark:bg-neutral-900 border border-neutral-200/60 dark:border-neutral-700/60">
                      <span className="text-[10px] text-neutral-400 block">Reserved</span>
                      <span className="font-mono font-bold text-amber-600 dark:text-amber-400">
                        {sourceBalance?.reservedStock ?? 0}
                      </span>
                    </div>
                    <div className="p-2 rounded-xl bg-white dark:bg-neutral-900 border border-neutral-200/60 dark:border-neutral-700/60">
                      <span className="text-[10px] text-neutral-400 block">Available</span>
                      <span className="font-mono font-bold text-emerald-600 dark:text-emerald-400">
                        {sourceAvailable}
                      </span>
                    </div>
                  </div>
                </div>

                {/* 2. Transfer Arrow & Quantity Input */}
                <div className="lg:col-span-2 flex flex-col justify-center items-center p-3 rounded-2xl bg-neutral-100/70 dark:bg-neutral-800/40 border border-neutral-200 dark:border-neutral-700/80 space-y-3">
                  <div className="w-8 h-8 rounded-full bg-white dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 flex items-center justify-center text-neutral-600 dark:text-neutral-300 shadow-xs">
                    <ArrowRight className="w-4 h-4 hidden lg:block" />
                    <ArrowRightLeft className="w-4 h-4 lg:hidden" />
                  </div>

                  <div className="w-full space-y-1 text-center">
                    <label className="text-[11px] font-bold text-neutral-700 dark:text-neutral-300 block">
                      Transfer Units
                    </label>
                    <input
                      type="number"
                      min="1"
                      max={sourceAvailable}
                      step="1"
                      value={quantityInput}
                      onChange={e => setQuantityInput(e.target.value)}
                      className={`w-full px-2 py-2 text-center text-base font-mono font-bold rounded-xl border ${
                        !isValidQty && !isSameStore
                          ? 'border-red-400 bg-red-50/30 text-red-700 dark:text-red-300'
                          : 'border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-neutral-900 dark:text-white'
                      }`}
                    />
                  </div>

                  {/* Quick Preset Buttons */}
                  <div className="flex items-center gap-1">
                    <button
                      type="button"
                      disabled={sourceAvailable < 1}
                      onClick={() => setQuantityInput('1')}
                      className="px-2 py-0.5 rounded-md bg-white dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 text-[10px] font-bold text-neutral-700 dark:text-neutral-300 hover:bg-neutral-200 dark:hover:bg-neutral-700 cursor-pointer disabled:opacity-40"
                    >
                      1
                    </button>
                    <button
                      type="button"
                      disabled={sourceAvailable < 5}
                      onClick={() => setQuantityInput(String(Math.min(5, sourceAvailable)))}
                      className="px-2 py-0.5 rounded-md bg-white dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 text-[10px] font-bold text-neutral-700 dark:text-neutral-300 hover:bg-neutral-200 dark:hover:bg-neutral-700 cursor-pointer disabled:opacity-40"
                    >
                      5
                    </button>
                    <button
                      type="button"
                      disabled={sourceAvailable < 10}
                      onClick={() => setQuantityInput(String(Math.min(10, sourceAvailable)))}
                      className="px-2 py-0.5 rounded-md bg-white dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 text-[10px] font-bold text-neutral-700 dark:text-neutral-300 hover:bg-neutral-200 dark:hover:bg-neutral-700 cursor-pointer disabled:opacity-40"
                    >
                      10
                    </button>
                    <button
                      type="button"
                      disabled={sourceAvailable <= 0}
                      onClick={() => setQuantityInput(String(sourceAvailable))}
                      className="px-2 py-0.5 rounded-md bg-white dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 text-[10px] font-bold text-neutral-900 dark:text-white hover:bg-neutral-200 dark:hover:bg-neutral-700 cursor-pointer disabled:opacity-40"
                    >
                      Max
                    </button>
                  </div>
                </div>

                {/* 3. Destination Store Card (To) */}
                <div className="lg:col-span-5 p-4 rounded-2xl bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-700 space-y-3">
                  <div className="flex items-center justify-between">
                    <label className="text-xs font-bold text-neutral-900 dark:text-white flex items-center gap-1.5">
                      <MarketplaceLogo platform={destStore.platform as any} size="xs" />
                      <span>To Store (调入店铺 - Destination) *</span>
                    </label>
                    <span className="text-[11px] font-mono font-bold px-2 py-0.5 rounded-md bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white">
                      Avail: {destAvailable}
                    </span>
                  </div>

                  <select
                    value={destinationStoreId}
                    onChange={e => setDestinationStoreId(e.target.value)}
                    className="w-full px-3 py-2 text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-neutral-900 dark:text-white font-medium cursor-pointer"
                  >
                    {safeStores.filter(s => !s.isComingSoon).map(s => (
                      <option key={s.id} value={s.id}>
                        {s.nickname || s.name} ({s.platformDisplayName})
                      </option>
                    ))}
                  </select>

                  <div className="grid grid-cols-3 gap-2 text-center text-xs pt-1">
                    <div className="p-2 rounded-xl bg-white dark:bg-neutral-900 border border-neutral-200/60 dark:border-neutral-700/60">
                      <span className="text-[10px] text-neutral-400 block">Physical</span>
                      <span className="font-mono font-bold text-neutral-800 dark:text-neutral-200">
                        {destBalance?.physicalStock ?? 0}
                      </span>
                    </div>
                    <div className="p-2 rounded-xl bg-white dark:bg-neutral-900 border border-neutral-200/60 dark:border-neutral-700/60">
                      <span className="text-[10px] text-neutral-400 block">Reserved</span>
                      <span className="font-mono font-bold text-amber-600 dark:text-amber-400">
                        {destBalance?.reservedStock ?? 0}
                      </span>
                    </div>
                    <div className="p-2 rounded-xl bg-white dark:bg-neutral-900 border border-neutral-200/60 dark:border-neutral-700/60">
                      <span className="text-[10px] text-neutral-400 block">Available</span>
                      <span className="font-mono font-bold text-emerald-600 dark:text-emerald-400">
                        {destAvailable}
                      </span>
                    </div>
                  </div>
                </div>
              </div>

              {/* Validation Warnings */}
              {isSameStore && (
                <div className="p-3 rounded-xl bg-red-50 dark:bg-red-950/30 border border-red-200 dark:border-red-800/50 flex items-center gap-2 text-xs text-red-700 dark:text-red-300 font-semibold">
                  <AlertTriangle className="w-4 h-4 text-red-500 shrink-0" />
                  <span>Source and Destination stores cannot be identical. Please select different accounts.</span>
                </div>
              )}

              {sourceAvailable <= 0 && !isSameStore && (
                <div className="p-3 rounded-xl bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800/50 flex items-center gap-2 text-xs text-amber-700 dark:text-amber-300 font-semibold">
                  <AlertTriangle className="w-4 h-4 text-amber-500 shrink-0" />
                  <span>The selected source store ({sourceStore.name}) has 0 available units. Please choose another source store or replenish inventory.</span>
                </div>
              )}

              {numericQty > sourceAvailable && (
                <div className="p-3 rounded-xl bg-red-50 dark:bg-red-950/30 border border-red-200 dark:border-red-800/50 flex items-center gap-2 text-xs text-red-700 dark:text-red-300 font-semibold">
                  <AlertTriangle className="w-4 h-4 text-red-500 shrink-0" />
                  <span>Requested transfer ({numericQty} units) exceeds available stock in {sourceStore.name} ({sourceAvailable} units). Units reserved for customer orders cannot be moved.</span>
                </div>
              )}
            </div>

            {/* ================= SECTION 3: OPERATOR & REASON METADATA ================= */}
            <div className="space-y-3">
              <label className="text-xs font-bold text-neutral-900 dark:text-white">
                3. Audit Metadata & Operator Sign-off (操作员与原因)
              </label>

              <div className="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
                <div className="space-y-1">
                  <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">
                    Operator / Admin *
                  </label>
                  <input
                    type="text"
                    value={operator}
                    onChange={e => setOperator(e.target.value)}
                    className="w-full px-3 py-2 text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-neutral-900 dark:text-white font-medium"
                  />
                </div>

                <div className="space-y-1">
                  <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">
                    Transfer Reason *
                  </label>
                  <select
                    value={reason}
                    onChange={e => setReason(e.target.value)}
                    className="w-full px-3 py-2 text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-neutral-900 dark:text-white cursor-pointer"
                  >
                    <option value="Store Stock Rebalance">Store Stock Rebalance</option>
                    <option value="Campaign Promotion Preparation">Campaign Promotion Preparation</option>
                    <option value="Out of Stock Prevention">Out of Stock Prevention</option>
                    <option value="Direct Storefront VIP Order Allocation">Direct Storefront VIP Order Allocation</option>
                    <option value="Channel Quota Adjustment">Channel Quota Adjustment</option>
                  </select>
                </div>

                <div className="space-y-1">
                  <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">
                    Notes (Optional)
                  </label>
                  <input
                    type="text"
                    value={notes}
                    onChange={e => setNotes(e.target.value)}
                    placeholder="e.g. Flash sale stock buffer..."
                    className="w-full px-3 py-2 text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-neutral-900 dark:text-white"
                  />
                </div>
              </div>
            </div>

            {/* ================= SECTION 4: SUBMIT ACTION BAR ================= */}
            <div className="pt-3 border-t border-neutral-100 dark:border-neutral-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
              <div className="text-xs text-neutral-500">
                {isValidQty && !isSameStore ? (
                  <span>
                    Moving <strong>{numericQty} unit(s)</strong> of <strong className="font-mono">{selectedItem?.masterSku}</strong> from <strong>{sourceStore.name}</strong> to <strong>{destStore.name}</strong>.
                  </span>
                ) : (
                  <span>Complete product selection and valid quantity to proceed.</span>
                )}
              </div>

              <button
                type="submit"
                disabled={!isValidQty || isSameStore || sourceAvailable <= 0}
                className="px-6 py-2.5 rounded-xl bg-neutral-900 hover:bg-neutral-800 dark:bg-white dark:hover:bg-neutral-100 text-white dark:text-neutral-950 border border-neutral-700 dark:border-neutral-200 disabled:opacity-40 disabled:cursor-not-allowed text-xs font-bold transition-all shadow-xs flex items-center justify-center gap-2 cursor-pointer shrink-0"
              >
                <ArrowRightLeft className="w-4 h-4" />
                <span>Execute Store Stock Transfer ({isValidQty ? numericQty : 0} Units)</span>
              </button>
            </div>
          </form>
        </div>
      </div>

      {/* Transfer History Table */}
      <div className="p-5 sm:p-6 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs space-y-4">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-neutral-100 dark:border-neutral-800 pb-4">
          <div>
            <h3 className="text-sm font-bold text-neutral-900 dark:text-white flex items-center gap-2">
              <ArrowRightLeft className="w-4 h-4 text-neutral-400" />
              <span>Store Stock Transfer History (调配记录)</span>
              <span className="text-xs font-semibold px-2 py-0.5 rounded-md bg-neutral-100 dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300 border border-neutral-200 dark:border-neutral-700">
                {transfersList.length} Total
              </span>
            </h3>
            <p className="text-xs text-neutral-500 dark:text-neutral-400">
              Audit trail of all inter-store allocation transactions and channel sync delivery states.
            </p>
          </div>

          <div className="relative w-full sm:w-64">
            <Search className="w-4 h-4 absolute left-3 top-2.5 text-neutral-400" />
            <input
              type="text"
              placeholder="Filter by SKU, store, transfer #..."
              value={historySearch}
              onChange={e => setHistorySearch(e.target.value)}
              className="w-full pl-9 pr-3 py-1.5 text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white"
            />
          </div>
        </div>

        {/* Transfers Table */}
        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs">
            <thead>
              <tr className="border-b border-neutral-100 dark:border-neutral-800 text-neutral-400 uppercase text-[10px] tracking-wider bg-neutral-50/50 dark:bg-neutral-800/40">
                <th className="py-2.5 px-3">Transfer # & Time</th>
                <th className="py-2.5 px-3">Product / SKU</th>
                <th className="py-2.5 px-3">Source Store</th>
                <th className="py-2.5 px-3">Destination Store</th>
                <th className="py-2.5 px-3 text-center">Qty</th>
                <th className="py-2.5 px-3">JW Ledger</th>
                <th className="py-2.5 px-3">Platform Sync Status</th>
                <th className="py-2.5 px-3">Operator / Reason</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800">
              {(filteredHistory || []).map(record => {
                const hasError = record.sourcePlatformSyncStatus === 'failed' || record.destinationPlatformSyncStatus === 'failed';
                const isPending = ['pending', 'syncing'].includes(record.sourcePlatformSyncStatus)
                  || ['pending', 'syncing'].includes(record.destinationPlatformSyncStatus);
                return (
                  <tr key={record.id} className="hover:bg-neutral-50/60 dark:hover:bg-neutral-800/50 transition-colors">
                    <td className="py-3 px-3">
                      <div className="font-mono font-bold text-neutral-900 dark:text-white">{record.transferNumber}</div>
                      <div className="text-[10px] text-neutral-400">{new Date(record.timestamp).toLocaleString()}</div>
                    </td>
                    <td className="py-3 px-3">
                      <div className="font-mono font-bold text-neutral-900 dark:text-white">{record.masterSku}</div>
                      <div className="text-[11px] text-neutral-700 dark:text-neutral-300 truncate max-w-xs">{record.productName}</div>
                    </td>
                    <td className="py-3 px-3">
                      <div className="flex items-center gap-1.5 font-medium text-neutral-800 dark:text-neutral-200">
                        <MarketplaceLogo platform={record.sourcePlatform as any} size="xs" />
                        <span className="truncate max-w-[140px]">{record.sourceStoreName}</span>
                      </div>
                    </td>
                    <td className="py-3 px-3">
                      <div className="flex items-center gap-1.5 font-medium text-neutral-800 dark:text-neutral-200">
                        <MarketplaceLogo platform={record.destinationPlatform as any} size="xs" />
                        <span className="truncate max-w-[140px]">{record.destinationStoreName}</span>
                      </div>
                    </td>
                    <td className="py-3 px-3 text-center">
                      <span className="font-mono font-bold text-sm text-neutral-800 dark:text-neutral-200 bg-neutral-100 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 px-2 py-0.5 rounded-md">
                        {record.quantity}
                      </span>
                    </td>
                    <td className="py-3 px-3">
                      <span className="inline-flex items-center gap-1 text-[11px] font-bold text-emerald-700 dark:text-emerald-400">
                        <CheckCircle2 className="w-3.5 h-3.5" />
                        <span>Succeeded</span>
                      </span>
                    </td>
                    <td className="py-3 px-3">
                      {hasError ? (
                        <div className="space-y-1">
                          <span className="inline-flex items-center gap-1 text-[11px] font-bold text-red-600 dark:text-red-400 bg-red-100 dark:bg-red-950/60 px-2 py-0.5 rounded-md">
                            <AlertTriangle className="w-3 h-3" />
                            <span>Platform Sync Failed</span>
                          </span>
                          {record.sourceFailureReason && (
                            <div className="text-[10px] text-red-500 max-w-xs truncate" title={record.sourceFailureReason}>
                              Source: {record.sourceFailureReason}
                            </div>
                          )}
                          {record.destinationFailureReason && (
                            <div className="text-[10px] text-red-500 max-w-xs truncate" title={record.destinationFailureReason}>
                              Destination: {record.destinationFailureReason}
                            </div>
                          )}
                          <div className="flex flex-wrap gap-2">
                            {record.sourcePlatformSyncStatus === 'failed' && record.id !== 'not-created' && (
                              <button
                                type="button"
                                onClick={() => onRetryPlatformSync(record.id, 'source')}
                                className="inline-flex items-center gap-1 text-[10px] font-bold text-neutral-900 dark:text-white hover:underline cursor-pointer"
                              >
                                <RefreshCw className="w-3 h-3" />
                                <span>Retry Source Only</span>
                              </button>
                            )}
                            {record.destinationPlatformSyncStatus === 'failed' && record.id !== 'not-created' && (
                              <button
                                type="button"
                                onClick={() => onRetryPlatformSync(record.id, 'destination')}
                                className="inline-flex items-center gap-1 text-[10px] font-bold text-neutral-900 dark:text-white hover:underline cursor-pointer"
                              >
                                <RefreshCw className="w-3 h-3" />
                                <span>Retry Destination Only</span>
                              </button>
                            )}
                          </div>
                        </div>
                      ) : isPending ? (
                        <span className="inline-flex items-center gap-1 text-[11px] font-semibold text-amber-600 dark:text-amber-400">
                          <RefreshCw className="w-3.5 h-3.5" />
                          <span>Awaiting Channel Confirmation</span>
                        </span>
                      ) : (
                        <span className="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-600 dark:text-emerald-400">
                          <CheckCircle2 className="w-3.5 h-3.5" />
                          <span>Both Stores Synced</span>
                        </span>
                      )}
                    </td>
                    <td className="py-3 px-3">
                      <div className="text-neutral-800 dark:text-neutral-200 font-medium">{record.operator}</div>
                      <div className="text-[10px] text-neutral-400 truncate max-w-xs">{record.reason}</div>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      </div>

      {/* ================= MODAL: DEDICATED PRODUCT CATALOG PICKER (Handles 1000+ Products & 5000+ SKUs) ================= */}
      {isCatalogModalOpen && (
        <div className="fixed inset-0 z-50 overflow-y-auto bg-neutral-950/70 backdrop-blur-xs flex items-center justify-center p-3 sm:p-6 animate-in fade-in duration-150">
          <div className="bg-white dark:bg-neutral-900 rounded-3xl shadow-2xl border border-neutral-200 dark:border-neutral-800 w-full max-w-5xl overflow-hidden flex flex-col max-h-[90vh]">
            {/* Modal Header */}
            <div className="p-5 sm:p-6 border-b border-neutral-100 dark:border-neutral-800 flex items-center justify-between shrink-0">
              <div className="space-y-1">
                <div className="flex items-center gap-2">
                  <Package className="w-5 h-5 text-neutral-500" />
                  <h3 className="text-base font-bold text-neutral-900 dark:text-white">
                    Select Product / Master SKU from Catalog
                  </h3>
                  <span className="text-xs font-mono font-bold px-2 py-0.5 rounded-full bg-neutral-100 dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300 border border-neutral-200 dark:border-neutral-700">
                    {safeInventory.length.toLocaleString()} Indexed
                  </span>
                </div>
                <p className="text-xs text-neutral-500">
                  Search across 1,000+ products and 5,000+ SKUs by code, product title, barcode, or filter by category and stock availability.
                </p>
              </div>
              <button
                type="button"
                onClick={() => setIsCatalogModalOpen(false)}
                className="p-1.5 rounded-xl text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition cursor-pointer"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            {/* Filter Bar */}
            <div className="p-4 sm:p-5 bg-neutral-50/70 dark:bg-neutral-850/50 border-b border-neutral-100 dark:border-neutral-800 grid grid-cols-1 sm:grid-cols-12 gap-3 shrink-0">
              {/* Search Bar */}
              <div className="sm:col-span-6 relative">
                <Search className="w-4 h-4 absolute left-3 top-2.5 text-neutral-400" />
                <input
                  type="text"
                  placeholder="Filter by SKU, Product Name, Variant, Barcode..."
                  value={modalSearch}
                  onChange={e => {
                    setModalSearch(e.target.value);
                    setModalPage(1);
                  }}
                  className="w-full pl-9 pr-8 py-2 text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-neutral-900 dark:text-white placeholder:text-neutral-400 focus:outline-hidden focus:ring-2 focus:ring-neutral-400"
                />
                {modalSearch && (
                  <button
                    type="button"
                    onClick={() => {
                      setModalSearch('');
                      setModalPage(1);
                    }}
                    className="absolute right-2.5 top-2.5 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200 cursor-pointer"
                  >
                    <X className="w-3.5 h-3.5" />
                  </button>
                )}
              </div>

              {/* Category Filter */}
              <div className="sm:col-span-3">
                <select
                  value={modalCategoryFilter}
                  onChange={e => {
                    setModalCategoryFilter(e.target.value);
                    setModalPage(1);
                  }}
                  className="w-full px-3 py-2 text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-neutral-900 dark:text-white cursor-pointer"
                >
                  <option value="all">All Categories ({availableCategories.length})</option>
                  {availableCategories.map(cat => (
                    <option key={cat} value={cat}>{cat}</option>
                  ))}
                </select>
              </div>

              {/* Stock Filter */}
              <div className="sm:col-span-3">
                <select
                  value={modalStockFilter}
                  onChange={e => {
                    setModalStockFilter(e.target.value as any);
                    setModalPage(1);
                  }}
                  className="w-full px-3 py-2 text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-neutral-900 dark:text-white cursor-pointer"
                >
                  <option value="all">All Stock Statuses</option>
                  <option value="in_stock">In Stock (Avail &gt; 0)</option>
                  <option value="has_source_stock">Has Stock in Source ({sourceStore.name})</option>
                </select>
              </div>
            </div>

            {/* Modal Catalog Table / Items */}
            <div className="overflow-y-auto flex-1 divide-y divide-neutral-100 dark:divide-neutral-800">
              {paginatedModalItems.length === 0 ? (
                <div className="p-12 text-center text-xs text-neutral-500 space-y-3">
                  <Package className="w-10 h-10 mx-auto text-neutral-300 dark:text-neutral-600" />
                  <div className="font-semibold text-neutral-700 dark:text-neutral-300">
                    No matching products found
                  </div>
                  <p className="max-w-md mx-auto text-[11px] text-neutral-400">
                    Try adjusting your search terms or filters to locate the product among the catalog.
                  </p>
                  <button
                    type="button"
                    onClick={() => {
                      setModalSearch('');
                      setModalCategoryFilter('all');
                      setModalStockFilter('all');
                      setModalPage(1);
                    }}
                    className="px-3 py-1.5 rounded-xl border border-neutral-200 dark:border-neutral-700 text-xs font-semibold hover:bg-neutral-100 dark:hover:bg-neutral-800 cursor-pointer"
                  >
                    Reset All Filters
                  </button>
                </div>
              ) : (
                paginatedModalItems.map(item => {
                  const isCurrent = item.masterSku === selectedSku;
                  const balances = storeBalances[item.masterSku] || {};
                  const sourceAvail = balances[sourceStoreId]?.availableStock ?? 0;
                  const totalAvail = Object.values(balances).reduce((acc, b) => acc + (b.availableStock ?? 0), 0);

                  return (
                    <div
                      key={item.id}
                      className={`p-3.5 sm:p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:bg-neutral-50 dark:hover:bg-neutral-800/50 transition-colors ${
                        isCurrent ? 'bg-neutral-100/70 dark:bg-neutral-800/80' : ''
                      }`}
                    >
                      <div className="flex items-center gap-3.5 min-w-0">
                        <div className="w-12 h-12 rounded-xl bg-neutral-100 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 overflow-hidden shrink-0 flex items-center justify-center">
                          {item.imageUrl ? (
                            <img
                              src={item.imageUrl}
                              alt={item.productName}
                              className="w-full h-full object-cover"
                              loading="lazy"
                              decoding="async"
                              referrerPolicy="no-referrer"
                            />
                          ) : (
                            <Package className="w-6 h-6 text-neutral-400" />
                          )}
                        </div>

                        <div className="min-w-0 space-y-0.5">
                          <div className="flex items-center gap-2">
                            <span className="font-mono font-bold text-xs text-neutral-900 dark:text-white">
                              {item.masterSku}
                            </span>
                            {item.barcode && (
                              <span className="font-mono text-[10px] text-neutral-400 hidden sm:inline">
                                ({item.barcode})
                              </span>
                            )}
                            {isCurrent && (
                              <span className="text-[9px] font-bold px-1.5 py-0.2 rounded bg-neutral-900 text-white dark:bg-white dark:text-neutral-900">
                                Current
                              </span>
                            )}
                          </div>
                          <div className="text-xs font-semibold text-neutral-800 dark:text-neutral-200 truncate max-w-lg">
                            {item.productName}
                          </div>
                          <div className="flex items-center gap-2 text-[11px] text-neutral-400">
                            {item.variantName && <span>Variant: {item.variantName}</span>}
                            {item.warehouseLocation && <span>• Bin: {item.warehouseLocation}</span>}
                          </div>
                        </div>
                      </div>

                      {/* Store stock breakdown & Select Button */}
                      <div className="flex items-center justify-between sm:justify-end gap-4 shrink-0 pl-15 sm:pl-0">
                        <div className="text-right space-y-0.5">
                          <div className="text-xs font-mono font-bold text-neutral-900 dark:text-white">
                            Total Avail: {totalAvail}
                          </div>
                          <div className="text-[11px] text-neutral-500">
                            In {sourceStore.nickname || sourceStore.name}:{' '}
                            <strong className={sourceAvail > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-neutral-400'}>
                              {sourceAvail} units
                            </strong>
                          </div>
                        </div>

                        <button
                          type="button"
                          onClick={() => handleSelectSku(item.masterSku)}
                          className={`px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 cursor-pointer shrink-0 ${
                            isCurrent
                              ? 'bg-neutral-200 dark:bg-neutral-700 text-neutral-700 dark:text-neutral-200'
                              : 'bg-neutral-900 hover:bg-neutral-800 dark:bg-white dark:hover:bg-neutral-100 text-white dark:text-neutral-950 shadow-xs'
                          }`}
                        >
                          {isCurrent ? <Check className="w-3.5 h-3.5" /> : null}
                          <span>{isCurrent ? 'Selected' : 'Select SKU'}</span>
                        </button>
                      </div>
                    </div>
                  );
                })
              )}
            </div>

            {/* Modal Pagination Footer */}
            <div className="p-4 border-t border-neutral-100 dark:border-neutral-800 flex items-center justify-between text-xs shrink-0 bg-white dark:bg-neutral-900">
              <div className="text-neutral-500">
                Showing{' '}
                <strong className="text-neutral-900 dark:text-white">
                  {modalFilteredItems.length > 0 ? (modalPage - 1) * ITEMS_PER_PAGE + 1 : 0}
                </strong>
                -
                <strong className="text-neutral-900 dark:text-white">
                  {Math.min(modalPage * ITEMS_PER_PAGE, modalFilteredItems.length)}
                </strong>{' '}
                of <strong className="text-neutral-900 dark:text-white">{modalFilteredItems.length.toLocaleString()}</strong> results
              </div>

              <div className="flex items-center gap-2">
                <button
                  type="button"
                  disabled={modalPage <= 1}
                  onClick={() => setModalPage(p => Math.max(1, p - 1))}
                  className="px-3 py-1.5 rounded-xl border border-neutral-200 dark:border-neutral-700 text-neutral-700 dark:text-neutral-300 font-semibold disabled:opacity-40 disabled:cursor-not-allowed hover:bg-neutral-100 dark:hover:bg-neutral-800 cursor-pointer flex items-center gap-1"
                >
                  <ChevronLeft className="w-3.5 h-3.5" />
                  <span>Previous</span>
                </button>
                <span className="font-mono text-xs px-2 text-neutral-500">
                  {modalPage} / {totalPages}
                </span>
                <button
                  type="button"
                  disabled={modalPage >= totalPages}
                  onClick={() => setModalPage(p => Math.min(totalPages, p + 1))}
                  className="px-3 py-1.5 rounded-xl border border-neutral-200 dark:border-neutral-700 text-neutral-700 dark:text-neutral-300 font-semibold disabled:opacity-40 disabled:cursor-not-allowed hover:bg-neutral-100 dark:hover:bg-neutral-800 cursor-pointer flex items-center gap-1"
                >
                  <span>Next</span>
                  <ChevronRight className="w-3.5 h-3.5" />
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Multi-Stage Execution Result Modal */}
      {activeTransferExecution.isOpen && (
        <div className="fixed inset-0 z-50 overflow-y-auto bg-neutral-950/70 backdrop-blur-xs flex items-center justify-center p-3 sm:p-4">
          <div className="bg-white dark:bg-neutral-900 rounded-2xl shadow-2xl border border-neutral-200 dark:border-neutral-800 w-full max-w-lg overflow-hidden p-6 space-y-5">
            <div className="flex items-center justify-between">
              <h4 className="text-base font-bold text-neutral-900 dark:text-white flex items-center gap-2">
                <ArrowRightLeft className="w-5 h-5 text-neutral-400" />
                <span>Store Transfer Execution Pipeline</span>
              </h4>
              <button
                type="button"
                onClick={() => setActiveTransferExecution({ isOpen: false, stage: 'preparing' })}
                className="p-1 rounded-lg text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            {/* 3 Stages */}
            <div className="space-y-3">
              {/* Stage 1: JW Ledger */}
              <div className="p-3 rounded-xl bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800 flex items-center justify-between">
                <div className="flex items-center gap-2.5">
                  <CheckCircle2 className="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
                  <div>
                    <div className="text-xs font-bold text-emerald-950 dark:text-emerald-100">Step 1: JW Inventory Ledger Transfer</div>
                    <div className="text-[11px] text-emerald-700 dark:text-emerald-300">
                      Instantly shifted {numericQty} units from {sourceStore.name} to {destStore.name}.
                    </div>
                  </div>
                </div>
                <span className="text-[10px] font-bold px-2 py-0.5 rounded bg-emerald-200 text-emerald-900">Done</span>
              </div>

              {/* Stage 2: Source Platform Update */}
              <div className={`p-3 rounded-xl border flex items-center justify-between ${
                activeTransferExecution.stage === 'source_syncing'
                  ? 'bg-neutral-100 dark:bg-neutral-800 border-neutral-300 dark:border-neutral-700 text-neutral-900 dark:text-white'
                  : 'bg-emerald-50 dark:bg-emerald-950/30 border-emerald-200 text-emerald-900'
              }`}>
                <div className="flex items-center gap-2.5">
                  {activeTransferExecution.stage === 'source_syncing' ? (
                    <RefreshCw className="w-5 h-5 text-neutral-600 dark:text-neutral-300 animate-spin" />
                  ) : (
                    <CheckCircle2 className="w-5 h-5 text-emerald-600" />
                  )}
                  <div>
                    <div className="text-xs font-bold">Step 2: Source Channel Stock Update ({sourceStore.platformDisplayName})</div>
                    <div className="text-[11px] opacity-80">
                      {activeTransferExecution.stage === 'source_syncing' ? 'Broadcasting decremented ATP...' : 'Confirmed synced with source store.'}
                    </div>
                  </div>
                </div>
                <span className="text-[10px] font-bold px-2 py-0.5 rounded bg-white/80 dark:bg-neutral-800">
                  {activeTransferExecution.stage === 'source_syncing' ? 'Syncing...' : 'Confirmed'}
                </span>
              </div>

              {/* Stage 3: Destination Platform Update */}
              <div className={`p-3 rounded-xl border flex items-center justify-between ${
                activeTransferExecution.stage === 'dest_syncing'
                  ? 'bg-neutral-100 dark:bg-neutral-800 border-neutral-300 dark:border-neutral-700 text-neutral-900 dark:text-white'
                  : activeTransferExecution.stage === 'has_platform_error'
                  ? 'bg-red-50 border-red-200 text-red-900'
                  : 'bg-emerald-50 dark:bg-emerald-950/30 border-emerald-200 text-emerald-900'
              }`}>
                <div className="flex items-center gap-2.5">
                  {activeTransferExecution.stage === 'dest_syncing' ? (
                    <RefreshCw className="w-5 h-5 text-neutral-600 dark:text-neutral-300 animate-spin" />
                  ) : activeTransferExecution.stage === 'has_platform_error' ? (
                    <AlertTriangle className="w-5 h-5 text-red-600" />
                  ) : (
                    <CheckCircle2 className="w-5 h-5 text-emerald-600" />
                  )}
                  <div>
                    <div className="text-xs font-bold">Step 3: Destination Channel Stock Update ({destStore.platformDisplayName})</div>
                    <div className="text-[11px] opacity-80">
                      {activeTransferExecution.stage === 'dest_syncing' 
                        ? 'Broadcasting incremented ATP...' 
                        : activeTransferExecution.stage === 'has_platform_error'
                        ? 'API Gateway timeout during platform write.'
                        : 'Confirmed synced with destination store.'}
                    </div>
                  </div>
                </div>
                <span className="text-[10px] font-bold px-2 py-0.5 rounded bg-white/80 dark:bg-neutral-800">
                  {activeTransferExecution.stage === 'dest_syncing' ? 'Syncing...' : activeTransferExecution.stage === 'has_platform_error' ? 'Failed' : 'Confirmed'}
                </span>
              </div>
            </div>

            {/* Error handling reassurance if platform failed */}
            {activeTransferExecution.stage === 'has_platform_error' && (
              <div className="p-3.5 rounded-xl bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800/50 text-xs text-amber-900 dark:text-amber-200 space-y-1">
                <div className="font-bold text-amber-950 dark:text-amber-100">
                  Safe Targeted Recovery Available:
                </div>
                <p>
                  JW internal ledger transfer succeeded and stock is securely allocated. You can retry pushing the stock update to {destStore.name} without re-executing ledger deduction.
                </p>
              </div>
            )}

            <div className="pt-2 flex justify-end gap-2">
              <button
                type="button"
                onClick={() => setActiveTransferExecution({ isOpen: false, stage: 'preparing' })}
                className="px-5 py-2 rounded-xl bg-neutral-900 text-white dark:bg-white dark:text-neutral-900 text-xs font-bold cursor-pointer"
              >
                Done
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};
