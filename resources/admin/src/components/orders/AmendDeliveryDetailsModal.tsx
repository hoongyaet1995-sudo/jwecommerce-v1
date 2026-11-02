import {OrderDialogOverlay} from './OrderDialogOverlay';
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

const AmendDeliveryDetailsForm: React.FC<AmendDeliveryDetailsModalProps> = ({
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

  const field=(label:string,value:string,set:(v:string)=>void,key:string,required=true)=><label>{label}<input value={value} required={required} disabled={isSubmitting||isDelivered||isCancelled} onChange={e=>set(e.target.value)}/>{errors[key]&&<span role="alert">{errors[key]}</span>}</label>;
  return <OrderDialogOverlay onClose={onClose} busy={isSubmitting}><section className="jw-order-dialog" role="dialog" aria-modal="true" aria-label="Edit delivery details"><header><h2>Edit delivery details</h2><button disabled={isSubmitting} onClick={onClose} aria-label="Close"><X size={18}/></button></header><p>Order {order.orderNumber}</p><form onSubmit={handleSubmit}>
  {(isDelivered||isCancelled)&&<p role="alert">Delivery details cannot be changed for a delivered, cancelled or fully refunded order.</p>}
  {!isDelivered&&!isCancelled&&hasActiveShipment&&<p>A shipment already exists. Updating this address does not reroute that parcel. Contact the courier if it has been handed over.</p>}
  {permissionError&&<p role="alert">{permissionError}</p>}{errors.form&&<p role="alert">{errors.form}</p>}{isSuccess&&<p role="status">Delivery details saved.</p>}
  <div className="jw-order-fields">{field('First name',firstName,setFirstName,'firstName')}{field('Last name',lastName,setLastName,'lastName')}</div>
  {field('Phone',phone,setPhone,'phone')}{field('Address',address1,setAddress1,'address1')}{field('Address line 2',address2,setAddress2,'address2',false)}
  <div className="jw-order-fields">{field('Postcode',postalCode,setPostalCode,'postalCode')}{field('City',city,setCity,'city')}</div>
  <label>State<select value={province} disabled={isSubmitting||isDelivered||isCancelled} onChange={e=>setProvince(e.target.value)}>{!MALAYSIA_STATES.some(s=>s.value===province)&&<option value={province}>{province}</option>}{MALAYSIA_STATES.map(s=><option key={s.value} value={s.value}>{s.label}</option>)}</select></label>
  <label>Country<input value={country} disabled/></label>
  <label>Reason<select value={reason} disabled={isSubmitting} onChange={e=>setReason(e.target.value)}>{AMENDMENT_REASONS.map(r=><option key={r}>{r}</option>)}</select></label>
  <label>Internal note<textarea value={internalNote} required minLength={8} disabled={isSubmitting} onChange={e=>setInternalNote(e.target.value)}/>{errors.internalNote&&<span role="alert">{errors.internalNote}</span>}</label>
  <label className="jw-order-toggle"><button type="button" role="switch" aria-checked={saveToAddressBook} onClick={()=>setSaveToAddressBook(!saveToAddressBook)} disabled={isSubmitting}><span/></button>Save to customer address book</label>
  <footer><button type="button" className="jw-btn" disabled={isSubmitting} onClick={onClose}>Cancel</button><button className="jw-btn jw-btn-primary" disabled={isSubmitting||isDelivered||isCancelled||!hasChanged||isSuccess}>{isSubmitting?'Saving…':'Save changes'}</button></footer></form></section></OrderDialogOverlay>;
};
export const AmendDeliveryDetailsModal:React.FC<AmendDeliveryDetailsModalProps> = props => props.isOpen&&props.order ? <AmendDeliveryDetailsForm key={props.order.id} {...props}/> : null;
