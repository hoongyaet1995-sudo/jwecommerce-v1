import React from 'react';
import { StoreFulfillmentBinding, EntityWarehouse } from '../../types';
import { MarketplaceLogo } from './MarketplaceLogo';
import {
  Store,
  Warehouse,
  MapPin,
  Link2,
  Edit2,
  CheckCircle2,
  Info,
  Layers,
  ArrowRight,
  ExternalLink,
  ShieldCheck
} from 'lucide-react';

interface StoreWarehouseBindingTabProps {
  bindings: StoreFulfillmentBinding[];
  warehouses: EntityWarehouse[];
  onOpenEditBindingModal: (binding: StoreFulfillmentBinding) => void;
}

export const StoreWarehouseBindingTab: React.FC<StoreWarehouseBindingTabProps> = ({
  bindings = [],
  warehouses = [],
  onOpenEditBindingModal
}) => {
  // Stats
  const totalStores = bindings.length;
  const mappedStores = bindings.filter(b => b.boundWarehouseId).length;
  const platformLocationsConfigured = bindings.filter(b => b.platformLocationId).length;

  return (
    <div className="space-y-6">
      
      {/* Educational Banner explaining warehouse binding & multi-store concurrency */}
      <div className="p-5 rounded-2xl bg-blue-500/10 border border-blue-500/20 text-blue-900 dark:text-blue-200 space-y-2">
        <div className="flex items-center gap-2 font-bold text-sm">
          <Info className="w-5 h-5 text-blue-500 shrink-0" />
          <span>Fulfillment Warehouse Routing & Platform Location ID Mapping</span>
        </div>
        <p className="text-xs opacity-90 leading-relaxed max-w-4xl">
          In <strong>Independent Warehouse Mode</strong>, each sales channel can be bound to a physical entity warehouse. Multiple stores can use the <strong>same</strong> warehouse without duplicating its recorded stock. Saving a binding does not enable automatic order deductions or platform stock writes.
        </p>
      </div>

      {/* Stats Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div className="p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
          <div className="flex items-center justify-between text-neutral-500 mb-1">
            <span className="text-xs font-bold uppercase tracking-wider">Configured Channels</span>
            <Store className="w-4 h-4 text-neutral-700 dark:text-neutral-300" />
          </div>
          <div className="flex items-baseline gap-2">
            <span className="text-2xl font-black text-neutral-900 dark:text-white">{totalStores}</span>
            <span className="text-xs text-neutral-400">Channels Listed</span>
          </div>
          <p className="text-[11px] text-neutral-500 mt-2">
            JW Webstore ({bindings.filter(b=>b.platform==='webstore').length}), Shopee ({bindings.filter(b=>b.platform==='shopee').length}), Lazada ({bindings.filter(b=>b.platform==='lazada').length})
          </p>
        </div>

        <div className="p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
          <div className="flex items-center justify-between text-neutral-500 mb-1">
            <span className="text-xs font-bold uppercase tracking-wider">Warehouse Bound</span>
            <Warehouse className="w-4 h-4 text-emerald-500" />
          </div>
          <div className="flex items-baseline gap-2">
            <span className="text-2xl font-black text-emerald-600 dark:text-emerald-400">{mappedStores}/{totalStores}</span>
            <span className="text-xs text-neutral-400">Routing Assigned</span>
          </div>
          <div className="flex items-center gap-1.5 text-[11px] text-emerald-600 dark:text-emerald-400 mt-2 font-medium">
            <CheckCircle2 className="w-3.5 h-3.5" />
            <span>{totalStores ? Math.round(mappedStores / totalStores * 100) : 0}% fulfillment routes mapped</span>
          </div>
        </div>

        <div className="p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
          <div className="flex items-center justify-between text-neutral-500 mb-1">
            <span className="text-xs font-bold uppercase tracking-wider">Platform Location IDs</span>
            <Link2 className="w-4 h-4 text-purple-500" />
          </div>
          <div className="flex items-baseline gap-2">
            <span className="text-2xl font-black text-purple-600 dark:text-purple-400">{platformLocationsConfigured}</span>
            <span className="text-xs text-neutral-400">Saved Location IDs</span>
          </div>
          <p className="text-[11px] text-neutral-500 mt-2">
            Stored routing references; provider validation is separate
          </p>
        </div>
      </div>

      {/* Store Fulfillment Mappings Table */}
      <div className="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-xs overflow-hidden">
        <div className="p-5 border-b border-neutral-200 dark:border-neutral-800 flex items-center justify-between bg-neutral-50 dark:bg-neutral-950/50">
          <div>
            <h3 className="font-bold text-sm text-neutral-900 dark:text-white">
              Store to Entity Warehouse Fulfillment Matrix
            </h3>
            <p className="text-xs text-neutral-500 mt-0.5">
              Determines which facility holds physical goods, fulfills orders, and pushes ATP broadcasts for each store
            </p>
          </div>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs">
            <thead className="bg-neutral-50 dark:bg-neutral-950/70 border-b border-neutral-200 dark:border-neutral-800 text-neutral-500 font-bold uppercase tracking-wider text-[10px]">
              <tr>
                <th className="py-3.5 px-4">Connected Store</th>
                <th className="py-3.5 px-4">Channel Type</th>
                <th className="py-3.5 px-4">Fulfilling Entity Warehouse</th>
                <th className="py-3.5 px-4">Platform Location ID</th>
                <th className="py-3.5 px-4">Platform Location Label</th>
                <th className="py-3.5 px-4 text-center">Priority</th>
                <th className="py-3.5 px-4 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-200 dark:divide-neutral-800">
              {bindings.map(binding => {
                const wh = warehouses.find(w => w.id === binding.boundWarehouseId);

                return (
                  <tr key={binding.id} className="hover:bg-neutral-50 dark:hover:bg-neutral-800/40 transition-colors">
                    {/* Store */}
                    <td className="py-4 px-4">
                      <div className="flex items-center gap-3">
                        <div className="p-2 rounded-xl bg-neutral-100 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 shrink-0">
                          <MarketplaceLogo platform={binding.platform} size={20} />
                        </div>
                        <div>
                          <p className="font-bold text-neutral-900 dark:text-white">
                            {binding.dataClassification==='test'?'[TEST] ':binding.dataClassification==='operational'?'':'[Unclassified] '}{binding.storeName}
                          </p>
                          <p className="text-[11px] text-neutral-500 font-mono">
                            ID: {binding.storeIdentifier}
                          </p>
                        </div>
                      </div>
                    </td>

                    {/* Channel */}
                    <td className="py-4 px-4">
                      <span className="text-[10px] font-bold px-2 py-0.5 rounded-full bg-neutral-100 dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300 border border-neutral-300 dark:border-neutral-700 uppercase">
                        {binding.platform}
                      </span>
                    </td>

                    {/* Bound Warehouse */}
                    <td className="py-4 px-4">
                      <div className="flex items-center gap-2">
                        <Warehouse className="w-4 h-4 text-neutral-400 shrink-0" />
                        <div>
                          <p className="font-bold text-neutral-900 dark:text-white">
                            {wh ? wh.name : binding.boundWarehouseName}
                          </p>
                          <p className="text-[11px] text-neutral-500 font-mono">
                            {wh ? `${wh.code} • ${typeof wh.address === 'string' ? wh.state || 'Location not configured' : [wh.address?.city, wh.address?.state].filter(Boolean).join(', ') || 'Location not configured'}` : binding.boundWarehouseCode}
                          </p>
                        </div>
                      </div>
                    </td>

                    {/* Platform Location ID */}
                    <td className="py-4 px-4">
                      {binding.platformLocationId ? (
                        <span className="font-mono font-bold text-purple-600 dark:text-purple-400 bg-purple-500/10 px-2 py-0.5 rounded border border-purple-500/20">
                          {binding.platformLocationId}
                        </span>
                      ) : (
                        <span className="text-neutral-400 italic">Not set (default)</span>
                      )}
                    </td>

                    {/* Platform Location Label */}
                    <td className="py-4 px-4 text-neutral-700 dark:text-neutral-300">
                      {binding.platformLocationName || '—'}
                    </td>

                    {/* Priority */}
                    <td className="py-4 px-4 text-center">
                      <span className="font-bold font-mono px-2 py-0.5 rounded bg-neutral-100 dark:bg-neutral-800 text-neutral-800 dark:text-neutral-200">
                        P{binding.fulfillmentPriority || 1}
                      </span>
                    </td>

                    {/* Actions */}
                    <td className="py-4 px-4 text-right">
                      <button
                        onClick={() => onOpenEditBindingModal(binding)}
                        className="px-3 py-1.5 rounded-xl bg-neutral-100 dark:bg-neutral-800 hover:bg-neutral-200 dark:hover:bg-neutral-700 text-neutral-700 dark:text-neutral-300 font-semibold text-xs flex items-center gap-1.5 ml-auto transition-colors cursor-pointer"
                      >
                        <Edit2 className="w-3.5 h-3.5" />
                        <span>Edit Route</span>
                      </button>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      </div>

    </div>
  );
};
