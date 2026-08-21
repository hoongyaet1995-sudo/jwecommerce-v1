import React, { useState } from 'react';
import { 
  X, 
  Calendar, 
  Clock, 
  Truck, 
  MapPin, 
  Package, 
  User, 
  Phone, 
  CheckCircle2, 
  AlertCircle 
} from 'lucide-react';
import { CourierCode, PickupRequest } from '../../types/shipping';

interface PickupScheduleModalProps {
  isOpen: boolean;
  onClose: () => void;
  onSubmit: (request: Omit<PickupRequest, 'id' | 'createdAt'>) => void;
  defaultCourier?: CourierCode;
  selectedParcelCount?: number;
  totalWeightKg?: number;
}

export const PickupScheduleModal: React.FC<PickupScheduleModalProps> = ({
  isOpen,
  onClose,
  onSubmit,
  defaultCourier = 'jnt',
  selectedParcelCount = 12,
  totalWeightKg = 18.5
}) => {
  const tomorrow = new Date();
  tomorrow.setDate(tomorrow.getDate() + 1);
  const defaultDateStr = tomorrow.toISOString().split('T')[0];

  const [courier, setCourier] = useState<CourierCode>(defaultCourier);
  const [pickupDate, setPickupDate] = useState(defaultDateStr);
  const [timeSlot, setTimeSlot] = useState<'09:00 - 13:00' | '14:00 - 18:00'>('14:00 - 18:00');
  const [vehicleType, setVehicleType] = useState<'motorbike' | 'car_van' | '1_ton_lorry' | '3_ton_truck'>('car_van');
  const [warehouseLocation, setWarehouseLocation] = useState('Subang Central Warehouse (Bay 3)');
  const [parcelCount, setParcelCount] = useState(selectedParcelCount);
  const [weightKg, setWeightKg] = useState(totalWeightKg);
  const [driverNotes, setDriverNotes] = useState('Please call 10 mins prior to arrival. Security gate code: #4760.');
  const [contactName, setContactName] = useState('Kamal bin Ahmad');
  const [contactPhone, setContactPhone] = useState('+60 12-889 1234');

  if (!isOpen) return null;

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    onSubmit({
      courier,
      pickupDate,
      timeSlot,
      vehicleType,
      warehouseLocation,
      totalParcels: parcelCount,
      estimatedWeightKg: weightKg,
      status: 'confirmed',
      driverNotes,
      contactPerson: {
        name: contactName,
        phone: contactPhone
      }
    });
    onClose();
  };

  return (
    <div id="pickup-schedule-modal-overlay" className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4 overflow-y-auto backdrop-blur-sm">
      <div className="bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-2xl max-w-xl w-full flex flex-col shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
        {/* Header */}
        <div className="flex items-center justify-between px-6 py-4 border-b border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-900/80">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl bg-amber-50 dark:bg-amber-900/30 flex items-center justify-center text-amber-600 dark:text-amber-400">
              <Truck className="w-5 h-5" />
            </div>
            <div>
              <h3 className="text-base font-semibold text-neutral-900 dark:text-neutral-100">
                Book Courier Doorstep Pickup
              </h3>
              <p className="text-xs text-neutral-500 dark:text-neutral-400">
                Dispatch courier pickup request directly via Malaysian Courier APIs
              </p>
            </div>
          </div>
          <button
            onClick={onClose}
            className="p-1.5 rounded-lg text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Form */}
        <form onSubmit={handleSubmit} className="p-6 space-y-4">
          {/* Courier Selector */}
          <div>
            <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 uppercase tracking-wider mb-2">
              Select Courier Partner
            </label>
            <div className="grid grid-cols-3 gap-2">
              {[
                { id: 'jnt', name: 'J&T Express', desc: 'Same-day / Next-day' },
                { id: 'ninjavan', name: 'Ninja Van', desc: 'Scheduled Slots' },
                { id: 'posmalaysia', name: 'Pos Laju', desc: 'National Post' },
                { id: 'flashexpress', name: 'Flash Express', desc: '365 Days Pickup' },
                { id: 'easyparcel', name: 'EasyParcel', desc: 'Multi-Courier Aggregator' },
                { id: 'dhl', name: 'DHL eCommerce', desc: 'Enterprise Hub' },
              ].map((c) => (
                <button
                  type="button"
                  key={c.id}
                  onClick={() => setCourier(c.id as CourierCode)}
                  className={`p-2.5 rounded-xl border text-left transition-all ${
                    courier === c.id
                      ? 'border-blue-600 dark:border-blue-500 bg-blue-50/60 dark:bg-blue-900/20 text-blue-900 dark:text-blue-200 shadow-sm ring-1 ring-blue-500/30'
                      : 'border-neutral-200 dark:border-neutral-800 hover:bg-neutral-50 dark:hover:bg-neutral-800/60 text-neutral-800 dark:text-neutral-300'
                  }`}
                >
                  <div className="text-xs font-bold">{c.name}</div>
                  <div className="text-[10px] text-neutral-500 dark:text-neutral-400 mt-0.5 truncate">{c.desc}</div>
                </button>
              ))}
            </div>
          </div>

          {/* Date & Time Slot */}
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label className="block text-xs font-medium text-neutral-700 dark:text-neutral-300 mb-1 flex items-center gap-1.5">
                <Calendar className="w-3.5 h-3.5 text-neutral-400" />
                Pickup Date
              </label>
              <input
                type="date"
                value={pickupDate}
                min={new Date().toISOString().split('T')[0]}
                onChange={(e) => setPickupDate(e.target.value)}
                required
                className="w-full px-3 py-2 text-xs rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>

            <div>
              <label className="block text-xs font-medium text-neutral-700 dark:text-neutral-300 mb-1 flex items-center gap-1.5">
                <Clock className="w-3.5 h-3.5 text-neutral-400" />
                Pickup Time Slot
              </label>
              <select
                value={timeSlot}
                onChange={(e) => setTimeSlot(e.target.value as any)}
                className="w-full px-3 py-2 text-xs rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="09:00 - 13:00">Morning (09:00 AM - 01:00 PM)</option>
                <option value="14:00 - 18:00">Afternoon (02:00 PM - 06:00 PM)</option>
              </select>
            </div>
          </div>

          {/* Vehicle Type & Warehouse Location */}
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label className="block text-xs font-medium text-neutral-700 dark:text-neutral-300 mb-1 flex items-center gap-1.5">
                <Truck className="w-3.5 h-3.5 text-neutral-400" />
                Dispatched Vehicle Type
              </label>
              <select
                value={vehicleType}
                onChange={(e) => setVehicleType(e.target.value as any)}
                className="w-full px-3 py-2 text-xs rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="motorbike">Motorbike Courier (1-5 small parcels)</option>
                <option value="car_van">Van / MPV (6-50 parcels)</option>
                <option value="1_ton_lorry">1-Ton Lorry (50-200 parcels)</option>
                <option value="3_ton_truck">3-Ton Heavy Truck (Bulk / Pallets)</option>
              </select>
            </div>

            <div>
              <label className="block text-xs font-medium text-neutral-700 dark:text-neutral-300 mb-1 flex items-center gap-1.5">
                <MapPin className="w-3.5 h-3.5 text-neutral-400" />
                Pickup Origin Hub
              </label>
              <select
                value={warehouseLocation}
                onChange={(e) => setWarehouseLocation(e.target.value)}
                className="w-full px-3 py-2 text-xs rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="Subang Central Warehouse (Bay 3)">Subang Central Warehouse (47600 Selangor)</option>
                <option value="Shah Alam Fulfillment Hub">Shah Alam Fulfillment Hub (40400 Selangor)</option>
                <option value="Penang Island Regional Hub">Bayan Lepas Logistics Hub (11900 Penang)</option>
                <option value="Johor Bahru Depot">Tebrau Industrial Park Depot (81100 Johor)</option>
              </select>
            </div>
          </div>

          {/* Parcel Count & Weight */}
          <div className="grid grid-cols-2 gap-3 bg-neutral-50 dark:bg-neutral-800/40 p-3 rounded-xl border border-neutral-200 dark:border-neutral-700/60">
            <div>
              <label className="block text-[11px] font-semibold text-neutral-600 dark:text-neutral-400 mb-1">
                Estimated Parcel Count
              </label>
              <div className="relative">
                <input
                  type="number"
                  min="1"
                  value={parcelCount}
                  onChange={(e) => setParcelCount(parseInt(e.target.value) || 1)}
                  className="w-full px-3 py-1.5 text-xs rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 font-mono"
                />
                <span className="absolute right-3 top-2 text-[10px] text-neutral-400 font-semibold">pcs</span>
              </div>
            </div>

            <div>
              <label className="block text-[11px] font-semibold text-neutral-600 dark:text-neutral-400 mb-1">
                Est. Total Gross Weight
              </label>
              <div className="relative">
                <input
                  type="number"
                  step="0.1"
                  min="0.1"
                  value={weightKg}
                  onChange={(e) => setWeightKg(parseFloat(e.target.value) || 0.5)}
                  className="w-full px-3 py-1.5 text-xs rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 font-mono"
                />
                <span className="absolute right-3 top-2 text-[10px] text-neutral-400 font-semibold">kg</span>
              </div>
            </div>
          </div>

          {/* Contact Person */}
          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="block text-xs font-medium text-neutral-700 dark:text-neutral-300 mb-1">
                Warehouse Contact Person
              </label>
              <input
                type="text"
                value={contactName}
                onChange={(e) => setContactName(e.target.value)}
                required
                className="w-full px-3 py-2 text-xs rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100"
              />
            </div>
            <div>
              <label className="block text-xs font-medium text-neutral-700 dark:text-neutral-300 mb-1">
                Contact Phone (MY +60)
              </label>
              <input
                type="text"
                value={contactPhone}
                onChange={(e) => setContactPhone(e.target.value)}
                required
                className="w-full px-3 py-2 text-xs rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 font-mono"
              />
            </div>
          </div>

          {/* Driver Notes */}
          <div>
            <label className="block text-xs font-medium text-neutral-700 dark:text-neutral-300 mb-1">
              Instructions for Courier Driver / Loading Bay
            </label>
            <input
              type="text"
              value={driverNotes}
              onChange={(e) => setDriverNotes(e.target.value)}
              placeholder="e.g. Loading Bay B, ring bell at gate"
              className="w-full px-3 py-2 text-xs rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100"
            />
          </div>

          {/* Notice box */}
          <div className="p-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-xl text-[11px] text-blue-800 dark:text-blue-300 flex items-start gap-2">
            <AlertCircle className="w-4 h-4 shrink-0 text-blue-600 mt-0.5" />
            <div>
              Pickup request will generate an official courier dispatch job number. Please ensure all <strong>{parcelCount} parcels</strong> have printed 4x6" AWBs attached before driver arrival.
            </div>
          </div>

          {/* Footer buttons */}
          <div className="pt-2 flex items-center justify-end gap-2 border-t border-neutral-200 dark:border-neutral-800">
            <button
              type="button"
              onClick={onClose}
              className="px-4 py-2 text-xs font-medium text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800 rounded-lg transition-colors cursor-pointer"
            >
              Cancel
            </button>
            <button
              type="submit"
              className="flex items-center gap-1.5 px-4 py-2 text-xs font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors shadow-sm cursor-pointer"
            >
              <Truck className="w-3.5 h-3.5" />
              Confirm & Book Pickup
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};
