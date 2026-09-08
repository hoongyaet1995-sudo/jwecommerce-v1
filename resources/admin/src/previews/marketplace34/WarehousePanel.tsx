import React from 'react';
import {Warehouse,Plus,Edit2,Trash2,MapPin} from 'lucide-react';
export function WarehousePanel({entityWarehouses=[],onOpenAddWarehouseModal,onOpenEditWarehouseModal,statusMessage}:any) {
 const storeFulfillmentBindings:any[]=[];
 const inventoryItems:any[]=[];
 const deleteConfirmId=null;
 const setDeleteConfirmId=(_:any)=>{};
 const handleDelete=(_:any)=>{};
 return (
      <div className="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-xs overflow-hidden">
        <div className="p-5 border-b border-neutral-200 dark:border-neutral-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-neutral-50 dark:bg-neutral-950/50">
          <div>
            <div className="flex items-center gap-2">
              <h3 className="font-bold text-sm text-neutral-900 dark:text-white">
                JW Entity Fulfillment Warehouses
              </h3>
              <span className="text-[10px] font-bold px-2 py-0.5 rounded-full bg-neutral-200 dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300">
                {statusMessage || `${entityWarehouses.length} Facilities`}
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
                            {wh.name}
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
                            {wh.address || 'Address not configured'}
                          </p>
                          <p className="text-[11px] text-neutral-500">
                            {wh.state}
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
                        Not verified
                      </span>
                      <span className="block text-[10px] text-neutral-400">
                        {boundStores.length === 1 ? 'Store' : 'Stores'}
                      </span>
                    </td>

                    {/* Stocked SKUs */}
                    <td className="py-4 px-4 text-center">
                      <span className="font-bold text-neutral-900 dark:text-white text-sm">
                        Not verified
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
                            disabled
                            title="Warehouse deletion is not enabled yet"
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

); }
