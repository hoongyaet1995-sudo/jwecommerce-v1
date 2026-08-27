import React, { useState } from 'react';
import { 
  X, 
  Truck, 
  MapPin, 
  Calendar, 
  Clock, 
  User, 
  Phone, 
  MessageCircle, 
  CheckCircle2, 
  AlertTriangle, 
  Package, 
  ExternalLink, 
  RotateCcw,
  Zap,
  Code,
  Layers,
  Plane
} from 'lucide-react';
import { ShipmentParcel } from '../../types/shipping';

interface ParcelTrackingModalProps {
  parcel: ShipmentParcel | null;
  onClose: () => void;
  onRedeliver?: (parcelId: string) => void;
  onSimulateEvent?: (parcelId: string, eventType: string, location: string, description: string) => void;
  onOpenAWB?: (parcel: ShipmentParcel) => void;
}

export const ParcelTrackingModal: React.FC<ParcelTrackingModalProps> = ({
  parcel,
  onClose,
  onRedeliver,
  onSimulateEvent,
  onOpenAWB
}) => {
  const [activeTab, setActiveTab] = useState<'timeline' | 'details' | 'json'>('timeline');

  if (!parcel) return null;

  const isFailed = parcel.status === 'failed_delivery';

  const getStatusBadge = (status: ShipmentParcel['status']) => {
    switch (status) {
      case 'delivered':
        return <span className="px-2.5 py-1 text-xs font-semibold rounded-md bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">Delivered</span>;
      case 'out_for_delivery':
        return <span className="px-2.5 py-1 text-xs font-semibold rounded-md bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-300">Out for Delivery</span>;
      case 'in_transit':
      case 'picked_up':
        return <span className="px-2.5 py-1 text-xs font-semibold rounded-md bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300">In Transit</span>;
      case 'failed_delivery':
        return <span className="px-2.5 py-1 text-xs font-semibold rounded-md bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300">Delivery Exception (NITC)</span>;
      case 'ready_to_ship':
        return <span className="px-2.5 py-1 text-xs font-semibold rounded-md bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">Ready to Ship (AWB Printed)</span>;
      case 'shipped':
        return <span className="px-2.5 py-1 text-xs font-semibold rounded-md bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300">Dispatched</span>;
      default:
        return <span className="px-2.5 py-1 text-xs font-semibold rounded-md bg-neutral-100 text-neutral-800 dark:bg-neutral-800 dark:text-neutral-300">Pending AWB</span>;
    }
  };

  const getCourierName = (code: string) => {
    switch (code) {
      case 'jnt': return 'J&T Express Malaysia';
      case 'ninjavan': return 'Ninja Van Malaysia';
      case 'posmalaysia': return 'Pos Malaysia (Pos Laju)';
      case 'flashexpress': return 'Flash Express MY';
      case 'easyparcel': return 'EasyParcel Aggregator';
      case 'dhl': return 'DHL eCommerce MY';
      default: return (code || '').toUpperCase();
    }
  };

  return (
    <div id="parcel-tracking-modal-overlay" className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4 overflow-y-auto backdrop-blur-sm">
      <div className="bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-2xl max-w-2xl w-full flex flex-col shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
        {/* Header */}
        <div className="flex items-center justify-between px-6 py-4 border-b border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-900/80">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl bg-blue-50 dark:bg-blue-900/30 flex items-center justify-center text-blue-600 dark:text-blue-400">
              <Truck className="w-5 h-5" />
            </div>
            <div>
              <div className="flex items-center gap-2">
                <h3 className="text-base font-semibold text-neutral-900 dark:text-neutral-100 font-mono">
                  {parcel.trackingNumber || 'UNASSIGNED-AWB'}
                </h3>
                {getStatusBadge(parcel.status)}
              </div>
              <p className="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">
                {getCourierName(parcel.courier)} • Order #{parcel.orderNumber}
              </p>
            </div>
          </div>

          <div className="flex items-center gap-2">
            {parcel.trackingNumber && onOpenAWB && (
              <button
                onClick={() => onOpenAWB(parcel)}
                className="hidden sm:flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/30 rounded-lg transition-colors border border-blue-200 dark:border-blue-800"
              >
                <Package className="w-3.5 h-3.5" />
                View 4x6" AWB
              </button>
            )}
            <button
              onClick={onClose}
              className="p-1.5 rounded-lg text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors"
            >
              <X className="w-5 h-5" />
            </button>
          </div>
        </div>

        {/* Tab switch */}
        <div className="flex items-center gap-1 px-6 border-b border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 text-xs font-medium">
          <button
            onClick={() => setActiveTab('timeline')}
            className={`py-3 px-3 border-b-2 font-medium flex items-center gap-1.5 transition-colors ${
              activeTab === 'timeline'
                ? 'border-blue-600 text-blue-600 dark:text-blue-400 font-semibold'
                : 'border-transparent text-neutral-500 hover:text-neutral-800 dark:hover:text-neutral-200'
            }`}
          >
            <Clock className="w-3.5 h-3.5" />
            Tracking Milestone Events ({parcel.trackingEvents?.length || 0})
          </button>
          <button
            onClick={() => setActiveTab('details')}
            className={`py-3 px-3 border-b-2 font-medium flex items-center gap-1.5 transition-colors ${
              activeTab === 'details'
                ? 'border-blue-600 text-blue-600 dark:text-blue-400 font-semibold'
                : 'border-transparent text-neutral-500 hover:text-neutral-800 dark:hover:text-neutral-200'
            }`}
          >
            <Layers className="w-3.5 h-3.5" />
            Recipient & Parcel Specs
          </button>
          <button
            onClick={() => setActiveTab('json')}
            className={`py-3 px-3 border-b-2 font-medium flex items-center gap-1.5 transition-colors ${
              activeTab === 'json'
                ? 'border-blue-600 text-blue-600 dark:text-blue-400 font-semibold'
                : 'border-transparent text-neutral-500 hover:text-neutral-800 dark:hover:text-neutral-200'
            }`}
          >
            <Code className="w-3.5 h-3.5" />
            Raw API Webhook Payload
          </button>
        </div>

        {/* Modal Body */}
        <div className="p-6 overflow-y-auto max-h-[60vh] space-y-4">
          {/* Exception Warning Banner if NITC / Failed */}
          {isFailed && (
            <div className="p-4 bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-800 rounded-xl flex items-start justify-between gap-3">
              <div className="flex items-start gap-2.5">
                <AlertTriangle className="w-5 h-5 text-rose-600 dark:text-rose-400 shrink-0 mt-0.5" />
                <div>
                  <div className="text-xs font-bold text-rose-900 dark:text-rose-200">
                    Delivery Exception: {parcel.exceptionReason || 'Notice of Incomplete Delivery (NITC)'}
                  </div>
                  <div className="text-[11px] text-rose-700 dark:text-rose-300 mt-0.5">
                    Recipient contact details are restricted in this shipment list. Trigger re-delivery through the available workflow.
                  </div>
                </div>
              </div>
              <div className="flex items-center gap-2 shrink-0">
                <button
                  disabled
                  title="Recipient contact is restricted in shipment-list responses"
                  className="flex items-center gap-1 px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-lg shadow-sm transition-colors cursor-pointer"
                >
                  <MessageCircle className="w-3.5 h-3.5" />
                  Recipient contact restricted
                </button>
                {onRedeliver && (
                  <button
                    onClick={() => onRedeliver(parcel.id)}
                    className="flex items-center gap-1 px-3 py-1.5 bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold rounded-lg shadow-sm transition-colors cursor-pointer"
                  >
                    <RotateCcw className="w-3.5 h-3.5" />
                    Request Re-delivery
                  </button>
                )}
              </div>
            </div>
          )}

          {activeTab === 'timeline' && (
            <div className="space-y-4">
              {/* Milestone visual track */}
              <div className="relative pl-6 space-y-6 before:absolute before:left-2.5 before:top-2 before:bottom-2 before:w-0.5 before:bg-neutral-200 dark:before:bg-neutral-800">
                {(parcel.trackingEvents || []).map((event, idx) => (
                  <div key={idx} className="relative group">
                    {/* Step Icon */}
                    <div className={`absolute -left-6 top-0.5 w-5 h-5 rounded-full flex items-center justify-center border-2 ${
                      idx === 0
                        ? 'bg-blue-600 border-blue-600 text-white'
                        : 'bg-white dark:bg-neutral-900 border-neutral-300 dark:border-neutral-700 text-neutral-400'
                    }`}>
                      <div className={`w-2 h-2 rounded-full ${idx === 0 ? 'bg-white' : 'bg-neutral-400'}`} />
                    </div>

                    <div>
                      <div className="flex items-baseline justify-between gap-2">
                        <span className={`text-xs font-semibold ${idx === 0 ? 'text-blue-600 dark:text-blue-400' : 'text-neutral-800 dark:text-neutral-200'}`}>
                          {event.status}
                        </span>
                        <span className="text-[10px] font-mono text-neutral-400 shrink-0">
                          {event.timestamp}
                        </span>
                      </div>
                      <p className="text-xs text-neutral-600 dark:text-neutral-400 mt-0.5">
                        {event.description}
                      </p>
                      <div className="flex items-center gap-1 text-[10px] font-medium text-neutral-400 mt-1">
                        <MapPin className="w-3 h-3 text-neutral-400" />
                        {event.location}
                      </div>
                    </div>
                  </div>
                ))}
              </div>

              {/* Real-time Webhook Testing Simulator */}
              {onSimulateEvent && (
                <div className="mt-6 pt-4 border-t border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-800/40 p-3.5 rounded-xl">
                  <div className="flex items-center gap-1.5 text-xs font-semibold text-neutral-800 dark:text-neutral-200 mb-2">
                    <Zap className="w-3.5 h-3.5 text-amber-500" />
                    Simulate Real-time Courier Webhook Event
                  </div>
                  <div className="flex flex-wrap gap-2">
                    <button
                      onClick={() => onSimulateEvent(parcel.id, 'Picked Up by Courier', 'Subang Jaya Gateway', 'Courier driver scanned parcel at merchant pickup point.')}
                      className="px-2.5 py-1 text-[11px] font-medium bg-white dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg hover:border-blue-500 text-neutral-700 dark:text-neutral-300 transition-colors"
                    >
                      + Ingest "Picked Up"
                    </button>
                    <button
                      onClick={() => onSimulateEvent(parcel.id, 'Out for Delivery', 'Petaling Jaya Hub', 'Assigned to delivery courier Mohd Hafiz (012-3991823).')}
                      className="px-2.5 py-1 text-[11px] font-medium bg-white dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg hover:border-blue-500 text-neutral-700 dark:text-neutral-300 transition-colors"
                    >
                      + Ingest "Out for Delivery"
                    </button>
                    <button
                      onClick={() => onSimulateEvent(parcel.id, 'Delivered to Recipient', 'Front Door / Guardhouse', 'Delivered and signed by recipient. Proof of delivery photo uploaded.')}
                      className="px-2.5 py-1 text-[11px] font-medium bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-300 dark:border-emerald-700 rounded-lg text-emerald-800 dark:text-emerald-300 transition-colors"
                    >
                      + Ingest "Delivered"
                    </button>
                    <button
                      onClick={() => onSimulateEvent(parcel.id, 'Delivery Failed (NITC)', 'Recipient Address', 'Delivery attempt 1 failed. Recipient premise closed/unreachable.')}
                      className="px-2.5 py-1 text-[11px] font-medium bg-rose-50 dark:bg-rose-900/30 border border-rose-300 dark:border-rose-700 rounded-lg text-rose-800 dark:text-rose-300 transition-colors"
                    >
                      + Ingest "Failed (NITC)"
                    </button>
                  </div>
                </div>
              )}
            </div>
          )}

          {activeTab === 'details' && (
            <div className="space-y-4 text-xs">
              {/* Recipient Details */}
              <div className="p-3.5 bg-neutral-50 dark:bg-neutral-800/40 rounded-xl border border-neutral-200 dark:border-neutral-800 space-y-2">
                <div className="font-semibold text-neutral-800 dark:text-neutral-200 flex items-center justify-between">
                  <span>Recipient & Delivery Address</span>
                  <span className="px-2 py-0.5 text-[10px] uppercase font-bold rounded bg-neutral-100 text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300">
                    Region restricted
                  </span>
                </div>
                <div className="grid grid-cols-2 gap-2 text-neutral-600 dark:text-neutral-300">
                  <div>
                    <span className="text-neutral-400 block text-[10px]">Customer Name</span>
                    <span className="font-medium text-neutral-900 dark:text-neutral-100">{parcel.customerName}</span>
                  </div>
                  <div>
                    <span className="text-neutral-400 block text-[10px]">Contact Phone</span>
                    <span className="font-mono text-neutral-900 dark:text-neutral-100">Restricted in shipment list</span>
                  </div>
                </div>
                <div>
                  <span className="text-neutral-400 block text-[10px]">Full Address</span>
                  <span className="text-neutral-800 dark:text-neutral-200">
                    Recipient address restricted in shipment list
                  </span>
                </div>
              </div>

              {/* Physical Specs & Routing */}
              <div className="grid grid-cols-2 gap-3">
                <div className="p-3.5 bg-neutral-50 dark:bg-neutral-800/40 rounded-xl border border-neutral-200 dark:border-neutral-800 space-y-1.5">
                  <div className="font-semibold text-neutral-800 dark:text-neutral-200">Weights & Dimensions</div>
                  <div className="flex justify-between text-neutral-600 dark:text-neutral-400">
                    <span>Actual Gross Weight:</span>
                    <span className="font-bold text-neutral-900 dark:text-neutral-100 font-mono">{parcel.totalWeightKg === null ? 'Not configured' : `${parcel.totalWeightKg} kg`}</span>
                  </div>
                  <div className="flex justify-between text-neutral-600 dark:text-neutral-400">
                    <span>Volumetric Weight:</span>
                    <span className="font-bold text-neutral-900 dark:text-neutral-100 font-mono">{parcel.volumetricWeightKg === null ? 'Not configured' : `${parcel.volumetricWeightKg} kg`}</span>
                  </div>
                  <div className="flex justify-between text-neutral-600 dark:text-neutral-400">
                    <span>Box Dimensions:</span>
                    <span className="font-mono text-neutral-900 dark:text-neutral-100">
                      {parcel.dimensionsCm ? `${parcel.dimensionsCm.length}x${parcel.dimensionsCm.width}x${parcel.dimensionsCm.height} cm` : 'Not configured'}
                    </span>
                  </div>
                </div>

                <div className="p-3.5 bg-neutral-50 dark:bg-neutral-800/40 rounded-xl border border-neutral-200 dark:border-neutral-800 space-y-1.5">
                  <div className="font-semibold text-neutral-800 dark:text-neutral-200">Payment & Charges</div>
                  <div className="flex justify-between text-neutral-600 dark:text-neutral-400">
                    <span>Payment Mode:</span>
                    <span className="font-bold uppercase text-neutral-900 dark:text-neutral-100">{parcel.paymentMode}</span>
                  </div>
                  {parcel.codAmount && (
                    <div className="flex justify-between text-neutral-600 dark:text-neutral-400">
                      <span>COD Collect Amount:</span>
                      <span className="font-bold text-rose-600 font-mono">RM {parcel.codAmount.toFixed(2)}</span>
                    </div>
                  )}
                  <div className="flex justify-between text-neutral-600 dark:text-neutral-400">
                    <span>Courier Quoted Fee:</span>
                    <span className="font-bold text-neutral-900 dark:text-neutral-100 font-mono">{parcel.systemQuotedCharge === null ? 'Not configured' : `RM ${parcel.systemQuotedCharge.toFixed(2)}`}</span>
                  </div>
                </div>
              </div>
            </div>
          )}

          {activeTab === 'json' && (
            <div className="bg-neutral-950 text-neutral-200 p-4 rounded-xl font-mono text-[11px] overflow-x-auto">
              <pre>{JSON.stringify(parcel, null, 2)}</pre>
            </div>
          )}
        </div>

        {/* Footer */}
        <div className="px-6 py-3.5 border-t border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-900 flex items-center justify-between">
          <button
            disabled
            title="Recipient contact is restricted in shipment-list responses"
            className="flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-emerald-700 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-900/30 rounded-lg transition-colors border border-emerald-200 dark:border-emerald-800 cursor-pointer"
          >
            <MessageCircle className="w-3.5 h-3.5" />
            Recipient contact restricted
          </button>
          <button
            onClick={onClose}
            className="px-4 py-2 text-xs font-medium text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800 rounded-lg transition-colors cursor-pointer"
          >
            Close
          </button>
        </div>
      </div>
    </div>
  );
};
