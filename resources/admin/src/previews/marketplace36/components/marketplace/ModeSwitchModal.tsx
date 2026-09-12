import React, { useState, useMemo, useEffect } from 'react';
import {
  InventoryOperatingMode,
  StaffUser,
  EntityWarehouse,
  MarketplaceInventoryItem,
  ModeSwitchServerCheck,
  InventoryModeSwitchAudit
} from '../../types';
import {
  Layers,
  Warehouse,
  AlertTriangle,
  CheckCircle2,
  X,
  ArrowRight,
  ShieldAlert,
  Info,
  Split,
  Merge,
  UserCheck,
  Truck,
  RefreshCw,
  Building,
  Lock,
  Boxes
} from 'lucide-react';

interface ModeSwitchModalProps {
  isOpen: boolean;
  onClose: () => void;
  currentMode: InventoryOperatingMode;
  initialTargetMode?: InventoryOperatingMode;
  onConfirmSwitch: (
    targetMode: InventoryOperatingMode,
    reason: string,
    operator: string,
    strategy: 'primary_all' | 'explicit_distribution',
    explicitAllocations?: Record<string, Record<string, number>>
  ) => Promise<{ success: boolean; error?: string; audit?: InventoryModeSwitchAudit }>;
  staffUsers?: StaffUser[];
  currentUserName?: string;
  skuCount: number;
  entityWarehouses?: EntityWarehouse[];
  inventoryItems?: MarketplaceInventoryItem[];
  checkReadiness?: (targetMode: InventoryOperatingMode) => ModeSwitchServerCheck;
}

export const ModeSwitchModal: React.FC<ModeSwitchModalProps> = ({
  isOpen,
  onClose,
  currentMode,
  initialTargetMode,
  onConfirmSwitch,
  staffUsers = [],
  currentUserName,
  skuCount,
  entityWarehouses = [],
  inventoryItems = [],
  checkReadiness
}) => {
  // Determine target mode
  const defaultTarget: InventoryOperatingMode =
    currentMode === 'unconfigured'
      ? (initialTargetMode || 'independent_warehouses')
      : currentMode === 'shared_pool'
        ? 'independent_warehouses'
        : 'shared_pool';

  const [targetMode, setTargetMode] = useState<InventoryOperatingMode>(defaultTarget);

  useEffect(() => {
    if (currentMode === 'unconfigured') {
      setTargetMode(initialTargetMode || 'independent_warehouses');
    } else {
      setTargetMode(currentMode === 'shared_pool' ? 'independent_warehouses' : 'shared_pool');
    }
  }, [currentMode, initialTargetMode, isOpen]);

  // Reason
  const [reason, setReason] = useState<string>(
    currentMode === 'unconfigured' ? '' : targetMode === 'independent_warehouses'
      ? 'Transitioning to regional fulfillment centers (Cyberjaya Central & Penang North Hub)'
      : 'Consolidating regional warehouse inventory into unified omnichannel master pool'
  );

  // Allocation Strategy (Strictly conserved, NO even split!)
  const [allocationStrategy, setAllocationStrategy] = useState<'primary_all' | 'explicit_distribution'>('primary_all');
  
  // Explicit allocations state: sku -> { warehouseId -> quantity }
  const [explicitAllocations, setExplicitAllocations] = useState<Record<string, Record<string, number>>>(() => {
    const initial: Record<string, Record<string, number>> = {};
    const primaryWh = entityWarehouses.find(w => w.isDefaultMaster) || entityWarehouses[0];
    inventoryItems.forEach(item => {
      initial[item.masterSku] = {};
      entityWarehouses.forEach(wh => {
        initial[item.masterSku][wh.id] = (wh.id === primaryWh?.id) ? (item.totalPhysicalStock || 0) : 0;
      });
    });
    return initial;
  });

  const [acknowledged, setAcknowledged] = useState<boolean>(false);
  const [isSubmitting, setIsSubmitting] = useState<boolean>(false);
  const [serverError, setServerError] = useState<string | null>(null);

  // Authenticated Operator: Strictly locked to current authenticated employee
  const operatorName = currentUserName || 'Authenticated employee unavailable';

  // Server Pre-Check for Blocking Orders & In-Transit Transfers
  const serverReadiness: ModeSwitchServerCheck = useMemo(() => {
    if (checkReadiness) {
      try {return checkReadiness(targetMode);} catch {
        return {canSwitch:false,blockingOrders:[],inTransitTransfers:[]};
      }
    }
    return {
      canSwitch: false,
      blockingOrders: [],
      inTransitTransfers: []
    };
  }, [checkReadiness, targetMode]);

  // Stock conservation check for explicit distribution
  const conservationViolations = useMemo(() => {
    if (currentMode === 'unconfigured' || targetMode !== 'independent_warehouses' || allocationStrategy !== 'explicit_distribution') {
      return [];
    }
    const violations: { sku: string; total: number; sum: number; discrepancy: number }[] = [];
    inventoryItems.forEach(item => {
      const alloc = explicitAllocations[item.masterSku] || {};
      const sum: number = Object.values(alloc).reduce<number>((acc, q) => acc + (Number(q) || 0), 0);
      const total = item.totalPhysicalStock ?? 0;
      if (sum !== total) {
        violations.push({
          sku: item.masterSku,
          total,
          sum,
          discrepancy: sum - total
        });
      }
    });
    return violations;
  }, [currentMode, targetMode, allocationStrategy, inventoryItems, explicitAllocations]);

  const hasBlockingConstraints = !serverReadiness.canSwitch || conservationViolations.length > 0;

  if (!isOpen) return null;

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!acknowledged || hasBlockingConstraints || isSubmitting || !currentUserName || !reason.trim()) return;

    setIsSubmitting(true);
    setServerError(null);

    try {
      const res = await onConfirmSwitch(
        targetMode,
        reason.trim(),
        operatorName,
        allocationStrategy,
        allocationStrategy === 'explicit_distribution' ? explicitAllocations : undefined
      );

      if (res.success) {
        onClose();
      } else {
        setServerError(res.error || 'Server rejected mode switch request.');
      }
    } catch (err: any) {
      setServerError(err.message || 'An error occurred contacting the server.');
    } finally {
      setIsSubmitting(false);
    }
  };

  const primaryWh = entityWarehouses.find(w => w.isDefaultMaster) || entityWarehouses[0];

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/75 backdrop-blur-xs overflow-y-auto">
      <div className="relative w-full max-w-2xl max-h-[calc(100dvh-1.5rem)] sm:max-h-[calc(100dvh-2.5rem)] my-auto flex flex-col bg-white dark:bg-neutral-900 rounded-2xl shadow-2xl border border-neutral-200 dark:border-neutral-800 overflow-hidden animate-in fade-in zoom-in-95 duration-150">
        
        {/* Header - Pinned */}
        <div className="p-4 sm:p-5 border-b border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-950/70 flex items-center justify-between shrink-0">
          <div className="flex items-center gap-3">
            <div className={`p-2.5 rounded-xl border ${
              targetMode === 'independent_warehouses'
                ? 'bg-purple-500/10 text-purple-600 dark:text-purple-400 border-purple-500/20'
                : 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border-blue-500/20'
            }`}>
              {targetMode === 'independent_warehouses' ? <Split className="w-5 h-5" /> : <Merge className="w-5 h-5" />}
            </div>
            <div>
              <h3 className="text-sm font-bold text-neutral-900 dark:text-white">
                {currentMode === 'unconfigured' ? 'Configure Inventory Architecture' : 'Switch Inventory Operating Architecture'}
              </h3>
              <p className="text-xs text-neutral-500 mt-0.5">
                Current: <span className="font-semibold capitalize text-neutral-700 dark:text-neutral-300">{currentMode === 'unconfigured' ? 'Awaiting Selection (Not Configured)' : currentMode.replace('_', ' ')}</span>
              </p>
            </div>
          </div>
          <button
            onClick={onClose}
            className="p-1.5 rounded-lg text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-200 hover:bg-neutral-100 dark:hover:bg-neutral-800 cursor-pointer transition-colors"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        <form onSubmit={handleSubmit} className="flex flex-col flex-1 min-h-0 overflow-hidden">
          <div className="p-4 sm:p-6 space-y-5 text-xs overflow-y-auto flex-1 min-h-0">
            
            {/* Server Error Alert */}
            {serverError && (
              <div className="p-3.5 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-800 dark:text-rose-300 flex items-start gap-2.5">
                <AlertTriangle className="w-4 h-4 text-rose-500 shrink-0 mt-0.5" />
                <div>
                  <span className="font-bold block text-xs">Server Contract Error</span>
                  <span className="text-[11px]">{serverError}</span>
                </div>
              </div>
            )}

            {/* Target Mode Selector for Unconfigured State */}
            {currentMode === 'unconfigured' && (
              <div className="space-y-2">
                <label className="block font-bold text-neutral-800 dark:text-neutral-200">
                  Select Target Architecture <span className="text-rose-500">*</span>
                </label>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <button
                    type="button"
                    onClick={() => setTargetMode('independent_warehouses')}
                    className={`p-3.5 rounded-xl border text-left cursor-pointer transition-all ${
                      targetMode === 'independent_warehouses'
                        ? 'bg-purple-500/10 border-purple-500 text-neutral-900 dark:text-white shadow-xs'
                        : 'bg-neutral-50 dark:bg-neutral-950 border-neutral-200 dark:border-neutral-800 text-neutral-500'
                    }`}
                  >
                    <div className="flex items-center gap-2 font-bold text-xs mb-1">
                      <Split className="w-4 h-4 text-purple-600 dark:text-purple-400" />
                      <span>Independent Multi-Warehouse</span>
                    </div>
                    <p className="text-[11px] text-neutral-500">
                      Per-facility stock & ATP tracking. Stores bind to specific fulfillment warehouses.
                    </p>
                  </button>

                  <button
                    type="button"
                    onClick={() => setTargetMode('shared_pool')}
                    className={`p-3.5 rounded-xl border text-left cursor-pointer transition-all ${
                      targetMode === 'shared_pool'
                        ? 'bg-blue-500/10 border-blue-500 text-neutral-900 dark:text-white shadow-xs'
                        : 'bg-neutral-50 dark:bg-neutral-950 border-neutral-200 dark:border-neutral-800 text-neutral-500'
                    }`}
                  >
                    <div className="flex items-center gap-2 font-bold text-xs mb-1">
                      <Merge className="w-4 h-4 text-blue-600 dark:text-blue-400" />
                      <span>Unified Shared Inventory Pool</span>
                    </div>
                    <p className="text-[11px] text-neutral-500">
                      Single global stock pool shared across website, Shopee & Lazada channels.
                    </p>
                  </button>
                </div>
              </div>
            )}

            {/* Architecture Comparison Cards */}
            {currentMode !== 'unconfigured' && (
              <div className="grid grid-cols-2 gap-3">
                <div className="p-3.5 rounded-xl bg-neutral-50 dark:bg-neutral-950 border border-neutral-200 dark:border-neutral-800 space-y-1 opacity-80">
                  <span className="text-[10px] font-bold text-neutral-400 uppercase tracking-wider block">Current Mode</span>
                  <p className="font-bold text-neutral-900 dark:text-white text-xs">
                    {currentMode === 'shared_pool' ? 'Shared Inventory Pool' : 'Independent Warehouses'}
                  </p>
                  <p className="text-[11px] text-neutral-500 leading-relaxed">
                    {currentMode === 'shared_pool'
                      ? 'Global inventory pool across webstore and channels.'
                      : 'Physical facilities operate independent stock and routing.'}
                  </p>
                </div>

                <div className="p-3.5 rounded-xl bg-purple-500/5 dark:bg-purple-500/10 border border-purple-500/30 space-y-1">
                  <span className="text-[10px] font-bold text-purple-600 dark:text-purple-400 uppercase tracking-wider block">Switching To</span>
                  <p className="font-bold text-neutral-900 dark:text-white text-xs flex items-center gap-1.5">
                    <span>{targetMode === 'independent_warehouses' ? 'Independent Warehouses' : 'Shared Inventory Pool'}</span>
                    <span className="w-2 h-2 rounded-full bg-emerald-500 animate-pulse" />
                  </p>
                  <p className="text-[11px] text-neutral-600 dark:text-neutral-300 leading-relaxed">
                    {targetMode === 'independent_warehouses'
                      ? 'Partition stock into physical warehouse balances. Stores deduct from mapped facility.'
                      : 'Consolidate warehouse balances into a single master pool.'}
                  </p>
                </div>
              </div>
            )}

            {/* Server Blocking Pre-Check Alert */}
            {!serverReadiness.canSwitch && (
              <div className="p-4 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-900 dark:text-rose-300 space-y-2">
                <div className="flex items-center gap-2 font-bold text-xs">
                  <ShieldAlert className="w-4 h-4 text-rose-600 shrink-0" />
                  <span>Server Constraints Blocking Mode Switch</span>
                </div>
                <p className="text-[11px] leading-relaxed">
                  The server pre-flight check has identified blocking conditions that prevent switching modes safely:
                </p>

                {serverReadiness.blockingOrders.length > 0 && (
                  <div className="space-y-1.5 pt-1">
                    <span className="font-bold text-[10px] uppercase text-rose-700 dark:text-rose-400">
                      Unrouted Orders Pending Allocation ({serverReadiness.blockingOrders.length})
                    </span>
                    {serverReadiness.blockingOrders.map((ord, idx) => (
                      <div key={idx} className="p-2 rounded bg-white/60 dark:bg-neutral-900/60 border border-rose-500/20 flex items-center justify-between text-[11px]">
                        <span>Order <strong className="font-mono">{ord.orderNumber}</strong> ({ord.platform}) — SKU: {ord.sku}</span>
                        <span className="text-rose-600 dark:text-rose-400 font-medium">{ord.reason}</span>
                      </div>
                    ))}
                  </div>
                )}

                {serverReadiness.inTransitTransfers.length > 0 && (
                  <div className="space-y-1.5 pt-1">
                    <span className="font-bold text-[10px] uppercase text-rose-700 dark:text-rose-400">
                      In-Transit Inter-Warehouse Transfers ({serverReadiness.inTransitTransfers.length})
                    </span>
                    {serverReadiness.inTransitTransfers.map(tr => (
                      <div key={tr.id} className="p-2 rounded bg-white/60 dark:bg-neutral-900/60 border border-rose-500/20 flex items-center justify-between text-[11px]">
                        <div className="flex items-center gap-1.5">
                          <Truck className="w-3.5 h-3.5 text-amber-600" />
                          <span>Transfer <strong className="font-mono">{tr.id}</strong>: {tr.quantity} units of {tr.sku}</span>
                        </div>
                        <span className="text-neutral-500">{tr.sourceWarehouseName} ➔ {tr.destinationWarehouseName}</span>
                      </div>
                    ))}
                  </div>
                )}
              </div>
            )}

            {/* Stock Conservation Rule when switching to Independent Warehouses */}
            {currentMode !== 'unconfigured' && targetMode === 'independent_warehouses' && (
              <div className="space-y-3">
                <div className="flex items-center justify-between">
                  <label className="block font-bold text-neutral-800 dark:text-neutral-200">
                    Stock Partitioning Strategy (Stock Conservation Mandated) <span className="text-rose-500">*</span>
                  </label>
                  <span className="text-[10px] font-mono text-emerald-600 dark:text-emerald-400 bg-emerald-500/10 px-2 py-0.5 rounded border border-emerald-500/20 font-bold">
                    Zero-Discrepancy Enforced
                  </span>
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                  <label className={`p-3 rounded-xl border cursor-pointer transition-all flex items-start gap-2.5 ${
                    allocationStrategy === 'primary_all'
                      ? 'bg-neutral-100 dark:bg-neutral-800 border-neutral-400 dark:border-neutral-500 text-neutral-900 dark:text-white shadow-xs'
                      : 'bg-white dark:bg-neutral-950 border-neutral-200 dark:border-neutral-800 text-neutral-600 dark:text-neutral-400'
                  }`}>
                    <input
                      type="radio"
                      name="strategy"
                      value="primary_all"
                      checked={allocationStrategy === 'primary_all'}
                      onChange={() => setAllocationStrategy('primary_all')}
                      className="mt-0.5"
                    />
                    <div>
                      <span className="font-bold block text-xs">Master Facility Attribution (100%)</span>
                      <span className="text-[10px] text-neutral-500 mt-0.5 block leading-relaxed">
                        Assigns 100% of physical stock to Default Master ({primaryWh?.name || 'Cyberjaya Central Hub'}). Secondary hubs initialize at 0 units for verified transfer or inbound. Strictly conserved.
                      </span>
                    </div>
                  </label>

                  <label className={`p-3 rounded-xl border cursor-pointer transition-all flex items-start gap-2.5 ${
                    allocationStrategy === 'explicit_distribution'
                      ? 'bg-neutral-100 dark:bg-neutral-800 border-neutral-400 dark:border-neutral-500 text-neutral-900 dark:text-white shadow-xs'
                      : 'bg-white dark:bg-neutral-950 border-neutral-200 dark:border-neutral-800 text-neutral-600 dark:text-neutral-400'
                  }`}>
                    <input
                      type="radio"
                      name="strategy"
                      value="explicit_distribution"
                      checked={allocationStrategy === 'explicit_distribution'}
                      onChange={() => setAllocationStrategy('explicit_distribution')}
                      className="mt-0.5"
                    />
                    <div>
                      <span className="font-bold block text-xs">Explicit Facility Allocation</span>
                      <span className="text-[10px] text-neutral-500 mt-0.5 block leading-relaxed">
                        Specify exact physical stock distribution across active facilities. Total must equal total physical stock with zero remainder.
                      </span>
                    </div>
                  </label>
                </div>

                {/* Explicit Allocation Grid if chosen */}
                {allocationStrategy === 'explicit_distribution' && (
                  <div className="p-3.5 rounded-xl bg-neutral-50 dark:bg-neutral-950 border border-neutral-200 dark:border-neutral-800 space-y-3">
                    <span className="font-bold text-neutral-800 dark:text-neutral-200 block text-xs">
                      Per-Facility Physical Stock Attribution
                    </span>

                    <div className="space-y-2 max-h-48 overflow-y-auto">
                      {inventoryItems.map(item => {
                        const alloc = explicitAllocations[item.masterSku] || {};
                        const sum: number = Object.values(alloc).reduce<number>((acc, q) => acc + (Number(q) || 0), 0);
                        const total = item.totalPhysicalStock ?? 0;
                        const diff = sum - total;

                        return (
                          <div key={item.id} className="p-2.5 rounded-lg bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 space-y-1.5">
                            <div className="flex items-center justify-between">
                              <span className="font-mono font-bold text-neutral-900 dark:text-white">{item.masterSku}</span>
                              <div className="flex items-center gap-2">
                                <span className="text-[11px] text-neutral-500">Total Physical: <strong className="text-neutral-800 dark:text-neutral-200">{total}</strong></span>
                                {diff === 0 ? (
                                  <span className="text-[10px] font-bold text-emerald-600 bg-emerald-500/10 px-1.5 py-0.5 rounded">Conserved</span>
                                ) : (
                                  <span className="text-[10px] font-bold text-rose-600 bg-rose-500/10 px-1.5 py-0.5 rounded">
                                    Discrepancy: {diff > 0 ? `+${diff}` : diff}
                                  </span>
                                )}
                              </div>
                            </div>

                            <div className="grid grid-cols-2 sm:grid-cols-3 gap-2">
                              {entityWarehouses.map(wh => (
                                <div key={wh.id} className="flex items-center gap-1.5">
                                  <span className="text-[10px] text-neutral-500 truncate w-20">{wh.code}:</span>
                                  <input
                                    type="number"
                                    min="0"
                                    value={alloc[wh.id] ?? 0}
                                    onChange={e => {
                                      const val = parseInt(e.target.value, 10) || 0;
                                      setExplicitAllocations(prev => ({
                                        ...prev,
                                        [item.masterSku]: {
                                          ...(prev[item.masterSku] || {}),
                                          [wh.id]: val
                                        }
                                      }));
                                    }}
                                    className="w-16 px-2 py-1 rounded bg-neutral-100 dark:bg-neutral-800 border border-neutral-300 dark:border-neutral-700 text-xs font-mono text-neutral-900 dark:text-white"
                                  />
                                </div>
                              ))}
                            </div>
                          </div>
                        );
                      })}
                    </div>

                    {conservationViolations.length > 0 && (
                      <div className="p-2.5 rounded-lg bg-rose-500/10 border border-rose-500/30 text-rose-800 dark:text-rose-300 text-[11px] flex items-center gap-2">
                        <AlertTriangle className="w-4 h-4 text-rose-600 shrink-0" />
                        <span>
                          <strong>Stock Conservation Error:</strong> {conservationViolations.length} SKU(s) have unallocated or mismatched units. The total assigned must equal total physical stock exactly.
                        </span>
                      </div>
                    )}
                  </div>
                )}
              </div>
            )}

            {/* Authenticated Staff Operator (Locked to prevent impersonation) */}
            <div className="p-3 rounded-xl bg-neutral-50 dark:bg-neutral-950 border border-neutral-200 dark:border-neutral-800 flex items-center justify-between">
              <div className="flex items-center gap-2.5">
                <UserCheck className="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
                <div>
                  <span className="text-[10px] text-neutral-400 uppercase tracking-wider block font-medium">Authorizing Operator (Authenticated Staff)</span>
                  <span className="font-bold text-neutral-900 dark:text-white text-xs">{operatorName}</span>
                </div>
              </div>
              <div className="flex items-center gap-1.5 text-[10px] font-mono text-neutral-400 bg-neutral-100 dark:bg-neutral-800 px-2 py-1 rounded">
                <Lock className="w-3 h-3 text-neutral-500" />
                <span>VERIFIED SESSION</span>
              </div>
            </div>

            {/* Reason for Switch */}
            <div>
              <label className="block font-bold text-neutral-700 dark:text-neutral-300 mb-1.5">
                {currentMode === 'unconfigured' ? 'Reason for Initial Configuration' : 'Mandatory Reason for Architecture Switch'} <span className="text-rose-500">*</span>
              </label>
              <textarea
                rows={2}
                value={reason}
                onChange={e => setReason(e.target.value)}
                required
                className="w-full px-3 py-2 rounded-xl bg-neutral-50 dark:bg-neutral-950 border border-neutral-300 dark:border-neutral-700 text-neutral-900 dark:text-white text-xs focus:outline-hidden focus:ring-2 focus:ring-purple-500"
              />
            </div>

            {/* Confirmation Checkbox */}
            <label className="flex items-start gap-2.5 p-3.5 rounded-xl bg-neutral-100/80 dark:bg-neutral-800/40 border border-neutral-200 dark:border-neutral-800 cursor-pointer">
              <input
                type="checkbox"
                checked={acknowledged}
                onChange={e => setAcknowledged(e.target.checked)}
                className="mt-0.5 rounded text-neutral-900 focus:ring-neutral-500"
              />
              <span className="text-[11px] text-neutral-700 dark:text-neutral-300 leading-snug">
                {currentMode === 'unconfigured'
                  ? 'I acknowledge that this selects the Basic WMS inventory mode. Physical quantities remain unverified until counted. Existing order deductions and platform stock writes remain unchanged.'
                  : <>I acknowledge that executing this architecture change alters inventory tracking across {skuCount} SKUs and updates fulfillment deduction rules for connected Shopee and Lazada channels.</>}
              </span>
            </label>

          </div>

          {/* Actions - Pinned Footer */}
          <div className="p-3.5 sm:px-6 sm:py-3.5 flex items-center justify-between border-t border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-950/70 shrink-0">
            <button
              type="button"
              onClick={onClose}
              className="px-4 py-2 text-xs font-semibold text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800 rounded-xl cursor-pointer transition-colors"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={!acknowledged || hasBlockingConstraints || isSubmitting || !currentUserName || !reason.trim()}
              className="px-5 py-2 bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 hover:bg-neutral-800 dark:hover:bg-neutral-100 disabled:opacity-40 disabled:cursor-not-allowed text-xs font-bold rounded-xl shadow-xs flex items-center gap-1.5 cursor-pointer transition-colors"
            >
              {isSubmitting ? (
                <>
                  <RefreshCw className="w-3.5 h-3.5 animate-spin" />
                  <span>Submitting to Server...</span>
                </>
              ) : (
                <>
                  <CheckCircle2 className="w-3.5 h-3.5" />
                  <span>{currentMode === 'unconfigured' ? 'Confirm Initial Configuration' : 'Confirm & Execute Migration'}</span>
                </>
              )}
            </button>
          </div>

        </form>

      </div>
    </div>
  );
};
