import React, { useState, useEffect } from 'react';
import {
  X,
  CheckCircle2,
  Clock,
  Truck,
  Package,
  AlertTriangle,
  RotateCcw,
  Ban,
  FileText,
  MapPin,
  ShieldAlert
} from 'lucide-react';
import { ShipmentParcel, ShipmentStatus } from '../../types/shipping';

interface UpdateShipmentStatusModalProps {
  isOpen: boolean;
  parcel: ShipmentParcel | null;
  onClose: () => void;
  onUpdateStatus: (
    parcelId: string,
    newStatus: ShipmentStatus,
    auditNote: string,
    location?: string,
    voidConfirmed?: boolean,
  ) => Promise<void>;
}

interface StatusOption {
  status: ShipmentStatus;
  label: string;
  description: string;
  icon: React.ElementType;
  badgeClass: string;
  isDestructive?: boolean;
}

const ALL_STATUS_OPTIONS: StatusOption[] = [
  {
    status: 'ready_to_ship',
    label: 'Shipped / Ready to Ship',
    description: 'Parcel packed and awaiting pickup or initial dispatch.',
    icon: Package,
    badgeClass: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300 border-blue-200 dark:border-blue-800'
  },
  {
    status: 'in_transit',
    label: 'In Transit',
    description: 'Parcel has departed facility and is en route via transport network.',
    icon: Truck,
    badgeClass: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300 border-indigo-200 dark:border-indigo-800'
  },
  {
    status: 'out_for_delivery',
    label: 'Out for Delivery',
    description: 'Assigned to driver/courier rider for final destination delivery.',
    icon: Clock,
    badgeClass: 'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300 border-purple-200 dark:border-purple-800'
  },
  {
    status: 'delivered',
    label: 'Delivered',
    description: 'Successfully handed over and signed by recipient / at doorstep.',
    icon: CheckCircle2,
    badgeClass: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800'
  },
  {
    status: 'failed_delivery',
    label: 'Failed Delivery',
    description: 'Delivery attempt unsuccessful (unreachable, customer absent, or rejected).',
    icon: AlertTriangle,
    badgeClass: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300 border-amber-200 dark:border-amber-800'
  },
  {
    status: 'returned',
    label: 'Returned',
    description: 'Consignment returned back to merchant warehouse / sender.',
    icon: RotateCcw,
    badgeClass: 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300 border-rose-200 dark:border-rose-800'
  },
  {
    status: 'cancelled',
    label: 'Void / Mark Cancelled',
    description: 'Shipment cancelled before handoff to courier/driver.',
    icon: Ban,
    badgeClass: 'bg-neutral-100 text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300 border-neutral-200 dark:border-neutral-700',
    isDestructive: true
  }
];

export const UpdateShipmentStatusModal: React.FC<UpdateShipmentStatusModalProps> = (props) =>
  props.isOpen && props.parcel ? <ActiveShipmentStatusModal {...props} parcel={props.parcel}/> : null;

const ActiveShipmentStatusModal: React.FC<Omit<UpdateShipmentStatusModalProps,'parcel'> & {parcel:ShipmentParcel}> = ({
  isOpen,
  parcel,
  onClose,
  onUpdateStatus,
}) => {

  const [selectedStatus, setSelectedStatus] = useState<ShipmentStatus | null>(null);
  const [auditNote, setAuditNote] = useState<string>('');
  const [locationName, setLocationName] = useState<string>('Warehouse Dispatch Hub');
  const [error, setError] = useState<string | null>(null);
  const [isSuccess, setIsSuccess] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [definitions, setDefinitions] = useState<Record<string,{name:string;description:string}> | null>(null);
  useEffect(()=>{
    let active=true;
    fetch('/admin/api/commerce-statuses/shipment',{credentials:'same-origin',headers:{Accept:'application/json'}})
      .then(async response=>{if(!response.ok)throw new Error('Shipment statuses could not be loaded.');return response.json();})
      .then(payload=>{if(active)setDefinitions(Object.fromEntries(payload.data.map((row:{code:string;name:string;description:string})=>[row.code,row])));})
      .catch(error=>{if(active)setError(error.message);});
    return()=>{active=false;};
  },[]);
  const statusOptions = definitions ? ALL_STATUS_OPTIONS.filter(option=>definitions[option.status]).map(option=>({...option,label:definitions[option.status].name,description:definitions[option.status].description})) : [];


  // Compute allowed forward status transitions strictly according to requirements:
  // - Delivered may be selected directly from Shipped or In Transit.
  // - Do not allow reverse status transitions.
  // - Void / Mark Cancelled is shown only before the parcel is handed to a courier (e.g. pending or ready_to_ship).
  const getAllowedOptions = (): StatusOption[] => {
    const current = parcel.status;

    if (current === 'pending' || current === 'ready_to_ship' || current === 'shipped') {
      return statusOptions.filter(opt =>
        ['in_transit', 'out_for_delivery', 'delivered', 'failed_delivery', 'returned', 'cancelled'].includes(opt.status)
      );
    }

    if (current === 'in_transit') {
      return statusOptions.filter(opt =>
        ['out_for_delivery', 'delivered', 'failed_delivery', 'returned'].includes(opt.status)
      );
    }

    if (current === 'out_for_delivery') {
      return statusOptions.filter(opt =>
        ['delivered', 'failed_delivery', 'returned'].includes(opt.status)
      );
    }

    if (current === 'failed_delivery') {
      return statusOptions.filter(opt =>
        ['in_transit', 'out_for_delivery', 'delivered', 'returned'].includes(opt.status)
      );
    }

    if (current === 'delivered') {
      return statusOptions.filter(opt =>
        ['returned'].includes(opt.status)
      );
    }

    return [];
  };

  const allowedOptions = getAllowedOptions();

  useEffect(() => {
    if (isOpen) {
      setSelectedStatus(null);
      setAuditNote('');
      setLocationName(
        parcel.fulfillmentMethod === 'manual_delivery'
          ? 'Local Delivery Route'
          : 'Courier Linehaul Hub'
      );
      setError(null);
      setIsSuccess(false);
      setIsSubmitting(false);
    }
  }, [isOpen, parcel?.id]);

  const handleSave = async () => {
    if (!selectedStatus) {
      setError('Please select a new target status.');
      return;
    }

    if (!auditNote.trim()) {
      setError('An audit note is required when updating shipment status.');
      return;
    }

    setIsSubmitting(true);
    setError(null);
    try {
      await onUpdateStatus(
        parcel.id, selectedStatus, auditNote.trim(), locationName.trim() || undefined,
        selectedStatus === 'cancelled',
      );
      setIsSuccess(true);
      setTimeout(onClose, 700);
    } catch (error) {
      setError(error instanceof Error ? error.message : 'Shipment status could not be updated.');
    } finally {
      setIsSubmitting(false);
    }
  };

  const getStatusLabel = (status: ShipmentStatus) => {
    if (definitions?.[status]) return definitions[status].name;
    switch (status) {
      case 'pending': return 'Pending AWB';
      case 'ready_to_ship': return 'Shipped / Ready to Ship';
      case 'shipped': return 'Dispatched';
      case 'picked_up': return 'Picked Up';
      case 'in_transit': return 'In Transit';
      case 'out_for_delivery': return 'Out for Delivery';
      case 'delivered': return 'Delivered';
      case 'failed_delivery': return 'Failed Delivery';
      case 'returned': return 'Returned';
      case 'cancelled': return 'Cancelled / Voided';
      default: return status;
    }
  };

  return (
    <div id="update-shipment-status-overlay" className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4 overflow-y-auto backdrop-blur-sm animate-in fade-in duration-200">
      <div className="bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-2xl max-w-lg w-full flex flex-col shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">

        {/* Header */}
        <div className="flex items-center justify-between px-6 py-4 border-b border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-900/80">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center text-blue-600 dark:text-blue-400 font-black text-sm">
              <Truck className="w-5 h-5" />
            </div>
            <div>
              <div className="flex items-center gap-2">
                <h3 className="text-base font-bold text-neutral-900 dark:text-neutral-100">
                  Update Shipment Status
                </h3>
                <span className="px-2 py-0.5 text-[10px] font-bold rounded-full bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800">
                  Team managed
                </span>
              </div>
              <p className="text-xs text-neutral-500 dark:text-neutral-400 font-mono">
                AWB: {parcel.trackingNumber || 'Manual Delivery'} • Order #{parcel.orderNumber}
              </p>
            </div>
          </div>

          <button
            onClick={onClose}
            className="p-1.5 rounded-lg text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors cursor-pointer"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Modal Body */}
        <div className="p-6 space-y-4 overflow-y-auto max-h-[70vh] text-xs">

          {/* Current Status Pill */}
          <div className="p-3 rounded-xl bg-neutral-50 dark:bg-neutral-800/40 border border-neutral-200 dark:border-neutral-800 flex items-center justify-between">
            <span className="text-neutral-500 dark:text-neutral-400">Current Status:</span>
            <span className="font-bold text-neutral-900 dark:text-neutral-100 px-2.5 py-1 rounded-lg bg-white dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 shadow-xs">
              {getStatusLabel(parcel.status)}
            </span>
          </div>

          {/* Success Flash Banner */}
          {isSuccess && (
            <div className="p-3.5 rounded-xl bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800 flex items-center gap-2.5 text-emerald-800 dark:text-emerald-200">
              <CheckCircle2 className="w-5 h-5 text-emerald-600 shrink-0" />
              <span>Status successfully updated and milestone audit event appended!</span>
            </div>
          )}

          {/* Allowed Next Status Choices */}
          <div className="space-y-2">
            <label className="block font-bold text-neutral-900 dark:text-neutral-100 uppercase tracking-wider text-[11px]">
              Select New Target Status
            </label>

            {allowedOptions.length === 0 ? (
              <div className="p-4 rounded-xl bg-neutral-50 dark:bg-neutral-800/30 text-center text-neutral-500 text-xs">
                {definitions === null ? 'Loading shipment statuses�' : `No further manual status transitions are available for ${getStatusLabel(parcel.status)}.`}
              </div>
            ) : (
              <div className="space-y-2">
                {allowedOptions.map((opt) => {
                  const isSelected = selectedStatus === opt.status;
                  const Icon = opt.icon;

                  return (
                    <div
                      key={opt.status}
                      onClick={() => {
                        setSelectedStatus(opt.status);
                        setError(null);
                      }}
                      className={`p-3 rounded-xl border transition-all cursor-pointer flex items-start gap-3 ${
                        isSelected
                          ? opt.isDestructive
                            ? 'border-rose-500 bg-rose-50/40 dark:bg-rose-900/20 ring-2 ring-rose-500/20 shadow-xs'
                            : 'border-blue-500 bg-blue-50/40 dark:bg-blue-900/20 ring-2 ring-blue-500/20 shadow-xs'
                          : 'border-neutral-200 dark:border-neutral-800 hover:border-neutral-300 dark:hover:border-neutral-700 bg-white dark:bg-neutral-900'
                      }`}
                    >
                      <div className={`w-4 h-4 rounded-full flex items-center justify-center border mt-0.5 shrink-0 transition-all ${
                        isSelected
                          ? opt.isDestructive
                            ? 'border-rose-600 bg-rose-600 text-white'
                            : 'border-blue-600 bg-blue-600 text-white'
                          : 'border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800'
                      }`}>
                        {isSelected && <div className="w-1.5 h-1.5 rounded-full bg-white" />}
                      </div>

                      <div className="space-y-0.5 flex-1">
                        <div className="flex items-center gap-2">
                          <Icon className={`w-3.5 h-3.5 ${opt.isDestructive ? 'text-rose-500' : 'text-blue-600'}`} />
                          <span className="font-bold text-neutral-900 dark:text-neutral-100">
                            {opt.label}
                          </span>
                        </div>
                        <p className="text-[11px] text-neutral-500 dark:text-neutral-400">
                          {opt.description}
                        </p>
                      </div>
                    </div>
                  );
                })}
              </div>
            )}
          </div>

          {/* Audit Note & Location Inputs */}
          {allowedOptions.length > 0 && (
            <div className="space-y-3 pt-2">
              <div>
                <label className="block font-semibold text-neutral-800 dark:text-neutral-200 mb-1">
                  Location / Station <span className="text-neutral-400 font-normal">(Optional)</span>
                </label>
                <div className="relative">
                  <MapPin className="w-3.5 h-3.5 text-neutral-400 absolute left-3 top-3" />
                  <input
                    type="text"
                    value={locationName}
                    onChange={(e) => setLocationName(e.target.value)}
                    placeholder="e.g. Subang Distribution Hub, Klang Valley Delivery Van"
                    className="w-full pl-8 pr-3 py-2 text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-blue-500"
                  />
                </div>
              </div>

              <div>
                <label className="block font-semibold text-neutral-800 dark:text-neutral-200 mb-1">
                  Dispatch / Status Audit Note <span className="text-rose-500">*</span>
                </label>
                <div className="relative">
                  <FileText className="w-3.5 h-3.5 text-neutral-400 absolute left-3 top-3" />
                  <textarea
                    rows={2}
                    value={auditNote}
                    onChange={(e) => {
                      setAuditNote(e.target.value);
                      setError(null);
                    }}
                    placeholder="Provide operational note for this status transition (e.g. Handed to customer at doorstep)..."
                    className={`w-full pl-8 pr-3 py-2 text-xs rounded-xl border bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-blue-500 ${
                      error ? 'border-rose-500' : 'border-neutral-200 dark:border-neutral-700'
                    }`}
                  />
                </div>
              </div>

              {error && (
                <div className="p-2.5 rounded-lg bg-rose-50 dark:bg-rose-950/30 border border-rose-200 dark:border-rose-800 text-rose-700 dark:text-rose-300 text-[11px] flex items-center gap-1.5">
                  <ShieldAlert className="w-3.5 h-3.5 shrink-0" />
                  <span>{error}</span>
                </div>
              )}
            </div>
          )}

          {/* Footer actions */}
          <div className="pt-3 border-t border-neutral-200 dark:border-neutral-800 flex items-center justify-between">
            <span className="text-[11px] text-neutral-400 italic">
              Forward-only status updates logged to tracking audit
            </span>

            <div className="flex items-center gap-2">
              <button
                type="button"
                onClick={onClose}
                className="px-3.5 py-1.5 text-xs font-medium text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800 rounded-xl transition-colors cursor-pointer"
              >
                Cancel
              </button>

              {allowedOptions.length > 0 && (
                <button
                  type="button"
                  onClick={() => void handleSave()}
                  disabled={!selectedStatus || isSuccess || isSubmitting}
                  className="px-4 py-1.5 text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed rounded-xl transition-colors cursor-pointer shadow-sm flex items-center gap-1.5"
                >
                  <CheckCircle2 className="w-3.5 h-3.5" />
                  {isSubmitting ? 'Saving…' : 'Save Status Update'}
                </button>
              )}
            </div>
          </div>

        </div>
      </div>
    </div>
  );
};
