import React, { useState } from 'react';
import {
  X,
  AlertTriangle,
  RotateCcw,
  CheckCircle2,
  ShieldAlert,
  Loader2,
  FileText,
  Truck,
  Package,
  ArrowRight,
  Info,
  Check,
  Edit3,
  ExternalLink,
  ShieldCheck,
  AlertCircle,
  HelpCircle,
  RefreshCw,
  User,
  MapPin
} from 'lucide-react';
import {
  ShipmentParcel,
  CorrectionReasonType
} from '../../types/shipping';

interface CorrectFulfilmentModalProps {
  isOpen: boolean;
  parcel: ShipmentParcel | null;
  isOrderCancelled?: boolean;
  onClose: () => void;
  onConfirmVoidAndReplace: (
    parcelId: string,
    data: {
      reason: CorrectionReasonType | string;
      reasonLabel: string;
      auditNote: string;
      providerCancellationAcknowledged: boolean;
    }
  ) => Promise<void> | void;
  onConfirmCorrectDetailsOnly: (
    parcelId: string,
    data: {
      trackingNumber?: string;
      trackingUrl?: string;
      driverName?: string;
      handoffReference?: string;
      externalCourierName?: string;
      auditNote: string;
    }
  ) => Promise<void> | void;
  onOpenAmendDeliveryDetails?: (orderNumber: string) => void;
}

const CORRECTION_REASONS: { value: CorrectionReasonType; label: string; description: string }[] = [
  {
    value: 'wrong_fulfillment_method',
    label: 'Wrong fulfilment method',
    description: 'Selected EasyParcel instead of own courier / manual driver, or vice versa.'
  },
  {
    value: 'wrong_easyparcel_courier',
    label: 'Wrong EasyParcel courier/service',
    description: 'Booked J&T instead of Pos Laju / Flash, or wrong pickup/dropoff tier.'
  },
  {
    value: 'wrong_external_courier',
    label: 'Wrong external courier',
    description: 'Assigned incorrect offline carrier contract or wrong external branch.'
  },
  {
    value: 'incorrect_package_info',
    label: 'Incorrect package information',
    description: 'Weight, volumetric dimensions, or dangerous goods/liquid flag misentered.'
  },
  {
    value: 'incorrect_receiver_details',
    label: 'Incorrect receiver details',
    description: 'Destination postcode, address typo, or phone number mismatch.'
  },
  {
    value: 'duplicate_accidental_dispatch',
    label: 'Duplicate/accidental dispatch',
    description: 'Consignment generated twice or triggered prematurely by staff.'
  },
  {
    value: 'customer_requested_change',
    label: 'Customer requested delivery change',
    description: 'Customer contacted support to update address or courier preference.'
  },
  {
    value: 'other_operational_error',
    label: 'Other operational error',
    description: 'Warehouse packaging discrepancy or special operational exception.'
  },
];

export const CorrectFulfilmentModal: React.FC<CorrectFulfilmentModalProps> = ({
  isOpen,
  parcel,
  isOrderCancelled = false,
  onClose,
  onConfirmVoidAndReplace,
  onConfirmCorrectDetailsOnly,
  onOpenAmendDeliveryDetails,
}) => {
  if (!isOpen || !parcel) return null;

  const isProviderManaged = parcel.managementMode === 'provider_managed' || parcel.courier === 'easyparcel';
  const isDelivered = parcel.status === 'delivered';

  // Mode Selection: 'void_and_replace' | 'correct_details_only'
  const [activeWorkflow, setActiveWorkflow] = useState<'void_and_replace' | 'correct_details_only'>('void_and_replace');

  // Reason & Acknowledgement state
  const [selectedReason, setSelectedReason] = useState<CorrectionReasonType>('wrong_fulfillment_method');
  const [methodAcknowledgement, setMethodAcknowledgement] = useState<boolean>(false);
  const [auditNote, setAuditNote] = useState<string>('');

  // Provider Unconfirmed Warning state (demonstrates simulated provider API acknowledgement)
  const [providerCancellationUnconfirmed] = useState<boolean>(isProviderManaged);
  const [unconfirmedAcknowledgeCheck, setUnconfirmedAcknowledgeCheck] = useState<boolean>(false);

  // Correct Details Only form state
  const [detailTrackingNumber, setDetailTrackingNumber] = useState<string>(parcel.trackingNumber || '');
  const [detailTrackingUrl, setDetailTrackingUrl] = useState<string>(parcel.trackingUrl || '');
  const [detailDriverName, setDetailDriverName] = useState<string>(parcel.driverName || '');
  const [detailHandoffRef, setDetailHandoffRef] = useState<string>(parcel.handoffReference || '');
  const [detailCourierName, setDetailCourierName] = useState<string>(parcel.externalCourierName || '');
  const [detailAuditNote, setDetailAuditNote] = useState<string>('');

  // UI / Testing states
  const [isSubmitting, setIsSubmitting] = useState<boolean>(false);
  const [validationErrors, setValidationErrors] = useState<{ [key: string]: string }>({});
  const [permissionError, setPermissionError] = useState<string | null>(null);
  const [mutationError, setMutationError] = useState<string | null>(null);
  const [isSuccess, setIsSuccess] = useState<boolean>(false);
  const [successMessage, setSuccessMessage] = useState<string>('');

  // Check if current reason suggests amending receiver details
  const isAddressAmendmentSuggested =
    selectedReason === 'incorrect_receiver_details' || selectedReason === 'customer_requested_change';

  // Validation function
  const validateVoidAndReplace = () => {
    const errors: { [key: string]: string } = {};

    if (!selectedReason) {
      errors.reason = 'Please select a correction reason.';
    }

    if (!methodAcknowledgement) {
      errors.acknowledgement = 'Method-specific cancellation acknowledgement is mandatory.';
    }

    if (isProviderManaged && providerCancellationUnconfirmed && !unconfirmedAcknowledgeCheck) {
      errors.unconfirmed = 'Please acknowledge that manual provider reconciliation will be performed.';
    }

    if (!auditNote.trim()) {
      errors.auditNote = 'Internal Audit Note is required (minimum 10 characters).';
    } else if (auditNote.trim().length < 10) {
      errors.auditNote = 'Internal Audit Note must contain at least 10 characters explaining the operational cause.';
    }

    setValidationErrors(errors);
    return Object.keys(errors).length === 0;
  };

  const validateDetailsOnly = () => {
    const errors: { [key: string]: string } = {};

    if (parcel.fulfillmentMethod === 'manual_delivery' || parcel.courier === 'manual') {
      if (!detailDriverName.trim()) errors.driverName = 'Driver / Personnel name is required.';
    } else {
      if (!detailTrackingNumber.trim()) errors.trackingNumber = 'Tracking / AWB Number is required.';
    }

    if (!detailAuditNote.trim()) {
      errors.detailAuditNote = 'Internal Audit Note is required (minimum 10 characters).';
    } else if (detailAuditNote.trim().length < 10) {
      errors.detailAuditNote = 'Internal Audit Note must contain at least 10 characters.';
    }

    setValidationErrors(errors);
    return Object.keys(errors).length === 0;
  };

  // Submit Handler: Void & Replace
  const handleVoidAndReplace = async () => {
    setPermissionError(null);
    setMutationError(null);

    if (!validateVoidAndReplace()) return;

    setIsSubmitting(true);
    try {
      const reasonObj = CORRECTION_REASONS.find(r => r.value === selectedReason);
      await onConfirmVoidAndReplace(parcel.id, {
        reason: selectedReason,
        reasonLabel: reasonObj?.label || selectedReason,
        auditNote: auditNote.trim(),
        providerCancellationAcknowledged: methodAcknowledgement,
      });

      setSuccessMessage('Shipment attempt voided. Handing off to Choose Fulfilment Method...');
      setIsSuccess(true);
      setTimeout(() => {
        setIsSuccess(false);
        onClose();
      }, 1100);
    } catch (err: any) {
      const message = err?.message || 'Server mutation error: Failed to record fulfilment void. Please retry.';
      if (/permission|unauthori[sz]ed|forbidden/i.test(message)) setPermissionError(message);
      else setMutationError(message);
    } finally {
      setIsSubmitting(false);
    }
  };

  // Submit Handler: Correct Details Only
  const handleSaveDetailsOnly = async () => {
    setPermissionError(null);
    setMutationError(null);

    if (!validateDetailsOnly()) return;

    setIsSubmitting(true);
    try {
      await onConfirmCorrectDetailsOnly(parcel.id, {
        trackingNumber: detailTrackingNumber.trim() || undefined,
        trackingUrl: detailTrackingUrl.trim() || undefined,
        driverName: detailDriverName.trim() || undefined,
        handoffReference: detailHandoffRef.trim() || undefined,
        externalCourierName: detailCourierName.trim() || undefined,
        auditNote: detailAuditNote.trim(),
      });

      setSuccessMessage('Operational dispatch details corrected and audit log recorded.');
      setIsSuccess(true);
      setTimeout(() => {
        setIsSuccess(false);
        onClose();
      }, 1000);
    } catch (err: any) {
      const message = err?.message || 'Failed to update shipment details. Please retry.';
      if (/permission|unauthori[sz]ed|forbidden/i.test(message)) setPermissionError(message);
      else setMutationError(message);
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-6 bg-black/60 backdrop-blur-xs animate-in fade-in">
      <div className="w-full max-w-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-3xl shadow-2xl overflow-hidden flex flex-col max-h-[92vh] text-neutral-900 dark:text-white">

        {/* Modal Header */}
        <div className="px-6 py-4 border-b border-neutral-100 dark:border-neutral-800 flex items-center justify-between shrink-0 bg-neutral-50/50 dark:bg-neutral-800/40">
          <div>
            <div className="flex items-center gap-2 flex-wrap">
              <span className="font-mono font-bold text-base text-neutral-900 dark:text-white flex items-center gap-1.5">
                <RotateCcw className="w-4 h-4 text-blue-600 dark:text-blue-400" />
                Correct Fulfilment
              </span>
              <span className="font-mono text-xs px-2 py-0.5 rounded-md bg-neutral-200 dark:bg-neutral-700 text-neutral-800 dark:text-neutral-200 font-bold">
                Order #{parcel.orderNumber}
              </span>
              <span className="font-mono text-[11px] text-neutral-400">
                (Attempt #{parcel.id})
              </span>
            </div>
            <p className="text-xs text-neutral-400 mt-0.5">
              Safely recover or replace an active shipment attempt without altering customer order totals or inventory.
            </p>
          </div>

          <button
            onClick={onClose}
            disabled={isSubmitting}
            className="text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200 p-1.5 rounded-xl hover:bg-neutral-100 dark:hover:bg-neutral-800 transition cursor-pointer"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Modal Body: Scrollable Content */}
        <div className="p-6 overflow-y-auto space-y-5 text-xs">

          {/* HARD-BLOCK STATE 1: Delivered Order Hard Block */}
          {isDelivered && (
            <div className="p-4 rounded-2xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800/60 text-rose-800 dark:text-rose-300 flex items-start gap-3">
              <ShieldAlert className="w-5 h-5 shrink-0 mt-0.5 text-rose-600 dark:text-rose-400" />
              <div className="space-y-1">
                <div className="font-bold text-sm">Fulfilment Correction Locked (Shipment Delivered)</div>
                <p className="leading-relaxed text-[11px]">
                  This consignment has already achieved <strong>Delivered</strong> carrier status. Once delivered, fulfilment cannot be voided or corrected through standard dispatch recovery.
                </p>
                <p className="text-[11px] font-semibold text-rose-900 dark:text-rose-200 pt-1">
                  Action required: Direct staff to the <strong>Return / Replacement / Reshipment (RMA)</strong> workflow for customer receipt disputes or return parcel intake.
                </p>
              </div>
            </div>
          )}

          {/* HARD-BLOCK STATE 2: Customer Order Cancelled Hard Block */}
          {isOrderCancelled && !isDelivered && (
            <div className="p-4 rounded-2xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/60 text-amber-800 dark:text-amber-300 flex items-start gap-3">
              <AlertTriangle className="w-5 h-5 shrink-0 mt-0.5 text-amber-600 dark:text-amber-400" />
              <div className="space-y-1">
                <div className="font-bold text-sm">Fulfilment Correction Disabled (Customer Order Cancelled)</div>
                <p className="leading-relaxed text-[11px]">
                  The customer order #{parcel.orderNumber} has been officially cancelled. Further replacement fulfilment methods cannot be dispatched.
                </p>
              </div>
            </div>
          )}

          {/* Current Shipment Identity & Snapshot Summary Card */}
          <div className="p-4 rounded-2xl bg-neutral-50/70 dark:bg-neutral-800/40 border border-neutral-200 dark:border-neutral-800 space-y-3">
            <div className="flex items-center justify-between flex-wrap gap-2">
              <span className="font-bold text-xs uppercase tracking-wider text-neutral-600 dark:text-neutral-400 flex items-center gap-1.5">
                <Package className="w-3.5 h-3.5 text-blue-500" />
                Current Shipment Snapshot
              </span>

              {/* Fulfilment Method Badge */}
              <span className={`px-2 py-0.5 text-[10px] font-bold rounded uppercase tracking-wider ${
                isProviderManaged
                  ? 'bg-pink-100 text-pink-700 dark:bg-pink-900/50 dark:text-pink-300 border border-pink-200 dark:border-pink-800'
                  : 'bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300 border border-blue-200 dark:border-blue-800'
              }`}>
                {isProviderManaged ? 'Provider managed' : 'Team managed'}
              </span>
            </div>

            <div className="grid grid-cols-2 sm:grid-cols-4 gap-2.5 text-[11px]">
              <div className="p-2.5 rounded-xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 space-y-0.5">
                <span className="text-[10px] text-neutral-400 block">Courier / Service</span>
                <span className="font-bold text-neutral-900 dark:text-white uppercase truncate block">
                  {parcel.fulfillmentMethod === 'manual_delivery' || parcel.courier === 'manual'
                    ? 'In-House Delivery'
                    : parcel.externalCourierName || parcel.courier}
                </span>
                <span className="text-[10px] text-neutral-400 truncate block">{parcel.serviceType || 'Standard'}</span>
              </div>

              <div className="p-2.5 rounded-xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 space-y-0.5">
                <span className="text-[10px] text-neutral-400 block">AWB / Ref No.</span>
                <span className="font-bold font-mono text-neutral-900 dark:text-white truncate block">
                  {parcel.trackingNumber || parcel.handoffReference || 'Pending'}
                </span>
                {parcel.driverName && (
                  <span className="text-[10px] text-neutral-400 truncate block">Driver: {parcel.driverName}</span>
                )}
              </div>

              <div className="p-2.5 rounded-xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 space-y-0.5">
                <span className="text-[10px] text-neutral-400 block">Shipment Status</span>
                <span className="font-bold text-neutral-900 dark:text-white uppercase truncate block">
                  {parcel.status.replace(/_/g, ' ')}
                </span>
                <span className="text-[10px] text-neutral-400 block">
                  {parcel.isAwaitingReplacement ? 'Awaiting replacement' : 'Active attempt'}
                </span>
              </div>

              <div className="p-2.5 rounded-xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 space-y-0.5">
                <span className="text-[10px] text-neutral-400 block">Provider Cost / Created</span>
                <span className="font-bold font-mono text-neutral-900 dark:text-white block">
                  RM {parcel.actualCourierCharge?.toFixed(2) || parcel.systemQuotedCharge?.toFixed(2) || '0.00'}
                </span>
                <span className="text-[10px] text-neutral-400 block truncate">
                  {parcel.createdAt ? new Date(parcel.createdAt).toLocaleDateString() : 'Today'}
                </span>
              </div>
            </div>
          </div>

          {/* Workflow Mode Tabs (Void & Replace Method vs Correct Details Only) */}
          {!isDelivered && !isOrderCancelled && (
            <div className="p-1 rounded-xl bg-neutral-100 dark:bg-neutral-800 flex items-center text-xs">
              <button
                type="button"
                onClick={() => setActiveWorkflow('void_and_replace')}
                className={`flex-1 py-2 rounded-lg font-bold transition flex items-center justify-center gap-1.5 cursor-pointer ${
                  activeWorkflow === 'void_and_replace'
                    ? 'bg-white dark:bg-neutral-900 text-blue-600 dark:text-blue-400 shadow-xs'
                    : 'text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white'
                }`}
              >
                <RotateCcw className="w-3.5 h-3.5" />
                <span>Void Attempt & Replace Method</span>
              </button>

              <button
                type="button"
                onClick={() => setActiveWorkflow('correct_details_only')}
                className={`flex-1 py-2 rounded-lg font-bold transition flex items-center justify-center gap-1.5 cursor-pointer ${
                  activeWorkflow === 'correct_details_only'
                    ? 'bg-white dark:bg-neutral-900 text-indigo-600 dark:text-indigo-400 shadow-xs'
                    : 'text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white'
                }`}
              >
                <Edit3 className="w-3.5 h-3.5" />
                <span>Correct Details Only (No Rebooking)</span>
              </button>
            </div>
          )}

          {/* Permission Denied / Mutation Error Banners */}
          {permissionError && (
            <div className="p-3.5 rounded-2xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 text-rose-700 dark:text-rose-300 flex items-center justify-between gap-3 animate-in fade-in">
              <div className="flex items-center gap-2">
                <ShieldAlert className="w-4 h-4 text-rose-600 shrink-0" />
                <span className="font-semibold text-xs">{permissionError}</span>
              </div>
              <button
                type="button"
                onClick={() => setPermissionError(null)}
                className="text-[11px] font-bold text-rose-700 dark:text-rose-300 underline hover:no-underline cursor-pointer shrink-0"
              >
                Dismiss
              </button>
            </div>
          )}

          {mutationError && (
            <div className="p-3.5 rounded-2xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 text-rose-700 dark:text-rose-300 flex items-center justify-between gap-3 animate-in fade-in">
              <div className="flex items-center gap-2">
                <AlertCircle className="w-4 h-4 text-rose-600 shrink-0" />
                <span className="font-semibold text-xs">{mutationError}</span>
              </div>
              <button
                type="button"
                onClick={() => setMutationError(null)}
                className="text-[11px] font-bold text-rose-700 dark:text-rose-300 underline cursor-pointer shrink-0"
              >
                Dismiss
              </button>
            </div>
          )}

          {/* WORKFLOW 1: VOID ATTEMPT & CHOOSE NEW METHOD */}
          {activeWorkflow === 'void_and_replace' && !isDelivered && !isOrderCancelled && (
            <div className="space-y-4">

              {/* Reason Selector */}
              <div className="space-y-1.5">
                <label className="text-[11px] font-bold uppercase tracking-wider text-neutral-700 dark:text-neutral-300 block">
                  Correction Reason <span className="text-rose-500">*</span>
                </label>
                <select
                  value={selectedReason}
                  onChange={(e) => {
                    setSelectedReason(e.target.value as CorrectionReasonType);
                    if (validationErrors.reason) setValidationErrors(prev => ({ ...prev, reason: '' }));
                  }}
                  className={`w-full px-3 py-2 text-xs rounded-xl border bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white font-medium ${
                    validationErrors.reason ? 'border-rose-500' : 'border-neutral-300 dark:border-neutral-700'
                  }`}
                >
                  {CORRECTION_REASONS.map((r) => (
                    <option key={r.value} value={r.value}>
                      {r.label}
                    </option>
                  ))}
                </select>
                <p className="text-[11px] text-neutral-400 italic">
                  {CORRECTION_REASONS.find(r => r.value === selectedReason)?.description}
                </p>
                {validationErrors.reason && (
                  <span className="text-[10px] text-rose-500 block">{validationErrors.reason}</span>
                )}
              </div>

              {/* Contextual Bridge: Amend Delivery Details Shortcut */}
              {isAddressAmendmentSuggested && (
                <div className="p-3 rounded-xl bg-indigo-50/80 dark:bg-indigo-950/40 border border-indigo-200 dark:border-indigo-800/80 flex items-center justify-between gap-3 animate-in fade-in">
                  <div className="flex items-center gap-2">
                    <MapPin className="w-4 h-4 text-indigo-600 dark:text-indigo-400 shrink-0" />
                    <div>
                      <span className="font-bold text-xs text-indigo-950 dark:text-indigo-200">
                        Receiver / Delivery Address Update Required?
                      </span>
                      <p className="text-[11px] text-indigo-800/80 dark:text-indigo-300/80">
                        You can amend the recipient address before or alongside voiding this attempt.
                      </p>
                    </div>
                  </div>
                  {onOpenAmendDeliveryDetails && (
                    <button
                      type="button"
                      onClick={() => {
                        onClose();
                        onOpenAmendDeliveryDetails(parcel.orderNumber);
                      }}
                      className="px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs whitespace-nowrap shadow-xs transition flex items-center gap-1 cursor-pointer shrink-0"
                    >
                      <span>Amend Address</span>
                      <ExternalLink className="w-3 h-3" />
                    </button>
                  )}
                </div>
              )}

              {/* Method-Specific Acknowledgement Checkbox */}
              <div className="p-3.5 rounded-2xl bg-neutral-50 dark:bg-neutral-800/50 border border-neutral-200 dark:border-neutral-700 space-y-2">
                <div className="font-bold text-xs text-neutral-900 dark:text-white flex items-center gap-1.5">
                  <ShieldCheck className="w-3.5 h-3.5 text-blue-600 dark:text-blue-400" />
                  <span>Mandatory Operational Acknowledgement</span>
                </div>

                <label className="flex items-start gap-2.5 cursor-pointer text-xs text-neutral-800 dark:text-neutral-200 leading-relaxed">
                  <input
                    type="checkbox"
                    checked={methodAcknowledgement}
                    onChange={(e) => {
                      setMethodAcknowledgement(e.target.checked);
                      if (validationErrors.acknowledgement) {
                        setValidationErrors(prev => ({ ...prev, acknowledgement: '' }));
                      }
                    }}
                    className="w-4 h-4 rounded text-blue-600 accent-blue-600 mt-0.5 shrink-0 cursor-pointer"
                  />
                  <span>
                    {isProviderManaged ? (
                      <strong>
                        I acknowledge that this consignment has been voided or cancelled in the official EasyParcel merchant dashboard (or marked for manual reconciliation).
                      </strong>
                    ) : parcel.fulfillmentMethod === 'external_courier' ? (
                      <strong>
                        I acknowledge that the external courier partner has been contacted and the old AWB label ({parcel.trackingNumber || 'offline'}) has been voided/discarded.
                      </strong>
                    ) : (
                      <strong>
                        I acknowledge that the internal delivery personnel/driver has been notified and this manual delivery handoff has been reviewed/cancelled.
                      </strong>
                    )}
                  </span>
                </label>
                {validationErrors.acknowledgement && (
                  <span className="text-[10px] text-rose-500 block font-semibold">{validationErrors.acknowledgement}</span>
                )}
              </div>

              {/* Provider Cancellation Unconfirmed State Notice (for EasyParcel) */}
              {isProviderManaged && providerCancellationUnconfirmed && (
                <div className="p-3.5 rounded-2xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/80 text-amber-900 dark:text-amber-200 space-y-2">
                  <div className="flex items-center gap-2 font-bold text-xs text-amber-950 dark:text-amber-100">
                    <AlertTriangle className="w-4 h-4 text-amber-600 shrink-0" />
                    <span>Provider API Cancellation Pending / Unconfirmed</span>
                  </div>
                  <p className="text-[11px] leading-relaxed text-amber-800 dark:text-amber-300">
                    The EasyParcel automated cancellation webhook has not yet synced confirmation for AWB <strong>{parcel.trackingNumber}</strong>. You may proceed immediately with replacement booking after acknowledging that warehouse finance will reconcile any pending wallet refund.
                  </p>
                  <label className="flex items-center gap-2 cursor-pointer text-xs font-semibold text-amber-950 dark:text-amber-100 pt-1">
                    <input
                      type="checkbox"
                      checked={unconfirmedAcknowledgeCheck}
                      onChange={(e) => {
                        setUnconfirmedAcknowledgeCheck(e.target.checked);
                        if (validationErrors.unconfirmed) {
                          setValidationErrors(prev => ({ ...prev, unconfirmed: '' }));
                        }
                      }}
                      className="w-4 h-4 rounded text-amber-600 accent-amber-600 cursor-pointer"
                    />
                    <span>Staff Override: Proceed with replacement dispatch and log for wallet reconciliation</span>
                  </label>
                  {validationErrors.unconfirmed && (
                    <span className="text-[10px] text-rose-600 dark:text-rose-400 block font-bold">{validationErrors.unconfirmed}</span>
                  )}
                </div>
              )}

              {/* Mandatory Internal Audit Note */}
              <div className="space-y-1.5">
                <label className="text-[11px] font-bold uppercase tracking-wider text-neutral-700 dark:text-neutral-300 block">
                  Internal Audit Note <span className="text-rose-500">*</span>
                </label>
                <textarea
                  rows={2}
                  value={auditNote}
                  onChange={(e) => {
                    setAuditNote(e.target.value);
                    if (validationErrors.auditNote) setValidationErrors(prev => ({ ...prev, auditNote: '' }));
                  }}
                  placeholder="e.g. Voided EasyParcel booking due to customer upgrading to same-day dispatch. Old AWB destroyed."
                  className={`w-full px-3 py-2 text-xs rounded-xl border bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white ${
                    validationErrors.auditNote ? 'border-rose-500' : 'border-neutral-300 dark:border-neutral-700'
                  }`}
                />
                {validationErrors.auditNote && (
                  <span className="text-[10px] text-rose-500 block">{validationErrors.auditNote}</span>
                )}
              </div>

              {/* Impact Summary Callout */}
              <div className="p-3.5 rounded-2xl bg-emerald-50/60 dark:bg-emerald-950/20 border border-emerald-200/80 dark:border-emerald-900/60 space-y-1.5 text-[11px] text-emerald-900 dark:text-emerald-300">
                <div className="font-bold text-xs text-emerald-950 dark:text-emerald-200 flex items-center gap-1.5">
                  <CheckCircle2 className="w-3.5 h-3.5 text-emerald-600" />
                  <span>Immutable System Impact Guarantee</span>
                </div>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-1.5 pt-0.5">
                  <div className="flex items-center gap-1.5">
                    <Check className="w-3 h-3 text-emerald-600 shrink-0" />
                    <span>Customer Order stays active (no order cancel)</span>
                  </div>
                  <div className="flex items-center gap-1.5">
                    <Check className="w-3 h-3 text-emerald-600 shrink-0" />
                    <span>Inventory, payment, and totals remain unchanged</span>
                  </div>
                  <div className="flex items-center gap-1.5">
                    <Check className="w-3 h-3 text-emerald-600 shrink-0" />
                    <span>Old shipment attempt history is securely retained</span>
                  </div>
                  <div className="flex items-center gap-1.5">
                    <Check className="w-3 h-3 text-emerald-600 shrink-0" />
                    <span>No misleading "Order Cancelled" email sent</span>
                  </div>
                </div>
              </div>
            </div>
          )}

          {/* WORKFLOW 2: CORRECT DETAILS ONLY (TRACKING / DRIVER / URL) */}
          {activeWorkflow === 'correct_details_only' && !isDelivered && !isOrderCancelled && (
            <div className="space-y-4 animate-in fade-in">
              <div className="p-3 rounded-xl bg-blue-50 dark:bg-blue-950/30 border border-blue-200 dark:border-blue-800/60 text-blue-900 dark:text-blue-300 text-xs">
                <strong>Operational Details Correction:</strong> A full method replacement is unnecessary if only the carrier tracking number, direct tracking link, or driver name was mis-typed. Update below with an audit note.
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                {parcel.fulfillmentMethod === 'manual_delivery' || parcel.courier === 'manual' ? (
                  <>
                    <div>
                      <label className="text-[11px] font-semibold text-neutral-700 dark:text-neutral-300 block mb-1">
                        Driver / Personnel Name <span className="text-rose-500">*</span>
                      </label>
                      <input
                        type="text"
                        value={detailDriverName}
                        onChange={(e) => setDetailDriverName(e.target.value)}
                        className="w-full px-3 py-2 text-xs rounded-xl border bg-white dark:bg-neutral-800 border-neutral-300 dark:border-neutral-700 text-neutral-900 dark:text-white"
                        placeholder="e.g. Pak Din (Van 1)"
                      />
                      {validationErrors.driverName && (
                        <span className="text-[10px] text-rose-500 mt-0.5 block">{validationErrors.driverName}</span>
                      )}
                    </div>

                    <div>
                      <label className="text-[11px] font-semibold text-neutral-700 dark:text-neutral-300 block mb-1">
                        Handoff Reference
                      </label>
                      <input
                        type="text"
                        value={detailHandoffRef}
                        onChange={(e) => setDetailHandoffRef(e.target.value)}
                        className="w-full px-3 py-2 text-xs rounded-xl border bg-white dark:bg-neutral-800 border-neutral-300 dark:border-neutral-700 text-neutral-900 dark:text-white font-mono"
                        placeholder="e.g. MAN-2026-904"
                      />
                    </div>
                  </>
                ) : (
                  <>
                    <div>
                      <label className="text-[11px] font-semibold text-neutral-700 dark:text-neutral-300 block mb-1">
                        Courier Partner Name
                      </label>
                      <input
                        type="text"
                        value={detailCourierName}
                        onChange={(e) => setDetailCourierName(e.target.value)}
                        className="w-full px-3 py-2 text-xs rounded-xl border bg-white dark:bg-neutral-800 border-neutral-300 dark:border-neutral-700 text-neutral-900 dark:text-white uppercase font-bold"
                        placeholder="e.g. Ninja Van"
                      />
                    </div>

                    <div>
                      <label className="text-[11px] font-semibold text-neutral-700 dark:text-neutral-300 block mb-1">
                        Tracking / AWB Number <span className="text-rose-500">*</span>
                      </label>
                      <input
                        type="text"
                        value={detailTrackingNumber}
                        onChange={(e) => setDetailTrackingNumber(e.target.value)}
                        className="w-full px-3 py-2 text-xs rounded-xl border bg-white dark:bg-neutral-800 border-neutral-300 dark:border-neutral-700 text-neutral-900 dark:text-white font-mono font-bold"
                        placeholder="e.g. NVMY88294101"
                      />
                      {validationErrors.trackingNumber && (
                        <span className="text-[10px] text-rose-500 mt-0.5 block">{validationErrors.trackingNumber}</span>
                      )}
                    </div>

                    <div className="sm:col-span-2">
                      <label className="text-[11px] font-semibold text-neutral-700 dark:text-neutral-300 block mb-1">
                        Direct Carrier Tracking URL
                      </label>
                      <input
                        type="text"
                        value={detailTrackingUrl}
                        onChange={(e) => setDetailTrackingUrl(e.target.value)}
                        className="w-full px-3 py-2 text-xs rounded-xl border bg-white dark:bg-neutral-800 border-neutral-300 dark:border-neutral-700 text-neutral-900 dark:text-white font-mono text-[11px]"
                        placeholder="https://track.courier.com.my?awb=..."
                      />
                    </div>
                  </>
                )}

                <div className="sm:col-span-2 space-y-1">
                  <label className="text-[11px] font-semibold text-neutral-700 dark:text-neutral-300 block">
                    Correction Audit Note <span className="text-rose-500">*</span>
                  </label>
                  <textarea
                    rows={2}
                    value={detailAuditNote}
                    onChange={(e) => setDetailAuditNote(e.target.value)}
                    placeholder="e.g. Corrected mistyped digit in AWB tracking number from 0 to 8."
                    className="w-full px-3 py-2 text-xs rounded-xl border bg-white dark:bg-neutral-800 border-neutral-300 dark:border-neutral-700 text-neutral-900 dark:text-white"
                  />
                  {validationErrors.detailAuditNote && (
                    <span className="text-[10px] text-rose-500 block">{validationErrors.detailAuditNote}</span>
                  )}
                </div>
              </div>
            </div>
          )}

          {/* Success Banner */}
          {isSuccess && (
            <div className="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300 flex items-center gap-3 animate-in zoom-in-95">
              <CheckCircle2 className="w-5 h-5 text-emerald-600 shrink-0" />
              <div>
                <div className="font-bold text-xs">{successMessage}</div>
                <p className="text-[11px] text-emerald-700 dark:text-emerald-400 mt-0.5">
                  Shipment audit trail recorded and status synced across Logistics Hub.
                </p>
              </div>
            </div>
          )}

        </div>

        {/* Modal Footer Actions */}
        <div className="px-6 py-4 border-t border-neutral-100 dark:border-neutral-800 flex items-center justify-between shrink-0 bg-neutral-50/50 dark:bg-neutral-800/40">
          <button
            type="button"
            onClick={onClose}
            disabled={isSubmitting}
            className="px-4 py-2 rounded-xl text-xs font-semibold text-neutral-600 dark:text-neutral-300 hover:bg-neutral-200/60 dark:hover:bg-neutral-800 transition cursor-pointer"
          >
            Cancel / Close
          </button>

          {!isDelivered && !isOrderCancelled && (
            <div>
              {activeWorkflow === 'void_and_replace' ? (
                <button
                  type="button"
                  onClick={handleVoidAndReplace}
                  disabled={isSubmitting || isSuccess}
                  className="px-5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs shadow-sm transition flex items-center gap-2 cursor-pointer disabled:opacity-50"
                >
                  {isSubmitting ? (
                    <>
                      <Loader2 className="w-3.5 h-3.5 animate-spin" />
                      <span>Voiding Attempt...</span>
                    </>
                  ) : isSuccess ? (
                    <>
                      <Check className="w-3.5 h-3.5" />
                      <span>Proceeding to Replacement</span>
                    </>
                  ) : (
                    <>
                      <RotateCcw className="w-3.5 h-3.5" />
                      <span>Void Attempt & Choose New Method</span>
                    </>
                  )}
                </button>
              ) : (
                <button
                  type="button"
                  onClick={handleSaveDetailsOnly}
                  disabled={isSubmitting || isSuccess}
                  className="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-sm transition flex items-center gap-2 cursor-pointer disabled:opacity-50"
                >
                  {isSubmitting ? (
                    <>
                      <Loader2 className="w-3.5 h-3.5 animate-spin" />
                      <span>Saving Details...</span>
                    </>
                  ) : (
                    <>
                      <Check className="w-3.5 h-3.5" />
                      <span>Save Corrected Details</span>
                    </>
                  )}
                </button>
              )}
            </div>
          )}
        </div>
      </div>
    </div>
  );
};
