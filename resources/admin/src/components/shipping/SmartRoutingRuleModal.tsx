import React, { useState } from 'react';
import { 
  X, 
  GitFork, 
  ShieldAlert, 
  MapPin, 
  DollarSign, 
  Scale, 
  BatteryMedium, 
  Truck, 
  CheckCircle2, 
  HelpCircle 
} from 'lucide-react';
import { SmartRoutingRule, CourierCode } from '../../types/shipping';

interface SmartRoutingRuleModalProps {
  isOpen: boolean;
  rule: SmartRoutingRule | null;
  onClose: () => void;
  onSave: (rule: SmartRoutingRule) => void;
}

export const SmartRoutingRuleModal: React.FC<SmartRoutingRuleModalProps> = ({
  isOpen,
  rule,
  onClose,
  onSave
}) => {
  const isEditing = !!rule;

  const [name, setName] = useState(rule?.name || 'West Malaysia Heavy Freight Rule');
  const [description, setDescription] = useState(rule?.description || 'Route bulky parcels > 3kg to cost-effective ground carriers');
  const [region, setRegion] = useState<'all' | 'peninsular' | 'east_malaysia'>(
    rule?.conditions?.destinationRegion || 'peninsular'
  );
  const [minWeightKg, setMinWeightKg] = useState<number | undefined>(rule?.conditions?.minWeightKg);
  const [maxWeightKg, setMaxWeightKg] = useState<number | undefined>(rule?.conditions?.maxWeightKg);
  const [paymentMode, setPaymentMode] = useState<'all' | 'cod' | 'prepaid'>(
    rule?.conditions?.isCOD ? 'cod' : 'all'
  );
  const [containsBatteryOrLiquid, setContainsBatteryOrLiquid] = useState<boolean>(
    rule?.conditions?.isHazardousAirProhibited || false
  );
  const [primaryCourier, setPrimaryCourier] = useState<CourierCode>(rule?.action?.preferredCourier || 'jnt');
  const [serviceType, setServiceType] = useState(rule?.action?.serviceCode || 'Standard Express');
  const [fallbackCourier, setFallbackCourier] = useState<CourierCode>(rule?.action?.fallbackCourier || 'flashexpress');
  const [priority, setPriority] = useState(rule?.priority || 1);
  const [isActive, setIsActive] = useState(rule?.isActive ?? true);
  const [autoSignature, setAutoSignature] = useState(rule?.action?.autoRequireSignature ?? false);
  const [customNote, setCustomNote] = useState(rule?.action?.customNote || '');

  if (!isOpen) return null;

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    const newRule: SmartRoutingRule = {
      id: rule?.id || `rule_${Date.now()}`,
      name,
      priority,
      isActive,
      conditions: {
        destinationRegion: region,
        minWeightKg: minWeightKg || undefined,
        maxWeightKg: maxWeightKg || undefined,
        isCOD: paymentMode === 'cod' ? true : paymentMode === 'prepaid' ? false : undefined,
        isHazardousAirProhibited: containsBatteryOrLiquid
      },
      action: {
        preferredCourier: primaryCourier,
        fallbackCourier: fallbackCourier || 'easyparcel',
        serviceCode: serviceType || 'STD_EXPRESS',
        autoRequireSignature: autoSignature,
        customNote: customNote || undefined
      }
    };
    onSave(newRule);
    onClose();
  };

  return (
    <div id="smart-routing-rule-modal-overlay" className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4 overflow-y-auto backdrop-blur-sm">
      <div className="bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-2xl max-w-xl w-full flex flex-col shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
        {/* Header */}
        <div className="flex items-center justify-between px-6 py-4 border-b border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-900/80">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl bg-purple-50 dark:bg-purple-900/30 flex items-center justify-center text-purple-600 dark:text-purple-400">
              <GitFork className="w-5 h-5" />
            </div>
            <div>
              <h3 className="text-base font-semibold text-neutral-900 dark:text-neutral-100">
                {isEditing ? 'Edit Smart Routing Rule' : 'New Smart Courier Routing Rule'}
              </h3>
              <p className="text-xs text-neutral-500 dark:text-neutral-400">
                Define dynamic multi-courier dispatch rules by territory, weight, and payment
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
          {/* Rule Name & Desc */}
          <div className="space-y-3">
            <div>
              <label className="block text-xs font-medium text-neutral-700 dark:text-neutral-300 mb-1">
                Rule Title
              </label>
              <input
                type="text"
                value={name}
                onChange={(e) => setName(e.target.value)}
                required
                placeholder="e.g. East Malaysia Sabah/Sarawak Air Restriction Rule"
                className="w-full px-3 py-2 text-xs rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:outline-none focus:ring-2 focus:ring-purple-500"
              />
            </div>
            <div>
              <label className="block text-xs font-medium text-neutral-700 dark:text-neutral-300 mb-1">
                Description / Business Objective
              </label>
              <input
                type="text"
                value={description}
                onChange={(e) => setDescription(e.target.value)}
                placeholder="Explain when and why this rule triggers"
                className="w-full px-3 py-2 text-xs rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:outline-none focus:ring-2 focus:ring-purple-500"
              />
            </div>
          </div>

          {/* Section: Conditions */}
          <div className="border-t border-neutral-200 dark:border-neutral-800 pt-3">
            <h4 className="text-xs font-bold text-neutral-900 dark:text-neutral-100 uppercase tracking-wider mb-2.5 flex items-center gap-1.5">
              <MapPin className="w-3.5 h-3.5 text-purple-600" />
              1. Matching Conditions (IF)
            </h4>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-3">
              {/* Region */}
              <div>
                <label className="block text-[11px] font-medium text-neutral-600 dark:text-neutral-400 mb-1">
                  Destination Region
                </label>
                <select
                  value={region}
                  onChange={(e) => setRegion(e.target.value as 'all' | 'peninsular' | 'east_malaysia')}
                  className="w-full px-3 py-1.5 text-xs rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100"
                >
                  <option value="all">Entire Malaysia (Peninsular + East)</option>
                  <option value="peninsular">Peninsular Malaysia (West MY)</option>
                  <option value="east_malaysia">East Malaysia (Sabah, Sarawak, Labuan)</option>
                </select>
              </div>

              {/* Payment Mode */}
              <div>
                <label className="block text-[11px] font-medium text-neutral-600 dark:text-neutral-400 mb-1">
                  Payment Condition
                </label>
                <select
                  value={paymentMode}
                  onChange={(e) => setPaymentMode(e.target.value as any)}
                  className="w-full px-3 py-1.5 text-xs rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100"
                >
                  <option value="all">Any Payment (Prepaid + COD)</option>
                  <option value="cod">Cash on Delivery (COD) Only</option>
                  <option value="prepaid">Online Prepaid (FPX / Card / E-Wallet)</option>
                </select>
              </div>
            </div>

            {/* Weight Range */}
            <div className="grid grid-cols-2 gap-3 mb-3">
              <div>
                <label className="block text-[11px] font-medium text-neutral-600 dark:text-neutral-400 mb-1">
                  Min Parcel Weight (kg)
                </label>
                <input
                  type="number"
                  step="0.1"
                  min="0"
                  value={minWeightKg ?? ''}
                  onChange={(e) => setMinWeightKg(e.target.value ? parseFloat(e.target.value) : undefined)}
                  placeholder="e.g. 0.0"
                  className="w-full px-3 py-1.5 text-xs rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 font-mono"
                />
              </div>
              <div>
                <label className="block text-[11px] font-medium text-neutral-600 dark:text-neutral-400 mb-1">
                  Max Parcel Weight (kg)
                </label>
                <input
                  type="number"
                  step="0.1"
                  min="0"
                  value={maxWeightKg ?? ''}
                  onChange={(e) => setMaxWeightKg(e.target.value ? parseFloat(e.target.value) : undefined)}
                  placeholder="e.g. 5.0"
                  className="w-full px-3 py-1.5 text-xs rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 font-mono"
                />
              </div>
            </div>

            {/* Dangerous goods / battery */}
            <label className="flex items-center gap-2 text-xs text-neutral-700 dark:text-neutral-300 p-2.5 rounded-lg border border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-800/30 cursor-pointer">
              <input
                type="checkbox"
                checked={containsBatteryOrLiquid}
                onChange={(e) => setContainsBatteryOrLiquid(e.target.checked)}
                className="rounded text-purple-600 focus:ring-purple-500"
              />
              <BatteryMedium className="w-4 h-4 text-amber-500" />
              <span>Restricted Goods Flag: Contains Lithium Batteries, Liquids, or Aerosol Sprays</span>
            </label>
          </div>

          {/* Section: Action */}
          <div className="border-t border-neutral-200 dark:border-neutral-800 pt-3">
            <h4 className="text-xs font-bold text-neutral-900 dark:text-neutral-100 uppercase tracking-wider mb-2.5 flex items-center gap-1.5">
              <Truck className="w-3.5 h-3.5 text-purple-600" />
              2. Target Courier Routing (THEN)
            </h4>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-3">
              <div>
                <label className="block text-[11px] font-medium text-neutral-600 dark:text-neutral-400 mb-1">
                  Primary Assigned Courier
                </label>
                <select
                  value={primaryCourier}
                  onChange={(e) => setPrimaryCourier(e.target.value as CourierCode)}
                  className="w-full px-3 py-1.5 text-xs font-semibold rounded-lg border border-purple-300 dark:border-purple-700 bg-white dark:bg-neutral-800 text-purple-900 dark:text-purple-200"
                >
                  <option value="jnt">J&T Express Malaysia</option>
                  <option value="ninjavan">Ninja Van Malaysia</option>
                  <option value="posmalaysia">Pos Malaysia (Pos Laju)</option>
                  <option value="flashexpress">Flash Express Malaysia</option>
                  <option value="easyparcel">EasyParcel (Smart Aggregator)</option>
                  <option value="dhl">DHL eCommerce</option>
                </select>
              </div>

              <div>
                <label className="block text-[11px] font-medium text-neutral-600 dark:text-neutral-400 mb-1">
                  Fallback Courier (API Failover)
                </label>
                <select
                  value={fallbackCourier || ''}
                  onChange={(e) => setFallbackCourier((e.target.value || undefined) as CourierCode)}
                  className="w-full px-3 py-1.5 text-xs rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100"
                >
                  <option value="">None (Throw Manual Review)</option>
                  <option value="jnt">J&T Express</option>
                  <option value="ninjavan">Ninja Van</option>
                  <option value="posmalaysia">Pos Malaysia</option>
                  <option value="flashexpress">Flash Express</option>
                  <option value="easyparcel">EasyParcel</option>
                </select>
              </div>
            </div>

            <div>
              <label className="block text-[11px] font-medium text-neutral-600 dark:text-neutral-400 mb-1">
                Requested Service Tier
              </label>
              <input
                type="text"
                value={serviceType}
                onChange={(e) => setServiceType(e.target.value)}
                placeholder="e.g. Standard Express, Next-Day Air, Sea Freight"
                className="w-full px-3 py-1.5 text-xs rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100"
              />
            </div>
          </div>

          {/* Section: Priority & Active */}
          <div className="flex items-center justify-between pt-2 border-t border-neutral-200 dark:border-neutral-800">
            <div className="flex items-center gap-3">
              <label className="flex items-center gap-2 text-xs font-medium text-neutral-700 dark:text-neutral-300 cursor-pointer">
                <input
                  type="checkbox"
                  checked={isActive}
                  onChange={(e) => setIsActive(e.target.checked)}
                  className="rounded text-purple-600 focus:ring-purple-500"
                />
                Active Rule
              </label>
            </div>

            <div className="flex items-center gap-2">
              <button
                type="button"
                onClick={onClose}
                className="px-4 py-2 text-xs font-medium text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800 rounded-lg transition-colors cursor-pointer"
              >
                Cancel
              </button>
              <button
                type="submit"
                className="flex items-center gap-1.5 px-4 py-2 text-xs font-medium text-white bg-purple-600 hover:bg-purple-700 rounded-lg transition-colors shadow-sm cursor-pointer"
              >
                <CheckCircle2 className="w-3.5 h-3.5" />
                {isEditing ? 'Save Changes' : 'Create Rule'}
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  );
};
