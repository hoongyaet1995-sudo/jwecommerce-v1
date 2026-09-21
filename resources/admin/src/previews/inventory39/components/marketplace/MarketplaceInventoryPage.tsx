import React, { useState, useMemo, useEffect } from 'react';
import { useAdmin } from '../../context/AdminContext';
import { MarketplaceInventoryItem } from '../../types';
import { SubNavHeader } from '../common/SubNavHeader';
import { MarketplaceLogo } from './MarketplaceLogo';
import {
  Boxes,
  Store,
  ArrowRightLeft,
  History,
  Search,
  Filter,
  RefreshCw,
  AlertTriangle,
  CheckCircle2,
  Plus,
  Sliders,
  Settings,
  ClipboardCheck,
  ChevronDown,
  ChevronRight,
  Radio,
  Eye,
  Send,
  Sparkles,
  Info,
  ShieldAlert,
  ArrowUpRight,
  ShieldCheck
} from 'lucide-react';

import {
  InventoryStoreChannel,
  StoreStockAccountBalance,
  StoreStockTransferRecord,
  InventoryActivityItem
} from './inventory/inventoryTypes';

import {
  INVENTORY_STORE_CHANNELS,
  INITIAL_INDEPENDENT_STORE_BALANCES,
  INITIAL_STORE_STOCK_TRANSFERS,
  INITIAL_INVENTORY_ACTIVITY_LOGS
} from './inventory/inventoryConstants';

import { InitialStockCountModal } from './inventory/InitialStockCountModal';
import { StockIntakeAdjustmentModal } from './inventory/StockIntakeAdjustmentModal';
import { StoreStockTransfersTab } from './inventory/StoreStockTransfersTab';
import { InventoryActivityHistoryTab } from './inventory/InventoryActivityHistoryTab';
import { InventorySettingsModal } from './inventory/InventorySettingsModal';

export const resolveFirstCountItem = (
  inventory: MarketplaceInventoryItem[],
  selected: MarketplaceInventoryItem | null,
  sku: string
) => inventory.find(item => item.masterSku === sku)
  || (selected?.masterSku === sku ? selected : null);

export const MarketplaceInventoryPage: React.FC = () => {
  const {
    marketplaceInventory = [],
    inventoryModeCatalog = { items: [], summary: { total: 0, ready: 0, blocked: 0, excluded: 0 } },
    connections = [],
    marketplaceOrders = [],
    adjustStock,
    recordPhysicalCountAudit,
    broadcastStockToChannels,
    addToast,
    inventoryMode,
    inventoryStores,
    inventoryStoreBalances,
    inventoryActivityHistory,
    reloadInventory,
    executeStoreTransfer,
    retrySync,
    allocateStock,
    switchInventoryMode,
    toggleStoreManagement,
    syncAll,
    setCurrentSection
  } = useAdmin();

  // Active Tab: 'overview' | 'transfers' | 'history'
  const [activeTab, setActiveTab] = useState<'overview' | 'transfers' | 'history'>('overview');

  // Global Inventory Mode: 'shared' | 'independent' (Admin controlled)
  const [currentMode, setCurrentMode] = useState<'shared' | 'independent'>(inventoryMode);

  // Channel Stores Configuration
  const [stores, setStores] = useState<InventoryStoreChannel[]>(inventoryStores);

  // Independent Store Stock Account Balances: sku -> { storeId: StoreStockAccountBalance }
  const [storeBalances, setStoreBalances] = useState<Record<string, Record<string, StoreStockAccountBalance>>>(
    inventoryStoreBalances
  );

  // Store Stock Transfer Records
  const [transfersList, setTransfersList] = useState<StoreStockTransferRecord[]>([]);

  // Activity History Logs
  const [activityLogs, setActivityLogs] = useState<InventoryActivityItem[]>(inventoryActivityHistory);

  useEffect(() => setCurrentMode(inventoryMode), [inventoryMode]);
  useEffect(() => setStores(inventoryStores), [inventoryStores]);
  useEffect(() => setStoreBalances(inventoryStoreBalances), [inventoryStoreBalances]);
  useEffect(() => setActivityLogs(inventoryActivityHistory), [inventoryActivityHistory]);
  useEffect(() => {
    const restored = inventoryActivityHistory.filter((entry: any) => entry.actionType === 'store_transfer').map((entry: any) => {
      const receipt = entry.detailsPayload || {};
      const stages = receipt.external_stages || [];
      const status = (stage: string) => {
        const value = stages.find((item: any) => item.stage === stage)?.status;
        return value === 'confirmed' || value === 'not_applicable' ? 'succeeded' : value === 'failed' || value === 'auth_expired' || value === 'uncertain' ? 'failed' : value === 'processing' ? 'syncing' : 'pending';
      };
      return {id: entry.id, transferNumber: entry.referenceId || entry.id, timestamp: entry.timestamp,
        masterSku: entry.masterSku, productName: entry.productName, variantName: entry.variantName,
        sourceStoreId: '', sourceStoreName: 'Source store', destinationStoreId: '', destinationStoreName: 'Destination store',
        quantity: 0, operator: entry.operator, reason: entry.reason, ledgerStatus: 'succeeded',
        sourcePlatformSyncStatus: status('source'), destinationPlatformSyncStatus: status('destination'), externalStages: stages} as StoreStockTransferRecord;
    });
    setTransfersList(current => [...current, ...restored.filter((item: any) => !current.some(existing => existing.id === item.id))]);
  }, [inventoryActivityHistory]);

  // Search and Filters
  const [searchQuery, setSearchQuery] = useState('');
  const [catalogFilter, setCatalogFilter] = useState('operational');
  const [statusFilter, setStatusFilter] = useState<'all' | 'uncounted' | 'low_stock' | 'synced' | 'mismatch'>('all');
  const [platformFilter, setPlatformFilter] = useState<string>('all');
  const [selectedStoreFilter, setSelectedStoreFilter] = useState<string>('all');

  // Modals & Drawers
  const [isCountModalOpen, setIsCountModalOpen] = useState(false);
  const [selectedItemForCount, setSelectedItemForCount] = useState<MarketplaceInventoryItem | null>(null);

  const [isIntakeModalOpen, setIsIntakeModalOpen] = useState(false);
  const [selectedItemForIntake, setSelectedItemForIntake] = useState<MarketplaceInventoryItem | null>(null);
  const [intakeInitialType, setIntakeInitialType] = useState<'intake' | 'adjustment'>('intake');

  const [isSettingsModalOpen, setIsSettingsModalOpen] = useState(false);
  const [settingsInitialStep, setSettingsInitialStep] = useState<'view' | 'checklist'>('view');

  // Batch Broadcasting State
  const [isBroadcastingAll, setIsBroadcastingAll] = useState(false);

  // Raw items from admin context
  const allInventory = marketplaceInventory || [];
  const safeInventory = allInventory.filter((item: any) => {
    if (catalogFilter === 'all') return true;
    if (catalogFilter === 'test') return item.isTest;
    if (item.isTest) return false;
    if (catalogFilter === 'archived') return item.catalogStatus === 'archived';
    return ['active', 'draft'].includes(item.catalogStatus || 'active');
  });
  const recoveryTasks = useMemo(() => Object.values(storeBalances).flatMap((accounts: any) => Object.values(accounts as any)
    .flatMap((account: any) => account.syncTasks || [])).filter((task: any) => ['failed','auth_expired','uncertain','processing'].includes(task.status)), [storeBalances]);

  // Identify uncounted items (First Count required)
  const uncountedItems = useMemo(() => {
    return safeInventory.filter(
      item => (item as any).hasUncountedAccounts || item.physicalStockStatus === 'unknown' || item.totalPhysicalStock === null
    );
  }, [safeInventory]);

  // Overall aggregate metrics
  const metrics = useMemo(() => {
    const totalSkus = safeInventory.length;
    let totalPhysical = 0;
    let totalReserved = 0;
    let totalAvailable = 0;

    safeInventory.forEach(item => {
      if (item.totalPhysicalStock !== null && item.physicalStockStatus !== 'unknown') {
        totalPhysical += item.totalPhysicalStock;
      }
      totalReserved += item.reservedStock || 0;
      if (item.availableStock !== null && item.availableStock !== undefined) {
        totalAvailable += item.availableStock;
      }
    });

    const activeStores = stores.filter(s => s.connectionState === 'connected' && !s.isComingSoon);
    const activeStoresCount = activeStores.length;
    const activeStoreLabels = activeStores.map(s => s.nickname || s.name).join(', ');

    return {
      totalSkus,
      totalPhysical,
      totalReserved,
      totalAvailable,
      uncountedCount: uncountedItems.length,
      activeStoresCount,
      activeStoreLabels
    };
  }, [safeInventory, stores, uncountedItems]);

  // Filtered inventory list
  const filteredInventory = useMemo(() => {
    return safeInventory.filter(item => {
      // Search query filter
      if (searchQuery.trim()) {
        const q = searchQuery.toLowerCase();
        const matchesSku = item.masterSku.toLowerCase().includes(q);
        const matchesName = item.productName.toLowerCase().includes(q);
        const matchesVariant = item.variantName.toLowerCase().includes(q);
        const matchesBarcode = item.barcode ? item.barcode.toLowerCase().includes(q) : false;
        if (!matchesSku && !matchesName && !matchesVariant && !matchesBarcode) {
          return false;
        }
      }

      // Status filter
      if (statusFilter === 'uncounted') {
        if (!(item as any).hasUncountedAccounts && item.physicalStockStatus !== 'unknown' && item.totalPhysicalStock !== null) return false;
      } else if (statusFilter === 'low_stock') {
        if ((item.availableStock ?? 0) > 10) return false;
      } else if (statusFilter === 'synced') {
        if (item.syncStatus !== 'synced') return false;
      } else if (statusFilter === 'mismatch') {
        if (item.syncStatus !== 'mismatch' && item.syncStatus !== 'failed') return false;
      }

      // Platform filter
      if (platformFilter !== 'all') {
        const hasPlatform = platformFilter === 'webstore' || item.storeAssociations?.some(a => a.platform === platformFilter);
        if (!hasPlatform) return false;
      }

      // Store filter
      if (selectedStoreFilter !== 'all') {
        const hasStore = selectedStoreFilter === 'website' || item.storeAssociations?.some(a => a.connectionId === selectedStoreFilter);
        if (!hasStore) return false;
      }

      return true;
    });
  }, [safeInventory, searchQuery, statusFilter, platformFilter, selectedStoreFilter]);

  // Handler: Open First Count modal
  const handleOpenFirstCount = (item: MarketplaceInventoryItem) => {
    setSelectedItemForCount(item);
    setIsCountModalOpen(true);
  };

  // Handler: Save First Count
  const handleSaveFirstCount = async ({
    sku,
    targetStoreId,
    allocations,
    physicalCount,
    reason,
    notes,
    operator
  }: {
    sku: string;
    targetStoreId?: string;
    allocations?: Record<string, number>;
    physicalCount: number;
    reason: string;
    notes?: string;
    operator: string;
  }) => {
    const item = resolveFirstCountItem(safeInventory, selectedItemForCount, sku);
    if (!item) throw new Error('The selected SKU is no longer available. Reopen the inventory catalog and try again.');

    if (currentMode === 'independent' && allocations) {
      const receipt = await allocateStock(item, physicalCount, allocations, `${reason}: ${notes || ''}`.trim());
      await reloadInventory();
      return receipt;
    }

    // Use admin context recordPhysicalCountAudit
    await recordPhysicalCountAudit(item.id, physicalCount, `${reason}: ${notes || ''}`.trim(), targetStoreId, item);
    await reloadInventory();
    return;

    // In Independent mode, update the store's balance ledger
    if (currentMode === 'independent' && targetStoreId) {
      setStoreBalances(prev => {
        const currentSkuBalances = prev[sku] || {};
        const prevStore = currentSkuBalances[targetStoreId];
        const resStock = prevStore?.reservedStock || 0;
        const availStock = Math.max(0, physicalCount - resStock);

        return {
          ...prev,
          [sku]: {
            ...currentSkuBalances,
            [targetStoreId]: {
              storeId: targetStoreId,
              storeName: stores.find(s => s.id === targetStoreId)?.name || targetStoreId,
              storeNickname: stores.find(s => s.id === targetStoreId)?.nickname,
              platform: stores.find(s => s.id === targetStoreId)?.platform as any,
              isManagedByJw: true,
              connectionState: 'connected',
              isCounted: true,
              physicalStock: physicalCount,
              reservedStock: resStock,
              availableStock: availStock,
              platformStockSnapshot: physicalCount,
              platformReadStatus: 'ok',
              lastPlatformReadAt: new Date().toISOString(),
              syncState: 'enabled',
              lastConfirmedPushedQuantity: availStock,
              lastConfirmedPushedAt: new Date().toISOString(),
              lastSyncStatus: 'confirmed'
            }
          }
        };
      });
    }

    // Log activity
    const newLog: InventoryActivityItem = {
      id: `act-${Date.now()}`,
      timestamp: new Date().toISOString(),
      actionType: 'first_count',
      masterSku: sku,
      productName: item.productName,
      variantName: item.variantName,
      targetScope: currentMode === 'shared' ? 'Global Shared Pool' : (stores.find(s => s.id === targetStoreId)?.nickname || targetStoreId || 'Store Account'),
      quantityChange: physicalCount,
      previousStock: 'Not Counted',
      resultingStock: physicalCount,
      availableChange: physicalCount,
      operator,
      reason,
      referenceId: `COUNT-${sku}-${Date.now().toString().slice(-4)}`,
      status: 'succeeded',
      detailsPayload: {
        mode: currentMode,
        notes,
        verifiedPhysical: physicalCount
      }
    };

    setActivityLogs(prev => [newLog, ...prev]);

    addToast({
      type: 'success',
      title: 'Initial Count Confirmed',
      message: `Verified physical stock for ${sku} recorded at ${physicalCount} units. Available-to-promise updated.`
    });
  };

  // Handler: Open Intake/Adjustment modal
  const handleOpenIntake = (item: MarketplaceInventoryItem, type: 'intake' | 'adjustment' = 'intake') => {
    setSelectedItemForIntake(item);
    setIntakeInitialType(type);
    setIsIntakeModalOpen(true);
  };

  // Handler: Save Intake/Adjustment
  const handleSaveIntake = async ({
    sku,
    type,
    targetStoreId,
    deltaQuantity,
    newPhysicalStock,
    reason,
    notes,
    operator
  }: {
    sku: string;
    type: 'intake' | 'adjustment';
    targetStoreId?: string;
    deltaQuantity: number;
    newPhysicalStock: number;
    reason: string;
    notes?: string;
    operator: string;
  }) => {
    const item = safeInventory.find(i => i.masterSku === sku);
    if (!item) return;

    await adjustStock(item.id, deltaQuantity, `${reason}: ${notes || ''}`.trim(), type, targetStoreId, item);
    await reloadInventory();
    return;

    // Update independent balance if applicable
    if (currentMode === 'independent' && targetStoreId) {
      setStoreBalances(prev => {
        const skuBal = prev[sku] || {};
        const storeBal = skuBal[targetStoreId];
        const oldPhysical = storeBal?.physicalStock ?? 0;
        const targetPhysical = type === 'intake' ? oldPhysical + deltaQuantity : newPhysicalStock;
        const resStock = storeBal?.reservedStock ?? 0;
        const targetAvail = Math.max(0, targetPhysical - resStock);

        return {
          ...prev,
          [sku]: {
            ...skuBal,
            [targetStoreId]: {
              ...storeBal,
              storeId: targetStoreId,
              storeName: stores.find(s => s.id === targetStoreId)?.name || targetStoreId,
              platform: stores.find(s => s.id === targetStoreId)?.platform as any,
              isManagedByJw: true,
              connectionState: 'connected',
              isCounted: true,
              physicalStock: targetPhysical,
              reservedStock: resStock,
              availableStock: targetAvail,
              syncState: 'enabled',
              lastConfirmedPushedQuantity: targetAvail,
              lastConfirmedPushedAt: new Date().toISOString()
            }
          }
        };
      });
    }

    const newLog: InventoryActivityItem = {
      id: `act-${Date.now()}`,
      timestamp: new Date().toISOString(),
      actionType: type === 'intake' ? 'actual_intake' : 'stock_adjustment',
      masterSku: sku,
      productName: item.productName,
      variantName: item.variantName,
      targetScope: currentMode === 'shared' ? 'Global Shared Pool' : (stores.find(s => s.id === targetStoreId)?.name || 'Store Account'),
      quantityChange: deltaQuantity,
      previousStock: item.totalPhysicalStock ?? 0,
      resultingStock: newPhysicalStock,
      availableChange: deltaQuantity,
      operator,
      reason,
      referenceId: `${type.toUpperCase()}-${Date.now().toString().slice(-4)}`,
      status: 'succeeded',
      detailsPayload: { notes, deltaQuantity, newPhysicalStock }
    };

    setActivityLogs(prev => [newLog, ...prev]);

    addToast({
      type: 'success',
      title: type === 'intake' ? 'Stock Inbound Confirmed' : 'Audit Adjustment Saved',
      message: `${sku} physical stock updated to ${newPhysicalStock} units.`
    });
  };

  // Handler: Execute Store Stock Transfer (Independent Mode)
  const handleExecuteStoreTransfer = async (
    transferPayload: Omit<StoreStockTransferRecord, 'id' | 'transferNumber' | 'timestamp' | 'ledgerStatus' | 'sourcePlatformSyncStatus' | 'destinationPlatformSyncStatus'>
  ): Promise<StoreStockTransferRecord> => {
    const receipt = await executeStoreTransfer(transferPayload);
    const stages = receipt.external_stages || [];
    const displayStatus = (status?: string): StoreStockTransferRecord['sourcePlatformSyncStatus'] => {
      if (status === 'confirmed' || status === 'not_applicable') return 'succeeded';
      if (status === 'failed' || status === 'auth_expired' || status === 'uncertain') return 'failed';
      if (status === 'processing') return 'syncing';
      return 'pending';
    };
    const connectedRecord = {
      ...transferPayload,
      id: receipt.operation_id,
      transferNumber: receipt.operation_id,
      timestamp: new Date().toISOString(),
      ledgerStatus: 'succeeded',
      sourcePlatformSyncStatus: displayStatus(stages.find((s: any) => s.stage === 'source')?.status),
      destinationPlatformSyncStatus: displayStatus(stages.find((s: any) => s.stage === 'destination')?.status)
      ,externalStages: stages
    } as StoreStockTransferRecord;
    setTransfersList(prev => [connectedRecord, ...prev.filter(existing => existing.id !== connectedRecord.id)]);
    return connectedRecord;

    /* ZIP39 source fallback retained below for structural fidelity; the connected preview returns above. */
    const transferNumber = `TRF-${new Date().getFullYear()}-${Math.floor(1000 + Math.random() * 9000)}`;
    const timestamp = new Date().toISOString();

    // Step 1: Execute internal JW ledger deduction and credit
    const { masterSku, sourceStoreId, destinationStoreId, quantity } = transferPayload;

    setStoreBalances(prev => {
      const skuBal = prev[masterSku] || {};
      const srcBal = skuBal[sourceStoreId];
      const dstBal = skuBal[destinationStoreId];

      const newSrcPhys = Math.max(0, (srcBal?.physicalStock ?? 0) - quantity);
      const newSrcAvail = Math.max(0, (srcBal?.availableStock ?? 0) - quantity);

      const newDstPhys = (dstBal?.physicalStock ?? 0) + quantity;
      const newDstAvail = (dstBal?.availableStock ?? 0) + quantity;

      return {
        ...prev,
        [masterSku]: {
          ...skuBal,
          [sourceStoreId]: {
            ...srcBal,
            physicalStock: newSrcPhys,
            availableStock: newSrcAvail,
            lastConfirmedPushedQuantity: newSrcAvail,
            lastConfirmedPushedAt: new Date().toISOString()
          },
          [destinationStoreId]: {
            ...dstBal,
            physicalStock: newDstPhys,
            availableStock: newDstAvail,
            lastConfirmedPushedQuantity: newDstAvail,
            lastConfirmedPushedAt: new Date().toISOString()
          }
        }
      };
    });

    const record: StoreStockTransferRecord = {
      ...transferPayload,
      id: `trf-${Date.now()}`,
      transferNumber,
      timestamp,
      ledgerStatus: 'succeeded',
      sourcePlatformSyncStatus: 'succeeded',
      destinationPlatformSyncStatus: 'succeeded'
    };

    setTransfersList(prev => [record, ...prev]);

    // Record activity log
    const activityLog: InventoryActivityItem = {
      id: `act-${Date.now()}`,
      timestamp,
      actionType: 'store_transfer',
      masterSku,
      productName: transferPayload.productName,
      variantName: transferPayload.variantName,
      targetScope: `${transferPayload.sourceStoreName} -> ${transferPayload.destinationStoreName}`,
      quantityChange: quantity,
      previousStock: `Source Avail: ${(storeBalances[masterSku]?.[sourceStoreId]?.availableStock ?? 0)}`,
      resultingStock: `Source Avail: ${(storeBalances[masterSku]?.[sourceStoreId]?.availableStock ?? 0) - quantity}`,
      operator: transferPayload.operator,
      reason: transferPayload.reason,
      referenceId: transferNumber,
      status: 'succeeded',
      detailsPayload: {
        sourceStore: transferPayload.sourceStoreName,
        destinationStore: transferPayload.destinationStoreName,
        quantity,
        totalInventoryConserved: true
      }
    };

    setActivityLogs(prev => [activityLog, ...prev]);

    addToast({
      type: 'success',
      title: 'Store Transfer Completed',
      message: `${quantity} units transferred from ${transferPayload.sourceStoreName} to ${transferPayload.destinationStoreName}. Total stock conserved.`
    });

    return record;
  };

  // Handler: Retry platform sync for transfer
  const handleRetryPlatformSync = async (transferId: string, target: 'source' | 'destination') => {
    const transfer = transfersList.find(t => t.id === transferId) as any;
    const stage = transfer?.externalStages?.find((s: any) => s.stage === target);
    if (!stage) throw new Error('The selected platform stage is unavailable.');
    await retrySync(stage.id);
    await reloadInventory();
    return;
    setTransfersList(prev =>
      prev.map(t => {
        if (t.id !== transferId) return t;
        return {
          ...t,
          [target === 'source' ? 'sourcePlatformSyncStatus' : 'destinationPlatformSyncStatus']: 'succeeded',
          destinationFailureReason: undefined
        };
      })
    );

    addToast({
      type: 'success',
      title: 'Platform Stock Sync Succeeded',
      message: `Stock broadcast confirmed on remote marketplace API without re-deducting JW ledger.`
    });
  };

  // Handler: Admin Global Mode Switch
  const handleSwitchMode = async (
    newMode: 'shared' | 'independent',
    staffName: string,
    reason: string,
    allocations?: Record<string, Record<string, number>>
  ) => {
    await switchInventoryMode(newMode, allocations || {});
    await reloadInventory();
    return { success: true };

    /* ZIP39 source fallback retained below for structural fidelity; the connected preview returns above. */
    setCurrentMode(newMode);

    // If switching to independent and allocations provided, initialize balances
    if (newMode === 'independent' && allocations) {
      const newBalances: Record<string, Record<string, StoreStockAccountBalance>> = {};

      Object.entries(allocations).forEach(([sku, storeMap]) => {
        newBalances[sku] = {};
        Object.entries(storeMap).forEach(([stId, qty]) => {
          const st = stores.find(s => s.id === stId);
          newBalances[sku][stId] = {
            storeId: stId,
            storeName: st?.name || stId,
            storeNickname: st?.nickname,
            platform: st?.platform as any,
            isManagedByJw: true,
            connectionState: 'connected',
            isCounted: true,
            physicalStock: qty,
            reservedStock: 0,
            availableStock: qty,
            platformStockSnapshot: qty,
            platformReadStatus: 'ok',
            lastPlatformReadAt: new Date().toISOString(),
            syncState: 'enabled',
            lastConfirmedPushedQuantity: qty,
            lastConfirmedPushedAt: new Date().toISOString(),
            lastSyncStatus: 'confirmed'
          };
        });
      });

      setStoreBalances(newBalances);
    }

    // Log activity
    const newLog: InventoryActivityItem = {
      id: `act-${Date.now()}`,
      timestamp: new Date().toISOString(),
      actionType: 'mode_change',
      masterSku: 'ALL_SKUS',
      productName: 'System-wide Operating Mode',
      targetScope: 'Global Admin Settings',
      quantityChange: 0,
      previousStock: currentMode === 'shared' ? 'Shared Stock' : 'Independent Stock',
      resultingStock: newMode === 'shared' ? 'Shared Stock' : 'Independent Stock',
      operator: staffName,
      reason,
      referenceId: `MODE-SW-${Date.now().toString().slice(-4)}`,
      status: 'succeeded',
      detailsPayload: { newMode, previousMode: currentMode }
    };

    setActivityLogs(prev => [newLog, ...prev]);

    addToast({
      type: 'success',
      title: 'Global Inventory Mode Updated',
      message: `System transitioned to ${newMode === 'shared' ? 'Shared Stock' : 'Independent Stock'} mode.`
    });

    return { success: true };
  };

  // Handler: pause or resume marketplace ATP synchronization.
  const handleToggleStoreManagement = async (storeId: string, enable: boolean) => {
    const result = await toggleStoreManagement(storeId, enable, enable ? 'Admin reviewed marketplace inventory sync readiness' : 'Admin paused marketplace inventory sync');
    await reloadInventory();
    return result;
    setStores(prev =>
      prev.map(s => {
        if (s.id !== storeId) return s;
        return {
          ...s,
          isManagedByJw: enable,
          syncStatus: enable ? 'synced' : 'disabled'
        };
      })
    );

    const store = stores.find(s => s.id === storeId);

    const newLog: InventoryActivityItem = {
      id: `act-${Date.now()}`,
      timestamp: new Date().toISOString(),
      actionType: 'store_takeover',
      masterSku: 'ALL_SKUS',
      productName: store?.name || 'Store Catalog',
      targetScope: store?.name || storeId,
      storeId,
      platform: store?.platform as any,
      quantityChange: 0,
      previousStock: enable ? 'Sync Paused / Setup Required' : 'Inventory Sync Active',
      resultingStock: enable ? 'Inventory Sync Active' : 'Sync Paused',
      operator: 'Alexander (Admin)',
      reason: enable ? 'Admin enabled marketplace inventory sync' : 'Admin paused marketplace inventory sync',
      referenceId: `TAKEOVER-${Date.now().toString().slice(-4)}`,
      status: 'succeeded'
    };

    setActivityLogs(prev => [newLog, ...prev]);

    addToast({
      type: 'info',
      title: enable ? 'Inventory Sync Active' : 'Inventory Sync Paused',
      message: enable
        ? `${store?.name} is enrolled in JW inventory and ATP updates will be pushed automatically.`
        : `${store?.name} remains in the JW ledger while external ATP broadcasts are paused.`
    });
  };

  // Handler: Batch broadcast to all channels
  const handleBroadcastAll = async () => {
    setIsBroadcastingAll(true);
    try {
      await syncAll();
      addToast({
        type: 'success',
        title: 'Omnichannel Stock Broadcast Dispatched',
        message: `Pushed available stock quantities across all ${stores.filter(s => s.isManagedByJw).length} managed stores.`
      });
    } finally {
      setIsBroadcastingAll(false);
    }
  };

  return (
    <div id="marketplace-inventory-page" className="p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto space-y-6 animate-in fade-in duration-200">
      {recoveryTasks.length > 0 && <div className="rounded-xl border border-amber-500/40 bg-amber-500/10 p-4 text-sm">
        <div className="font-semibold text-amber-700 dark:text-amber-300">Marketplace stock sync needs review</div>
        <div className="mt-1 text-neutral-600 dark:text-neutral-300">Check the marketplace quantity before retrying uncertain writes. Failed authorization requires reconnecting the store.</div>
        <div className="mt-3 flex flex-wrap gap-2">{recoveryTasks.map((task:any)=><button key={task.id} className="rounded-lg border border-amber-500/40 px-3 py-1.5 font-medium" onClick={async()=>{if(task.status==='uncertain'||task.status==='processing'){if(!window.confirm('Confirm the previous marketplace write was NOT applied before retrying.'))return;await retrySync(task.id,'not_applied')}else await retrySync(task.id);await reloadInventory()}}>Review / retry {task.id.slice(0,8)}</button>)}</div>
      </div>}
      {/* Sub Navigation & Header Breadcrumb */}
      <SubNavHeader
        parentSection="marketplace"
        parentLabel="Marketplace Ecosystem"
        currentLabel="Inventory Management"
        subtitle="Omnichannel store inventory allocation, Available-To-Promise (ATP) calculation, and physical stock verification."
        actions={
          <div className="flex items-center gap-2">
            <button
              type="button"
              onClick={() => setIsSettingsModalOpen(true)}
              className="px-3.5 py-2 rounded-xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 hover:bg-neutral-50 dark:hover:bg-neutral-800 text-neutral-700 dark:text-neutral-300 text-xs font-semibold transition-colors flex items-center gap-2 shadow-xs cursor-pointer"
            >
              <Settings className="w-4 h-4 text-neutral-500 dark:text-neutral-400" />
              <span>Inventory Settings</span>
            </button>
            <button
              type="button"
              disabled={isBroadcastingAll}
              onClick={handleBroadcastAll}
              className="px-4 py-2 rounded-xl bg-neutral-900 hover:bg-neutral-800 dark:bg-white dark:hover:bg-neutral-100 text-white dark:text-neutral-950 border border-neutral-700 dark:border-neutral-200 text-xs font-bold transition-all shadow-xs flex items-center gap-2 cursor-pointer disabled:opacity-50"
            >
              {isBroadcastingAll ? <RefreshCw className="w-4 h-4 animate-spin" /> : <Send className="w-4 h-4" />}
              <span>Sync All Stores</span>
            </button>
          </div>
        }
      />

      {/* Tabs Bar: Overview | Stock Transfers | Activity History */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-neutral-200 dark:border-neutral-800 pb-1">
        <div className="flex items-center gap-1 sm:gap-2">
          <button
            type="button"
            onClick={() => setActiveTab('overview')}
            className={`px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2 cursor-pointer border ${
              activeTab === 'overview'
                ? 'bg-neutral-900 dark:bg-neutral-800 text-white border-neutral-700 shadow-xs'
                : 'border-transparent text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-100 dark:hover:bg-neutral-850'
            }`}
          >
            <Boxes className="w-4 h-4" />
            <span>Inventory Overview (库存总览)</span>
            {uncountedItems.length > 0 && (
              <span className="text-[10px] px-1.5 py-0.2 rounded-full font-bold bg-amber-500/20 text-amber-500 dark:text-amber-400 border border-amber-500/30">
                {uncountedItems.length}
              </span>
            )}
          </button>

          {/* Tab 2: Stock Transfers (Only available in Independent Mode) */}
          {currentMode === 'independent' ? (
            <button
              type="button"
              onClick={() => setActiveTab('transfers')}
              className={`px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2 cursor-pointer border ${
                activeTab === 'transfers'
                  ? 'bg-neutral-900 dark:bg-neutral-800 text-white border-neutral-700 shadow-xs'
                  : 'border-transparent text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-100 dark:hover:bg-neutral-850'
              }`}
            >
              <ArrowRightLeft className="w-4 h-4" />
              <span>Stock Transfers (店铺调配)</span>
            </button>
          ) : (
            <div
              title="Stock transfers are only available in Independent Stock Mode. In Shared Stock Mode, all stores pull from the same central pool."
              className="px-3 py-2 text-xs font-medium text-neutral-400 dark:text-neutral-600 flex items-center gap-1.5 cursor-not-allowed opacity-60"
            >
              <ArrowRightLeft className="w-3.5 h-3.5" />
              <span>Stock Transfers (Disabled in Shared Mode)</span>
            </div>
          )}

          {/* Tab 3: Activity History */}
          <button
            type="button"
            onClick={() => setActiveTab('history')}
            className={`px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2 cursor-pointer border ${
              activeTab === 'history'
                ? 'bg-neutral-900 dark:bg-neutral-800 text-white border-neutral-700 shadow-xs'
                : 'border-transparent text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-100 dark:hover:bg-neutral-850'
            }`}
          >
            <History className="w-4 h-4" />
            <span>Activity History (操作与同步日志)</span>
            <span className="text-[10px] px-1.5 py-0.2 rounded-full font-bold bg-neutral-200 dark:bg-neutral-700 text-neutral-700 dark:text-neutral-300">
              {activityLogs.length}
            </span>
          </button>
        </div>

      </div>

      {/* ================= TAB 1: INVENTORY OVERVIEW ================= */}
      {activeTab === 'overview' && (
        <div className="space-y-6 animate-in fade-in duration-150">
          {/* Top Key Metrics */}
          <div className="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-5 gap-3.5">
            {/* 1. Total Master SKUs */}
            <div className="p-4 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs hover:border-neutral-700 transition-all">
              <div className="flex items-center justify-between text-neutral-500 dark:text-neutral-400 text-xs mb-1">
                <span>Master SKUs</span>
                <Boxes className="w-4 h-4 text-neutral-400" />
              </div>
              <div className="font-mono font-bold text-2xl text-neutral-900 dark:text-white">
                {metrics.totalSkus}
              </div>
              <div className="text-[11px] text-neutral-400 mt-1">Catalog items tracked</div>
            </div>

            {/* 2. Verified Physical Stock */}
            <div className="p-4 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs hover:border-neutral-700 transition-all">
              <div className="flex items-center justify-between text-neutral-500 dark:text-neutral-400 text-xs mb-1">
                <span>Physical Stock</span>
                <Boxes className="w-4 h-4 text-neutral-400" />
              </div>
              <div className="font-mono font-bold text-2xl text-neutral-900 dark:text-white">
                {metrics.totalPhysical}
              </div>
              <div className="text-[11px] text-neutral-400 mt-1">
                {metrics.uncountedCount > 0 ? `${metrics.uncountedCount} SKU(s) Awaiting Count` : 'All items verified'}
              </div>
            </div>

            {/* 3. Reserved Stock (Active Orders) */}
            <div className="p-4 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs hover:border-neutral-700 transition-all">
              <div className="flex items-center justify-between text-neutral-500 dark:text-neutral-400 text-xs mb-1">
                <span>Reserved Stock</span>
                <Radio className="w-4 h-4 text-neutral-400" />
              </div>
              <div className="font-mono font-bold text-2xl text-amber-500 dark:text-amber-400">
                {metrics.totalReserved}
              </div>
              <div className="text-[11px] text-neutral-400 mt-1">Locked by customer orders</div>
            </div>

            {/* 4. Available to Sell (ATP) */}
            <div className="p-4 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs hover:border-neutral-700 transition-all">
              <div className="flex items-center justify-between text-neutral-500 dark:text-neutral-400 text-xs font-semibold mb-1">
                <span>Available to Sell (ATP)</span>
                <Sparkles className="w-4 h-4 text-neutral-400" />
              </div>
              <div className="font-mono font-bold text-2xl text-neutral-900 dark:text-white">
                {metrics.totalAvailable}
              </div>
              <div className="text-[11px] text-neutral-400 font-medium mt-1">
                Physical - Reserved
              </div>
            </div>

            {/* 5. Sales Channels & Stores */}
            <div className="p-4 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs hover:border-neutral-700 transition-all col-span-2 lg:col-span-1">
              <div className="flex items-center justify-between text-neutral-500 dark:text-neutral-400 text-xs mb-1">
                <span>Connected Stores</span>
                <Store className="w-4 h-4 text-neutral-400" />
              </div>
              <div className="font-mono font-bold text-2xl text-neutral-900 dark:text-white">
                {metrics.activeStoresCount} <span className="text-xs font-normal text-neutral-500">Stores</span>
              </div>
              <div className="text-[11px] text-neutral-400 mt-1 truncate" title={metrics.activeStoreLabels}>{metrics.activeStoreLabels || 'No connected stores'}</div>
            </div>
          </div>

          {/* Uncounted SKUs Alert Bar */}
          {uncountedItems.length > 0 && (
            <div className="p-4 rounded-2xl bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800/50 flex flex-col sm:flex-row sm:items-center justify-between gap-3 animate-in slide-in-from-top-2">
              <div className="flex items-center gap-3">
                <div className="p-2 rounded-xl bg-amber-500/15 text-amber-700 dark:text-amber-400 shrink-0">
                  <ClipboardCheck className="w-5 h-5" />
                </div>
                <div className="text-xs">
                  <div className="font-bold text-amber-950 dark:text-amber-200">
                    {uncountedItems.length} Master SKU(s) Awaiting Initial Physical Stock Verification
                  </div>
                  <p className="text-amber-900/80 dark:text-amber-300/80 mt-0.5">
                    Items imported from marketplace stores remain in <strong>Not Counted</strong> status until staff performs an initial stock check. External channel quantities are reference snapshots only and cannot be released for sale without physical verification.
                  </p>
                </div>
              </div>

              <div className="flex items-center gap-2 shrink-0">
                {uncountedItems.length > 0 && uncountedItems[0] && (
                  <button
                    type="button"
                    onClick={() => handleOpenFirstCount(uncountedItems[0])}
                    className="px-4 py-2 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold transition shadow-xs flex items-center gap-1.5 cursor-pointer"
                  >
                    <ClipboardCheck className="w-4 h-4" />
                    <span>Start First Count ({uncountedItems[0].masterSku})</span>
                  </button>
                )}
              </div>
            </div>
          )}

          {/* Filters & Search Toolbar */}
          <div className="p-4 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs flex flex-col lg:flex-row lg:items-center justify-between gap-3">
            {/* Search Input */}
            <div className="flex-1 relative">
              <Search className="w-4 h-4 absolute left-3.5 top-3 text-neutral-400" />
              <input
                type="text"
                placeholder="Search by SKU, product name, variant, or barcode..."
                value={searchQuery}
                onChange={e => setSearchQuery(e.target.value)}
                className="w-full pl-10 pr-4 py-2 text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/80 text-neutral-900 dark:text-white placeholder:text-neutral-500"
              />
            </div>

            {/* Filter Dropdowns */}
            <div className="flex flex-wrap items-center gap-2">
              <select value={catalogFilter} onChange={e => setCatalogFilter(e.target.value)}
                className="px-3 py-2 text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300 font-medium cursor-pointer">
                <option value="operational">Active &amp; Draft</option>
                <option value="archived">Archived</option>
                <option value="test">Test</option>
                <option value="all">All Products</option>
              </select>
              {/* Status Filter */}
              <select
                value={statusFilter}
                onChange={e => setStatusFilter(e.target.value as any)}
                className="px-3 py-2 text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300 font-medium cursor-pointer"
              >
                <option value="all">All Statuses</option>
                <option value="uncounted">Not Counted (未盘点)</option>
                <option value="low_stock">Low Stock (≤ 10)</option>
                <option value="synced">Fully Synced</option>
                <option value="mismatch">Sync Mismatch / Error</option>
              </select>

              {/* Platform Filter */}
              <select
                value={platformFilter}
                onChange={e => setPlatformFilter(e.target.value)}
                className="px-3 py-2 text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300 font-medium cursor-pointer"
              >
                <option value="all">All Platforms</option>
                <option value="webstore">JW Storefront</option>
                <option value="shopee">Shopee</option>
                <option value="lazada">Lazada</option>
              </select>

              {/* Store Filter */}
              <select
                value={selectedStoreFilter}
                onChange={e => setSelectedStoreFilter(e.target.value)}
                className="px-3 py-2 text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300 font-medium cursor-pointer max-w-[160px] truncate"
              >
                <option value="all">All Stores</option>
                {(stores || []).filter(s => !s.isComingSoon).map(st => (
                  <option key={st.id} value={st.id}>
                    {st.nickname || st.name}
                  </option>
                ))}
              </select>

              {/* Quick Intake Button */}
              {safeInventory.length > 0 && (
                <button
                  type="button"
                  onClick={() => handleOpenIntake(safeInventory[0], 'intake')}
                  className="px-3.5 py-2 rounded-xl bg-neutral-900 hover:bg-neutral-800 dark:bg-white dark:hover:bg-neutral-100 text-white dark:text-neutral-950 border border-neutral-700 dark:border-neutral-200 text-xs font-bold transition flex items-center gap-1.5 shadow-xs cursor-pointer"
                >
                  <Plus className="w-3.5 h-3.5" />
                  <span>Actual Intake</span>
                </button>
              )}
            </div>
          </div>

          {/* Master SKU Inventory Matrix Table */}
          <div className="p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs overflow-hidden">
            <div className="overflow-x-auto">
              <table className="w-full text-left text-xs">
                <thead>
                  <tr className="border-b border-neutral-100 dark:border-neutral-800 text-neutral-400 uppercase text-[10px] tracking-wider bg-neutral-50/50 dark:bg-neutral-850/60">
                    <th className="py-3 px-3">Product / Master SKU</th>
                    <th className="py-3 px-3">
                      {currentMode === 'shared' ? 'Total Physical' : 'Physical Allocation'}
                    </th>
                    <th className="py-3 px-3">Reserved (Locked)</th>
                    <th className="py-3 px-3">Available to Sell (ATP)</th>
                    <th className="py-3 px-3">Connected Stores & Sync States</th>
                    <th className="py-3 px-3 text-right">Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800">
                  {(filteredInventory || []).map(item => {
                    const isUncounted = (item as any).hasUncountedAccounts || item.physicalStockStatus === 'unknown' || item.totalPhysicalStock === null;
                    const physicalStock = item.totalPhysicalStock ?? 0;
                    const reservedStock = item.reservedStock ?? 0;
                    const availableStock = item.availableStock ?? 0;

                    // Independent store balances for this SKU
                    const skuStoreBalances = (storeBalances && storeBalances[item.masterSku]) || {};

                    return (
                      <tr key={item.id} className="hover:bg-neutral-50/60 dark:hover:bg-neutral-800/50 transition-colors">
                        {/* 1. Product & SKU */}
                        <td className="py-3 px-3 min-w-[220px]">
                          <div className="flex items-center gap-3">
                            <div className="w-12 h-12 rounded-xl bg-neutral-100 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 overflow-hidden shrink-0 flex items-center justify-center">
                              {item.imageUrl ? (
                                <img src={item.imageUrl} alt={item.productName} className="w-full h-full object-cover" loading="lazy" decoding="async" />
                              ) : (
                                <Boxes className="w-5 h-5 text-neutral-400" />
                              )}
                            </div>
                            <div className="min-w-0">
                              <div className="flex items-center gap-2">
                                <span className="font-mono font-bold text-neutral-900 dark:text-white text-xs">
                                  {item.masterSku}
                                </span>
                                {isUncounted && (
                                  <span className="text-[10px] font-bold px-1.5 py-0.2 rounded bg-amber-100 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300 border border-amber-300/40">
                                    Not Counted
                                  </span>
                                )}
                              </div>
                              <div className="font-semibold text-neutral-900 dark:text-white truncate max-w-[220px] mt-0.5">
                                {item.productName}
                              </div>
                              <div className="text-[11px] text-neutral-400 truncate mt-0.5">
                                {item.variantName} {item.barcode && `• ${item.barcode}`}
                              </div>
                            </div>
                          </div>
                        </td>

                        {/* 2. Physical Stock */}
                        <td className="py-3 px-3">
                          {isUncounted ? (
                            <div className="space-y-1">
                              <span className="inline-block text-[11px] font-bold text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/30 px-2 py-0.5 rounded-md border border-amber-200 dark:border-amber-800/40">
                                Not Counted (未盘点)
                              </span>
                              <div className="text-[10px] text-neutral-400">Staff verification required</div>
                            </div>
                          ) : currentMode === 'shared' ? (
                            <div className="font-mono font-bold text-sm text-neutral-900 dark:text-white">
                              {physicalStock} <span className="text-xs text-neutral-400 font-normal">units</span>
                            </div>
                          ) : (
                            <div className="space-y-1">
                              <div className="font-mono font-bold text-neutral-900 dark:text-white">
                                {physicalStock} units total
                              </div>
                              <div className="text-[10px] text-neutral-400 space-y-0.5">
                                {(Object.values(skuStoreBalances) as StoreStockAccountBalance[]).map(b => (
                                  <div key={b.storeId} className="flex items-center justify-between gap-2 max-w-[150px]">
                                    <span className="truncate">{b.storeNickname || b.storeName}:</span>
                                    <span className="font-mono font-semibold">{b.physicalStock ?? 'N/A'}</span>
                                  </div>
                                ))}
                              </div>
                            </div>
                          )}
                        </td>

                        {/* 3. Reserved Stock */}
                        <td className="py-3 px-3">
                          <div className="flex items-center gap-1.5">
                            <span className="font-mono font-bold text-amber-600 dark:text-amber-400 text-sm">
                              {reservedStock}
                            </span>
                            <span className="text-xs text-neutral-400">units</span>
                          </div>
                          <div className="text-[10px] text-neutral-400">Active orders locked</div>
                        </td>

                        {/* 4. Available to Sell (ATP) */}
                        <td className="py-3 px-3">
                          {isUncounted ? (
                            <div className="font-mono text-xs text-neutral-400 italic">
                              0 (Locked until counted)
                            </div>
                          ) : (
                            <div className="space-y-0.5">
                              <div className="font-mono font-bold text-neutral-900 dark:text-white text-base">
                                {availableStock} <span className="text-xs font-normal text-neutral-400">ATP</span>
                              </div>
                              <div className="text-[10px] text-neutral-400">
                                {currentMode === 'shared' ? 'Shared across all channels' : 'Total sellable'}
                              </div>
                            </div>
                          )}
                        </td>

                        {/* 5. Connected Stores & Sync States */}
                        <td className="py-3 px-3 min-w-[260px]">
                          <div className="space-y-1.5">
                            {(stores || []).filter(s => !s.isComingSoon).map(store => {
                              const assoc = (item.storeAssociations || []).find(a => a.connectionId === store.id);
                              const balance = skuStoreBalances[store.id];
                              const channelStock = assoc?.channelStockSnapshot ?? store.platformStockSnapshot ?? 0;
                              const isMapped = store.platform === 'webstore' || ((item as any).mappedStoreIds || []).includes(store.id);

                              return (
                                <div
                                  key={store.id}
                                  className="flex items-center justify-between p-1.5 rounded-lg bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-200/60 dark:border-neutral-700/60 text-[11px]"
                                >
                                  <div className="flex items-center gap-1.5 truncate max-w-[140px]">
                                    <MarketplaceLogo platform={store.platform as any} size="xs" />
                                    <span className="truncate font-medium text-neutral-800 dark:text-neutral-200">
                                      {store.nickname || store.name}
                                    </span>
                                  </div>

                                  {/* Sync & Takeover Badges */}
                                  <div className="flex items-center gap-1.5 shrink-0">
                                    {/* Connection indicator */}
                                    {store.connectionState === 'expired' ? (
                                      <span className="text-[9px] font-bold px-1.5 py-0.2 rounded bg-red-100 dark:bg-red-950/60 text-red-700 dark:text-red-300">
                                        Expired
                                      </span>
                                    ) : null}

                                    {/* Managed vs Unmanaged */}
                                    {currentMode === 'independent' && !isMapped ? (
                                      <span className="text-[9px] font-bold px-1.5 py-0.2 rounded bg-amber-100 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300">
                                        SKU Not Mapped
                                      </span>
                                    ) : !store.isManagedByJw ? (
                                      <span className="text-[9px] font-bold px-1.5 py-0.2 rounded bg-neutral-200 dark:bg-neutral-700 text-neutral-600 dark:text-neutral-300" title="Store not yet managed by JW">
                                        Unmanaged
                                      </span>
                                    ) : isUncounted ? (
                                      <span className="text-[9px] font-bold px-1.5 py-0.2 rounded bg-amber-100 text-amber-800">
                                        Queued
                                      </span>
                                    ) : (
                                      <span className="text-[9px] font-bold px-1.5 py-0.2 rounded bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300 flex items-center gap-1">
                                        <CheckCircle2 className="w-2.5 h-2.5" />
                                        <span>Synced: {currentMode === 'shared' ? availableStock : (balance?.availableStock ?? 0)}</span>
                                      </span>
                                    )}

                                    {/* Platform Reference Snapshot */}
                                    <span className="text-[10px] text-neutral-400 font-mono" title="Reported by channel API">
                                      ({channelStock})
                                    </span>
                                  </div>
                                </div>
                              );
                            })}
                          </div>
                        </td>

                        {/* 6. Actions */}
                        <td className="py-3 px-3 text-right whitespace-nowrap">
                          <div className="flex items-center justify-end gap-1.5">
                            {isUncounted ? (
                              <button
                                type="button"
                                onClick={() => handleOpenFirstCount(item)}
                                className="px-3 py-1.5 rounded-xl bg-neutral-900 hover:bg-neutral-800 dark:bg-neutral-800 dark:hover:bg-neutral-700 text-white text-xs font-bold border border-neutral-700 transition shadow-xs flex items-center gap-1 cursor-pointer"
                              >
                                <ClipboardCheck className="w-3.5 h-3.5" />
                                <span>First Count</span>
                              </button>
                            ) : (
                              <>
                                <button
                                  type="button"
                                  onClick={() => handleOpenIntake(item, 'intake')}
                                  className="p-1.5 rounded-lg border border-neutral-200 dark:border-neutral-700 text-neutral-600 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800 text-xs font-semibold transition cursor-pointer"
                                  title="Add Actual Inbound Stock"
                                >
                                  <Plus className="w-4 h-4 text-emerald-600" />
                                </button>
                                <button
                                  type="button"
                                  onClick={() => handleOpenIntake(item, 'adjustment')}
                                  className="p-1.5 rounded-lg border border-neutral-200 dark:border-neutral-700 text-neutral-600 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800 text-xs font-semibold transition cursor-pointer"
                                  title="Audit Adjust Physical Count"
                                >
                                  <Sliders className="w-4 h-4 text-neutral-400" />
                                </button>
                              </>
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
        </div>
      )}

      {/* ================= TAB 2: STORE STOCK TRANSFERS ================= */}
      {activeTab === 'transfers' && (
        <StoreStockTransfersTab
          inventory={safeInventory}
          stores={stores}
          storeBalances={storeBalances}
          transfersList={transfersList}
          onExecuteTransfer={handleExecuteStoreTransfer}
          onRetryPlatformSync={handleRetryPlatformSync}
        />
      )}

      {/* ================= TAB 3: ACTIVITY HISTORY ================= */}
      {activeTab === 'history' && (
        <InventoryActivityHistoryTab logs={activityLogs} />
      )}

      {/* MODAL 1: First Count Modal (9 steps) */}
      <InitialStockCountModal
        isOpen={isCountModalOpen}
        onClose={() => {
          setIsCountModalOpen(false);
          setSelectedItemForCount(null);
        }}
        item={selectedItemForCount}
        mode={currentMode}
        stores={stores}
        storeBalances={storeBalances}
        onSaveCount={handleSaveFirstCount}
        uncountedQueue={uncountedItems}
        onSelectNextItem={next => setSelectedItemForCount(next)}
        onContinueModeSwitchPreparation={() => {
          setIsCountModalOpen(false);
          setSelectedItemForCount(null);
          setSettingsInitialStep('checklist');
          setIsSettingsModalOpen(true);
        }}
        onNavigateToTransfers={() => { setIsCountModalOpen(false); setActiveTab('transfers'); }}
        onNavigateToSkuMapping={() => setCurrentSection('marketplace-mapping')}
        onRetrySync={async taskId => {
          await retrySync(taskId);
          await reloadInventory();
        }}
      />

      {/* MODAL 2: Stock Intake & Audit Adjustment */}
      <StockIntakeAdjustmentModal
        isOpen={isIntakeModalOpen}
        onClose={() => {
          setIsIntakeModalOpen(false);
          setSelectedItemForIntake(null);
        }}
        item={selectedItemForIntake}
        mode={currentMode}
        stores={stores}
        initialType={intakeInitialType}
        onSave={handleSaveIntake}
      />

      {/* MODAL 3: Admin Master Settings (Mode Transition & Store Sync) */}
      <InventorySettingsModal
        isOpen={isSettingsModalOpen}
        onClose={() => {
          setIsSettingsModalOpen(false);
          setSettingsInitialStep('view');
        }}
        currentMode={currentMode}
        stores={stores}
        inventory={safeInventory}
        modeCatalog={inventoryModeCatalog}
        onSwitchMode={handleSwitchMode}
        onToggleStoreManagement={handleToggleStoreManagement}
        initialSwitchFlowStep={settingsInitialStep}
        onSelectSkuForCount={(sku, variantId) => {
          const it = safeInventory.find(i => i.masterSku === sku);
          if (it) {
            handleOpenFirstCount(it);
            return;
          }
          const catalogItem = inventoryModeCatalog.items.find((entry: any) => entry.variant_id === variantId || entry.sku === sku);
          if (catalogItem) handleOpenFirstCount({id: '', variantId: catalogItem.variant_id, masterSku: catalogItem.sku, barcode: '', productName: catalogItem.product, variantName: catalogItem.variant, imageUrl: catalogItem.thumbnail_url || '', warehouseLocation: '', catalogStockSnapshot: catalogItem.catalog_stock_snapshot, physicalStockStatus: 'unknown', hasUncountedAccounts: true, totalPhysicalStock: null, reservedStock: catalogItem.reserved_stock || 0, safetyStock: 0, availableStock: null, reorderPoint: 0, costPrice: 0, sellingPrice: 0, channelMappings: {}, autoSyncEnabled: false, historyLog: [], storeAssociations: []} as any);
        }}
      />
    </div>
  );
};
