import React, { useState } from 'react';
import { 
  GitFork, 
  Plus, 
  Trash2, 
  Edit, 
  Check, 
  ArrowRight, 
  Calculator, 
  Scale, 
  MapPin, 
  Truck, 
  ShieldAlert, 
  Zap,
  BatteryMedium,
  DollarSign,
  Clock
} from 'lucide-react';
import { SmartRoutingRule, CourierConfig, CourierCode } from '../../types/shipping';
import { DeleteConfirmationModal } from '../common/DeleteConfirmationModal';

interface SmartRoutingTabProps {
  rules: SmartRoutingRule[];
  couriers: CourierConfig[];
  onSaveRule: (rule: SmartRoutingRule) => void;
  onDeleteRule: (ruleId: string) => void;
  onReorderRules: (reorderedRules: SmartRoutingRule[]) => void;
  onOpenNewRuleModal: () => void;
  onOpenEditRuleModal: (rule: SmartRoutingRule) => void;
}

export const SmartRoutingTab: React.FC<SmartRoutingTabProps> = ({
  rules,
  couriers,
  onSaveRule,
  onDeleteRule,
  onReorderRules,
  onOpenNewRuleModal,
  onOpenEditRuleModal
}) => {
  const [ruleToDelete, setRuleToDelete] = useState<SmartRoutingRule | null>(null);
  // Calculator state
  const [destPostcode, setDestPostcode] = useState('88400');
  const [destState, setDestState] = useState('Sabah');
  const [weightKg, setWeightKg] = useState<number>(2.0);
  const [lengthCm, setLengthCm] = useState<number>(30);
  const [widthCm, setWidthCm] = useState<number>(20);
  const [heightCm, setHeightCm] = useState<number>(15);
  const [isCod, setIsCod] = useState(false);
  const [codAmount, setCodAmount] = useState<number>(120);
  const [hasBattery, setHasBattery] = useState(false);

  // Calculate volumetric weight: (L x W x H) / 5000 (standard courier divisor in Malaysia)
  const volumetricWeight = Math.round(((lengthCm * widthCm * heightCm) / 5000) * 100) / 100;
  const chargeableWeight = Math.max(weightKg, volumetricWeight);

  // Region detection
  const isEastMalaysia = destState === 'Sabah' || destState === 'Sarawak';

  // Live Rate quotes computation for Malaysian Couriers
  const calculateRates = () => {
    const quotes = [
      {
        courier: 'jnt',
        name: 'J&T Express',
        baseRate: isEastMalaysia ? 18.50 + Math.max(0, chargeableWeight - 1) * 8.50 : 6.50 + Math.max(0, chargeableWeight - 1) * 1.50,
        sst: isEastMalaysia ? 1.48 : 0.52,
        fuelSurcharge: 1.20,
        codFee: isCod ? Math.max(3.00, codAmount * 0.02) : 0,
        deliveryDays: isEastMalaysia ? '3 - 5 Working Days' : '1 - 2 Working Days',
        badge: isEastMalaysia ? 'Pos Laju Preferred' : 'Fastest Next-Day',
        recommended: !isEastMalaysia && !hasBattery
      },
      {
        courier: 'ninjavan',
        name: 'Ninja Van Malaysia',
        baseRate: isEastMalaysia ? 19.00 + Math.max(0, chargeableWeight - 1) * 9.00 : 7.00 + Math.max(0, chargeableWeight - 1) * 1.60,
        sst: isEastMalaysia ? 1.52 : 0.56,
        fuelSurcharge: 1.00,
        codFee: isCod ? Math.max(2.50, codAmount * 0.02) : 0,
        deliveryDays: isEastMalaysia ? '3 - 4 Working Days' : '1 - 2 Working Days',
        badge: isCod ? 'Top COD Choice' : 'Standard Express',
        recommended: isCod
      },
      {
        courier: 'posmalaysia',
        name: 'Pos Malaysia (Pos Laju)',
        baseRate: isEastMalaysia ? 14.50 + Math.max(0, chargeableWeight - 1) * 6.00 : 7.50 + Math.max(0, chargeableWeight - 1) * 1.80,
        sst: isEastMalaysia ? 1.16 : 0.60,
        fuelSurcharge: 0.80,
        codFee: isCod ? Math.max(3.50, codAmount * 0.025) : 0,
        deliveryDays: isEastMalaysia ? '2 - 3 Working Days' : '2 - 3 Working Days',
        badge: isEastMalaysia ? 'Best East MY Air Rate' : 'National Coverage',
        recommended: isEastMalaysia
      },
      {
        courier: 'flashexpress',
        name: 'Flash Express MY',
        baseRate: isEastMalaysia ? 20.00 + Math.max(0, chargeableWeight - 1) * 9.50 : 5.80 + Math.max(0, chargeableWeight - 1) * 1.40,
        sst: isEastMalaysia ? 1.60 : 0.46,
        fuelSurcharge: 0.90,
        codFee: isCod ? Math.max(3.00, codAmount * 0.022) : 0,
        deliveryDays: isEastMalaysia ? '4 - 6 Working Days' : '1 - 2 Working Days',
        badge: !isEastMalaysia ? 'Lowest West MY Rate' : 'Standard',
        recommended: false
      },
      {
        courier: 'easyparcel',
        name: 'EasyParcel Aggregator',
        baseRate: isEastMalaysia ? 15.00 + Math.max(0, chargeableWeight - 1) * 6.50 : 6.00 + Math.max(0, chargeableWeight - 1) * 1.50,
        sst: 0.48,
        fuelSurcharge: 0.70,
        codFee: isCod ? Math.max(2.80, codAmount * 0.02) : 0,
        deliveryDays: isEastMalaysia ? '3 - 4 Working Days' : '1 - 2 Working Days',
        badge: 'Multi-Courier Smart Quota',
        recommended: false
      }
    ];

    return quotes.map(q => ({
      ...q,
      totalFee: q.baseRate + q.sst + q.fuelSurcharge + q.codFee
    })).sort((a, b) => a.totalFee - b.totalFee);
  };

  const quotes = calculateRates();

  const handleToggleRuleActive = (rule: SmartRoutingRule) => {
    onSaveRule({
      ...rule,
      isActive: !rule.isActive
    });
  };

  return (
    <div className="space-y-6">
      {/* Section 1: Smart Routing Rules Manager */}
      <div className="p-5 rounded-2xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 shadow-sm space-y-4">
        <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
          <div>
            <h3 className="text-base font-bold text-neutral-900 dark:text-neutral-100 flex items-center gap-2">
              <GitFork className="w-4 h-4 text-purple-600" />
              Smart Courier Routing Rules Engine
            </h3>
            <p className="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">
              Automate carrier assignment based on territory, weight thresholds, COD conditions, and air cargo safety rules.
            </p>
          </div>

          <button
            onClick={onOpenNewRuleModal}
            className="flex items-center gap-1.5 px-3.5 py-2 text-xs font-bold text-white bg-purple-600 hover:bg-purple-700 rounded-xl shadow-sm transition-colors cursor-pointer"
          >
            <Plus className="w-3.5 h-3.5" />
            Add Routing Rule
          </button>
        </div>

        {/* Rules List */}
        <div className="space-y-2.5">
          {rules.map((rule, idx) => (
            <div
              key={rule.id}
              className={`p-4 rounded-xl border transition-all ${
                rule.isActive
                  ? 'border-neutral-200 dark:border-neutral-800 bg-neutral-50/50 dark:bg-neutral-800/30'
                  : 'border-neutral-200/50 dark:border-neutral-800/40 bg-neutral-100/30 dark:bg-neutral-900/40 opacity-60'
              }`}
            >
              <div className="flex items-start justify-between gap-3">
                <div className="flex items-start gap-3">
                  {/* Priority Number */}
                  <div className="w-6 h-6 rounded-md bg-purple-100 dark:bg-purple-900/40 text-purple-700 dark:text-purple-300 font-bold text-xs flex items-center justify-center font-mono shrink-0">
                    {idx + 1}
                  </div>

                  <div>
                    <div className="flex items-center gap-2">
                      <h4 className="text-xs font-bold text-neutral-900 dark:text-neutral-100">
                        {rule.name}
                      </h4>
                      <span className={`px-2 py-0.2 text-[9px] font-bold rounded uppercase ${
                        rule.isActive ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300' : 'bg-neutral-200 text-neutral-600 dark:bg-neutral-800 dark:text-neutral-400'
                      }`}>
                        {rule.isActive ? 'Active' : 'Disabled'}
                      </span>
                    </div>
                    <p className="text-[11px] text-neutral-500 dark:text-neutral-400 mt-0.5">
                      {rule.description}
                    </p>

                    {/* Condition Chips */}
                    <div className="flex flex-wrap items-center gap-1.5 mt-2.5">
                      <span className="text-[10px] text-neutral-400 font-semibold uppercase">IF:</span>
                      <span className="px-2 py-0.5 rounded-md bg-purple-50 dark:bg-purple-900/20 text-purple-800 dark:text-purple-300 border border-purple-200 dark:border-purple-800 text-[10px] font-medium">
                        Region: {(rule.conditions?.destinationRegion || 'all').replace('_', ' ').toUpperCase()}
                      </span>
                      {rule.conditions?.destinationStates && rule.conditions.destinationStates.length > 0 && (
                        <span className="px-2 py-0.5 rounded-md bg-purple-100/60 dark:bg-purple-950/40 text-purple-700 dark:text-purple-300 text-[10px] font-medium">
                          States: {rule.conditions.destinationStates.join(', ')}
                        </span>
                      )}
                      {rule.conditions?.minWeightKg !== undefined && (
                        <span className="px-2 py-0.5 rounded-md bg-neutral-200/70 dark:bg-neutral-700/60 text-neutral-800 dark:text-neutral-200 text-[10px] font-mono">
                          Weight ≥ {rule.conditions.minWeightKg}kg
                        </span>
                      )}
                      {rule.conditions?.maxWeightKg !== undefined && (
                        <span className="px-2 py-0.5 rounded-md bg-neutral-200/70 dark:bg-neutral-700/60 text-neutral-800 dark:text-neutral-200 text-[10px] font-mono">
                          Weight ≤ {rule.conditions.maxWeightKg}kg
                        </span>
                      )}
                      {rule.conditions?.isCOD !== undefined && (
                        <span className="px-2 py-0.5 rounded-md bg-neutral-200/70 dark:bg-neutral-700/60 text-neutral-800 dark:text-neutral-200 text-[10px] uppercase font-mono">
                          Pay: {rule.conditions.isCOD ? 'COD Only' : 'Prepaid Only'}
                        </span>
                      )}
                      {rule.conditions?.isHazardousAirProhibited && (
                        <span className="px-2 py-0.5 rounded-md bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300 text-[10px] font-medium flex items-center gap-1">
                          <BatteryMedium className="w-3 h-3" /> Contains Battery / Liquid
                        </span>
                      )}

                      <ArrowRight className="w-3 h-3 text-neutral-400" />
                      <span className="text-[10px] text-neutral-400 font-semibold uppercase">THEN:</span>

                      <span className="px-2 py-0.5 rounded-md bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-200 text-[10px] font-bold">
                        Assign: {(rule.action?.preferredCourier || 'jnt').toUpperCase()} ({rule.action?.serviceCode || 'Standard'})
                      </span>
                      {rule.action?.fallbackCourier && (
                        <span className="px-2 py-0.5 rounded-md bg-neutral-200/50 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400 text-[10px]">
                          Fallback: {rule.action.fallbackCourier.toUpperCase()}
                        </span>
                      )}
                      {rule.action?.autoRequireSignature && (
                        <span className="px-2 py-0.5 rounded-md bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 text-[10px]">
                          Signature Req
                        </span>
                      )}
                    </div>
                  </div>
                </div>

                <div className="flex items-center gap-2 shrink-0">
                  <button
                    onClick={() => handleToggleRuleActive(rule)}
                    className="text-xs text-neutral-500 hover:text-neutral-800 dark:hover:text-neutral-200 font-medium px-2 py-1"
                  >
                    {rule.isActive ? 'Disable' : 'Enable'}
                  </button>
                  <button
                    onClick={() => onOpenEditRuleModal(rule)}
                    className="p-1.5 text-neutral-600 dark:text-neutral-300 hover:bg-neutral-200 dark:hover:bg-neutral-700 rounded-lg transition-colors cursor-pointer"
                  >
                    <Edit className="w-3.5 h-3.5" />
                  </button>
                  <button
                    onClick={() => setRuleToDelete(rule)}
                    className="p-1.5 text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-900/30 rounded-lg transition-colors cursor-pointer"
                  >
                    <Trash2 className="w-3.5 h-3.5" />
                  </button>
                </div>
              </div>
            </div>
          ))}
        </div>
      </div>

      {/* Section 2: Real-time Multi-Carrier Rate Calculator */}
      <div className="p-5 rounded-2xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 shadow-sm space-y-4">
        <div>
          <h3 className="text-base font-bold text-neutral-900 dark:text-neutral-100 flex items-center gap-2">
            <Calculator className="w-4 h-4 text-blue-600" />
            Interactive Malaysia Multi-Carrier Rate Comparison Engine
          </h3>
          <p className="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">
            Test and simulate live shipping rates from Malaysian couriers with volumetric weight and fuel surcharges.
          </p>
        </div>

        {/* Inputs */}
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 bg-neutral-50 dark:bg-neutral-800/40 p-4 rounded-xl border border-neutral-200 dark:border-neutral-700/60">
          {/* Postcode & State */}
          <div>
            <label className="block text-[11px] font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
              Destination State & Postcode
            </label>
            <div className="flex gap-2">
              <select
                value={destState}
                onChange={(e) => setDestState(e.target.value)}
                className="w-1/2 px-2.5 py-1.5 text-xs rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100"
              >
                <option value="Selangor">Selangor</option>
                <option value="Kuala Lumpur">Kuala Lumpur</option>
                <option value="Penang">Penang</option>
                <option value="Johor">Johor</option>
                <option value="Sabah">Sabah (East)</option>
                <option value="Sarawak">Sarawak (East)</option>
                <option value="Kelantan">Kelantan</option>
                <option value="Perak">Perak</option>
              </select>
              <input
                type="text"
                value={destPostcode}
                onChange={(e) => setDestPostcode(e.target.value)}
                placeholder="Postcode"
                className="w-1/2 px-2.5 py-1.5 text-xs rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 font-mono"
              />
            </div>
          </div>

          {/* Actual Weight */}
          <div>
            <label className="block text-[11px] font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
              Actual Gross Weight (kg)
            </label>
            <input
              type="number"
              step="0.1"
              min="0.1"
              value={weightKg}
              onChange={(e) => setWeightKg(parseFloat(e.target.value) || 0.1)}
              className="w-full px-2.5 py-1.5 text-xs rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 font-mono"
            />
          </div>

          {/* Dimensions */}
          <div>
            <label className="block text-[11px] font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
              Box Size L x W x H (cm)
            </label>
            <div className="flex items-center gap-1">
              <input
                type="number"
                value={lengthCm}
                onChange={(e) => setLengthCm(parseInt(e.target.value) || 1)}
                className="w-1/3 px-2 py-1.5 text-xs rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 font-mono text-center"
              />
              <span className="text-neutral-400">×</span>
              <input
                type="number"
                value={widthCm}
                onChange={(e) => setWidthCm(parseInt(e.target.value) || 1)}
                className="w-1/3 px-2 py-1.5 text-xs rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 font-mono text-center"
              />
              <span className="text-neutral-400">×</span>
              <input
                type="number"
                value={heightCm}
                onChange={(e) => setHeightCm(parseInt(e.target.value) || 1)}
                className="w-1/3 px-2 py-1.5 text-xs rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 font-mono text-center"
              />
            </div>
          </div>

          {/* Payment & Restrictions */}
          <div>
            <label className="block text-[11px] font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
              COD & Cargo Flags
            </label>
            <div className="flex items-center gap-2">
              <label className="flex items-center gap-1 text-xs text-neutral-700 dark:text-neutral-300 cursor-pointer">
                <input
                  type="checkbox"
                  checked={isCod}
                  onChange={(e) => setIsCod(e.target.checked)}
                  className="rounded text-blue-600 focus:ring-blue-500"
                />
                COD (RM{codAmount})
              </label>
              <label className="flex items-center gap-1 text-xs text-neutral-700 dark:text-neutral-300 cursor-pointer">
                <input
                  type="checkbox"
                  checked={hasBattery}
                  onChange={(e) => setHasBattery(e.target.checked)}
                  className="rounded text-amber-600 focus:ring-amber-500"
                />
                Battery/Liquid
              </label>
            </div>
          </div>
        </div>

        {/* Volumetric Weight Notice */}
        <div className="flex flex-wrap items-center justify-between text-xs px-3 py-2 bg-blue-50/60 dark:bg-blue-900/20 rounded-lg text-blue-900 dark:text-blue-300 border border-blue-200 dark:border-blue-800/60">
          <div>
            Volumetric Weight: <strong>{volumetricWeight} kg</strong> • Actual Weight: <strong>{weightKg} kg</strong>
          </div>
          <div>
            Chargeable Billing Weight: <strong className="font-mono text-sm font-bold text-blue-700 dark:text-blue-200">{chargeableWeight} kg</strong>
          </div>
        </div>

        {/* Comparison Table */}
        <div className="rounded-xl border border-neutral-200 dark:border-neutral-800 overflow-hidden">
          <table className="w-full text-left text-xs">
            <thead>
              <tr className="bg-neutral-50 dark:bg-neutral-800/60 text-neutral-500 dark:text-neutral-400 font-semibold border-b border-neutral-200 dark:border-neutral-800">
                <th className="p-3">Courier Provider</th>
                <th className="p-3">Base Freight</th>
                <th className="p-3">SST (6%)</th>
                <th className="p-3">Fuel Surcharge</th>
                {isCod && <th className="p-3">COD Fee</th>}
                <th className="p-3">Estimated Transit</th>
                <th className="p-3 font-bold text-right">Total Net Fee</th>
                <th className="p-3 text-center">Smart Recommendation</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-200 dark:divide-neutral-800">
              {quotes.map((q) => (
                <tr 
                  key={q.courier}
                  className={`hover:bg-neutral-50 dark:hover:bg-neutral-800/30 transition-colors ${
                    q.recommended ? 'bg-emerald-50/50 dark:bg-emerald-900/10' : ''
                  }`}
                >
                  <td className="p-3 font-bold text-neutral-900 dark:text-neutral-100 flex items-center gap-2">
                    <span className="w-2 h-2 rounded-full bg-blue-600" />
                    {q.name}
                  </td>
                  <td className="p-3 font-mono text-neutral-700 dark:text-neutral-300">
                    RM {q.baseRate.toFixed(2)}
                  </td>
                  <td className="p-3 font-mono text-neutral-500 dark:text-neutral-400">
                    RM {q.sst.toFixed(2)}
                  </td>
                  <td className="p-3 font-mono text-neutral-500 dark:text-neutral-400">
                    RM {q.fuelSurcharge.toFixed(2)}
                  </td>
                  {isCod && (
                    <td className="p-3 font-mono text-rose-600 font-semibold">
                      RM {q.codFee.toFixed(2)}
                    </td>
                  )}
                  <td className="p-3 text-neutral-600 dark:text-neutral-400 flex items-center gap-1">
                    <Clock className="w-3 h-3 text-neutral-400" />
                    {q.deliveryDays}
                  </td>
                  <td className="p-3 text-right font-mono font-bold text-sm text-neutral-900 dark:text-neutral-100">
                    RM {q.totalFee.toFixed(2)}
                  </td>
                  <td className="p-3 text-center">
                    <span className={`px-2 py-0.5 rounded-full text-[10px] font-bold ${
                      q.recommended
                        ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-700'
                        : 'bg-neutral-100 text-neutral-600 dark:bg-neutral-800 dark:text-neutral-400'
                    }`}>
                      {q.badge}
                    </span>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
      <DeleteConfirmationModal isOpen={!!ruleToDelete} itemType="Smart Routing Rule" itemName={ruleToDelete?.name} itemDetails={ruleToDelete ? `Priority Rank: #${ruleToDelete.priority} • Target Carrier: ${couriers.find(courier => courier.courierCode === ruleToDelete.action.preferredCourier)?.name || ruleToDelete.action.preferredCourier}` : undefined} warningMessage={`Deleting "${ruleToDelete?.name}" stops this automated courier selection rule.`} impactItems={['Checkout will no longer use this routing decision.', 'Other routing rules continue in their configured priority order.']} confirmButtonText="Delete Routing Rule" onConfirm={() => { if (ruleToDelete) { onDeleteRule(ruleToDelete.id); setRuleToDelete(null); } }} onClose={() => setRuleToDelete(null)} />
    </div>
  );
};
