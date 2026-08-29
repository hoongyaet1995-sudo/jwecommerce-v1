import React, { useState } from 'react';
import { 
  Package, 
  Printer, 
  Truck, 
  Search, 
  Filter, 
  CheckCircle2, 
  Clock, 
  AlertTriangle, 
  Copy, 
  Check, 
  ExternalLink, 
  MapPin, 
  Plane, 
  Eye, 
  RotateCcw,
  Zap,
  Download,
  Calendar,
  Layers
} from 'lucide-react';
import { ShipmentParcel, CourierCode } from '../../types/shipping';

interface ShippingFulfillmentTabProps {
  parcels: ShipmentParcel[];
  onGenerateAWB: (parcelId: string, courierCode?: string) => void;
  onBatchGenerateAWB: (parcelIds: string[]) => void;
  onOpenAWBModal: (parcel: ShipmentParcel) => void;
  onOpenTrackingModal: (parcel: ShipmentParcel) => void;
  onOpenPickupModal: () => void;
  onUpdateStatus: (parcelId: string, status: ShipmentParcel['status'], desc?: string) => void;
  onOpenEasyParcelBooking: (parcel: ShipmentParcel) => void;
}

export const ShippingFulfillmentTab: React.FC<ShippingFulfillmentTabProps> = ({
  parcels,
  onGenerateAWB,
  onBatchGenerateAWB,
  onOpenAWBModal,
  onOpenTrackingModal,
  onOpenPickupModal,
  onUpdateStatus,
  onOpenEasyParcelBooking
}) => {
  const [activeStatusFilter, setActiveStatusFilter] = useState<'all' | 'pending' | 'ready_to_ship' | 'in_transit' | 'delivered' | 'failed_delivery'>('all');
  const [regionFilter, setRegionFilter] = useState<'all' | 'peninsular' | 'east_malaysia'>('all');
  const [courierFilter, setCourierFilter] = useState<string>('all');
  const [searchQuery, setSearchQuery] = useState('');
  const [selectedParcelIds, setSelectedParcelIds] = useState<string[]>([]);
  const [copiedId, setCopiedId] = useState<string | null>(null);

  // Filter logic
  const filteredParcels = parcels.filter(p => {
    // Status
    if (activeStatusFilter !== 'all') {
      if (activeStatusFilter === 'in_transit') {
        if (p.status !== 'shipped' && p.status !== 'in_transit' && p.status !== 'picked_up' && p.status !== 'out_for_delivery') return false;
      } else if (activeStatusFilter === 'failed_delivery') {
        if (p.status !== 'failed_delivery' && p.status !== 'returned') return false;
      } else if (p.status !== activeStatusFilter) {
        return false;
      }
    }

    // Courier
    if (courierFilter !== 'all' && p.courier !== courierFilter) return false;

    // Search query
    if (searchQuery.trim()) {
      const q = searchQuery.toLowerCase();
      const matchOrder = (p.orderNumber || '').toLowerCase().includes(q);
      const matchCustomer = (p.customerName || '').toLowerCase().includes(q);
      const matchTracking = (p.trackingNumber || '').toLowerCase().includes(q);
      if (!matchOrder && !matchCustomer && !matchTracking) return false;
    }

    return true;
  });

  // Select all handlers
  const handleSelectAll = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.checked) {
      setSelectedParcelIds(filteredParcels.map(p => p.id));
    } else {
      setSelectedParcelIds([]);
    }
  };

  const handleToggleSelect = (id: string) => {
    setSelectedParcelIds(prev => 
      prev.includes(id) ? prev.filter(item => item !== id) : [...prev, id]
    );
  };

  const handleCopy = (text: string, id: string) => {
    navigator.clipboard.writeText(text);
    setCopiedId(id);
    setTimeout(() => setCopiedId(null), 2000);
  };

  const getCourierBadge = (courier: string) => {
    switch (courier) {
      case 'jnt': return <span className="px-2 py-0.5 text-[10px] font-black rounded bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300">J&T EXPRESS</span>;
      case 'ninjavan': return <span className="px-2 py-0.5 text-[10px] font-black rounded bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300">NINJA VAN</span>;
      case 'posmalaysia': return <span className="px-2 py-0.5 text-[10px] font-black rounded bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">POS LAJU</span>;
      case 'flashexpress': return <span className="px-2 py-0.5 text-[10px] font-black rounded bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300">FLASH EXP</span>;
      case 'easyparcel': return <span className="px-2 py-0.5 text-[10px] font-black rounded bg-pink-100 text-pink-700 dark:bg-pink-900/40 dark:text-pink-300">EASYPARCEL</span>;
      case 'dhl': return <span className="px-2 py-0.5 text-[10px] font-black rounded bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">DHL ECOM</span>;
      default: return <span className="px-2 py-0.5 text-[10px] font-black rounded bg-neutral-100 text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300">MANUAL</span>;
    }
  };

  const getStatusBadge = (status: ShipmentParcel['status']) => {
    switch (status) {
      case 'pending':
        return <span className="px-2 py-0.5 text-[10px] font-semibold rounded bg-neutral-100 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-300">Pending AWB</span>;
      case 'ready_to_ship':
        return <span className="px-2 py-0.5 text-[10px] font-semibold rounded bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-300">Ready to Ship</span>;
      case 'shipped':
        return <span className="px-2 py-0.5 text-[10px] font-semibold rounded bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-300">Dispatched</span>;
      case 'picked_up':
      case 'in_transit':
        return <span className="px-2 py-0.5 text-[10px] font-semibold rounded bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-300">In Transit</span>;
      case 'out_for_delivery':
        return <span className="px-2 py-0.5 text-[10px] font-semibold rounded bg-purple-100 dark:bg-purple-900/40 text-purple-800 dark:text-purple-300">Out for Delivery</span>;
      case 'delivered':
        return <span className="px-2 py-0.5 text-[10px] font-semibold rounded bg-emerald-100 dark:bg-emerald-900/40 text-emerald-800 dark:text-emerald-300">Delivered</span>;
      case 'failed_delivery':
        return <span className="px-2 py-0.5 text-[10px] font-semibold rounded bg-rose-100 dark:bg-rose-900/40 text-rose-800 dark:text-rose-300">Delivery Exception</span>;
      case 'returned':
        return <span className="px-2 py-0.5 text-[10px] font-semibold rounded bg-rose-100 dark:bg-rose-900/40 text-rose-800 dark:text-rose-300">Returned</span>;
      default:
        return null;
    }
  };

  return (
    <div className="space-y-4">
      {/* Top Filter and Search Bar */}
      <div className="p-4 rounded-2xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 shadow-sm space-y-3">
        {/* Status Tabs */}
        <div className="flex flex-wrap items-center gap-1.5 border-b border-neutral-200 dark:border-neutral-800 pb-3">
          {[
            { id: 'all', label: 'All Shipments', count: parcels.length },
            { id: 'pending', label: 'Pending AWB', count: parcels.filter(p => p.status === 'pending').length },
            { id: 'ready_to_ship', label: 'Ready to Ship', count: parcels.filter(p => p.status === 'ready_to_ship').length },
            { id: 'in_transit', label: 'In Transit', count: parcels.filter(p => p.status === 'shipped' || p.status === 'in_transit' || p.status === 'picked_up' || p.status === 'out_for_delivery').length },
            { id: 'delivered', label: 'Delivered', count: parcels.filter(p => p.status === 'delivered').length },
            { id: 'failed_delivery', label: 'Exceptions (NITC)', count: parcels.filter(p => p.status === 'failed_delivery' || p.status === 'returned').length },
          ].map((tab) => (
            <button
              key={tab.id}
              onClick={() => setActiveStatusFilter(tab.id as any)}
              className={`px-3 py-1.5 rounded-xl text-xs font-semibold transition-all cursor-pointer flex items-center gap-1.5 ${
                activeStatusFilter === tab.id
                  ? 'bg-blue-600 text-white shadow-sm'
                  : 'bg-neutral-100 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-300 hover:bg-neutral-200 dark:hover:bg-neutral-700'
              }`}
            >
              <span>{tab.label}</span>
              <span className={`px-1.5 py-0.2 text-[10px] rounded-full ${
                activeStatusFilter === tab.id ? 'bg-white/20 text-white' : 'bg-neutral-200 dark:bg-neutral-700 text-neutral-700 dark:text-neutral-300'
              }`}>
                {tab.count}
              </span>
            </button>
          ))}
        </div>

        {/* Search & Secondary Filter Dropdowns */}
        <div className="flex flex-col sm:flex-row items-center justify-between gap-3">
          <div className="relative w-full sm:w-80">
            <Search className="w-4 h-4 absolute left-3 top-2.5 text-neutral-400" />
            <input
              type="text"
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              placeholder="Search Order #, Tracking, Buyer..."
              className="w-full pl-9 pr-3 py-2 text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>

          <div className="flex flex-wrap items-center gap-2 w-full sm:w-auto justify-end">
            {/* Region Filter */}
            <select
              value={regionFilter}
              onChange={(e) => setRegionFilter(e.target.value as any)}
              disabled
              title="Recipient region is restricted in shipment-list responses"
              className="px-3 py-2 text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-800 dark:text-neutral-200"
            >
              <option value="all">Region: Restricted in list</option>
              <option value="peninsular">Region: Peninsular (West MY)</option>
              <option value="east_malaysia">Region: East MY (Sabah & Sarawak)</option>
            </select>

            {/* Courier Filter */}
            <select
              value={courierFilter}
              onChange={(e) => setCourierFilter(e.target.value)}
              className="px-3 py-2 text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-800 dark:text-neutral-200"
            >
              <option value="all">Carrier: All Couriers</option>
              <option value="jnt">J&T Express</option>
              <option value="ninjavan">Ninja Van</option>
              <option value="posmalaysia">Pos Malaysia</option>
              <option value="flashexpress">Flash Express</option>
              <option value="easyparcel">EasyParcel</option>
              <option value="dhl">DHL eCommerce</option>
            </select>
          </div>
        </div>
      </div>

      {/* Batch Action Toolbar when items selected */}
      {selectedParcelIds.length > 0 && (
        <div className="p-3 bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-800 rounded-xl flex items-center justify-between animate-in fade-in duration-150">
          <div className="flex items-center gap-2 text-xs font-semibold text-blue-900 dark:text-blue-200">
            <span className="w-5 h-5 rounded-full bg-blue-600 text-white flex items-center justify-center text-[10px]">
              {selectedParcelIds.length}
            </span>
            <span>Parcels Selected for Batch Processing</span>
          </div>

          <div className="flex items-center gap-2">
            <button
              onClick={() => onBatchGenerateAWB(selectedParcelIds)}
              className="flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 rounded-lg shadow-sm transition-colors cursor-pointer"
            >
              <Zap className="w-3.5 h-3.5" />
              Batch Generate AWBs
            </button>
            <button
              onClick={() => {
                const first = parcels.find(p => selectedParcelIds.includes(p.id));
                if (first) onOpenAWBModal(first);
              }}
              className="flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-neutral-800 dark:text-neutral-200 bg-white dark:bg-neutral-800 hover:bg-neutral-100 rounded-lg border border-neutral-300 dark:border-neutral-700 transition-colors cursor-pointer"
            >
              <Printer className="w-3.5 h-3.5" />
              Print Selected AWBs
            </button>
            <button
              onClick={onOpenPickupModal}
              className="flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-amber-800 dark:text-amber-300 bg-amber-100 dark:bg-amber-900/40 hover:bg-amber-200 rounded-lg transition-colors cursor-pointer"
            >
              <Truck className="w-3.5 h-3.5" />
              Schedule Pickup
            </button>
          </div>
        </div>
      )}

      {/* Main Parcels Table */}
      <div className="rounded-2xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 overflow-hidden shadow-sm">
        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs border-collapse">
            <thead>
              <tr className="border-b border-neutral-200 dark:border-neutral-800 bg-neutral-50/80 dark:bg-neutral-800/40 text-neutral-500 dark:text-neutral-400 font-semibold select-none">
                <th className="p-3.5 w-10 text-center">
                  <input
                    type="checkbox"
                    checked={selectedParcelIds.length > 0 && selectedParcelIds.length === filteredParcels.length}
                    onChange={handleSelectAll}
                    className="rounded text-blue-600 focus:ring-blue-500"
                  />
                </th>
                <th className="p-3.5">Order & Buyer</th>
                <th className="p-3.5">Destination & Region</th>
                <th className="p-3.5">Assigned Courier & Route</th>
                <th className="p-3.5">Specs & Weight</th>
                <th className="p-3.5">Payment</th>
                <th className="p-3.5">Tracking Number / Status</th>
                <th className="p-3.5 text-right">Actions</th>
              </tr>
            </thead>

            <tbody className="divide-y divide-neutral-200 dark:divide-neutral-800/60">
              {filteredParcels.length === 0 ? (
                <tr>
                  <td colSpan={8} className="p-8 text-center text-neutral-400">
                    No shipments match the selected filter criteria.
                  </td>
                </tr>
              ) : (
                filteredParcels.map((parcel) => {
                  const isSelected = selectedParcelIds.includes(parcel.id);

                  return (
                    <tr 
                      key={parcel.id}
                      className={`hover:bg-neutral-50 dark:hover:bg-neutral-800/30 transition-colors ${
                        isSelected ? 'bg-blue-50/40 dark:bg-blue-900/10' : ''
                      }`}
                    >
                      {/* Checkbox */}
                      <td className="p-3.5 text-center">
                        <input
                          type="checkbox"
                          checked={isSelected}
                          onChange={() => handleToggleSelect(parcel.id)}
                          className="rounded text-blue-600 focus:ring-blue-500"
                        />
                      </td>

                      {/* Order & Buyer */}
                      <td className="p-3.5">
                        <div className="font-bold text-neutral-900 dark:text-neutral-100 font-mono">
                          #{parcel.orderNumber}
                        </div>
                        <div className="font-medium text-neutral-800 dark:text-neutral-200 mt-0.5">
                          {parcel.customerName}
                        </div>
                        <div className="text-[10px] text-neutral-400 font-mono">
                          Contact restricted • {parcel.items.length} items
                        </div>
                      </td>

                      {/* Destination & Region */}
                      <td className="p-3.5">
                        <div className="flex items-center gap-1.5">
                          <span className="font-bold font-mono text-neutral-900 dark:text-neutral-100">
                            Recipient details restricted
                          </span>
                        </div>
                        <div className="text-[11px] text-neutral-500 dark:text-neutral-400 mt-0.5 truncate max-w-[200px]">
                          Open the authorized order workflow for delivery details.
                        </div>
                        <div className="mt-1 flex items-center gap-1">
                          <span className="px-1.5 py-0.2 text-[9px] font-bold rounded uppercase bg-neutral-100 text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300">
                            Region restricted
                          </span>
                          {parcel.postcodeValidationStatus === 'valid' && (
                            <span className="text-[9px] text-emerald-600 dark:text-emerald-400 font-medium">✓ Valid Postcode</span>
                          )}
                        </div>
                      </td>

                      {/* Courier & Route */}
                      <td className="p-3.5">
                        <div className="flex items-center gap-1.5">
                          {getCourierBadge(parcel.courier)}
                        </div>
                        <div className="text-[11px] font-mono font-bold text-neutral-700 dark:text-neutral-300 mt-1">
                          {parcel.routingSortCode ? `Sort Hub: ${parcel.routingSortCode}` : 'Route not assigned'}
                        </div>
                        <div className="text-[10px] text-neutral-400">
                          {parcel.serviceType}
                        </div>
                      </td>

                      {/* Specs & Weight */}
                      <td className="p-3.5">
                        <div className="font-mono font-semibold text-neutral-800 dark:text-neutral-200">
                          {parcel.totalWeightKg === null ? 'Not configured' : `${parcel.totalWeightKg} kg`} <span className="text-neutral-400 font-normal">actual</span>
                        </div>
                        <div className="text-[10px] text-neutral-500 dark:text-neutral-400 font-mono">
                          Vol: {parcel.volumetricWeightKg === null ? '—' : `${parcel.volumetricWeightKg} kg`}
                        </div>
                        <div className="text-[10px] text-neutral-400 font-mono">
                          {parcel.dimensionsCm ? `${parcel.dimensionsCm.length}x${parcel.dimensionsCm.width}x${parcel.dimensionsCm.height}cm` : 'Dimensions not configured'}
                        </div>
                      </td>

                      {/* Payment */}
                      <td className="p-3.5">
                        {parcel.paymentMode === 'cod' ? (
                          <div className="text-rose-600 font-bold">
                            <span className="px-1.5 py-0.5 rounded bg-rose-100 dark:bg-rose-900/40 text-[10px] uppercase">COD</span>
                            <div className="font-mono text-xs mt-0.5">RM {parcel.codAmount?.toFixed(2)}</div>
                          </div>
                        ) : (
                          <span className="px-1.5 py-0.5 rounded bg-emerald-100 dark:bg-emerald-900/40 text-emerald-800 dark:text-emerald-300 text-[10px] font-bold uppercase">
                            Prepaid
                          </span>
                        )}
                      </td>

                      {/* Tracking / Status */}
                      <td className="p-3.5">
                        {parcel.trackingNumber ? (
                          <div className="space-y-1">
                            <div className="flex items-center gap-1 font-mono font-semibold text-neutral-900 dark:text-neutral-100">
                              <span>{parcel.trackingNumber}</span>
                              <button
                                onClick={() => handleCopy(parcel.trackingNumber!, parcel.id)}
                                className="p-1 text-neutral-400 hover:text-neutral-600 cursor-pointer"
                                title="Copy tracking"
                              >
                                {copiedId === parcel.id ? <Check className="w-3 h-3 text-emerald-500" /> : <Copy className="w-3 h-3" />}
                              </button>
                            </div>
                            {getStatusBadge(parcel.status)}
                          </div>
                        ) : (
                          <div className="space-y-1">
                            <span className="text-[11px] text-neutral-400 italic">No Tracking Assigned</span>
                            <div>{getStatusBadge(parcel.status)}</div>
                          </div>
                        )}
                      </td>

                      {/* Actions */}
                      <td className="p-3.5 text-right">
                        <div className="flex items-center justify-end gap-1.5">
                          {!parcel.trackingNumber ? (
                            <button
                              onClick={() => onOpenEasyParcelBooking(parcel)}
                              className="px-2.5 py-1 text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 rounded-lg shadow-sm transition-colors cursor-pointer flex items-center gap-1"
                            >
                              <Zap className="w-3 h-3" />
                              Gen AWB
                            </button>
                          ) : (
                            <button
                              onClick={() => onOpenAWBModal(parcel)}
                              className="p-1.5 text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/30 rounded-lg border border-blue-200 dark:border-blue-800 transition-colors cursor-pointer"
                              title="Print 4x6 Thermal AWB"
                            >
                              <Printer className="w-3.5 h-3.5" />
                            </button>
                          )}

                          <button
                            onClick={() => onOpenTrackingModal(parcel)}
                            className="p-1.5 text-neutral-600 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800 rounded-lg border border-neutral-200 dark:border-neutral-700 transition-colors cursor-pointer"
                            title="View Milestone Timeline"
                          >
                            <Eye className="w-3.5 h-3.5" />
                          </button>
                        </div>
                      </td>
                    </tr>
                  );
                })
              )}
            </tbody>
          </table>
        </div>

        {/* Footer info bar */}
        <div className="px-4 py-3 border-t border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-800/40 flex items-center justify-between text-xs text-neutral-500 dark:text-neutral-400">
          <div>
            Showing <strong>{filteredParcels.length}</strong> of <strong>{parcels.length}</strong> shipments
          </div>
          <div className="flex items-center gap-4">
            <span>Manual dispatch records do not create a provider waybill.</span>
            <span>Postcode API: <strong>Not connected</strong></span>
          </div>
        </div>
      </div>
    </div>
  );
};
