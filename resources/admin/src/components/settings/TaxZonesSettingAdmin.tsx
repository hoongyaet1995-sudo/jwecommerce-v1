import React, { useState } from 'react';
import { useAdmin } from '../../context/AdminContext';
import { GeoTaxZone, TaxClass, TaxRate, GlobalTaxEngineSettings } from '../../types/oscommerceSettings';
import {
  Percent,
  Plus,
  Edit2,
  Trash2,
  CheckCircle2,
  Layers,
  MapPin,
  FileText,
  Sliders,
  Calculator,
  ShieldCheck,
  Building,
  Info,
  Check,
  AlertCircle
} from 'lucide-react';

export const TaxZonesSettingAdmin: React.FC = () => {
  const {
    taxZones,
    taxClasses,
    taxRates,
    globalTaxEngine,
    saveGeoTaxZone,
    deleteGeoTaxZone,
    saveTaxClass,
    deleteTaxClass,
    saveTaxRate,
    deleteTaxRate,
    saveGlobalTaxEngine,
    addToast
  } = useAdmin();

  const [activeTab, setActiveTab] = useState<'rates' | 'classes' | 'zones' | 'engine'>('rates');

  // Modals
  const [isRateModalOpen, setIsRateModalOpen] = useState(false);
  const [editingRate, setEditingRate] = useState<TaxRate | null>(null);

  const [isClassModalOpen, setIsClassModalOpen] = useState(false);
  const [editingClass, setEditingClass] = useState<TaxClass | null>(null);

  const [isZoneModalOpen, setIsZoneModalOpen] = useState(false);
  const [editingZone, setEditingZone] = useState<GeoTaxZone | null>(null);

  // Live Inspector Simulator State
  const [simItemPrice, setSimItemPrice] = useState<number>(100);
  const [simSelectedClassId, setSimSelectedClassId] = useState<string>(taxClasses[0]?.id || 'tc_standard');
  const [simSelectedZoneId, setSimSelectedZoneId] = useState<string>(taxZones[0]?.id || 'gz_my_sst');

  // Rate Form
  const [rateForm, setRateForm] = useState<Partial<TaxRate>>({
    taxClassId: 'tc_standard',
    taxClassName: 'Standard Taxable Goods (SST 8%)',
    geoZoneId: 'gz_my_sst',
    geoZoneName: 'Malaysia Standard SST Zone',
    ratePercent: 8.0,
    description: 'Malaysian Sales & Service Tax (SST 8%)',
    priority: 1,
    compound: false,
    isEnabled: true
  });

  // Class Form
  const [classForm, setClassForm] = useState<Partial<TaxClass>>({
    name: '',
    description: '',
    defaultRate: 8.0,
    productCount: 0
  });

  // Zone Form
  const [zoneForm, setZoneForm] = useState<Partial<GeoTaxZone>>({
    name: '',
    description: '',
    countryIds: ['MY'],
    zoneIds: [],
    isActive: true
  });

  // Handlers for Tax Rates
  const handleOpenAddRate = () => {
    setEditingRate(null);
    setRateForm({
      id: 'tr_' + Date.now(),
      taxClassId: taxClasses[0]?.id || 'tc_standard',
      taxClassName: taxClasses[0]?.name || 'Standard Taxable Goods (SST 8%)',
      geoZoneId: taxZones[0]?.id || 'gz_my_sst',
      geoZoneName: taxZones[0]?.name || 'Malaysia Standard SST Zone',
      ratePercent: 8.0,
      description: 'SST 8% Rule',
      priority: 1,
      compound: false,
      isEnabled: true
    });
    setIsRateModalOpen(true);
  };

  const handleOpenEditRate = (rate: TaxRate) => {
    setEditingRate(rate);
    setRateForm({ ...rate });
    setIsRateModalOpen(true);
  };

  const handleSaveRate = (e: React.FormEvent) => {
    e.preventDefault();
    const selClass = taxClasses.find(c => c.id === rateForm.taxClassId);
    const selZone = taxZones.find(z => z.id === rateForm.geoZoneId);

    const finalRate: TaxRate = {
      id: rateForm.id || 'tr_' + Date.now(),
      taxClassId: rateForm.taxClassId || 'tc_standard',
      taxClassName: selClass?.name || 'Standard Taxable Goods (SST 8%)',
      geoZoneId: rateForm.geoZoneId || 'gz_my_sst',
      geoZoneName: selZone?.name || 'Malaysia Standard SST Zone',
      ratePercent: Number(rateForm.ratePercent) || 0,
      description: rateForm.description || 'Tax Rule',
      priority: Number(rateForm.priority) || 1,
      compound: Boolean(rateForm.compound),
      isEnabled: Boolean(rateForm.isEnabled)
    };

    saveTaxRate(finalRate);
    setIsRateModalOpen(false);
  };

  // Tax Classes Handlers
  const handleOpenAddClass = () => {
    setEditingClass(null);
    setClassForm({
      id: 'tc_' + Date.now(),
      name: '',
      description: '',
      defaultRate: 8.0,
      productCount: 0
    });
    setIsClassModalOpen(true);
  };

  const handleSaveClass = (e: React.FormEvent) => {
    e.preventDefault();
    if (!classForm.name?.trim()) return;

    const finalClass: TaxClass = {
      id: classForm.id || 'tc_' + Date.now(),
      name: classForm.name.trim(),
      description: classForm.description || '',
      defaultRate: Number(classForm.defaultRate) || 0,
      productCount: editingClass ? editingClass.productCount : 0
    };

    saveTaxClass(finalClass);
    setIsClassModalOpen(false);
  };

  // Geo Tax Zone Handlers
  const handleOpenAddZone = () => {
    setEditingZone(null);
    setZoneForm({
      id: 'gz_' + Date.now(),
      name: '',
      description: '',
      countryIds: ['MY'],
      zoneIds: [],
      isActive: true
    });
    setIsZoneModalOpen(true);
  };

  const handleSaveZone = (e: React.FormEvent) => {
    e.preventDefault();
    if (!zoneForm.name?.trim()) return;

    const finalZone: GeoTaxZone = {
      id: zoneForm.id || 'gz_' + Date.now(),
      name: zoneForm.name.trim(),
      description: zoneForm.description || '',
      countryIds: zoneForm.countryIds || ['MY'],
      zoneIds: zoneForm.zoneIds || [],
      isActive: Boolean(zoneForm.isActive)
    };

    saveGeoTaxZone(finalZone);
    setIsZoneModalOpen(false);
  };

  // Live Inspector Math
  const activeRateMatch = taxRates.find(
    r => r.taxClassId === simSelectedClassId && r.geoZoneId === simSelectedZoneId && r.isEnabled
  );
  const matchedPercentage = activeRateMatch ? activeRateMatch.ratePercent : 0;
  const computedTaxAmount = (simItemPrice * matchedPercentage) / 100;
  const totalPriceWithTax = simItemPrice + computedTaxAmount;

  return (
    <div className="space-y-6">
      {/* Header Banner */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
        <div>
          <div className="flex items-center gap-2">
            <span className="px-2.5 py-0.5 text-[10px] font-bold uppercase rounded-full bg-rose-500/20 text-rose-700 dark:text-rose-300 border border-rose-500/30">
              osCommerce Core Architecture
            </span>
            <span className="text-xs text-neutral-500">Tax Zones, Classes & SST Rate Engine</span>
          </div>
          <h2 className="text-lg sm:text-xl font-bold text-neutral-900 dark:text-white tracking-tight mt-1">
            Tax Zones & SST Setting
          </h2>
          <p className="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5 max-w-3xl">
            Configure the 3-tier osCommerce tax structure (<code className="font-mono text-indigo-600 dark:text-indigo-400">tax_rates</code>, <code className="font-mono text-indigo-600 dark:text-indigo-400">tax_classes</code>, <code className="font-mono text-indigo-600 dark:text-indigo-400">geo_zones</code>). Maps catalog products to specific territorial jurisdictions for accurate SST 8%, zero-rated exemptions, or duty-free calculations.
          </p>
        </div>

        <div className="flex items-center gap-2 self-start sm:self-auto">
          {activeTab === 'rates' && (
            <button
              onClick={handleOpenAddRate}
              className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold shadow-xs transition cursor-pointer"
            >
              <Plus className="w-4 h-4" />
              Add Tax Rate Rule
            </button>
          )}
          {activeTab === 'classes' && (
            <button
              onClick={handleOpenAddClass}
              className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold shadow-xs transition cursor-pointer"
            >
              <Plus className="w-4 h-4" />
              Add Tax Class
            </button>
          )}
          {activeTab === 'zones' && (
            <button
              onClick={handleOpenAddZone}
              className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold shadow-xs transition cursor-pointer"
            >
              <Plus className="w-4 h-4" />
              Add Geo Tax Zone
            </button>
          )}
        </div>
      </div>

      {/* Sub-Navigation Tabs */}
      <div className="flex flex-wrap items-center gap-1.5 p-1.5 bg-neutral-100 dark:bg-neutral-800/60 rounded-2xl border border-neutral-200 dark:border-neutral-700/60 w-fit">
        <button
          onClick={() => setActiveTab('rates')}
          className={`flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold transition ${
            activeTab === 'rates'
              ? 'bg-white dark:bg-neutral-900 text-indigo-600 dark:text-indigo-400 shadow-xs'
              : 'text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white'
          }`}
        >
          <Percent className="w-3.5 h-3.5" />
          <span>Tax Rates ({taxRates.length})</span>
        </button>

        <button
          onClick={() => setActiveTab('classes')}
          className={`flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold transition ${
            activeTab === 'classes'
              ? 'bg-white dark:bg-neutral-900 text-indigo-600 dark:text-indigo-400 shadow-xs'
              : 'text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white'
          }`}
        >
          <FileText className="w-3.5 h-3.5" />
          <span>Tax Classes ({taxClasses.length})</span>
        </button>

        <button
          onClick={() => setActiveTab('zones')}
          className={`flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold transition ${
            activeTab === 'zones'
              ? 'bg-white dark:bg-neutral-900 text-indigo-600 dark:text-indigo-400 shadow-xs'
              : 'text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white'
          }`}
        >
          <MapPin className="w-3.5 h-3.5" />
          <span>Geo Tax Zones ({taxZones.length})</span>
        </button>

        <button
          onClick={() => setActiveTab('engine')}
          className={`flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold transition ${
            activeTab === 'engine'
              ? 'bg-white dark:bg-neutral-900 text-indigo-600 dark:text-indigo-400 shadow-xs'
              : 'text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white'
          }`}
        >
          <Sliders className="w-3.5 h-3.5" />
          <span>Tax Engine Configuration</span>
        </button>
      </div>

      {/* Live Tax Calculator Inspector */}
      <div className="p-4 rounded-2xl bg-indigo-50/60 dark:bg-indigo-950/20 border border-indigo-200 dark:border-indigo-900/60 flex flex-col md:flex-row items-center justify-between gap-4 shadow-xs">
        <div className="flex items-center gap-3">
          <div className="p-2.5 rounded-xl bg-indigo-600 text-white">
            <Calculator className="w-5 h-5" />
          </div>
          <div>
            <h4 className="text-xs font-bold text-neutral-900 dark:text-white uppercase tracking-wider">
              Live Tax Rate Inspector Playground
            </h4>
            <p className="text-[11px] text-neutral-500">Test how Tax Class and Destination Geo Zone dynamically combine</p>
          </div>
        </div>

        <div className="flex flex-wrap items-center gap-3 w-full md:w-auto">
          <div className="space-y-0.5">
            <label className="text-[10px] font-bold text-neutral-500 uppercase">Item Base Price:</label>
            <div className="flex items-center gap-1 bg-white dark:bg-neutral-800 px-2.5 py-1 rounded-lg border border-neutral-300 dark:border-neutral-700 text-xs font-bold">
              <span>RM</span>
              <input
                type="number"
                value={simItemPrice}
                onChange={e => setSimItemPrice(Number(e.target.value) || 0)}
                className="w-16 bg-transparent outline-hidden font-mono"
              />
            </div>
          </div>

          <div className="space-y-0.5">
            <label className="text-[10px] font-bold text-neutral-500 uppercase">Tax Class:</label>
            <select
              value={simSelectedClassId}
              onChange={e => setSimSelectedClassId(e.target.value)}
              className="bg-white dark:bg-neutral-800 px-2.5 py-1.5 rounded-lg border border-neutral-300 dark:border-neutral-700 text-xs font-semibold"
            >
              {taxClasses.map(tc => (
                <option key={tc.id} value={tc.id}>{tc.name}</option>
              ))}
            </select>
          </div>

          <div className="space-y-0.5">
            <label className="text-[10px] font-bold text-neutral-500 uppercase">Delivery Zone:</label>
            <select
              value={simSelectedZoneId}
              onChange={e => setSimSelectedZoneId(e.target.value)}
              className="bg-white dark:bg-neutral-800 px-2.5 py-1.5 rounded-lg border border-neutral-300 dark:border-neutral-700 text-xs font-semibold"
            >
              {taxZones.map(gz => (
                <option key={gz.id} value={gz.id}>{gz.name}</option>
              ))}
            </select>
          </div>

          <div className="p-2 bg-white dark:bg-neutral-900 rounded-xl border border-indigo-300 dark:border-indigo-800 text-right min-w-[120px]">
            <div className="text-[10px] font-bold text-indigo-600 dark:text-indigo-400">
              Tax Rate: {matchedPercentage}%
            </div>
            <div className="text-xs font-bold text-neutral-900 dark:text-white font-mono">
              +RM {computedTaxAmount.toFixed(2)} (RM {totalPriceWithTax.toFixed(2)})
            </div>
          </div>
        </div>
      </div>

      {/* TAB 1: TAX RATES (Active Rules Matrix) */}
      {activeTab === 'rates' && (
        <div className="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 overflow-hidden shadow-xs">
          <div className="p-4 border-b border-neutral-200 dark:border-neutral-800 flex items-center justify-between">
            <h3 className="text-xs font-bold text-neutral-900 dark:text-white uppercase tracking-wider">
              Tax Rate Rules (osCommerce <code className="text-indigo-600 dark:text-indigo-400 font-mono">tax_rates</code>)
            </h3>
            <span className="text-xs text-neutral-500">Maps Class + Geo Zone to %</span>
          </div>

          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs">
              <thead>
                <tr className="bg-neutral-50 dark:bg-neutral-800/60 text-neutral-500 dark:text-neutral-400 font-semibold border-b border-neutral-200 dark:border-neutral-800">
                  <th className="py-3 px-4">Rule Description</th>
                  <th className="py-3 px-4">Tax Class</th>
                  <th className="py-3 px-4">Jurisdiction Geo Zone</th>
                  <th className="py-3 px-4 text-center">Tax % Rate</th>
                  <th className="py-3 px-4 text-center">Priority</th>
                  <th className="py-3 px-4 text-center">Status</th>
                  <th className="py-3 px-4 text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-neutral-200 dark:divide-neutral-800">
                {taxRates.map((tr) => (
                  <tr key={tr.id} className="hover:bg-neutral-50/70 dark:hover:bg-neutral-800/40 transition-colors">
                    <td className="py-3.5 px-4 font-bold text-neutral-900 dark:text-white">
                      {tr.description}
                    </td>
                    <td className="py-3.5 px-4 font-semibold text-indigo-600 dark:text-indigo-400">
                      {tr.taxClassName}
                    </td>
                    <td className="py-3.5 px-4 text-neutral-700 dark:text-neutral-300">
                      <span className="inline-flex items-center gap-1">
                        <MapPin className="w-3 h-3 text-neutral-400" />
                        {tr.geoZoneName}
                      </span>
                    </td>
                    <td className="py-3.5 px-4 text-center">
                      <span className="inline-block px-2.5 py-0.5 rounded-full text-xs font-mono font-bold bg-indigo-100 text-indigo-800 dark:bg-indigo-950 dark:text-indigo-300">
                        {tr.ratePercent.toFixed(2)}%
                      </span>
                    </td>
                    <td className="py-3.5 px-4 text-center font-mono text-neutral-500">
                      P{tr.priority}
                    </td>
                    <td className="py-3.5 px-4 text-center">
                      <span className={`inline-block px-2 py-0.5 rounded-full text-[10px] font-bold ${
                        tr.isEnabled
                          ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300'
                          : 'bg-neutral-100 text-neutral-500 dark:bg-neutral-800 dark:text-neutral-400'
                      }`}>
                        {tr.isEnabled ? 'Active' : 'Disabled'}
                      </span>
                    </td>
                    <td className="py-3.5 px-4 text-right">
                      <div className="flex items-center justify-end gap-1.5">
                        <button
                          onClick={() => handleOpenEditRate(tr)}
                          className="p-1.5 text-neutral-500 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-950/50 rounded-lg transition"
                        >
                          <Edit2 className="w-3.5 h-3.5" />
                        </button>
                        <button
                          onClick={() => deleteTaxRate(tr.id)}
                          className="p-1.5 text-neutral-500 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/50 rounded-lg transition"
                        >
                          <Trash2 className="w-3.5 h-3.5" />
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* TAB 2: TAX CLASSES (Product Categories) */}
      {activeTab === 'classes' && (
        <div className="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 overflow-hidden shadow-xs">
          <div className="p-4 border-b border-neutral-200 dark:border-neutral-800 flex items-center justify-between">
            <h3 className="text-xs font-bold text-neutral-900 dark:text-white uppercase tracking-wider">
              Tax Classes (osCommerce <code className="text-indigo-600 dark:text-indigo-400 font-mono">tax_class</code>)
            </h3>
            <span className="text-xs text-neutral-500">Assigned to catalog products</span>
          </div>

          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs">
              <thead>
                <tr className="bg-neutral-50 dark:bg-neutral-800/60 text-neutral-500 dark:text-neutral-400 font-semibold border-b border-neutral-200 dark:border-neutral-800">
                  <th className="py-3 px-4">Class Title</th>
                  <th className="py-3 px-4">Description</th>
                  <th className="py-3 px-4 text-center">Default Rate</th>
                  <th className="py-3 px-4 text-center">Assigned Products</th>
                  <th className="py-3 px-4 text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-neutral-200 dark:divide-neutral-800">
                {taxClasses.map((tc) => (
                  <tr key={tc.id} className="hover:bg-neutral-50/70 dark:hover:bg-neutral-800/40 transition-colors">
                    <td className="py-3.5 px-4 font-bold text-neutral-900 dark:text-white">
                      {tc.name}
                    </td>
                    <td className="py-3.5 px-4 text-neutral-500">
                      {tc.description}
                    </td>
                    <td className="py-3.5 px-4 text-center font-mono font-bold text-indigo-600 dark:text-indigo-400">
                      {tc.defaultRate}%
                    </td>
                    <td className="py-3.5 px-4 text-center font-bold text-neutral-700 dark:text-neutral-300">
                      {tc.productCount} SKUs
                    </td>
                    <td className="py-3.5 px-4 text-right">
                      <button
                        onClick={() => deleteTaxClass(tc.id)}
                        className="p-1.5 text-neutral-500 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/50 rounded-lg transition"
                      >
                        <Trash2 className="w-3.5 h-3.5" />
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* TAB 3: GEO TAX ZONES */}
      {activeTab === 'zones' && (
        <div className="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 overflow-hidden shadow-xs">
          <div className="p-4 border-b border-neutral-200 dark:border-neutral-800 flex items-center justify-between">
            <h3 className="text-xs font-bold text-neutral-900 dark:text-white uppercase tracking-wider">
              Geo Tax Zones (osCommerce <code className="text-indigo-600 dark:text-indigo-400 font-mono">geo_zones</code>)
            </h3>
            <span className="text-xs text-neutral-500">Group countries/zones for taxation</span>
          </div>

          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs">
              <thead>
                <tr className="bg-neutral-50 dark:bg-neutral-800/60 text-neutral-500 dark:text-neutral-400 font-semibold border-b border-neutral-200 dark:border-neutral-800">
                  <th className="py-3 px-4">Tax Zone Name</th>
                  <th className="py-3 px-4">Description</th>
                  <th className="py-3 px-4">Target Countries</th>
                  <th className="py-3 px-4">Zone Coverage</th>
                  <th className="py-3 px-4 text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-neutral-200 dark:divide-neutral-800">
                {taxZones.map((gz) => (
                  <tr key={gz.id} className="hover:bg-neutral-50/70 dark:hover:bg-neutral-800/40 transition-colors">
                    <td className="py-3.5 px-4 font-bold text-neutral-900 dark:text-white">
                      {gz.name}
                    </td>
                    <td className="py-3.5 px-4 text-neutral-500">
                      {gz.description}
                    </td>
                    <td className="py-3.5 px-4 font-mono font-bold text-indigo-600 dark:text-indigo-400">
                      {gz.countryIds.join(', ')}
                    </td>
                    <td className="py-3.5 px-4">
                      {gz.zoneIds.length === 0 ? (
                        <span className="px-2 py-0.5 text-[10px] font-bold rounded-md bg-neutral-100 dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300">
                          All States / Nationwide
                        </span>
                      ) : (
                        <span className="px-2 py-0.5 text-[10px] font-bold rounded-md bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                          {gz.zoneIds.join(', ')}
                        </span>
                      )}
                    </td>
                    <td className="py-3.5 px-4 text-right">
                      <button
                        onClick={() => deleteGeoTaxZone(gz.id)}
                        className="p-1.5 text-neutral-500 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/50 rounded-lg transition"
                      >
                        <Trash2 className="w-3.5 h-3.5" />
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* TAB 4: TAX ENGINE GLOBAL CONFIGURATION */}
      {activeTab === 'engine' && (
        <div className="p-6 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 space-y-6 shadow-xs max-w-2xl">
          <div className="border-b border-neutral-200 dark:border-neutral-800 pb-3">
            <h3 className="text-sm font-bold text-neutral-900 dark:text-white uppercase tracking-wider">
              Storefront Tax Engine Global Parameters
            </h3>
            <p className="text-xs text-neutral-500">osCommerce calculation basis and catalog display rules</p>
          </div>

          <div className="space-y-4 text-xs">
            <div className="flex items-center justify-between p-3 rounded-xl bg-neutral-50 dark:bg-neutral-800">
              <div>
                <span className="font-bold text-neutral-900 dark:text-white block">Calculate Tax by Address</span>
                <span className="text-[11px] text-neutral-500">Standard osCommerce uses Customer Delivery Address</span>
              </div>
              <select
                value={globalTaxEngine.calculateTaxBasedOn}
                onChange={e => saveGlobalTaxEngine({ calculateTaxBasedOn: e.target.value as any })}
                className="px-3 py-1.5 rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-xs font-semibold"
              >
                <option value="shipping_address">Delivery / Shipping Destination</option>
                <option value="billing_address">Customer Billing Address</option>
                <option value="store_origin">Store Origin Warehouse</option>
              </select>
            </div>

            <div className="flex items-center justify-between p-3 rounded-xl bg-neutral-50 dark:bg-neutral-800">
              <div>
                <span className="font-bold text-neutral-900 dark:text-white block">Display Prices with Tax Included</span>
                <span className="text-[11px] text-neutral-500">Show gross pricing directly on product catalog</span>
              </div>
              <input
                type="checkbox"
                checked={globalTaxEngine.pricesIncludeTax}
                onChange={e => saveGlobalTaxEngine({ pricesIncludeTax: e.target.checked })}
                className="w-4 h-4 rounded text-indigo-600 accent-indigo-600 cursor-pointer"
              />
            </div>
          </div>
        </div>
      )}

      {/* Add / Edit Tax Rate Modal */}
      {isRateModalOpen && (
        <div className="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto">
          <div className="bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-3xl p-6 max-w-lg w-full shadow-2xl space-y-5 animate-in fade-in zoom-in-95 duration-150 my-8">
            <div className="flex items-center justify-between border-b border-neutral-200 dark:border-neutral-800 pb-3">
              <div>
                <h3 className="text-base font-bold text-neutral-900 dark:text-white">
                  {editingRate ? 'Edit Tax Rate Rule' : 'Add New Tax Rate Rule'}
                </h3>
                <p className="text-xs text-neutral-500">osCommerce tax rate mapping</p>
              </div>
              <button
                onClick={() => setIsRateModalOpen(false)}
                className="p-2 rounded-xl text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800 text-xs font-bold"
              >
                ✕
              </button>
            </div>

            <form onSubmit={handleSaveRate} className="space-y-4">
              <div className="space-y-1">
                <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Description / Label *</label>
                <input
                  type="text"
                  required
                  value={rateForm.description || ''}
                  onChange={e => setRateForm({ ...rateForm, description: e.target.value })}
                  placeholder="e.g. Sales & Service Tax (SST 8%)"
                  className="w-full px-3 py-2 rounded-xl border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs"
                />
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                <div className="space-y-1">
                  <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Tax Class</label>
                  <select
                    value={rateForm.taxClassId}
                    onChange={e => setRateForm({ ...rateForm, taxClassId: e.target.value })}
                    className="w-full px-3 py-2 rounded-xl border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs"
                  >
                    {taxClasses.map(c => (
                      <option key={c.id} value={c.id}>{c.name}</option>
                    ))}
                  </select>
                </div>

                <div className="space-y-1">
                  <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Geo Tax Zone</label>
                  <select
                    value={rateForm.geoZoneId}
                    onChange={e => setRateForm({ ...rateForm, geoZoneId: e.target.value })}
                    className="w-full px-3 py-2 rounded-xl border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs"
                  >
                    {taxZones.map(z => (
                      <option key={z.id} value={z.id}>{z.name}</option>
                    ))}
                  </select>
                </div>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                <div className="space-y-1">
                  <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Tax Rate Percentage (%)</label>
                  <input
                    type="number"
                    step="0.01"
                    required
                    value={rateForm.ratePercent ?? 8.0}
                    onChange={e => setRateForm({ ...rateForm, ratePercent: Number(e.target.value) })}
                    className="w-full px-3 py-2 rounded-xl border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs font-mono font-bold"
                  />
                </div>

                <div className="space-y-1">
                  <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Priority Sequence</label>
                  <input
                    type="number"
                    value={rateForm.priority ?? 1}
                    onChange={e => setRateForm({ ...rateForm, priority: Number(e.target.value) })}
                    className="w-full px-3 py-2 rounded-xl border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs font-mono"
                  />
                </div>
              </div>

              <div className="flex items-center justify-end gap-2 pt-3 border-t border-neutral-200 dark:border-neutral-800">
                <button
                  type="button"
                  onClick={() => setIsRateModalOpen(false)}
                  className="px-4 py-2 rounded-xl border border-neutral-300 dark:border-neutral-700 text-xs font-semibold text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold shadow-xs"
                >
                  Save Tax Rate
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};
