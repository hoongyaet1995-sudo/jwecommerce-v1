import React, { useState } from 'react';
import {
  InventoryOperatingMode,
  EntityWarehouse,
  InventoryModeSwitchAudit,
  StoreFulfillmentBinding,
  MarketplaceInventoryItem
} from '../../types';
import {
  Layers,
  Warehouse,
  Split,
  Merge,
  Plus,
  Edit2,
  Trash2,
  CheckCircle2,
  AlertTriangle,
  History,
  ShieldCheck,
  Building,
  MapPin,
  Store,
  Boxes,
  HelpCircle,
  ExternalLink
} from 'lucide-react';

interface ModeAndWarehouseConfigTabProps {
  currentMode: InventoryOperatingMode;
  entityWarehouses: EntityWarehouse[];
  storeFulfillmentBindings: StoreFulfillmentBinding[];
  inventoryItems: MarketplaceInventoryItem[];
  auditLogs: InventoryModeSwitchAudit[];
  onOpenSwitchModal: (targetMode?: InventoryOperatingMode) => void;
  onOpenAddWarehouseModal: () => void;
  onOpenEditWarehouseModal: (warehouse: EntityWarehouse) => void;
  onDeleteWarehouse: (warehouseId: string) => { success: boolean; error?: string };
  onResetToUnconfigured?: () => void;
}

export const ModeAndWarehouseConfigTab: React.FC<ModeAndWarehouseConfigTabProps> = ({
  currentMode,
  entityWarehouses = [],
  storeFulfillmentBindings = [],
  inventoryItems = [],
  auditLogs = [],
  onOpenSwitchModal,
  onOpenAddWarehouseModal,
  onOpenEditWarehouseModal,
  onDeleteWarehouse,
  onResetToUnconfigured
}) => {
  const [deleteConfirmId, setDeleteConfirmId] = useState<string | null>(null);

  const handleDelete = (wh: EntityWarehouse) => {
    const res = onDeleteWarehouse(wh.id);
    if (!res.success) {
      alert(res.error || 'Failed to delete warehouse');
    }
    setDeleteConfirmId(null);
  };

  const isUnconfigured = currentMode === 'unconfigured';

  return (
    <div className="space-y-6">
      
      {/* 1. Operating Architecture Card */}
      <div className="p-6 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs space-y-4">
        {isUnconfigured ? (
          <div className="space-y-4">
            <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
              <div className="flex items-start gap-4">
                <div className="p-3 rounded-2xl border bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20">
                  <AlertTriangle className="w-6 h-6" />
                </div>
                <div>
                  <div className="flex items-center gap-2.5">
                    <h2 className="text-lg font-black text-neutral-900 dark:text-white">
                      Inventory Architecture: Awaiting Initial Configuration
                    </h2>
                    <span className="text-[11px] font-bold px-2.5 py-0.5 rounded-full border bg-amber-500/10 text-amber-700 dark:text-amber-300 border-amber-500/30">
                      Not Configured (Default Inactive)
                    </span>
                  </div>
                  <p className="text-xs text-neutral-500 mt-1 max-w-2xl leading-relaxed">
                    No operating architecture is currently active. The system does not assume or default to any mode. Please select either Independent Multi-Warehouse Mode or Unified Shared Inventory Pool Mode to initialize WMS inventory routing.
                  </p>
                </div>
              </div>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4 pt-2">
              <div className="p-4 rounded-xl border border-purple-500/30 bg-purple-500/5 dark:bg-purple-500/10 flex flex-col justify-between space-y-3">
                <div className="space-y-1.5">
                  <div className="flex items-center gap-2">
                    <Split className="w-5 h-5 text-purple-600 dark:text-purple-400" />
                    <span className="font-bold text-sm text-neutral-900 dark:text-white">Independent Multi-Warehouse Mode</span>
                  </div>
                  <p className="text-xs text-neutral-600 dark:text-neutral-300 leading-relaxed">
                    Physical facilities (Cyberjaya Central Hub, Penang North Hub) operate separate physical stocks and ATP. Connected marketplace stores fulfill strictly from their assigned facility.
                  </p>
                </div>
                <button
                  onClick={() => onOpenSwitchModal('independent_warehouses')}
                  className="w-full py-2.5 px-4 rounded-xl bg-purple-600 hover:bg-purple-700 text-white text-xs font-bold transition-colors cursor-pointer flex items-center justify-center gap-2"
                >
                  <Split className="w-4 h-4" />
                  <span>Initialize as Independent Multi-Warehouse</span>
                </button>
              </div>

              <div className="p-4 rounded-xl border border-blue-500/30 bg-blue-500/5 dark:bg-blue-500/10 flex flex-col justify-between space-y-3">
                <div className="space-y-1.5">
                  <div className="flex items-center gap-2">
                    <Merge className="w-5 h-5 text-blue-600 dark:text-blue-400" />
                    <span className="font-bold text-sm text-neutral-900 dark:text-white">Unified Shared Inventory Pool</span>
                  </div>
                  <p className="text-xs text-neutral-600 dark:text-neutral-300 leading-relaxed">
                    A single unified aggregate stock pool is shared by the JW Webstore, Shopee stores, and Lazada stores. Warehouse entities act as physical shelving reference bins.
                  </p>
                </div>
                <button
                  onClick={() => onOpenSwitchModal('shared_pool')}
                  className="w-full py-2.5 px-4 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold transition-colors cursor-pointer flex items-center justify-center gap-2"
                >
                  <Merge className="w-4 h-4" />
                  <span>Initialize as Unified Shared Pool</span>
                </button>
              </div>
            </div>
          </div>
        ) : (
          <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div className="flex items-start gap-4">
              <div className={`p-3 rounded-2xl border ${
                currentMode === 'independent_warehouses'
                  ? 'bg-purple-500/10 text-purple-600 dark:text-purple-400 border-purple-500/20'
                  : 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border-blue-500/20'
              }`}>
                {currentMode === 'independent_warehouses' ? (
                  <Split className="w-6 h-6" />
                ) : (
                  <Merge className="w-6 h-6" />
                )}
              </div>
              <div>
                <div className="flex items-center gap-2.5">
                  <h2 className="text-lg font-black text-neutral-900 dark:text-white">
                    {currentMode === 'independent_warehouses'
                      ? 'Independent Multi-Warehouse Mode'
                      : 'Unified Shared Inventory Pool Mode'}
                  </h2>
                  <span className={`text-[11px] font-bold px-2.5 py-0.5 rounded-full border ${
                    currentMode === 'independent_warehouses'
                      ? 'bg-purple-500/10 text-purple-700 dark:text-purple-300 border-purple-500/30'
                      : 'bg-blue-500/10 text-blue-700 dark:text-blue-300 border-blue-500/30'
                  }`}>
                    Active Architecture
                  </span>
                </div>
                <p className="text-xs text-neutral-500 mt-1 max-w-2xl leading-relaxed">
                  {currentMode === 'independent_warehouses'
                    ? 'Each physical facility (Cyberjaya, Penang, etc.) maintains distinct physical stock, reservations, and Available-to-Promise (ATP). Connected Shopee/Lazada channels and JW Webstore deduct from their specifically bound warehouse.'
                    : 'Every JW SKU maintains a single unified stock pool. The JW Webstore, multiple Shopee stores, and multiple Lazada stores all share and deduct from the same master balance. Warehouse locations serve solely as physical storage bin references.'}
                </p>
              </div>
            </div>

            <div className="flex items-center gap-2">
              <button
                onClick={() => onOpenSwitchModal()}
                className="px-4 py-2.5 rounded-xl bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 hover:bg-neutral-800 dark:hover:bg-neutral-100 text-xs font-bold shadow-xs flex items-center gap-2 cursor-pointer shrink-0 transition-all"
              >
                {currentMode === 'independent_warehouses' ? (
                  <>
                    <Merge className="w-4 h-4" />
                    <span>Switch to Shared Pool</span>
                  </>
                ) : (
                  <>
                    <Split className="w-4 h-4" />
                    <span>Switch to Multi-Warehouse</span>
                  </>
                )}
              </button>
              {onResetToUnconfigured && (
                <button
                  onClick={onResetToUnconfigured}
                  title="Test resetting operating mode to unconfigured initial state"
                  className="p-2.5 rounded-xl border border-neutral-200 dark:border-neutral-800 hover:bg-neutral-100 dark:hover:bg-neutral-800 text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-200 text-xs font-medium cursor-pointer transition-colors"
                >
                  Reset
                </button>
              )}
            </div>
          </div>
        )}

        {/* Informative Specs Comparison */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-3 pt-3 border-t border-neutral-200 dark:border-neutral-800 text-xs">
          <div className="p-3 rounded-xl bg-neutral-50 dark:bg-neutral-950 border border-neutral-200 dark:border-neutral-800">
            <span className="font-bold text-neutral-700 dark:text-neutral-300 block mb-1">
              Store Quota Independence
            </span>
            <p className="text-[11px] text-neutral-500">
              {currentMode === 'independent_warehouses'
                ? 'Stock is managed at the warehouse level, NOT artificial store quotas. Multiple stores mapped to Cyberjaya share its stock concurrently.'
                : 'Configured channels reference one shared stock pool. Platform writes require separate authorization.'}
            </p>
          </div>
          <div className="p-3 rounded-xl bg-neutral-50 dark:bg-neutral-950 border border-neutral-200 dark:border-neutral-800">
            <span className="font-bold text-neutral-700 dark:text-neutral-300 block mb-1">
              Fulfillment Routing
            </span>
            <p className="text-[11px] text-neutral-500">
              {currentMode === 'independent_warehouses'
                ? 'Store bindings record the selected fulfillment warehouse. Basic WMS does not activate automatic order deductions.'
                : 'The shared master balance is independent of shipping origin. Basic WMS does not activate automatic order deductions.'}
            </p>
          </div>
          <div className="p-3 rounded-xl bg-neutral-50 dark:bg-neutral-950 border border-neutral-200 dark:border-neutral-800">
            <span className="font-bold text-neutral-700 dark:text-neutral-300 block mb-1">
              Stock Rebalancing
            </span>
            <p className="text-[11px] text-neutral-500">
              {currentMode === 'independent_warehouses'
                ? 'Formal warehouse transfers require the advanced WMS workflow. They are not activated by selecting this mode.'
                : 'Stock can be received into any shelf bin tag without requiring inter-warehouse transfer workflows.'}
            </p>
          </div>
        </div>
      </div>

      {/* 2. Entity Warehouses Management */}
      <div className="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-xs overflow-hidden">
        <div className="p-5 border-b border-neutral-200 dark:border-neutral-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-neutral-50 dark:bg-neutral-950/50">
          <div>
            <div className="flex items-center gap-2">
              <h3 className="font-bold text-sm text-neutral-900 dark:text-white">
                JW Entity Fulfillment Warehouses
              </h3>
              <span className="text-[10px] font-bold px-2 py-0.5 rounded-full bg-neutral-200 dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300">
                {entityWarehouses.length} Facilities
              </span>
            </div>
            <p className="text-xs text-neutral-500 mt-0.5">
              Physical stocking locations that store inventory, perform audits, and dispatch customer orders
            </p>
          </div>

          <button
            onClick={onOpenAddWarehouseModal}
            className="px-3.5 py-2 rounded-xl bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 hover:bg-neutral-800 dark:hover:bg-neutral-100 text-xs font-bold shadow-xs flex items-center gap-1.5 cursor-pointer shrink-0 transition-colors"
          >
            <Plus className="w-3.5 h-3.5" />
            <span>Add Entity Warehouse</span>
          </button>
        </div>

        {/* Warehouses Table */}
        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs">
            <thead className="bg-neutral-50 dark:bg-neutral-950/70 border-b border-neutral-200 dark:border-neutral-800 text-neutral-500 font-bold uppercase tracking-wider text-[10px]">
              <tr>
                <th className="py-3.5 px-4">Warehouse Code / Facility</th>
                <th className="py-3.5 px-4">Address & Location</th>
                <th className="py-3.5 px-4">Supervisor Contact</th>
                <th className="py-3.5 px-4 text-center">Bound Stores</th>
                <th className="py-3.5 px-4 text-center">SKUs Stocked</th>
                <th className="py-3.5 px-4 text-center">Status</th>
                <th className="py-3.5 px-4 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-200 dark:divide-neutral-800">
              {entityWarehouses.map(wh => {
                const boundStores = storeFulfillmentBindings.filter(b => b.boundWarehouseId === wh.id);
                const stockedSkuCount = inventoryItems.filter(i =>
                  (i.warehouseBalances || []).some(b => b.warehouseId === wh.id && b.physicalStock > 0)
                ).length;

                return (
                  <tr key={wh.id} className="hover:bg-neutral-50 dark:hover:bg-neutral-800/40 transition-colors">
                    {/* Facility */}
                    <td className="py-4 px-4">
                      <div className="flex items-center gap-3">
                        <div className="p-2 rounded-xl bg-neutral-100 dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300 border border-neutral-200 dark:border-neutral-700 shrink-0">
                          <Warehouse className="w-4 h-4" />
                        </div>
                        <div>
                          <div className="flex items-center gap-2">
                            <span className="font-mono font-bold text-neutral-900 dark:text-white">
                              {wh.code}
                            </span>
                            {wh.isDefaultMaster && (
                              <span className="text-[9px] font-bold px-1.5 py-0.5 rounded bg-amber-500/15 text-amber-700 dark:text-amber-300 border border-amber-500/30">
                                ★ Default Master
                              </span>
                            )}
                          </div>
                          <p className="font-semibold text-neutral-800 dark:text-neutral-200 mt-0.5">
                            {wh.dataClassification==='test'?'[TEST] ':wh.dataClassification==='operational'?'':'[Unclassified] '}{wh.name}
                          </p>
                        </div>
                      </div>
                    </td>

                    {/* Address */}
                    <td className="py-4 px-4 text-neutral-600 dark:text-neutral-400">
                      <div className="flex items-start gap-1.5">
                        <MapPin className="w-3.5 h-3.5 text-neutral-400 shrink-0 mt-0.5" />
                        <div>
                          <p className="font-medium text-neutral-800 dark:text-neutral-200">
                            {(typeof wh.address === 'string' ? wh.address : wh.address?.addressLine) || 'Address not configured'}
                          </p>
                          <p className="text-[11px] text-neutral-500">
                            {typeof wh.address === 'string' ? wh.state : <>{wh.address?.city}, {wh.address?.state} {wh.address?.postalCode}</>}
                          </p>
                        </div>
                      </div>
                    </td>

                    {/* Supervisor */}
                    <td className="py-4 px-4">
                      <p className="font-medium text-neutral-800 dark:text-neutral-200">
                        {wh.contactPerson || 'Unassigned'}
                      </p>
                      <p className="text-[11px] text-neutral-500 font-mono">
                        {wh.contactPhone || '—'}
                      </p>
                    </td>

                    {/* Bound Stores */}
                    <td className="py-4 px-4 text-center">
                      <span className="font-bold text-neutral-900 dark:text-white text-sm">
                        {boundStores.length}
                      </span>
                      <span className="block text-[10px] text-neutral-400">
                        {boundStores.length === 1 ? 'Store' : 'Stores'}
                      </span>
                    </td>

                    {/* Stocked SKUs */}
                    <td className="py-4 px-4 text-center">
                      <span className="font-bold text-neutral-900 dark:text-white text-sm">
                        {stockedSkuCount}
                      </span>
                      <span className="block text-[10px] text-neutral-400">
                        Active SKUs
                      </span>
                    </td>

                    {/* Status */}
                    <td className="py-4 px-4 text-center">
                      <span className={`inline-block font-bold text-[10px] px-2 py-0.5 rounded-full border ${
                        wh.status === 'active'
                          ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-500/20'
                          : wh.status === 'maintenance'
                          ? 'bg-amber-500/10 text-amber-700 dark:text-amber-400 border-amber-500/20'
                          : 'bg-neutral-200 dark:bg-neutral-800 text-neutral-500 border-neutral-300 dark:border-neutral-700'
                      }`}>
                        {wh.status.toUpperCase()}
                      </span>
                    </td>

                    {/* Actions */}
                    <td className="py-4 px-4 text-right">
                      <div className="inline-flex items-center gap-1">
                        <button
                          onClick={() => onOpenEditWarehouseModal(wh)}
                          className="p-1.5 rounded-lg bg-neutral-100 dark:bg-neutral-800 hover:bg-neutral-200 dark:hover:bg-neutral-700 text-neutral-700 dark:text-neutral-300 transition-colors cursor-pointer"
                          title="Edit Warehouse Configuration"
                        >
                          <Edit2 className="w-3.5 h-3.5" />
                        </button>

                        {!wh.isDefaultMaster && (
                          <button
                            onClick={() => setDeleteConfirmId(wh.id)}
                            className="p-1.5 rounded-lg text-rose-500 hover:bg-rose-500/10 transition-colors cursor-pointer"
                            title="Delete Warehouse"
                          >
                            <Trash2 className="w-3.5 h-3.5" />
                          </button>
                        )}
                      </div>

                      {/* Delete Confirmation Inline */}
                      {deleteConfirmId === wh.id && (
                        <div className="mt-2 p-2 bg-rose-50 dark:bg-rose-950/50 border border-rose-200 dark:border-rose-900 rounded-xl text-left space-y-1.5">
                          <p className="text-[10px] text-rose-700 dark:text-rose-300 font-bold">
                            Delete this facility?
                          </p>
                          <div className="flex items-center gap-1.5">
                            <button
                              onClick={() => handleDelete(wh)}
                              className="px-2 py-1 bg-rose-600 text-white rounded text-[10px] font-bold"
                            >
                              Confirm Delete
                            </button>
                            <button
                              onClick={() => setDeleteConfirmId(null)}
                              className="px-2 py-1 bg-neutral-200 dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300 rounded text-[10px]"
                            >
                              Cancel
                            </button>
                          </div>
                        </div>
                      )}
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      </div>

      {/* 3. Architecture Switch & Migration Audit History */}
      <div className="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-xs overflow-hidden">
        <div className="p-5 border-b border-neutral-200 dark:border-neutral-800 flex items-center justify-between bg-neutral-50 dark:bg-neutral-950/50">
          <div className="flex items-center gap-2">
            <History className="w-4 h-4 text-neutral-500" />
            <h3 className="font-bold text-sm text-neutral-900 dark:text-white">
              Architecture Migration & Mode Switch Audit Trail
            </h3>
          </div>
          <span className="text-[10px] text-neutral-400 font-mono">
            {auditLogs.length} events logged
          </span>
        </div>

        {auditLogs.length === 0 ? (
          <div className="p-8 text-center text-neutral-400 text-xs">
            <ShieldCheck className="w-6 h-6 mx-auto mb-2 text-neutral-300 dark:text-neutral-700" />
            <p className="font-medium">No architecture switch audits recorded yet</p>
          </div>
        ) : (
          <div className="divide-y divide-neutral-200 dark:divide-neutral-800 text-xs">
            {auditLogs.map(log => (
              <div key={log.id} className="p-4 hover:bg-neutral-50 dark:hover:bg-neutral-800/30 transition-colors">
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                  <div className="flex items-center gap-2">
                    <span className="font-bold text-neutral-900 dark:text-white font-mono">
                      {log.fromMode === 'shared_pool' ? 'Shared Pool' : 'Independent'}
                    </span>
                    <span className="text-neutral-400">→</span>
                    <span className="font-bold text-purple-600 dark:text-purple-400 font-mono">
                      {log.toMode === 'shared_pool' ? 'Shared Pool' : 'Independent Warehouses'}
                    </span>
                    <span className="text-[10px] px-2 py-0.5 rounded-full bg-neutral-100 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-300 border border-neutral-200 dark:border-neutral-700 font-bold">
                      {log.affectedSkuCount} SKUs Reallocated
                    </span>
                  </div>

                  <span className="text-[11px] text-neutral-400 font-mono">
                    {new Date(log.timestamp).toLocaleString()}
                  </span>
                </div>

                <p className="text-[11px] text-neutral-600 dark:text-neutral-300 mt-1.5">
                  <strong>Reason:</strong> {log.reason}
                </p>

                <div className="mt-2 flex items-center justify-between text-[10px] text-neutral-400 pt-1.5 border-t border-neutral-200 dark:border-neutral-800">
                  <span>Authorized by: <strong className="text-neutral-700 dark:text-neutral-300">{log.operator}</strong></span>
                  {log.strategy && <span>Strategy: <strong className="text-neutral-700 dark:text-neutral-300">{log.strategy === 'primary_all' ? 'Primary Warehouse 100%' : 'Even Proportional Split'}</strong></span>}
                </div>
              </div>
            ))}
          </div>
        )}
      </div>

    </div>
  );
};
