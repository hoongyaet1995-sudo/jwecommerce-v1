import React, { useState, useEffect } from 'react';
import {
  X,
  Package,
  Truck,
  Check,
  Clock,
  AlertCircle,
  RefreshCw,
  ShieldCheck,
  MapPin,
  ChevronRight,
  ChevronLeft,
  AlertTriangle,
  Zap,
  CheckCircle2,
  FileText,
  User,
  Link,
  Info
} from 'lucide-react';
import { ShipmentParcel, EasyParcelRateOption, FulfillmentMethodType } from '../../types/shipping';
import type { EasyParcelBooking, EasyParcelPackageSpec, EasyParcelQuote } from '../../integration/easyParcelFulfillmentData';
import { packageDraftFromParcel, packageSpecFromDraft } from './easyParcelPackageConfirmation';

interface ChooseFulfillmentMethodModalProps {
  isOpen: boolean;
  parcel: ShipmentParcel | null;
  easyParcelConnected: boolean;
  onClose: () => void;
  onQuoteEasyParcel: (parcel: ShipmentParcel, packageSpec: EasyParcelPackageSpec) => Promise<EasyParcelQuote>;
  onConfirmEasyParcelBooking: (parcel: ShipmentParcel, quote: EasyParcelQuote, serviceCode: string) => Promise<EasyParcelBooking>;
  onConfirmExternalCourier: (
    parcelId: string,
    data: {
      courierCode: string;
      courierName: string;
      trackingNumber: string;
      trackingUrl?: string;
      auditNote: string;
    }
  ) => Promise<void>;
  onConfirmManualDelivery: (
    parcelId: string,
    data: {
      driverName: string;
      handoffReference?: string;
      auditNote: string;
    }
  ) => Promise<void>;
}

const EXTERNAL_COURIER_OPTIONS = [
  { id: 'ninjavan', name: 'Ninja Van Malaysia' },
  { id: 'jnt', name: 'J&T Express Malaysia' },
  { id: 'dhl_express', name: 'DHL Express' },
  { id: 'dhlecommerce', name: 'DHL eCommerce' },
  { id: 'gdex', name: 'GDEX' },
  { id: 'posmalaysia', name: 'Pos Laju / Pos Malaysia' },
  { id: 'flashexpress', name: 'Flash Express' },
  { id: 'citylink', name: 'City-Link Express' },
  { id: 'other', name: 'Other courier' },
];

export const ChooseFulfillmentMethodModal: React.FC<ChooseFulfillmentMethodModalProps> = ({
  isOpen,
  parcel,
  easyParcelConnected,
  onClose,
  onQuoteEasyParcel,
  onConfirmEasyParcelBooking,
  onConfirmExternalCourier,
  onConfirmManualDelivery,
}) => {
  if (!isOpen || !parcel) return null;

  const [selectedMethod, setSelectedMethod] = useState<FulfillmentMethodType | null>(null);
  const [step, setStep] = useState<'choose_method' | 'easyparcel_rates'>('choose_method');

  // External Courier form state
  const [externalCourierId, setExternalCourierId] = useState<string>('ninjavan');
  const [customCourierName, setCustomCourierName] = useState<string>('');
  const [externalTrackingNumber, setExternalTrackingNumber] = useState<string>('');
  const [externalTrackingUrl, setExternalTrackingUrl] = useState<string>('');
  const [externalAuditNote, setExternalAuditNote] = useState<string>('');
  const [externalFormErrors, setExternalFormErrors] = useState<{ [key: string]: string }>({});

  // Manual / Own Delivery form state
  const [driverName, setDriverName] = useState<string>('');
  const [handoffRef, setHandoffRef] = useState<string>('');
  const [manualAuditNote, setManualAuditNote] = useState<string>('');
  const [manualFormErrors, setManualFormErrors] = useState<{ [key: string]: string }>({});

  // EasyParcel rate fetching state
  const [isLoadingRates, setIsLoadingRates] = useState(false);
  const [ratesError, setRatesError] = useState<string | null>(null);
  const [rates, setRates] = useState<EasyParcelRateOption[]>([]);
  const [activeQuote, setActiveQuote] = useState<EasyParcelQuote | null>(null);
  const [selectedServiceId, setSelectedServiceId] = useState<string | null>(null);
  const [isBooking, setIsBooking] = useState(false);
  const [quoteTimestamp, setQuoteTimestamp] = useState<number | null>(null);
  const [isQuoteExpired, setIsQuoteExpired] = useState(false);
  const [isBookedSuccess, setIsBookedSuccess] = useState(false);

  // Reset states when opening
  useEffect(() => {
    if (isOpen && parcel) {
      setSelectedMethod(null);
      setStep('choose_method');
      setExternalCourierId('ninjavan');
      setCustomCourierName('');
      setExternalTrackingNumber('');
      setExternalTrackingUrl('');
      setExternalAuditNote('');
      setExternalFormErrors({});
      setDriverName('');
      setHandoffRef('');
      setManualAuditNote('');
      setManualFormErrors({});
      setIsBookedSuccess(false);
      setIsBooking(false);
      setSelectedServiceId(null);
      setActiveQuote(null);
    }
  }, [isOpen, parcel?.id]);

  // Fetch real EasyParcel rates through the existing protected Laravel API.
  const fetchEasyParcelRates = async () => {
    if (!easyParcelConnected) {
      setRatesError('EasyParcel is not connected. Choose External Courier or Manual / Own Delivery.');
      return;
    }
    setIsLoadingRates(true);
    setRatesError(null);
    setSelectedServiceId(null);
    setIsQuoteExpired(false);

    try {
      const quote = await onQuoteEasyParcel(parcel, packageSpecFromDraft(packageDraftFromParcel(parcel)));
      setActiveQuote(quote);
      setRates(quote.services.map(service => ({
        serviceId: service.serviceCode,
        serviceName: service.serviceName,
        courierName: service.courierName,
        courierCode: service.courierCode,
        courierLogo: '',
        priceRM: service.amount,
        estDays: service.deliveryDuration ?? 'Provider estimate unavailable',
        pickupType: service.isDropoff ? 'Drop-off Point' : 'Doorstep Pickup',
        supportsCOD: service.supportsCod,
      })));
      setSelectedServiceId(null);
      setQuoteTimestamp(quote.expiresAt ? new Date(quote.expiresAt).getTime() : Date.now() + (15 * 60 * 1000));
    } catch (error) {
      setActiveQuote(null);
      setRates([]);
      setRatesError(error instanceof Error ? error.message : 'Failed to retrieve EasyParcel rates.');
    } finally {
      setIsLoadingRates(false);
    }
  };

  // Handle quote expiration timer
  useEffect(() => {
    if (!quoteTimestamp) return;
    const timer = setTimeout(() => {
      setIsQuoteExpired(true);
    }, Math.max(0, quoteTimestamp - Date.now()));
    return () => clearTimeout(timer);
  }, [quoteTimestamp]);

  // Handle selecting EasyParcel method
  const handleProceedToEasyParcel = () => {
    setSelectedMethod('easyparcel');
    setStep('easyparcel_rates');
    fetchEasyParcelRates();
  };

  // Handle Confirm EasyParcel Booking
  const handleConfirmEasyParcel = async () => {
    if (!selectedServiceId || !activeQuote || isBooking || isQuoteExpired) return;
    const chosen = rates.find(r => r.serviceId === selectedServiceId);
    if (!chosen) return;

    setIsBooking(true);
    try {
      await onConfirmEasyParcelBooking(parcel, activeQuote, chosen.serviceId);
      setIsBookedSuccess(true);
      setTimeout(() => {
        onClose();
      }, 1100);
    } catch {
      setRatesError('Failed to confirm EasyParcel booking. Please try again.');
    } finally {
      setIsBooking(false);
    }
  };

  // Validate and Submit External Courier
  const handleRecordExternalDispatch = async () => {
    const errors: { [key: string]: string } = {};

    if (externalCourierId === 'other' && !customCourierName.trim()) {
      errors.customCourierName = 'Custom courier name is required';
    }
    if (!externalTrackingNumber.trim()) {
      errors.externalTrackingNumber = 'Tracking / AWB Number is required';
    }
    if (!externalAuditNote.trim()) {
      errors.externalAuditNote = 'Dispatch Audit Note is required';
    }

    if (Object.keys(errors).length > 0) {
      setExternalFormErrors(errors);
      return;
    }

    const selectedCourierObj = EXTERNAL_COURIER_OPTIONS.find(c => c.id === externalCourierId);
    const courierDisplayName = externalCourierId === 'other'
      ? customCourierName.trim()
      : (selectedCourierObj?.name || 'External Courier');

    setIsBooking(true);
    try {
      await onConfirmExternalCourier(parcel.id, {
        courierCode: externalCourierId, courierName: courierDisplayName,
        trackingNumber: externalTrackingNumber.trim(), trackingUrl: externalTrackingUrl.trim() || undefined,
        auditNote: externalAuditNote.trim()
      });
      setIsBookedSuccess(true);
      setTimeout(onClose, 900);
    } catch (error) {
      setExternalFormErrors({ submit: error instanceof Error ? error.message : 'External courier dispatch could not be recorded.' });
    } finally { setIsBooking(false); }
  };

  // Validate and Submit Manual Delivery
  const handleRecordManualDispatch = async () => {
    const errors: { [key: string]: string } = {};

    if (!driverName.trim()) {
      errors.driverName = 'Delivery Partner / Driver Name is required';
    }
    if (!manualAuditNote.trim()) {
      errors.manualAuditNote = 'Dispatch Audit Note is required';
    }

    if (Object.keys(errors).length > 0) {
      setManualFormErrors(errors);
      return;
    }

    setIsBooking(true);
    try {
      await onConfirmManualDelivery(parcel.id, { driverName: driverName.trim(), handoffReference: handoffRef.trim() || undefined, auditNote: manualAuditNote.trim() });
      setIsBookedSuccess(true);
      setTimeout(onClose, 900);
    } catch (error) {
      setManualFormErrors({ submit: error instanceof Error ? error.message : 'Manual delivery could not be recorded.' });
    } finally { setIsBooking(false); }
  };

  const selectedRate = rates.find(r => r.serviceId === selectedServiceId);

  return (
    <div id="choose-fulfillment-method-overlay" className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4 overflow-y-auto backdrop-blur-sm animate-in fade-in duration-200">
      <div className="bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-2xl max-w-2xl w-full flex flex-col shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">

        {/* Header */}
        <div className="flex items-center justify-between px-6 py-4 border-b border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-900/80">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center text-blue-600 dark:text-blue-400 font-black text-sm">
              <Truck className="w-5 h-5" />
            </div>
            <div>
              <div className="flex items-center gap-2">
                <h3 className="text-base font-bold text-neutral-900 dark:text-neutral-100">
                  Choose Fulfilment Method
                </h3>
                <span className="px-2 py-0.5 text-[10px] font-bold rounded-full bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800">
                  Order #{parcel.orderNumber}
                </span>
              </div>
              <p className="text-xs text-neutral-500 dark:text-neutral-400">
                Select how this order will be dispatched. Provider capabilities are shown truthfully.
              </p>
            </div>
          </div>

          <button
            onClick={onClose}
            disabled={isBooking}
            className="p-1.5 rounded-lg text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors cursor-pointer"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Modal Body */}
        <div className="p-6 space-y-5 overflow-y-auto max-h-[75vh]">

          {/* Success Flash Banner */}
          {isBookedSuccess && (
            <div className="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800 flex items-center gap-3 animate-in fade-in">
              <CheckCircle2 className="w-6 h-6 text-emerald-600 shrink-0" />
              <div>
                <h4 className="text-xs font-bold text-emerald-900 dark:text-emerald-200">
                  Fulfilment Recorded Successfully!
                </h4>
                <p className="text-[11px] text-emerald-700 dark:text-emerald-300">
                  Shipment dispatch status and tracking reference updated. Returning to queue...
                </p>
              </div>
            </div>
          )}

          {/* Package Summary Card */}
          <div className="p-4 rounded-xl border border-neutral-200 dark:border-neutral-800 bg-neutral-50/70 dark:bg-neutral-800/40 space-y-3">
            <div className="flex items-center justify-between">
              <span className="text-xs font-bold text-neutral-900 dark:text-neutral-100 uppercase tracking-wider flex items-center gap-1.5">
                <Package className="w-3.5 h-3.5 text-blue-600" />
                Package Summary
              </span>
              <span className="text-[11px] text-neutral-500 dark:text-neutral-400 font-mono">
                Order Ref: {parcel.orderNumber}
              </span>
            </div>

            <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
              <div className="p-2.5 rounded-lg bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800">
                <span className="text-[10px] text-neutral-400 block font-medium">Destination Postcode</span>
                <span className="font-bold font-mono text-neutral-900 dark:text-neutral-100">
                  {parcel.destinationPostcode ?? 'Not available'}
                </span>
                <div className="text-[10px] text-neutral-500 truncate">Authorized fulfilment route</div>
              </div>

              <div className="p-2.5 rounded-lg bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800">
                <span className="text-[10px] text-neutral-400 block font-medium">Actual Weight</span>
                <span className="font-bold font-mono text-neutral-900 dark:text-neutral-100">
                  {parcel.totalWeightKg} kg
                </span>
                <div className="text-[10px] text-neutral-400 font-mono">Vol: {parcel.volumetricWeightKg} kg</div>
              </div>

              <div className="p-2.5 rounded-lg bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800">
                <span className="text-[10px] text-neutral-400 block font-medium">Dimensions (L×W×H)</span>
                <span className="font-bold font-mono text-neutral-900 dark:text-neutral-100">
                  {parcel.dimensionsCm?.length} × {parcel.dimensionsCm?.width} × {parcel.dimensionsCm?.height} cm
                </span>
                <div className="text-[10px] text-neutral-500">Standard Parcel</div>
              </div>

              <div className="p-2.5 rounded-lg bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800">
                <span className="text-[10px] text-neutral-400 block font-medium">Payment Mode</span>
                <span className={`font-bold text-xs ${
                  parcel.paymentMode === 'cod' ? 'text-rose-600' : 'text-emerald-600'
                }`}>
                  {parcel.paymentMode === 'cod' ? `COD (RM ${parcel.codAmount?.toFixed(2)})` : 'Prepaid'}
                </span>
                <div className="text-[10px] text-neutral-500">{parcel.items?.length || 1} item(s)</div>
              </div>
            </div>

            {/* Recipient Details */}
            <div className="pt-2 text-xs text-neutral-600 dark:text-neutral-400 flex items-start gap-2">
              <MapPin className="w-3.5 h-3.5 text-neutral-400 shrink-0 mt-0.5" />
              <span>
                <strong>{parcel.customerName}</strong> • Recipient details restricted to the authorized order workflow
              </span>
            </div>
          </div>

          {/* STEP 1: METHOD SELECTION */}
          {step === 'choose_method' && (
            <div className="space-y-4">
              <label className="text-xs font-bold text-neutral-900 dark:text-neutral-100 uppercase tracking-wider block">
                Select Dispatch Method
              </label>

              <div className="space-y-3">
                {/* Method A: Book with EasyParcel */}
                <div
                  onClick={() => setSelectedMethod('easyparcel')}
                  className={`p-4 rounded-xl border transition-all cursor-pointer ${
                    selectedMethod === 'easyparcel'
                      ? 'border-pink-500 bg-pink-50/40 dark:bg-pink-900/20 ring-2 ring-pink-500/20 shadow-xs'
                      : 'border-neutral-200 dark:border-neutral-800 hover:border-neutral-300 dark:hover:border-neutral-700 bg-white dark:bg-neutral-900'
                  }`}
                >
                  <div className="flex items-start justify-between gap-3">
                    <div className="flex items-start gap-3">
                      <div className={`w-5 h-5 rounded-full flex items-center justify-center border mt-0.5 shrink-0 transition-all ${
                        selectedMethod === 'easyparcel'
                          ? 'border-pink-600 bg-pink-600 text-white'
                          : 'border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800'
                      }`}>
                        {selectedMethod === 'easyparcel' && <Check className="w-3 h-3 stroke-[3]" />}
                      </div>

                      <div className="space-y-1">
                        <div className="flex items-center gap-2 flex-wrap">
                          <span className="text-xs font-bold text-neutral-900 dark:text-neutral-100">
                            Book with EasyParcel
                          </span>
                          <span className="px-2 py-0.5 text-[10px] font-bold rounded-full bg-pink-100 dark:bg-pink-900/40 text-pink-700 dark:text-pink-300 border border-pink-200 dark:border-pink-800">
                            {easyParcelConnected ? 'Connected provider' : 'Provider not connected'}
                          </span>
                        </div>
                        <p className="text-xs text-neutral-600 dark:text-neutral-300">
                          Compare eligible services and generate an official EasyParcel airway bill.
                        </p>
                        <p className="text-[11px] text-neutral-400 italic pt-0.5">
                          Courier service selection, AWB creation, and tracking updates are managed through EasyParcel.
                        </p>
                      </div>
                    </div>

                    <div className="shrink-0 text-pink-600 font-bold text-xs flex items-center gap-0.5">
                      <span>Rate & Book</span>
                      <ChevronRight className="w-3.5 h-3.5" />
                    </div>
                  </div>

                  {/* If EasyParcel Selected, provide immediate action button */}
                  {selectedMethod === 'easyparcel' && (
                    <div className="mt-3 pt-3 border-t border-pink-200 dark:border-pink-800/60 flex items-center justify-between animate-in fade-in">
                      <span className="text-[11px] text-pink-700 dark:text-pink-300 font-medium">
                        {easyParcelConnected ? 'Ready to fetch real-time carrier quotes & SLAs' : 'Connect EasyParcel first, or choose a team-managed method'}
                      </span>
                      <button
                        type="button"
                          onClick={(e) => {
                            e.stopPropagation();
                            if (easyParcelConnected) handleProceedToEasyParcel();
                          }}
                          disabled={!easyParcelConnected}
                        className="px-4 py-1.5 text-xs font-bold text-white bg-pink-600 hover:bg-pink-700 rounded-lg transition-colors cursor-pointer flex items-center gap-1.5 shadow-sm"
                      >
                        <Zap className="w-3.5 h-3.5" />
                        Compare Rates & Continue
                      </button>
                    </div>
                  )}
                </div>

                {/* Method B: Record External Courier */}
                <div
                  onClick={() => setSelectedMethod('external_courier')}
                  className={`p-4 rounded-xl border transition-all cursor-pointer ${
                    selectedMethod === 'external_courier'
                      ? 'border-blue-500 bg-blue-50/40 dark:bg-blue-900/20 ring-2 ring-blue-500/20 shadow-xs'
                      : 'border-neutral-200 dark:border-neutral-800 hover:border-neutral-300 dark:hover:border-neutral-700 bg-white dark:bg-neutral-900'
                  }`}
                >
                  <div className="flex items-start justify-between gap-3">
                    <div className="flex items-start gap-3">
                      <div className={`w-5 h-5 rounded-full flex items-center justify-center border mt-0.5 shrink-0 transition-all ${
                        selectedMethod === 'external_courier'
                          ? 'border-blue-600 bg-blue-600 text-white'
                          : 'border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800'
                      }`}>
                        {selectedMethod === 'external_courier' && <Check className="w-3 h-3 stroke-[3]" />}
                      </div>

                      <div className="space-y-1">
                        <div className="flex items-center gap-2 flex-wrap">
                          <span className="text-xs font-bold text-neutral-900 dark:text-neutral-100">
                            Record External Courier
                          </span>
                          <span className="px-2 py-0.5 text-[10px] font-bold rounded-full bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800">
                            Manual tracking record
                          </span>
                        </div>
                        <p className="text-xs text-neutral-600 dark:text-neutral-300">
                          Use an AWB already booked in a courier’s own portal and record it in JW Studio.
                        </p>
                        <p className="text-[11px] text-neutral-400 italic pt-0.5">
                          JW Studio will not generate an AWB, quote rates, fetch tracking, or receive webhooks for this courier.
                        </p>
                      </div>
                    </div>
                  </div>

                  {/* Form fields for External Courier */}
                  {selectedMethod === 'external_courier' && (
                    <div
                      onClick={(e) => e.stopPropagation()}
                      className="mt-4 pt-4 border-t border-blue-200 dark:border-blue-800/60 space-y-3 text-xs animate-in fade-in"
                    >
                      <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        {/* Courier Dropdown */}
                        <div>
                          <label className="block font-semibold text-neutral-800 dark:text-neutral-200 mb-1">
                            Courier <span className="text-rose-500">*</span>
                          </label>
                          <select
                            value={externalCourierId}
                            onChange={(e) => setExternalCourierId(e.target.value)}
                            className="w-full px-3 py-2 text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-blue-500"
                          >
                            {EXTERNAL_COURIER_OPTIONS.map((c) => (
                              <option key={c.id} value={c.id}>
                                {c.name}
                              </option>
                            ))}
                          </select>
                        </div>

                        {/* Custom courier name if other */}
                        {externalCourierId === 'other' && (
                          <div>
                            <label className="block font-semibold text-neutral-800 dark:text-neutral-200 mb-1">
                              Custom Courier Name <span className="text-rose-500">*</span>
                            </label>
                            <input
                              type="text"
                              value={customCourierName}
                              onChange={(e) => {
                                setCustomCourierName(e.target.value);
                                setExternalFormErrors(prev => ({ ...prev, customCourierName: '' }));
                              }}
                              placeholder="e.g. Line Clear Express, Aramex, Lalamove"
                              className={`w-full px-3 py-2 text-xs rounded-xl border bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-blue-500 ${
                                externalFormErrors.customCourierName ? 'border-rose-500' : 'border-neutral-200 dark:border-neutral-700'
                              }`}
                            />
                            {externalFormErrors.customCourierName && (
                              <span className="text-[10px] text-rose-500 mt-0.5 block">{externalFormErrors.customCourierName}</span>
                            )}
                          </div>
                        )}

                        {/* Tracking / AWB Number */}
                        <div className={externalCourierId !== 'other' ? 'sm:col-span-1' : 'sm:col-span-2'}>
                          <label className="block font-semibold text-neutral-800 dark:text-neutral-200 mb-1">
                            Tracking / AWB Number <span className="text-rose-500">*</span>
                          </label>
                          <input
                            type="text"
                            value={externalTrackingNumber}
                            onChange={(e) => {
                              setExternalTrackingNumber(e.target.value);
                              setExternalFormErrors(prev => ({ ...prev, externalTrackingNumber: '' }));
                            }}
                            placeholder="e.g. NVMY8819203847 or PL892019283MY"
                            className={`w-full px-3 py-2 text-xs font-mono rounded-xl border bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-blue-500 ${
                              externalFormErrors.externalTrackingNumber ? 'border-rose-500' : 'border-neutral-200 dark:border-neutral-700'
                            }`}
                          />
                          {externalFormErrors.externalTrackingNumber && (
                            <span className="text-[10px] text-rose-500 mt-0.5 block">{externalFormErrors.externalTrackingNumber}</span>
                          )}
                        </div>
                      </div>

                      {/* Optional Tracking URL */}
                      <div>
                        <label className="block font-semibold text-neutral-800 dark:text-neutral-200 mb-1">
                          Tracking URL <span className="text-neutral-400 font-normal">(Optional)</span>
                        </label>
                        <input
                          type="url"
                          value={externalTrackingUrl}
                          onChange={(e) => setExternalTrackingUrl(e.target.value)}
                          placeholder="https://track.courier.com/..."
                          className="w-full px-3 py-2 text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-blue-500"
                        />
                      </div>

                      {/* Required Dispatch Audit Note */}
                      <div>
                        <label className="block font-semibold text-neutral-800 dark:text-neutral-200 mb-1">
                          Dispatch Audit Note <span className="text-rose-500">*</span>
                        </label>
                        <textarea
                          rows={2}
                          value={externalAuditNote}
                          onChange={(e) => {
                            setExternalAuditNote(e.target.value);
                            setExternalFormErrors(prev => ({ ...prev, externalAuditNote: '' }));
                          }}
                          placeholder="State who booked/handed off the consignment and relevant batch details..."
                          className={`w-full px-3 py-2 text-xs rounded-xl border bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-blue-500 ${
                            externalFormErrors.externalAuditNote ? 'border-rose-500' : 'border-neutral-200 dark:border-neutral-700'
                          }`}
                        />
                        {externalFormErrors.externalAuditNote && (
                          <span className="text-[10px] text-rose-500 mt-0.5 block">{externalFormErrors.externalAuditNote}</span>
                        )}
                      </div>

                      {/* Submit button for external courier */}
                      <div className="pt-2 flex justify-end">
                        {externalFormErrors.submit && <span className="mr-auto self-center text-[10px] text-rose-500">{externalFormErrors.submit}</span>}
                        <button
                          type="button"
                          onClick={() => void handleRecordExternalDispatch()}
                          disabled={isBooking}
                          className="px-5 py-2 text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 rounded-xl transition-colors cursor-pointer shadow-sm flex items-center gap-1.5"
                        >
                          <FileText className="w-3.5 h-3.5" />
                          Record External Dispatch
                        </button>
                      </div>
                    </div>
                  )}
                </div>

                {/* Method C: Manual / Own Delivery */}
                <div
                  onClick={() => setSelectedMethod('manual_delivery')}
                  className={`p-4 rounded-xl border transition-all cursor-pointer ${
                    selectedMethod === 'manual_delivery'
                      ? 'border-indigo-500 bg-indigo-50/40 dark:bg-indigo-900/20 ring-2 ring-indigo-500/20 shadow-xs'
                      : 'border-neutral-200 dark:border-neutral-800 hover:border-neutral-300 dark:hover:border-neutral-700 bg-white dark:bg-neutral-900'
                  }`}
                >
                  <div className="flex items-start justify-between gap-3">
                    <div className="flex items-start gap-3">
                      <div className={`w-5 h-5 rounded-full flex items-center justify-center border mt-0.5 shrink-0 transition-all ${
                        selectedMethod === 'manual_delivery'
                          ? 'border-indigo-600 bg-indigo-600 text-white'
                          : 'border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800'
                      }`}>
                        {selectedMethod === 'manual_delivery' && <Check className="w-3 h-3 stroke-[3]" />}
                      </div>

                      <div className="space-y-1">
                        <div className="flex items-center gap-2 flex-wrap">
                          <span className="text-xs font-bold text-neutral-900 dark:text-neutral-100">
                            Manual / Own Delivery
                          </span>
                          <span className="px-2 py-0.5 text-[10px] font-bold rounded-full bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800">
                            Team-managed delivery
                          </span>
                        </div>
                        <p className="text-xs text-neutral-600 dark:text-neutral-300">
                          Record self-delivery, an internal driver, store runner, or hand-delivered orders.
                        </p>
                        <p className="text-[11px] text-neutral-400 italic pt-0.5">
                          No courier AWB is created. Delivery updates are maintained by your team.
                        </p>
                      </div>
                    </div>
                  </div>

                  {/* Form fields for Manual Delivery */}
                  {selectedMethod === 'manual_delivery' && (
                    <div
                      onClick={(e) => e.stopPropagation()}
                      className="mt-4 pt-4 border-t border-indigo-200 dark:border-indigo-800/60 space-y-3 text-xs animate-in fade-in"
                    >
                      <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        {/* Delivery Partner / Driver Name */}
                        <div>
                          <label className="block font-semibold text-neutral-800 dark:text-neutral-200 mb-1">
                            Delivery Partner / Driver Name <span className="text-rose-500">*</span>
                          </label>
                          <input
                            type="text"
                            value={driverName}
                            onChange={(e) => {
                              setDriverName(e.target.value);
                              setManualFormErrors(prev => ({ ...prev, driverName: '' }));
                            }}
                            placeholder="e.g. Mohd Azman (Internal Fleet) or Store Runner"
                            className={`w-full px-3 py-2 text-xs rounded-xl border bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-indigo-500 ${
                              manualFormErrors.driverName ? 'border-rose-500' : 'border-neutral-200 dark:border-neutral-700'
                            }`}
                          />
                          {manualFormErrors.driverName && (
                            <span className="text-[10px] text-rose-500 mt-0.5 block">{manualFormErrors.driverName}</span>
                          )}
                        </div>

                        {/* Optional Hand-off Reference */}
                        <div>
                          <label className="block font-semibold text-neutral-800 dark:text-neutral-200 mb-1">
                            Hand-off Reference <span className="text-neutral-400 font-normal">(Optional)</span>
                          </label>
                          <input
                            type="text"
                            value={handoffRef}
                            onChange={(e) => setHandoffRef(e.target.value)}
                            placeholder="e.g. RUNNER-REF-101 or Van WXY 8812"
                            className="w-full px-3 py-2 text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-indigo-500"
                          />
                        </div>
                      </div>

                      {/* Required Dispatch Audit Note */}
                      <div>
                        <label className="block font-semibold text-neutral-800 dark:text-neutral-200 mb-1">
                          Dispatch Audit Note <span className="text-rose-500">*</span>
                        </label>
                        <textarea
                          rows={2}
                          value={manualAuditNote}
                          onChange={(e) => {
                            setManualAuditNote(e.target.value);
                            setManualFormErrors(prev => ({ ...prev, manualAuditNote: '' }));
                          }}
                          placeholder="Note dispatch time, handoff checkpoint, driver contact, or delivery instructions..."
                          className={`w-full px-3 py-2 text-xs rounded-xl border bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-indigo-500 ${
                            manualFormErrors.manualAuditNote ? 'border-rose-500' : 'border-neutral-200 dark:border-neutral-700'
                          }`}
                        />
                        {manualFormErrors.manualAuditNote && (
                          <span className="text-[10px] text-rose-500 mt-0.5 block">{manualFormErrors.manualAuditNote}</span>
                        )}
                      </div>

                      {/* Submit button for manual delivery */}
                      <div className="pt-2 flex justify-end">
                        {manualFormErrors.submit && <span className="mr-auto self-center text-[10px] text-rose-500">{manualFormErrors.submit}</span>}
                        <button
                          type="button"
                          onClick={() => void handleRecordManualDispatch()}
                          disabled={isBooking}
                          className="px-5 py-2 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl transition-colors cursor-pointer shadow-sm flex items-center gap-1.5"
                        >
                          <Truck className="w-3.5 h-3.5" />
                          Record Manual Dispatch
                        </button>
                      </div>
                    </div>
                  )}
                </div>
              </div>

              {/* Bottom footer button for Step 1 */}
              <div className="pt-2 flex items-center justify-between">
                <span className="text-[11px] text-neutral-400 flex items-center gap-1">
                  <ShieldCheck className="w-3.5 h-3.5 text-blue-600" />
                  Truthful Fulfilment Engine • No fictitious API claims
                </span>
                <button
                  type="button"
                  onClick={onClose}
                  className="px-4 py-2 text-xs font-medium text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800 rounded-xl transition-colors cursor-pointer"
                >
                  Cancel
                </button>
              </div>
            </div>
          )}

          {/* STEP 2: EASYPARCEL RATES SELECTION VIEW */}
          {step === 'easyparcel_rates' && (
            <div className="space-y-4 animate-in fade-in">
              <div className="flex items-center justify-between">
                <button
                  type="button"
                  onClick={() => setStep('choose_method')}
                  className="flex items-center gap-1 text-xs font-semibold text-neutral-600 dark:text-neutral-300 hover:text-neutral-900 dark:hover:text-white transition-colors cursor-pointer"
                >
                  <ChevronLeft className="w-4 h-4" />
                  Back to Method Selection
                </button>

                {!isLoadingRates && (
                  <button
                    type="button"
                    onClick={() => void fetchEasyParcelRates()}
                    className="flex items-center gap-1 text-[11px] text-pink-600 hover:text-pink-700 font-semibold cursor-pointer"
                  >
                    <RefreshCw className="w-3 h-3" />
                    Re-fetch Quotes
                  </button>
                )}
              </div>

              {/* Rate Expiration Banner */}
              {isQuoteExpired && (
                <div className="p-3.5 rounded-xl bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800 flex items-center justify-between gap-3">
                  <div className="flex items-center gap-2 text-xs text-amber-800 dark:text-amber-300">
                    <AlertTriangle className="w-4 h-4 text-amber-600 shrink-0" />
                    <span>Quote expired (15-minute rate guarantee). Refresh rates to continue.</span>
                  </div>
                  <button
                    type="button"
                    onClick={fetchEasyParcelRates}
                    className="flex items-center gap-1 px-3 py-1.5 text-xs font-semibold text-amber-800 dark:text-amber-200 bg-amber-100 dark:bg-amber-900/50 hover:bg-amber-200 rounded-lg transition-colors cursor-pointer shrink-0"
                  >
                    <RefreshCw className="w-3 h-3" />
                    Refresh Rates
                  </button>
                </div>
              )}

              {/* Service List Title */}
              <label className="text-xs font-bold text-neutral-900 dark:text-neutral-100 uppercase tracking-wider flex items-center gap-1.5">
                <Truck className="w-3.5 h-3.5 text-pink-600" />
                EasyParcel Available Courier Services ({rates.length})
              </label>

              {/* Loading state */}
              {isLoadingRates && (
                <div className="py-10 text-center space-y-3 bg-neutral-50 dark:bg-neutral-800/30 rounded-xl border border-neutral-200 dark:border-neutral-800">
                  <RefreshCw className="w-6 h-6 animate-spin text-pink-600 mx-auto" />
                  <div className="text-xs font-semibold text-neutral-800 dark:text-neutral-200">
                    Fetching EasyParcel rates…
                  </div>
                  <p className="text-[11px] text-neutral-400">
                    Querying live courier SLAs and pricing for postcode {parcel.destinationPostcode ?? 'the authorized destination'}
                  </p>
                </div>
              )}

              {/* Error state */}
              {!isLoadingRates && ratesError && (
                <div className="p-5 text-center space-y-3 bg-rose-50 dark:bg-rose-950/20 rounded-xl border border-rose-200 dark:border-rose-800">
                  <AlertCircle className="w-6 h-6 text-rose-600 mx-auto" />
                  <div className="text-xs font-semibold text-rose-900 dark:text-rose-200">
                    {ratesError}
                  </div>
                  <button
                    type="button"
                    onClick={() => void fetchEasyParcelRates()}
                    className="px-3 py-1.5 text-xs font-bold text-white bg-rose-600 hover:bg-rose-700 rounded-lg transition-colors cursor-pointer"
                  >
                    Retry Quote
                  </button>
                </div>
              )}

              {/* Empty state */}
              {!isLoadingRates && !ratesError && rates.length === 0 && (
                <div className="p-6 text-center space-y-2 bg-neutral-50 dark:bg-neutral-800/30 rounded-xl border border-neutral-200 dark:border-neutral-800 text-neutral-500">
                  <p className="text-xs">No courier services available for the specified weight or destination postcode.</p>
                  <button
                    type="button"
                    onClick={() => void fetchEasyParcelRates()}
                    className="text-xs font-semibold text-pink-600 hover:underline cursor-pointer"
                  >
                    Try refreshing quotes
                  </button>
                </div>
              )}

              {/* List of returned EasyParcel services */}
              {!isLoadingRates && !ratesError && rates.length > 0 && (
                <div className="space-y-2.5">
                  {rates.map((rate) => {
                    const isSelected = selectedServiceId === rate.serviceId;
                    const isCodIncompatible = parcel.paymentMode === 'cod' && !rate.supportsCOD;

                    return (
                      <div
                        key={rate.serviceId}
                        onClick={() => {
                          if (!isCodIncompatible && !isQuoteExpired) {
                            setSelectedServiceId(rate.serviceId);
                          }
                        }}
                        className={`p-3.5 rounded-xl border transition-all flex items-center justify-between gap-3 cursor-pointer ${
                          isSelected
                            ? 'border-pink-500 bg-pink-50/40 dark:bg-pink-900/20 ring-2 ring-pink-500/20 shadow-xs'
                            : isCodIncompatible
                            ? 'border-neutral-200 dark:border-neutral-800 opacity-50 cursor-not-allowed bg-neutral-50 dark:bg-neutral-800/20'
                            : 'border-neutral-200 dark:border-neutral-800 hover:border-neutral-300 dark:hover:border-neutral-700 bg-white dark:bg-neutral-900'
                        }`}
                      >
                        <div className="flex items-center gap-3">
                          {/* Radio Indicator */}
                          <div className={`w-5 h-5 rounded-full flex items-center justify-center border transition-all ${
                            isSelected
                              ? 'border-pink-600 bg-pink-600 text-white'
                              : 'border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800'
                          }`}>
                            {isSelected && <Check className="w-3 h-3 stroke-[3]" />}
                          </div>

                          {/* Courier Logo & Info */}
                          <div className="w-9 h-9 rounded-lg bg-neutral-100 dark:bg-neutral-800 flex items-center justify-center overflow-hidden font-bold text-xs">
                            {rate.courierLogo ? (
                              <img src={rate.courierLogo} alt={rate.courierName} className="w-full h-full object-cover" />
                            ) : (
                              rate.courierName.substring(0, 3).toUpperCase()
                            )}
                          </div>

                          <div>
                            <div className="flex items-center gap-2">
                              <span className="text-xs font-bold text-neutral-900 dark:text-neutral-100">
                                {rate.serviceName}
                              </span>
                              <span className="px-1.5 py-0.2 text-[9px] font-semibold rounded bg-neutral-100 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-300">
                                {rate.pickupType}
                              </span>
                            </div>

                            <div className="flex items-center gap-2 mt-0.5 text-[11px] text-neutral-500 dark:text-neutral-400">
                              <span className="flex items-center gap-1">
                                <Clock className="w-3 h-3 text-neutral-400" />
                                {rate.estDays}
                              </span>
                              <span>•</span>
                              <span className={rate.supportsCOD ? 'text-emerald-600 font-medium' : 'text-neutral-400'}>
                                {rate.supportsCOD ? 'COD Supported' : 'Prepaid Only'}
                              </span>
                              {rate.highlights && (
                                <>
                                  <span>•</span>
                                  <span className="text-pink-600 dark:text-pink-400 font-medium">{rate.highlights}</span>
                                </>
                              )}
                            </div>
                          </div>
                        </div>

                        {/* Price tag */}
                        <div className="text-right shrink-0">
                          <div className="font-mono font-black text-sm text-neutral-900 dark:text-neutral-100">
                            RM {rate.priceRM.toFixed(2)}
                          </div>
                          <span className="text-[10px] text-neutral-400">Excl. SST</span>
                        </div>
                      </div>
                    );
                  })}
                </div>
              )}

              {/* Security & Summary */}
              <div className="flex items-center justify-between p-3 rounded-xl bg-neutral-50 dark:bg-neutral-800/40 border border-neutral-200 dark:border-neutral-800 text-xs">
                <span className="text-neutral-500 dark:text-neutral-400">
                  {selectedRate ? (
                    <span>Selected: <strong>{selectedRate.serviceName}</strong> (RM {selectedRate.priceRM.toFixed(2)})</span>
                  ) : (
                    <span className="text-amber-600 dark:text-amber-400">Please select one courier service to continue</span>
                  )}
                </span>
                <span className="text-[11px] text-neutral-400 flex items-center gap-1">
                  <ShieldCheck className="w-3.5 h-3.5 text-emerald-600" />
                  EasyParcel Open API
                </span>
              </div>

              {/* Action buttons */}
              <div className="pt-2 flex items-center justify-end gap-2.5">
                <button
                  type="button"
                  onClick={() => setStep('choose_method')}
                  disabled={isBooking}
                  className="px-4 py-2 text-xs font-medium text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800 rounded-xl transition-colors cursor-pointer"
                >
                  Back
                </button>

                <button
                  type="button"
                  onClick={handleConfirmEasyParcel}
                  disabled={!selectedServiceId || isBooking || isQuoteExpired}
                  className={`flex items-center gap-2 px-5 py-2 text-xs font-bold text-white rounded-xl transition-all shadow-sm ${
                    !selectedServiceId || isBooking || isQuoteExpired
                      ? 'bg-neutral-300 dark:bg-neutral-700 cursor-not-allowed text-neutral-500'
                      : 'bg-pink-600 hover:bg-pink-700 cursor-pointer'
                  }`}
                >
                  {isBooking ? (
                    <>
                      <RefreshCw className="w-3.5 h-3.5 animate-spin" />
                      <span>Booking & Generating AWB...</span>
                    </>
                  ) : (
                    <>
                      <Zap className="w-3.5 h-3.5" />
                      <span>Confirm Booking & Generate AWB</span>
                    </>
                  )}
                </button>
              </div>
            </div>
          )}

        </div>
      </div>
    </div>
  );
};
