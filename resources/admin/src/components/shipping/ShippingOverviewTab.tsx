import React from 'react';
import { 
  Package, 
  Truck, 
  Clock, 
  AlertTriangle, 
  CheckCircle2, 
  TrendingUp, 
  ShieldCheck, 
  Plane, 
  Wallet, 
  Zap,
  ArrowUpRight,
  RotateCcw,
  Plus
} from 'lucide-react';
import { CourierConfig, ShipmentParcel, PickupRequest } from '../../types/shipping';

interface ShippingOverviewTabProps {
  parcels: ShipmentParcel[];
  couriers: CourierConfig[];
  pickupRequests: PickupRequest[];
  onNavigateTab: (tab: 'fulfillment' | 'routing' | 'couriers' | 'tracking' | 'reconciliation') => void;
  onOpenPickupModal: () => void;
}

export const ShippingOverviewTab: React.FC<ShippingOverviewTabProps> = ({
  parcels,
  couriers,
  pickupRequests,
  onNavigateTab,
  onOpenPickupModal
}) => {
  const pendingAwb = parcels.filter(p => p.status === 'pending');
  const readyToShip = parcels.filter(p => p.status === 'ready_to_ship');
  const inTransit = parcels.filter(p => p.status === 'shipped' || p.status === 'in_transit' || p.status === 'picked_up' || p.status === 'out_for_delivery');
  const delivered = parcels.filter(p => p.status === 'delivered');
  const exceptions = parcels.filter(p => ['failed_delivery', 'returned', 'booking_failed', 'cancelled', 'on_hold'].includes(p.status));

  const eastMalaysiaCount = parcels.filter(p => p.isEastMalaysiaAirRestricted).length;
  const codCount = parcels.filter(p => p.paymentMode === 'cod').length;
  const totalCodAmount = parcels.filter(p => p.paymentMode === 'cod').reduce((sum, p) => sum + (p.codAmount || 0), 0);

  const easyParcelConfig = couriers.find(c => c.courierCode === 'easyparcel');
  const easyParcelBalance = easyParcelConfig?.walletBalance ?? null;

  // Courier Volume Breakdown
  const courierCounts = couriers.map(c => {
    const count = parcels.filter(p => p.courier === c.courierCode).length;
    return {
      ...c,
      count,
      percent: parcels.length > 0 ? Math.round((count / parcels.length) * 100) : 0
    };
  });

  return (
    <div className="space-y-6">
      {/* Top Banner: Operations KPI Cards */}
      <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3.5">
        {/* Pending AWB */}
        <div 
          onClick={() => onNavigateTab('fulfillment')}
          className="p-4 rounded-xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900/70 hover:border-amber-500/50 dark:hover:border-amber-500/40 transition-all cursor-pointer shadow-sm group"
        >
          <div className="flex items-center justify-between text-neutral-500 dark:text-neutral-400">
            <span className="text-xs font-medium">To Generate AWB</span>
            <div className="w-7 h-7 rounded-lg bg-amber-50 dark:bg-amber-900/30 flex items-center justify-center text-amber-600 dark:text-amber-400 group-hover:scale-105 transition-transform">
              <Clock className="w-3.5 h-3.5" />
            </div>
          </div>
          <div className="text-2xl font-black font-mono text-neutral-900 dark:text-neutral-100 mt-2">
            {pendingAwb.length}
          </div>
          <div className="text-[11px] text-amber-600 dark:text-amber-400 mt-1 flex items-center gap-1 font-medium">
            Requires Waybill Print →
          </div>
        </div>

        {/* Ready to Ship */}
        <div 
          onClick={() => onNavigateTab('fulfillment')}
          className="p-4 rounded-xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900/70 hover:border-blue-500/50 dark:hover:border-blue-500/40 transition-all cursor-pointer shadow-sm group"
        >
          <div className="flex items-center justify-between text-neutral-500 dark:text-neutral-400">
            <span className="text-xs font-medium">Ready to Pack & Ship</span>
            <div className="w-7 h-7 rounded-lg bg-blue-50 dark:bg-blue-900/30 flex items-center justify-center text-blue-600 dark:text-blue-400 group-hover:scale-105 transition-transform">
              <Package className="w-3.5 h-3.5" />
            </div>
          </div>
          <div className="text-2xl font-black font-mono text-neutral-900 dark:text-neutral-100 mt-2">
            {readyToShip.length}
          </div>
          <div className="text-[11px] text-blue-600 dark:text-blue-400 mt-1 flex items-center gap-1 font-medium">
            Awaiting Courier Pickup →
          </div>
        </div>

        {/* In Transit */}
        <div 
          onClick={() => onNavigateTab('tracking')}
          className="p-4 rounded-xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900/70 hover:border-purple-500/50 dark:hover:border-purple-500/40 transition-all cursor-pointer shadow-sm group"
        >
          <div className="flex items-center justify-between text-neutral-500 dark:text-neutral-400">
            <span className="text-xs font-medium">In-Transit Shipments</span>
            <div className="w-7 h-7 rounded-lg bg-purple-50 dark:bg-purple-900/30 flex items-center justify-center text-purple-600 dark:text-purple-400 group-hover:scale-105 transition-transform">
              <Truck className="w-3.5 h-3.5" />
            </div>
          </div>
          <div className="text-2xl font-black font-mono text-neutral-900 dark:text-neutral-100 mt-2">
            {inTransit.length}
          </div>
          <div className="text-[11px] text-purple-600 dark:text-purple-400 mt-1 flex items-center gap-1 font-medium">
            Shipment Event Audit →
          </div>
        </div>

        {/* Delivered SLA */}
        <div 
          onClick={() => onNavigateTab('tracking')}
          className="p-4 rounded-xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900/70 hover:border-emerald-500/50 dark:hover:border-emerald-500/40 transition-all cursor-pointer shadow-sm group"
        >
          <div className="flex items-center justify-between text-neutral-500 dark:text-neutral-400">
            <span className="text-xs font-medium">Delivered Shipments</span>
            <div className="w-7 h-7 rounded-lg bg-emerald-50 dark:bg-emerald-900/30 flex items-center justify-center text-emerald-600 dark:text-emerald-400 group-hover:scale-105 transition-transform">
              <CheckCircle2 className="w-3.5 h-3.5" />
            </div>
          </div>
          <div className="text-2xl font-black font-mono text-neutral-900 dark:text-neutral-100 mt-2">
            {delivered.length}
          </div>
          <div className="text-[11px] text-emerald-600 dark:text-emerald-400 mt-1 flex items-center gap-1 font-medium">
            Completed Safely →
          </div>
        </div>

        {/* NITC / Exceptions */}
        <div 
          onClick={() => onNavigateTab('reconciliation')}
          className="p-4 rounded-xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900/70 hover:border-rose-500/50 dark:hover:border-rose-500/40 transition-all cursor-pointer shadow-sm group col-span-2 sm:col-span-1"
        >
          <div className="flex items-center justify-between text-neutral-500 dark:text-neutral-400">
            <span className="text-xs font-medium">Exceptions & NITC</span>
            <div className="w-7 h-7 rounded-lg bg-rose-50 dark:bg-rose-900/30 flex items-center justify-center text-rose-600 dark:text-rose-400 group-hover:scale-105 transition-transform">
              <AlertTriangle className="w-3.5 h-3.5" />
            </div>
          </div>
          <div className="text-2xl font-black font-mono text-rose-600 dark:text-rose-400 mt-2">
            {exceptions.length}
          </div>
          <div className="text-[11px] text-rose-600 dark:text-rose-400 mt-1 flex items-center gap-1 font-medium">
            Resolve Delivery Issues →
          </div>
        </div>
      </div>

      {/* Quick Action Hub & EasyParcel Wallet Row */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
        {/* Quick Dispatch Action Bar */}
        <div className="lg:col-span-2 p-5 bg-gradient-to-r from-blue-900/90 to-indigo-950 text-white rounded-2xl shadow-md flex flex-col justify-between">
          <div>
            <div className="flex items-center gap-2">
              <span className="px-2.5 py-0.5 rounded-md bg-blue-500/30 text-blue-200 text-[10px] font-mono uppercase font-bold tracking-wider">
                Manual Fulfillment
              </span>
              <span className="text-xs text-blue-200">• {couriers.filter(c => c.isEnabled).length} connected courier APIs</span>
            </div>
            <h3 className="text-lg font-bold mt-2">
              Shipment Dispatch & Event Audit Workspace
            </h3>
            <p className="text-xs text-blue-100/80 mt-1 max-w-xl">
              Dispatch processing orders with a carrier and tracking number, then retain each warehouse milestone as an append-only shipment event. Provider labels, pickups, webhooks, and billing remain unavailable until a carrier integration is configured.
            </p>
          </div>

          <div className="flex flex-wrap items-center gap-2.5 mt-5">
            <button
              onClick={() => onNavigateTab('fulfillment')}
              className="flex items-center gap-1.5 px-4 py-2 bg-white text-blue-900 hover:bg-blue-50 text-xs font-bold rounded-xl transition-colors shadow-sm cursor-pointer"
            >
              <Package className="w-3.5 h-3.5" />
              View Shipment Queue ({pendingAwb.length})
            </button>
            <button
              onClick={onOpenPickupModal}
              className="flex items-center gap-1.5 px-4 py-2 bg-blue-700/80 hover:bg-blue-600 text-white text-xs font-semibold rounded-xl transition-colors border border-blue-400/30 cursor-pointer"
            >
              <Truck className="w-3.5 h-3.5" />
              Carrier Pickup Unavailable
            </button>
            <button
              onClick={() => onNavigateTab('routing')}
              className="flex items-center gap-1.5 px-3.5 py-2 bg-blue-950/60 hover:bg-blue-900 text-blue-200 text-xs font-semibold rounded-xl transition-colors border border-blue-500/20 cursor-pointer"
            >
              <Zap className="w-3.5 h-3.5 text-amber-400" />
              Review Rate Matrix
            </button>
          </div>
        </div>

        {/* EasyParcel / Aggregator Wallet & COD summary */}
        <div className="p-5 rounded-2xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 flex flex-col justify-between shadow-sm">
          <div>
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-2">
                <div className="w-8 h-8 rounded-lg bg-pink-50 dark:bg-pink-900/30 flex items-center justify-center text-pink-600">
                  <Wallet className="w-4 h-4" />
                </div>
                <div>
                  <h4 className="text-xs font-bold text-neutral-900 dark:text-neutral-100">
                    EasyParcel Provider Status
                  </h4>
                  <span className="text-[10px] text-neutral-400">Provider integration required</span>
                </div>
              </div>
              <button
                onClick={() => onNavigateTab('couriers')}
                className="flex items-center gap-1 px-2.5 py-1 text-[11px] font-semibold text-pink-600 dark:text-pink-400 bg-pink-50 dark:bg-pink-900/30 hover:bg-pink-100 rounded-lg transition-colors border border-pink-200 dark:border-pink-800 cursor-pointer"
              >
                <Plus className="w-3 h-3" />
                Configure Provider
              </button>
            </div>

            <div className="mt-3">
              <div className="text-2xl font-black font-mono text-neutral-900 dark:text-neutral-100">
                {easyParcelBalance === null ? 'Not connected' : `RM ${easyParcelBalance.toFixed(2)}`}
              </div>
              <p className="text-[11px] text-neutral-500 dark:text-neutral-400 mt-0.5">
                {easyParcelBalance === null ? 'No wallet balance is shown until the EasyParcel provider is connected.' : 'Provider-reported balance'}
              </p>
            </div>
          </div>

          <div className="mt-4 pt-3 border-t border-neutral-200 dark:border-neutral-800 grid grid-cols-2 gap-2 text-xs">
            <div>
              <span className="text-[10px] text-neutral-400 block">Pending COD Value</span>
              <span className="font-bold font-mono text-rose-600">RM {totalCodAmount.toFixed(2)}</span>
            </div>
            <div>
              <span className="text-[10px] text-neutral-400 block">East Malaysia Air</span>
              <span className="font-bold font-mono text-purple-600">{eastMalaysiaCount} parcels</span>
            </div>
          </div>
        </div>
      </div>

      {/* Courier Share & Scheduled Pickups Grid */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
        {/* Courier Volume Allocation */}
        <div className="p-5 rounded-2xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 shadow-sm">
          <div className="flex items-center justify-between mb-4">
            <h4 className="text-sm font-bold text-neutral-900 dark:text-neutral-100 flex items-center gap-2">
              <TrendingUp className="w-4 h-4 text-blue-600" />
              Carrier Allocation
            </h4>
            <button
              onClick={() => onNavigateTab('couriers')}
              className="text-xs text-blue-600 dark:text-blue-400 hover:underline font-medium"
            >
              Configure Providers →
            </button>
          </div>

          <div className="space-y-3">
            {courierCounts.length > 0 ? courierCounts.map((c) => (
              <div key={c.id} className="space-y-1">
                <div className="flex items-center justify-between text-xs">
                  <div className="flex items-center gap-2">
                    <span className="font-semibold text-neutral-800 dark:text-neutral-200">
                      {c.displayName}
                    </span>
                    <span className={`px-1.5 py-0.2 text-[9px] uppercase font-bold rounded ${
                      c.environment === 'production' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300'
                    }`}>
                      {c.environment}
                    </span>
                  </div>
                  <div className="font-mono text-neutral-600 dark:text-neutral-300 text-xs">
                    {c.count} parcels ({c.percent}%)
                  </div>
                </div>

                {/* Progress bar */}
                <div className="w-full h-2 rounded-full bg-neutral-100 dark:bg-neutral-800 overflow-hidden">
                  <div 
                    className={`h-full rounded-full transition-all duration-500 ${
                      c.courierCode === 'jnt' ? 'bg-red-500' :
                      c.courierCode === 'ninjavan' ? 'bg-rose-600' :
                      c.courierCode === 'posmalaysia' ? 'bg-amber-500' :
                      c.courierCode === 'flashexpress' ? 'bg-yellow-500' :
                      c.courierCode === 'easyparcel' ? 'bg-pink-500' : 'bg-blue-600'
                    }`}
                    style={{ width: `${Math.max(c.percent, 3)}%` }}
                  />
                </div>
              </div>
            )) : <p className="text-xs text-neutral-500 dark:text-neutral-400">No courier integrations are connected.</p>}
          </div>
        </div>

        {/* Scheduled Courier Pickups */}
        <div className="p-5 rounded-2xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 shadow-sm flex flex-col justify-between">
          <div>
            <div className="flex items-center justify-between mb-4">
              <h4 className="text-sm font-bold text-neutral-900 dark:text-neutral-100 flex items-center gap-2">
                <Truck className="w-4 h-4 text-amber-500" />
                Carrier Pickup Requests
              </h4>
              <button
                onClick={onOpenPickupModal}
                className="text-xs text-blue-600 dark:text-blue-400 hover:underline font-medium"
              >
                Provider Required
              </button>
            </div>

            <div className="space-y-2.5">
              {pickupRequests.length > 0 ? pickupRequests.slice(0, 3).map((req) => (
                <div 
                  key={req.id}
                  className="p-3 rounded-xl border border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-800/40 flex items-center justify-between"
                >
                  <div className="flex items-center gap-3">
                    <div className="w-9 h-9 rounded-lg bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-300 flex items-center justify-center font-bold text-xs">
                      {(req.courier || 'courier').toUpperCase()}
                    </div>
                    <div>
                      <div className="text-xs font-bold text-neutral-900 dark:text-neutral-100">
                        {req.pickupDate} • {req.timeSlot}
                      </div>
                      <div className="text-[11px] text-neutral-500 dark:text-neutral-400">
                        {req.totalParcels} parcels (~{req.estimatedWeightKg} kg) • {req.warehouseLocation}
                      </div>
                    </div>
                  </div>

                  <div className="text-right">
                    <span className="px-2 py-0.5 text-[10px] font-semibold rounded bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-300 uppercase">
                      {req.status}
                    </span>
                    {req.assignedDriver && (
                      <div className="text-[10px] text-neutral-400 font-mono mt-0.5">
                        Driver: {req.assignedDriver.name}
                      </div>
                    )}
                  </div>
                </div>
              )) : <p className="text-xs text-neutral-500 dark:text-neutral-400">No provider pickup requests are recorded.</p>}
            </div>
          </div>

          <div className="mt-4 pt-3 border-t border-neutral-200 dark:border-neutral-800 text-[11px] text-neutral-500 dark:text-neutral-400 flex items-center justify-between">
            <span>Carrier collection cut-off is configured by the connected provider.</span>
            <span className="font-mono">Provider not connected</span>
          </div>
        </div>
      </div>
    </div>
  );
};
