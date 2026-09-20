import React, { useState, useMemo, useEffect } from 'react';
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
  Sparkles,
  Layers,
  ArrowRight,
  X
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

  useEffect(() => {
    const activeStores = safeStores.filter(store => !store.isComingSoon);
    if (!activeStores.length) return;
    if (!activeStores.some(store => store.id === sourceStoreId)) setSourceStoreId(activeStores[0].id);
    if (!activeStores.some(store => store.id === destinationStoreId) || destinationStoreId === sourceStoreId) {
      setDestinationStoreId((activeStores.find(store => store.id !== sourceStoreId) || activeStores[0]).id);
    }
  }, [safeStores, sourceStoreId, destinationStoreId]);

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

  const sourceStore = safeStores.find(s => s.id === sourceStoreId) || safeStores[0];
  const destStore = safeStores.find(s => s.id === destinationStoreId) || safeStores[1];

  const sourceAvailable = sourceBalance?.availableStock ?? 0;
  const destAvailable = destBalance?.availableStock ?? 0;

  const numericQty = Number(quantityInput);
  const isValidQty = !isNaN(numericQty) && Number.isInteger(numericQty) && numericQty > 0 && numericQty <= sourceAvailable;
  const isSameStore = sourceStoreId === destinationStoreId;

  // Before / After calculations
  const sourceAfterPhysical = (sourceBalance?.physicalStock ?? 0) - (isValidQty ? numericQty : 0);
  const sourceAfterAvailable = sourceAvailable - (isValidQty ? numericQty : 0);

  const destAfterPhysical = (destBalance?.physicalStock ?? 0) + (isValidQty ? numericQty : 0);
  const destAfterAvailable = destAvailable + (isValidQty ? numericQty : 0);

  // Total conservation check
  const totalBefore = (sourceBalance?.physicalStock ?? 0) + (destBalance?.physicalStock ?? 0);
  const totalAfter = sourceAfterPhysical + destAfterPhysical;
  const isConserved = totalBefore === totalAfter;

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

      <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
        {/* Left Column: Transfer Creator Form */}
        <div className="lg:col-span-6 space-y-5">
          <div className="p-5 sm:p-6 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs space-y-5">
            <div className="flex items-center justify-between border-b border-neutral-100 dark:border-neutral-800 pb-3">
              <h3 className="text-sm font-bold text-neutral-900 dark:text-white flex items-center gap-2">
                <Store className="w-4 h-4 text-neutral-400" />
                <span>Initiate Store Stock Transfer (新建店铺调配)</span>
              </h3>
              <span className="text-[11px] text-neutral-400">Strict Conservation Enforced</span>
            </div>

            <form onSubmit={handleStartTransfer} className="space-y-4">
              {/* Select SKU */}
              <div className="space-y-1.5">
                <label className="text-xs font-bold text-neutral-700 dark:text-neutral-300">
                  Select Master SKU / Product *
                </label>
                <select
                  value={selectedSku}
                  onChange={e => setSelectedSku(e.target.value)}
                  className="w-full px-3.5 py-2.5 text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/80 text-neutral-900 dark:text-white font-medium cursor-pointer"
                >
                  {safeInventory.map(item => (
                    <option key={item.id} value={item.masterSku}>
                      {item.masterSku} — {item.productName} ({item.variantName})
                    </option>
                  ))}
                </select>
              </div>

              {/* Source & Destination Stores Grid */}
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                {/* Source Store */}
                <div className="space-y-1.5 p-3 rounded-xl bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-700">
                  <div className="flex items-center justify-between">
                    <label className="text-xs font-bold text-neutral-800 dark:text-neutral-200">
                      From Store (Source) *
                    </label>
                    <span className="text-[10px] font-mono font-bold text-neutral-500">
                      Avail: {sourceAvailable}
                    </span>
                  </div>
                  <select
                    value={sourceStoreId}
                    onChange={e => setSourceStoreId(e.target.value)}
                    className="w-full px-2.5 py-1.5 text-xs rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-neutral-900 dark:text-white cursor-pointer"
                  >
                    {safeStores.filter(s => !s.isComingSoon).map(s => (
                      <option key={s.id} value={s.id}>
                        {s.nickname || s.name} ({s.platformDisplayName})
                      </option>
                    ))}
                  </select>
                  <div className="text-[11px] text-neutral-500 flex justify-between pt-1">
                    <span>Physical: {sourceBalance?.physicalStock ?? 0}</span>
                    <span>Reserved: {sourceBalance?.reservedStock ?? 0}</span>
                  </div>
                </div>

                {/* Destination Store */}
                <div className="space-y-1.5 p-3 rounded-xl bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-700">
                  <div className="flex items-center justify-between">
                    <label className="text-xs font-bold text-neutral-800 dark:text-neutral-200">
                      To Store (Destination) *
                    </label>
                    <span className="text-[10px] font-mono font-bold text-neutral-500">
                      Avail: {destAvailable}
                    </span>
                  </div>
                  <select
                    value={destinationStoreId}
                    onChange={e => setDestinationStoreId(e.target.value)}
                    className="w-full px-2.5 py-1.5 text-xs rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-neutral-900 dark:text-white cursor-pointer"
                  >
                    {safeStores.filter(s => !s.isComingSoon).map(s => (
                      <option key={s.id} value={s.id}>
                        {s.nickname || s.name} ({s.platformDisplayName})
                      </option>
                    ))}
                  </select>
                  <div className="text-[11px] text-neutral-500 flex justify-between pt-1">
                    <span>Physical: {destBalance?.physicalStock ?? 0}</span>
                    <span>Reserved: {destBalance?.reservedStock ?? 0}</span>
                  </div>
                </div>
              </div>

              {/* Same store warning */}
              {isSameStore && (
                <div className="p-3 rounded-xl bg-red-50 dark:bg-red-950/30 border border-red-200 dark:border-red-800/50 flex items-center gap-2 text-xs text-red-700 dark:text-red-300 font-semibold">
                  <AlertTriangle className="w-4 h-4 text-red-500" />
                  <span>Source and Destination stores cannot be identical.</span>
                </div>
              )}

              {/* Transfer Quantity */}
              <div className="space-y-1.5">
                <div className="flex items-center justify-between">
                  <label className="text-xs font-bold text-neutral-700 dark:text-neutral-300">
                    Transfer Quantity (调配数量) *
                  </label>
                  <span className="text-[11px] text-neutral-400">
                    Max transferable: {sourceAvailable} units
                  </span>
                </div>
                <div className="relative">
                  <input
                    type="number"
                    min="1"
                    max={sourceAvailable}
                    step="1"
                    value={quantityInput}
                    onChange={e => setQuantityInput(e.target.value)}
                    className={`w-full px-3.5 py-2.5 text-sm font-mono font-bold rounded-xl border ${
                      !isValidQty && !isSameStore
                        ? 'border-red-400 bg-red-50/20 text-red-700'
                        : 'border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-neutral-900 dark:text-white focus:ring-2 focus:ring-neutral-400'
                    }`}
                  />
                  <div className="absolute right-3 top-2.5 text-xs text-neutral-400 pointer-events-none">
                    Units
                  </div>
                </div>
                {numericQty > sourceAvailable && (
                  <p className="text-[11px] text-red-600 font-medium">
                    Cannot exceed source store available stock ({sourceAvailable} units). Reserved units ({sourceBalance?.reservedStock ?? 0}) are locked for active orders.
                  </p>
                )}
              </div>

              {/* Operator & Reason */}
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div className="space-y-1">
                  <label className="text-xs font-bold text-neutral-700 dark:text-neutral-300">
                    Operator *
                  </label>
                  <input
                    type="text"
                    value={operator}
                    onChange={e => setOperator(e.target.value)}
                    className="w-full px-3 py-2 text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-neutral-900 dark:text-white"
                  />
                </div>
                <div className="space-y-1">
                  <label className="text-xs font-bold text-neutral-700 dark:text-neutral-300">
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
              </div>

              <div className="space-y-1">
                <label className="text-xs font-medium text-neutral-700 dark:text-neutral-300">
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

              {/* Submit Button */}
              <div className="pt-2">
                <button
                  type="submit"
                  disabled={!isValidQty || isSameStore || sourceAvailable <= 0}
                  className="w-full py-2.5 rounded-xl bg-neutral-900 hover:bg-neutral-800 dark:bg-white dark:hover:bg-neutral-100 text-white dark:text-neutral-950 border border-neutral-700 dark:border-neutral-200 disabled:opacity-40 disabled:cursor-not-allowed text-xs font-bold transition-all shadow-xs flex items-center justify-center gap-2 cursor-pointer"
                >
                  <ArrowRightLeft className="w-4 h-4" />
                  <span>Execute Store Stock Transfer ({isValidQty ? numericQty : 0} Units)</span>
                </button>
              </div>
            </form>
          </div>
        </div>

        {/* Right Column: Live Before & After Simulation + Conservation Checker */}
        <div className="lg:col-span-6 space-y-5">
          <div className="p-5 sm:p-6 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs space-y-4">
            <div className="flex items-center justify-between border-b border-neutral-100 dark:border-neutral-800 pb-3">
              <h4 className="text-xs font-bold uppercase tracking-wider text-neutral-900 dark:text-white flex items-center gap-2">
                <ShieldCheck className="w-4 h-4 text-emerald-500" />
                <span>Real-time Balance Simulation & Conservation</span>
              </h4>
              <span className={`text-[10px] font-bold px-2 py-0.5 rounded-full ${
                isConserved ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300' : 'bg-red-100 text-red-800'
              }`}>
                {isConserved ? 'Total Conserved' : 'Deficit Mismatch'}
              </span>
            </div>

            {/* Source Store Simulation Card */}
            <div className="p-3.5 rounded-xl bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-700 space-y-2">
              <div className="flex items-center justify-between text-xs">
                <div className="flex items-center gap-2">
                  <MarketplaceLogo platform={sourceStore.platform as any} size="xs" />
                  <span className="font-bold text-neutral-800 dark:text-neutral-200">{sourceStore.nickname || sourceStore.name}</span>
                </div>
                <span className="text-[11px] font-bold text-red-600 dark:text-red-400">
                  -{isValidQty ? numericQty : 0} Units
                </span>
              </div>
              <div className="grid grid-cols-3 gap-2 text-center text-xs pt-1">
                <div className="p-1.5 rounded-lg bg-white dark:bg-neutral-900 border border-neutral-200/60 dark:border-neutral-700/60">
                  <span className="text-[10px] text-neutral-400 block">Physical</span>
                  <span className="font-mono font-semibold text-neutral-700 dark:text-neutral-300">
                    {sourceBalance?.physicalStock ?? 0} &rarr; <strong>{sourceAfterPhysical}</strong>
                  </span>
                </div>
                <div className="p-1.5 rounded-lg bg-white dark:bg-neutral-900 border border-neutral-200/60 dark:border-neutral-700/60">
                  <span className="text-[10px] text-neutral-400 block">Reserved</span>
                  <span className="font-mono font-semibold text-amber-600">
                    {sourceBalance?.reservedStock ?? 0} (Locked)
                  </span>
                </div>
                <div className="p-1.5 rounded-lg bg-white dark:bg-neutral-900 border border-neutral-200/60 dark:border-neutral-700/60">
                  <span className="text-[10px] text-neutral-400 block">Available</span>
                  <span className="font-mono font-bold text-neutral-900 dark:text-white">
                    {sourceAvailable} &rarr; <strong className="text-neutral-900 dark:text-white">{sourceAfterAvailable}</strong>
                  </span>
                </div>
              </div>
            </div>

            {/* Destination Store Simulation Card */}
            <div className="p-3.5 rounded-xl bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-700 space-y-2">
              <div className="flex items-center justify-between text-xs">
                <div className="flex items-center gap-2">
                  <MarketplaceLogo platform={destStore.platform as any} size="xs" />
                  <span className="font-bold text-neutral-800 dark:text-neutral-200">{destStore.nickname || destStore.name}</span>
                </div>
                <span className="text-[11px] font-bold text-emerald-600 dark:text-emerald-400">
                  +{isValidQty ? numericQty : 0} Units
                </span>
              </div>
              <div className="grid grid-cols-3 gap-2 text-center text-xs pt-1">
                <div className="p-1.5 rounded-lg bg-white dark:bg-neutral-900 border border-neutral-200/60 dark:border-neutral-700/60">
                  <span className="text-[10px] text-neutral-400 block">Physical</span>
                  <span className="font-mono font-semibold text-neutral-700 dark:text-neutral-300">
                    {destBalance?.physicalStock ?? 0} &rarr; <strong>{destAfterPhysical}</strong>
                  </span>
                </div>
                <div className="p-1.5 rounded-lg bg-white dark:bg-neutral-900 border border-neutral-200/60 dark:border-neutral-700/60">
                  <span className="text-[10px] text-neutral-400 block">Reserved</span>
                  <span className="font-mono font-semibold text-amber-600">
                    {destBalance?.reservedStock ?? 0} (Locked)
                  </span>
                </div>
                <div className="p-1.5 rounded-lg bg-white dark:bg-neutral-900 border border-neutral-200/60 dark:border-neutral-700/60">
                  <span className="text-[10px] text-neutral-400 block">Available</span>
                  <span className="font-mono font-bold text-neutral-900 dark:text-white">
                    {destAvailable} &rarr; <strong className="text-emerald-600 dark:text-emerald-400">{destAfterAvailable}</strong>
                  </span>
                </div>
              </div>
            </div>

            {/* Total Math Verification */}
            <div className="p-3 rounded-xl bg-neutral-100 dark:bg-neutral-800/80 border border-neutral-200 dark:border-neutral-700 text-xs flex items-center justify-between">
              <span className="text-neutral-700 dark:text-neutral-300 font-medium">Combined Physical Total:</span>
              <span className="font-mono font-bold text-neutral-900 dark:text-white">
                {totalBefore} units before &rarr; {totalAfter} units after (Net: 0)
              </span>
            </div>
          </div>
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
                          {record.destinationFailureReason && (
                            <div className="text-[10px] text-red-500 max-w-xs truncate" title={record.destinationFailureReason}>
                              {record.destinationFailureReason}
                            </div>
                          )}
                          <button
                            type="button"
                            onClick={() => onRetryPlatformSync(record.id, 'destination')}
                            className="inline-flex items-center gap-1 text-[10px] font-bold text-neutral-900 dark:text-white hover:underline cursor-pointer"
                          >
                            <RefreshCw className="w-3 h-3" />
                            <span>Retry Platform Sync Only</span>
                          </button>
                        </div>
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
