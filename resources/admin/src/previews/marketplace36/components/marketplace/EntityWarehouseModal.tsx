import React, { useState, useEffect } from 'react';
import { EntityWarehouse } from '../../types';
import {
  Warehouse,
  MapPin,
  Phone,
  User,
  ShieldCheck,
  CheckCircle2,
  X,
  AlertTriangle
} from 'lucide-react';

interface EntityWarehouseModalProps {
  isOpen: boolean;
  onClose: () => void;
  warehouse: EntityWarehouse | null;
  onSave: (data: Partial<EntityWarehouse>) => { success: boolean; error?: string } | Promise<{success:boolean;error?:string}>;
}

export const EntityWarehouseModal: React.FC<EntityWarehouseModalProps> = ({
  isOpen,
  onClose,
  warehouse,
  onSave
}) => {
  const isEditing = Boolean(warehouse);

  const [code, setCode] = useState(warehouse?.code || '');
  const [name, setName] = useState(warehouse?.name || '');
  const [addressLine, setAddressLine] = useState(typeof warehouse?.address === 'string' ? warehouse.address : '');
  const [city, setCity] = useState('');
  const [state, setState] = useState(warehouse?.state || 'Selangor');
  const [postalCode, setPostalCode] = useState('');
  const [contactName, setContactName] = useState(warehouse?.contactPerson || '');
  const [phone, setPhone] = useState(warehouse?.contactPhone || '');
  const [status, setStatus] = useState<'active' | 'inactive' | 'maintenance'>(warehouse?.status || 'active');
  const [isDefaultMaster, setIsDefaultMaster] = useState(warehouse?.isDefaultMaster || false);
  const [errorMsg, setErrorMsg] = useState<string | null>(null);

  useEffect(() => {
    if (warehouse) {
      setCode(warehouse.code);
      setName(warehouse.name);
      setAddressLine(typeof warehouse.address === 'string' ? warehouse.address : '');
      setCity('');
      setState(warehouse.state || 'Selangor');
      setPostalCode('');
      setContactName(warehouse.contactPerson || '');
      setPhone(warehouse.contactPhone || '');
      setStatus(warehouse.status || 'active');
      setIsDefaultMaster(warehouse.isDefaultMaster || false);
      setErrorMsg(null);
    } else {
      setCode(`WH-${Date.now().toString().slice(-4)}`);
      setName('');
      setAddressLine('');
      setCity('');
      setState('Selangor');
      setPostalCode('');
      setContactName('');
      setPhone('');
      setStatus('active');
      setIsDefaultMaster(false);
      setErrorMsg(null);
    }
  }, [warehouse, isOpen]);

  const [saving,setSaving]=useState(false);
  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if(saving)return;
    if (!code.trim()) {
      setErrorMsg('Warehouse Code is mandatory.');
      return;
    }
    if (!name.trim()) {
      setErrorMsg('Warehouse Name is mandatory.');
      return;
    }

    const combinedAddress = [addressLine.trim(), city.trim(), postalCode.trim()].filter(Boolean).join(', ') || addressLine.trim() || 'Malaysia';

    const payload: Partial<EntityWarehouse> = {
      ...(warehouse ? { id: warehouse.id } : {}),
      code: code.trim().toUpperCase(),
      name: name.trim(),
      status,
      isActive: status === 'active',
      isDefaultMaster,
      state: state.trim() || 'Selangor',
      contactPerson: contactName.trim(),
      contactPhone: phone.trim(),
      address: combinedAddress
    };

    setSaving(true);
    try {
    const res = await onSave(payload);
    if (!res.success) {
      setErrorMsg(res.error || 'Failed to save entity warehouse.');
      return;
    }

    onClose();
    } catch(error) {setErrorMsg(error instanceof Error?error.message:'Warehouse save failed.');}
    finally {setSaving(false);}
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/70 backdrop-blur-xs overflow-y-auto">
      <div className="relative w-full max-w-lg max-h-[calc(100dvh-1.5rem)] sm:max-h-[calc(100dvh-2.5rem)] my-auto flex flex-col bg-white dark:bg-neutral-900 rounded-2xl shadow-2xl border border-neutral-200 dark:border-neutral-800 overflow-hidden animate-in fade-in zoom-in-95 duration-150">
        
        {/* Header - Pinned */}
        <div className="p-4 sm:p-5 border-b border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-950/70 flex items-center justify-between shrink-0">
          <div className="flex items-center gap-3">
            <div className="p-2.5 rounded-xl bg-neutral-100 dark:bg-neutral-800 text-neutral-800 dark:text-neutral-200 border border-neutral-200 dark:border-neutral-700">
              <Warehouse className="w-5 h-5" />
            </div>
            <div>
              <h3 className="text-sm font-bold text-neutral-900 dark:text-white">
                {isEditing ? 'Configure Entity Warehouse' : 'Add Physical Entity Warehouse'}
              </h3>
              <p className="text-xs text-neutral-500 mt-0.5">
                Physical facility holding real inventory balances for fulfillment
              </p>
            </div>
          </div>
          <button
            onClick={onClose}
            className="p-1.5 rounded-lg text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-200 hover:bg-neutral-100 dark:hover:bg-neutral-800 cursor-pointer transition-colors"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        <form onSubmit={handleSubmit} className="flex flex-col flex-1 min-h-0 overflow-hidden">
          <div className="p-4 sm:p-6 space-y-4 text-xs overflow-y-auto flex-1 min-h-0">
            {errorMsg && (
              <div className="p-3 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-700 dark:text-rose-300 flex items-center gap-2">
                <AlertTriangle className="w-4 h-4 shrink-0 text-rose-500" />
                <span>{errorMsg}</span>
              </div>
            )}

            {/* Warehouse Code & Name */}
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
              <div>
                <label className="block font-bold text-neutral-700 dark:text-neutral-300 mb-1">
                  Facility Code <span className="text-rose-500">*</span>
                </label>
                <input
                  type="text"
                  value={code}
                  onChange={e => setCode(e.target.value.toUpperCase())}
                  placeholder="e.g. WH-CYB-01"
                  required
                  className="w-full px-3 py-2 rounded-xl bg-neutral-50 dark:bg-neutral-950 border border-neutral-300 dark:border-neutral-700 text-neutral-900 dark:text-white font-mono uppercase"
                />
              </div>

              <div className="sm:col-span-2">
                <label className="block font-bold text-neutral-700 dark:text-neutral-300 mb-1">
                  Facility Name <span className="text-rose-500">*</span>
                </label>
                <input
                  type="text"
                  value={name}
                  onChange={e => setName(e.target.value)}
                  placeholder="e.g. Cyberjaya Central Logistics Hub"
                  required
                  className="w-full px-3 py-2 rounded-xl bg-neutral-50 dark:bg-neutral-950 border border-neutral-300 dark:border-neutral-700 text-neutral-900 dark:text-white"
                />
              </div>
            </div>

            {/* Address Line */}
            <div>
              <label className="block font-semibold text-neutral-700 dark:text-neutral-300 mb-1 flex items-center gap-1.5">
                <MapPin className="w-3.5 h-3.5 text-neutral-400" />
                <span>Street Address</span>
              </label>
              <input
                type="text"
                value={addressLine}
                onChange={e => setAddressLine(e.target.value)}
                placeholder="e.g. Lot 4, Jalan Teknokrat 3, Cyber 5"
                className="w-full px-3 py-2 rounded-xl bg-neutral-50 dark:bg-neutral-950 border border-neutral-300 dark:border-neutral-700 text-neutral-900 dark:text-white"
              />
            </div>

            {/* City, State, Postcode */}
            <div className="grid grid-cols-3 gap-2.5">
              <div>
                <label className="block font-semibold text-neutral-700 dark:text-neutral-300 mb-1">City</label>
                <input
                  type="text"
                  value={city}
                  onChange={e => setCity(e.target.value)}
                  placeholder="Cyberjaya"
                  className="w-full px-2.5 py-2 rounded-xl bg-neutral-50 dark:bg-neutral-950 border border-neutral-300 dark:border-neutral-700 text-neutral-900 dark:text-white"
                />
              </div>
              <div>
                <label className="block font-semibold text-neutral-700 dark:text-neutral-300 mb-1">State</label>
                <input
                  type="text"
                  value={state}
                  onChange={e => setState(e.target.value)}
                  placeholder="Selangor"
                  className="w-full px-2.5 py-2 rounded-xl bg-neutral-50 dark:bg-neutral-950 border border-neutral-300 dark:border-neutral-700 text-neutral-900 dark:text-white"
                />
              </div>
              <div>
                <label className="block font-semibold text-neutral-700 dark:text-neutral-300 mb-1">Postal Code</label>
                <input
                  type="text"
                  value={postalCode}
                  onChange={e => setPostalCode(e.target.value)}
                  placeholder="63000"
                  className="w-full px-2.5 py-2 rounded-xl bg-neutral-50 dark:bg-neutral-950 border border-neutral-300 dark:border-neutral-700 text-neutral-900 dark:text-white font-mono"
                />
              </div>
            </div>

            {/* Contact Person & Phone */}
            <div className="grid grid-cols-2 gap-3">
              <div>
                <label className="block font-semibold text-neutral-700 dark:text-neutral-300 mb-1 flex items-center gap-1.5">
                  <User className="w-3.5 h-3.5 text-neutral-400" />
                  <span>Warehouse Supervisor</span>
                </label>
                <input
                  type="text"
                  value={contactName}
                  onChange={e => setContactName(e.target.value)}
                  placeholder="Ahmad Razali"
                  className="w-full px-3 py-2 rounded-xl bg-neutral-50 dark:bg-neutral-950 border border-neutral-300 dark:border-neutral-700 text-neutral-900 dark:text-white"
                />
              </div>
              <div>
                <label className="block font-semibold text-neutral-700 dark:text-neutral-300 mb-1 flex items-center gap-1.5">
                  <Phone className="w-3.5 h-3.5 text-neutral-400" />
                  <span>Contact Phone</span>
                </label>
                <input
                  type="text"
                  value={phone}
                  onChange={e => setPhone(e.target.value)}
                  placeholder="+60 12-345 6789"
                  className="w-full px-3 py-2 rounded-xl bg-neutral-50 dark:bg-neutral-950 border border-neutral-300 dark:border-neutral-700 text-neutral-900 dark:text-white"
                />
              </div>
            </div>

            {/* Status & Default Master */}
            <div className="pt-2 border-t border-neutral-200 dark:border-neutral-800 grid grid-cols-2 gap-3 items-center">
              <div>
                <label className="block font-bold text-neutral-700 dark:text-neutral-300 mb-1">Facility Status</label>
                <select
                  value={status}
                  onChange={e => setStatus(e.target.value as any)}
                  className="w-full px-3 py-2 rounded-xl bg-neutral-50 dark:bg-neutral-950 border border-neutral-300 dark:border-neutral-700 text-neutral-900 dark:text-white font-medium"
                >
                  <option value="active">Active (Online)</option>
                  <option value="maintenance">Under Maintenance</option>
                  <option value="inactive">Inactive / Closed</option>
                </select>
              </div>

              <label className="flex items-center gap-2.5 p-3 rounded-xl bg-neutral-50 dark:bg-neutral-950 border border-neutral-200 dark:border-neutral-800 cursor-pointer mt-5">
                <input
                  type="checkbox"
                  checked={isDefaultMaster}
                  onChange={e => setIsDefaultMaster(e.target.checked)}
                  className="rounded text-neutral-900 focus:ring-neutral-500"
                />
                <div>
                  <span className="font-bold text-neutral-900 dark:text-white block text-xs">Default Master Warehouse</span>
                  <span className="text-[10px] text-neutral-500 block">Primary hub for inbound stock</span>
                </div>
              </label>
            </div>
          </div>

          {/* Action Buttons - Pinned Footer */}
          <div className="p-3.5 sm:px-6 sm:py-3.5 flex items-center justify-between border-t border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-950/70 shrink-0">
            <button
              type="button"
              onClick={onClose}
              className="px-4 py-2.5 text-xs font-semibold text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800 rounded-xl cursor-pointer transition-colors"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={saving}
              className="px-5 py-2.5 bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 hover:bg-neutral-800 dark:hover:bg-neutral-100 text-xs font-bold rounded-xl shadow-xs flex items-center gap-1.5 cursor-pointer transition-colors"
            >
              <CheckCircle2 className="w-3.5 h-3.5" />
              <span>{isEditing ? 'Save Changes' : 'Create Entity Warehouse'}</span>
            </button>
          </div>

        </form>

      </div>
    </div>
  );
};
