import React, { useState } from 'react';
import {
  X,
  MapPin,
  CheckCircle2,
  AlertTriangle,
  AlertCircle,
  FileText,
  User,
  Phone,
  Building,
  ArrowRight,
  ShieldAlert,
  Loader2,
  Save,
  Check,
  Info
} from 'lucide-react';
import { Order, CustomerAddress } from '../../types';

interface AmendDeliveryDetailsModalProps {
  isOpen: boolean;
  order: Order | null;
  hasActiveShipment?: boolean;
  onClose: () => void;
  onSave: (
    orderId: string,
    updatedAddress: CustomerAddress,
    metadata: {
      reason: string;
      internalNote: string;
      saveToAddressBook: boolean;
    }
  ) => Promise<void> | void;
}

const MALAYSIA_STATES: { value: string; label: string }[] = [
  { value: 'Selangor', label: 'Selangor' },
  { value: 'Kuala Lumpur', label: 'Kuala Lumpur' },
  { value: 'Johor', label: 'Johor' },
  { value: 'Penang', label: 'Penang' },
  { value: 'Perak', label: 'Perak' },
  { value: 'Melaka', label: 'Melaka' },
  { value: 'Negeri Sembilan', label: 'Negeri Sembilan' },
  { value: 'Pahang', label: 'Pahang' },
  { value: 'Kedah', label: 'Kedah' },
  { value: 'Kelantan', label: 'Kelantan' },
  { value: 'Terengganu', label: 'Terengganu' },
  { value: 'Perlis', label: 'Perlis' },
  { value: 'Sabah', label: 'Sabah (East MY)' },
  { value: 'Sarawak', label: 'Sarawak (East MY)' },
  { value: 'Labuan', label: 'Labuan' },
  { value: 'Putrajaya', label: 'Putrajaya' },
];

const AMENDMENT_REASONS = [
  'Customer requested address change',
  'Typo in street name or unit number',
  'Postcode / City mismatch correction',
  'Building / Floor / Unit number missing',
  'Mobile contact number update',
  'Recipient name correction',
  'Customer relocated / temporary destination',
  'Other operational delivery amendment',
];

export const AmendDeliveryDetailsModal: React.FC<AmendDeliveryDetailsModalProps> = ({
  isOpen,
  order,
  hasActiveShipment = false,
  onClose,
  onSave,
}) => {
  if (!isOpen || !order) return null;

  const originalAddress = order.shippingAddress;

  // Form State
  const [firstName, setFirstName] = useState(originalAddress?.firstName || '');
  const [lastName, setLastName] = useState(originalAddress?.lastName || '');
  const [phone, setPhone] = useState(originalAddress?.phone || '');
  const [address1, setAddress1] = useState(originalAddress?.address1 || '');
  const [address2, setAddress2] = useState(originalAddress?.address2 || '');
  const [postalCode, setPostalCode] = useState(originalAddress?.postalCode || '');
  const [city, setCity] = useState(originalAddress?.city || '');
  const [province, setProvince] = useState(originalAddress?.province || 'Selangor');
  const [country, setCountry] = useState(originalAddress?.country || 'Malaysia');

  // Amendment Metadata State
  const [reason, setReason] = useState(AMENDMENT_REASONS[0]);
  const [internalNote, setInternalNote] = useState('');
  const [saveToAddressBook, setSaveToAddressBook] = useState(false);

  // Interaction & Validation States
  const [errors, setErrors] = useState<{ [key: string]: string }>({});
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [isSuccess, setIsSuccess] = useState(false);
  const [permissionError, setPermissionError] = useState<string | null>(null);

  // Check hard-block conditions
  const isDelivered = order.fulfillmentStatus === 'delivered';
  const isCancelled = order.fulfillmentStatus === 'cancelled' || order.paymentGatewayStatus === 'refunded';

  // Compare diffs
  const hasChanged =
    firstName.trim() !== (originalAddress?.firstName || '').trim() ||
    lastName.trim() !== (originalAddress?.lastName || '').trim() ||
    phone.trim() !== (originalAddress?.phone || '').trim() ||
    address1.trim() !== (originalAddress?.address1 || '').trim() ||
    address2.trim() !== (originalAddress?.address2 || '').trim() ||
    postalCode.trim() !== (originalAddress?.postalCode || '').trim() ||
    city.trim() !== (originalAddress?.city || '').trim() ||
    province.trim() !== (originalAddress?.province || '').trim() ||
    country.trim() !== (originalAddress?.country || '').trim();

  const validate = () => {
    const errs: { [key: string]: string } = {};

    if (!firstName.trim()) errs.firstName = 'First name is required';
    if (!lastName.trim()) errs.lastName = 'Last name is required';
    if (!phone.trim()) errs.phone = 'Mobile contact number is required';
    if (!address1.trim()) errs.address1 = 'Address line 1 is required';
    if (!postalCode.trim()) {
      errs.postalCode = 'Postcode is required';
    } else if (!/^\d{5}$/.test(postalCode.trim()) && country === 'Malaysia') {
      errs.postalCode = 'Malaysia postcode must be 5 numeric digits (e.g. 50450)';
    }
    if (!city.trim()) errs.city = 'City is required';
    if (!province.trim()) errs.province = 'State is required';
    if (!internalNote.trim()) {
      errs.internalNote = 'Internal audit note is required (minimum 8 characters)';
    } else if (internalNote.trim().length < 8) {
      errs.internalNote = 'Internal audit note must be at least 8 characters explaining the change';
    }

    setErrors(errs);
    return Object.keys(errs).length === 0;
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setPermissionError(null);

    if (!validate()) return;

    setIsSubmitting(true);
    try {
      const updatedAddr: CustomerAddress = {
        id: originalAddress?.id || `addr-${Date.now()}`,
        isDefault: originalAddress?.isDefault ?? true,
        type: 'shipping',
        firstName: firstName.trim(),
        lastName: lastName.trim(),
        phone: phone.trim(),
        address1: address1.trim(),
        address2: address2.trim() || undefined,
        city: city.trim(),
        province: province.trim(),
        postalCode: postalCode.trim(),
        country: country.trim(),
      };

      await onSave(order.id, updatedAddr, {
        reason,
        internalNote: internalNote.trim(),
        saveToAddressBook,
      });

      setIsSuccess(true);
      setTimeout(() => {
        setIsSuccess(false);
        onClose();
      }, 1200);
    } catch (err: any) {
      const message = err?.message || 'Failed to save amended delivery details. Please retry.';
      if (/permission|unauthori[sz]ed|forbidden/i.test(message)) setPermissionError(message);
      else setErrors({ form: message });
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
              <span className="font-mono font-bold text-base text-neutral-900 dark:text-white">
                Amend Delivery Details
              </span>
              <span className="font-mono text-xs px-2 py-0.5 rounded-md bg-indigo-100 dark:bg-indigo-950 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 font-semibold">
                Order #{order.orderNumber}
              </span>
              <span className="text-xs text-neutral-500">
                ({order.customerName})
              </span>
            </div>
            <p className="text-xs text-neutral-400 mt-0.5">
              Update recipient contact and destination address with immutable audit trail.
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

        {/* Modal Body: Scrollable */}
        <div className="p-6 overflow-y-auto space-y-5 text-xs">

          {/* Hard-block notice for Delivered */}
          {isDelivered && (
            <div className="p-4 rounded-2xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800/60 text-rose-800 dark:text-rose-300 flex items-start gap-3">
              <ShieldAlert className="w-5 h-5 shrink-0 mt-0.5 text-rose-600 dark:text-rose-400" />
              <div className="space-y-1">
                <div className="font-bold text-sm">Delivery Address Amendment Locked (Order Delivered)</div>
                <p className="leading-relaxed">
                  This order has already been marked as <strong>Delivered</strong>. Address details cannot be amended post-delivery. If the recipient claims non-receipt or needs replacement delivery, please direct staff to the <strong>Return / Replacement / Reshipment</strong> flow.
                </p>
              </div>
            </div>
          )}

          {/* Hard-block notice for Cancelled */}
          {isCancelled && !isDelivered && (
            <div className="p-4 rounded-2xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/60 text-amber-800 dark:text-amber-300 flex items-start gap-3">
              <AlertTriangle className="w-5 h-5 shrink-0 mt-0.5 text-amber-600 dark:text-amber-400" />
              <div className="space-y-1">
                <div className="font-bold text-sm">Delivery Amendment Disabled (Order Cancelled)</div>
                <p className="leading-relaxed">
                  This order is cancelled or fully refunded. Delivery address modifications are disabled.
                </p>
              </div>
            </div>
          )}

          {/* Active Shipment Guidance Banner */}
          {!isDelivered && !isCancelled && hasActiveShipment && (
            <div className="p-3.5 rounded-2xl bg-blue-50 dark:bg-blue-950/30 border border-blue-200 dark:border-blue-800/60 text-blue-800 dark:text-blue-300 flex items-start gap-2.5">
              <Info className="w-4 h-4 shrink-0 mt-0.5 text-blue-600 dark:text-blue-400" />
              <div className="space-y-0.5 text-[11px] leading-relaxed">
                <span className="font-bold">Active Shipment Guidance:</span>
                <p>
                  An active shipment consignment exists for this order. Amending the address here will update the order record and will be used for any subsequent or corrected fulfilment attempts. If the parcel has already been handed off to the courier, please also contact the carrier dispatch desk for physical parcel rerouting.
                </p>
              </div>
            </div>
          )}

          {/* Permission Error Banner */}
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

          {/* Main Amendment Form */}
          {!isDelivered && !isCancelled && (
            <form id="amend-address-form" onSubmit={handleSubmit} className="space-y-4">

              {/* Recipient Identity */}
              <div className="space-y-2">
                <div className="font-bold text-xs uppercase tracking-wider text-neutral-500 flex items-center gap-1.5">
                  <User className="w-3.5 h-3.5 text-indigo-500" />
                  <span>Recipient Information</span>
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <div>
                    <label className="text-[11px] font-semibold text-neutral-700 dark:text-neutral-300 block mb-1">
                      First Name <span className="text-rose-500">*</span>
                    </label>
                    <input
                      type="text"
                      value={firstName}
                      onChange={(e) => {
                        setFirstName(e.target.value);
                        if (errors.firstName) setErrors(prev => ({ ...prev, firstName: '' }));
                      }}
                      className={`w-full px-3 py-2 text-xs rounded-xl border bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white ${
                        errors.firstName
                          ? 'border-rose-500 focus:ring-rose-500'
                          : 'border-neutral-300 dark:border-neutral-700'
                      }`}
                      placeholder="e.g. Jason"
                    />
                    {errors.firstName && <span className="text-[10px] text-rose-500 mt-0.5 block">{errors.firstName}</span>}
                  </div>

                  <div>
                    <label className="text-[11px] font-semibold text-neutral-700 dark:text-neutral-300 block mb-1">
                      Last Name <span className="text-rose-500">*</span>
                    </label>
                    <input
                      type="text"
                      value={lastName}
                      onChange={(e) => {
                        setLastName(e.target.value);
                        if (errors.lastName) setErrors(prev => ({ ...prev, lastName: '' }));
                      }}
                      className={`w-full px-3 py-2 text-xs rounded-xl border bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white ${
                        errors.lastName
                          ? 'border-rose-500 focus:ring-rose-500'
                          : 'border-neutral-300 dark:border-neutral-700'
                      }`}
                      placeholder="e.g. Tan"
                    />
                    {errors.lastName && <span className="text-[10px] text-rose-500 mt-0.5 block">{errors.lastName}</span>}
                  </div>

                  <div className="sm:col-span-2">
                    <label className="text-[11px] font-semibold text-neutral-700 dark:text-neutral-300 block mb-1">
                      Mobile / Contact Phone <span className="text-rose-500">*</span>
                    </label>
                    <div className="relative">
                      <Phone className="w-3.5 h-3.5 absolute left-3 top-2.5 text-neutral-400" />
                      <input
                        type="text"
                        value={phone}
                        onChange={(e) => {
                          setPhone(e.target.value);
                          if (errors.phone) setErrors(prev => ({ ...prev, phone: '' }));
                        }}
                        className={`w-full pl-9 pr-3 py-2 text-xs rounded-xl border bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white font-mono ${
                          errors.phone
                            ? 'border-rose-500 focus:ring-rose-500'
                            : 'border-neutral-300 dark:border-neutral-700'
                        }`}
                        placeholder="e.g. +60123456789"
                      />
                    </div>
                    {errors.phone && <span className="text-[10px] text-rose-500 mt-0.5 block">{errors.phone}</span>}
                  </div>
                </div>
              </div>

              {/* Destination Address Fields */}
              <div className="space-y-2 pt-2 border-t border-neutral-100 dark:border-neutral-800">
                <div className="font-bold text-xs uppercase tracking-wider text-neutral-500 flex items-center gap-1.5">
                  <Building className="w-3.5 h-3.5 text-indigo-500" />
                  <span>Destination Address</span>
                </div>

                <div className="space-y-3">
                  <div>
                    <label className="text-[11px] font-semibold text-neutral-700 dark:text-neutral-300 block mb-1">
                      Address Line 1 (Street, Building, Unit) <span className="text-rose-500">*</span>
                    </label>
                    <input
                      type="text"
                      value={address1}
                      onChange={(e) => {
                        setAddress1(e.target.value);
                        if (errors.address1) setErrors(prev => ({ ...prev, address1: '' }));
                      }}
                      className={`w-full px-3 py-2 text-xs rounded-xl border bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white ${
                        errors.address1
                          ? 'border-rose-500 focus:ring-rose-500'
                          : 'border-neutral-300 dark:border-neutral-700'
                      }`}
                      placeholder="e.g. Unit 12-03, Menara Suria, Jalan 14/1"
                    />
                    {errors.address1 && <span className="text-[10px] text-rose-500 mt-0.5 block">{errors.address1}</span>}
                  </div>

                  <div>
                    <label className="text-[11px] font-semibold text-neutral-700 dark:text-neutral-300 block mb-1">
                      Address Line 2 (Optional)
                    </label>
                    <input
                      type="text"
                      value={address2}
                      onChange={(e) => setAddress2(e.target.value)}
                      className="w-full px-3 py-2 text-xs rounded-xl border bg-white dark:bg-neutral-800 border-neutral-300 dark:border-neutral-700 text-neutral-900 dark:text-white"
                      placeholder="e.g. Section 14"
                    />
                  </div>

                  <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                      <label className="text-[11px] font-semibold text-neutral-700 dark:text-neutral-300 block mb-1">
                        Postcode <span className="text-rose-500">*</span>
                      </label>
                      <input
                        type="text"
                        value={postalCode}
                        maxLength={5}
                        onChange={(e) => {
                          setPostalCode(e.target.value);
                          if (errors.postalCode) setErrors(prev => ({ ...prev, postalCode: '' }));
                        }}
                        className={`w-full px-3 py-2 text-xs rounded-xl border bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white font-mono ${
                          errors.postalCode
                            ? 'border-rose-500 focus:ring-rose-500'
                            : 'border-neutral-300 dark:border-neutral-700'
                        }`}
                        placeholder="50450"
                      />
                      {errors.postalCode && <span className="text-[10px] text-rose-500 mt-0.5 block">{errors.postalCode}</span>}
                    </div>

                    <div>
                      <label className="text-[11px] font-semibold text-neutral-700 dark:text-neutral-300 block mb-1">
                        City <span className="text-rose-500">*</span>
                      </label>
                      <input
                        type="text"
                        value={city}
                        onChange={(e) => {
                          setCity(e.target.value);
                          if (errors.city) setErrors(prev => ({ ...prev, city: '' }));
                        }}
                        className={`w-full px-3 py-2 text-xs rounded-xl border bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white ${
                          errors.city
                            ? 'border-rose-500 focus:ring-rose-500'
                            : 'border-neutral-300 dark:border-neutral-700'
                        }`}
                        placeholder="Petaling Jaya"
                      />
                      {errors.city && <span className="text-[10px] text-rose-500 mt-0.5 block">{errors.city}</span>}
                    </div>

                    <div>
                      <label className="text-[11px] font-semibold text-neutral-700 dark:text-neutral-300 block mb-1">
                        State <span className="text-rose-500">*</span>
                      </label>
                      <select
                        value={province}
                        onChange={(e) => setProvince(e.target.value)}
                        className="w-full px-3 py-2 text-xs rounded-xl border bg-white dark:bg-neutral-800 border-neutral-300 dark:border-neutral-700 text-neutral-900 dark:text-white font-medium"
                      >
                        {MALAYSIA_STATES.map((st) => (
                          <option key={st.value} value={st.value}>
                            {st.label}
                          </option>
                        ))}
                      </select>
                    </div>
                  </div>

                  <div>
                    <label className="text-[11px] font-semibold text-neutral-700 dark:text-neutral-300 block mb-1">
                      Country
                    </label>
                    <input
                      type="text"
                      value={country}
                      onChange={(e) => setCountry(e.target.value)}
                      className="w-full px-3 py-2 text-xs rounded-xl border bg-neutral-100 dark:bg-neutral-800/80 border-neutral-300 dark:border-neutral-700 text-neutral-700 dark:text-neutral-300 font-medium"
                      disabled
                    />
                  </div>
                </div>
              </div>

              {/* Mandatory Reason & Audit Note */}
              <div className="space-y-3 pt-2 border-t border-neutral-100 dark:border-neutral-800">
                <div className="font-bold text-xs uppercase tracking-wider text-neutral-500 flex items-center gap-1.5">
                  <FileText className="w-3.5 h-3.5 text-indigo-500" />
                  <span>Amendment Reason & Audit Trail</span>
                </div>

                <div className="space-y-2">
                  <div>
                    <label className="text-[11px] font-semibold text-neutral-700 dark:text-neutral-300 block mb-1">
                      Reason for Amendment <span className="text-rose-500">*</span>
                    </label>
                    <select
                      value={reason}
                      onChange={(e) => setReason(e.target.value)}
                      className="w-full px-3 py-2 text-xs rounded-xl border bg-white dark:bg-neutral-800 border-neutral-300 dark:border-neutral-700 font-medium text-neutral-900 dark:text-white"
                    >
                      {AMENDMENT_REASONS.map((r) => (
                        <option key={r} value={r}>
                          {r}
                        </option>
                      ))}
                    </select>
                  </div>

                  <div>
                    <label className="text-[11px] font-semibold text-neutral-700 dark:text-neutral-300 block mb-1">
                      Internal Audit Note <span className="text-rose-500">*</span>
                    </label>
                    <textarea
                      rows={2}
                      value={internalNote}
                      onChange={(e) => {
                        setInternalNote(e.target.value);
                        if (errors.internalNote) setErrors(prev => ({ ...prev, internalNote: '' }));
                      }}
                      placeholder="e.g. Customer contacted via WhatsApp requesting correction of unit number from 12-02 to 12-03."
                      className={`w-full px-3 py-2 text-xs rounded-xl border bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white ${
                        errors.internalNote
                          ? 'border-rose-500 focus:ring-rose-500'
                          : 'border-neutral-300 dark:border-neutral-700'
                      }`}
                    />
                    {errors.internalNote && <span className="text-[10px] text-rose-500 mt-0.5 block">{errors.internalNote}</span>}
                  </div>

                  {/* Customer Address Book Sync Checkbox (Unchecked by default) */}
                  <div className="pt-1">
                    <label className="flex items-center gap-2 cursor-pointer text-xs text-neutral-700 dark:text-neutral-300">
                      <input
                        type="checkbox"
                        checked={saveToAddressBook}
                        onChange={(e) => setSaveToAddressBook(e.target.checked)}
                        className="w-4 h-4 rounded text-indigo-600 accent-indigo-600 cursor-pointer"
                      />
                      <span>Also save updated address to customer address book profile</span>
                    </label>
                  </div>
                </div>
              </div>

              {/* Before vs After Comparison Summary */}
              {hasChanged && (
                <div className="p-3.5 rounded-2xl bg-neutral-50 dark:bg-neutral-800/50 border border-neutral-200 dark:border-neutral-700/80 space-y-2">
                  <div className="font-bold text-[11px] text-neutral-700 dark:text-neutral-300 uppercase tracking-wider flex items-center justify-between">
                    <span>Before vs. After Comparison</span>
                    <span className="text-[10px] text-indigo-600 dark:text-indigo-400 font-mono font-normal">
                      Pending Save
                    </span>
                  </div>

                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 text-[11px] font-mono">
                    {/* Before Card */}
                    <div className="p-2.5 rounded-xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 space-y-1">
                      <span className="text-[10px] font-bold text-neutral-400 block font-sans">ORIGINAL RECORD</span>
                      <div className="text-neutral-800 dark:text-neutral-200 font-bold font-sans">
                        {originalAddress?.firstName} {originalAddress?.lastName}
                      </div>
                      <div className="text-neutral-600 dark:text-neutral-400">{originalAddress?.address1}</div>
                      {originalAddress?.address2 && <div className="text-neutral-600 dark:text-neutral-400">{originalAddress.address2}</div>}
                      <div className="text-neutral-600 dark:text-neutral-400">
                        {originalAddress?.postalCode} {originalAddress?.city}, {originalAddress?.province}
                      </div>
                      <div className="text-neutral-500 text-[10px]">Tel: {originalAddress?.phone}</div>
                    </div>

                    {/* After Card */}
                    <div className="p-2.5 rounded-xl bg-indigo-50/50 dark:bg-indigo-950/30 border border-indigo-200 dark:border-indigo-900/60 space-y-1">
                      <span className="text-[10px] font-bold text-indigo-600 dark:text-indigo-400 block font-sans flex items-center gap-1">
                        <span>AMENDED PREVIEW</span>
                        <ArrowRight className="w-3 h-3" />
                      </span>
                      <div className="text-indigo-950 dark:text-indigo-200 font-bold font-sans">
                        {firstName} {lastName}
                      </div>
                      <div className="text-neutral-700 dark:text-neutral-300">{address1}</div>
                      {address2 && <div className="text-neutral-700 dark:text-neutral-300">{address2}</div>}
                      <div className="text-neutral-700 dark:text-neutral-300">
                        {postalCode} {city}, {province}
                      </div>
                      <div className="text-indigo-700 dark:text-indigo-300 text-[10px]">Tel: {phone}</div>
                    </div>
                  </div>
                </div>
              )}

            </form>
          )}

          {/* Success Message Banner */}
          {isSuccess && (
            <div className="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300 flex items-center gap-3 animate-in zoom-in-95">
              <CheckCircle2 className="w-5 h-5 text-emerald-600 shrink-0" />
              <div>
                <div className="font-bold text-xs">Delivery Address Amended Successfully</div>
                <p className="text-[11px] text-emerald-700 dark:text-emerald-400 mt-0.5">
                  Order destination records updated and immutable event logged to timeline.
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
            Cancel
          </button>

          {!isDelivered && !isCancelled && (
            <button
              type="submit"
              form="amend-address-form"
              disabled={isSubmitting || isSuccess}
              className="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-sm transition flex items-center gap-2 cursor-pointer disabled:opacity-50"
            >
              {isSubmitting ? (
                <>
                  <Loader2 className="w-3.5 h-3.5 animate-spin" />
                  <span>Saving Amendment...</span>
                </>
              ) : isSuccess ? (
                <>
                  <Check className="w-3.5 h-3.5" />
                  <span>Amended</span>
                </>
              ) : (
                <>
                  <Save className="w-3.5 h-3.5" />
                  <span>Save Delivery Details</span>
                </>
              )}
            </button>
          )}
        </div>
      </div>
    </div>
  );
};
