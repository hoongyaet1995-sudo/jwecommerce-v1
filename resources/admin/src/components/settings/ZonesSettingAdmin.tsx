import React, { useState } from 'react';
import { useAdmin } from '../../context/AdminContext';
import { ZoneSetting } from '../../types/oscommerceSettings';
import {
  Map,
  Plus,
  Edit2,
  Trash2,
  CheckCircle2,
  XCircle,
  Search,
  Truck,
  Building2,
  Layers,
  Sparkles,
  MapPin,
  HelpCircle
} from 'lucide-react';

export const ZonesSettingAdmin: React.FC = () => {
  const {
    zonesSetting,
    saveZoneSetting,
    toggleZoneStatus,
    deleteZoneSetting,
    countriesSetting,
    setCurrentSection,
    addToast
  } = useAdmin();

  const [selectedCountryId, setSelectedCountryId] = useState<string>('MY');
  const [searchTerm, setSearchTerm] = useState('');
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [editingZone, setEditingZone] = useState<ZoneSetting | null>(null);

  // Form State
  const [formData, setFormData] = useState<Partial<ZoneSetting>>({
    countryId: 'MY',
    countryName: 'Malaysia',
    zoneName: '',
    zoneCode: '',
    regionType: 'Peninsular Malaysia',
    shippingTier: 'Standard Domestic',
    isEnabled: true,
    postalPrefixes: []
  });

  const [postalPrefixInput, setPostalPrefixInput] = useState('');

  const filteredZones = zonesSetting.filter(z => {
    const matchesCountry = selectedCountryId === 'all' || z.countryId === selectedCountryId;
    const matchesSearch =
      z.zoneName.toLowerCase().includes(searchTerm.toLowerCase()) ||
      z.zoneCode.toLowerCase().includes(searchTerm.toLowerCase()) ||
      z.regionType.toLowerCase().includes(searchTerm.toLowerCase()) ||
      z.postalPrefixes.some(p => p.includes(searchTerm));

    return matchesCountry && matchesSearch;
  });

  const handleOpenAdd = () => {
    setEditingZone(null);
    const country = countriesSetting.find(c => c.id === selectedCountryId) || countriesSetting[0];
    setFormData({
      id: `${country.id}-ZONE-${Date.now().toString().slice(-4)}`,
      countryId: country.id,
      countryName: country.name,
      zoneName: '',
      zoneCode: '',
      regionType: 'Peninsular Malaysia',
      shippingTier: 'Standard Domestic',
      isEnabled: true,
      postalPrefixes: []
    });
    setPostalPrefixInput('');
    setIsModalOpen(true);
  };

  const handleOpenEdit = (zone: ZoneSetting) => {
    setEditingZone(zone);
    setFormData({ ...zone });
    setPostalPrefixInput(zone.postalPrefixes.join(', '));
    setIsModalOpen(true);
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!formData.zoneName?.trim() || !formData.zoneCode?.trim()) {
      addToast({ type: 'error', title: 'Fields Required', message: 'Please enter Zone Name and Code.' });
      return;
    }

    const country = countriesSetting.find(c => c.id === formData.countryId) || { id: 'MY', name: 'Malaysia' };
    const prefixes = postalPrefixInput
      .split(/[, ]+/)
      .map(p => p.trim())
      .filter(Boolean);

    const finalZone: ZoneSetting = {
      id: formData.id || `${country.id}-${formData.zoneCode.trim().toUpperCase()}`,
      countryId: country.id,
      countryName: country.name,
      zoneName: formData.zoneName.trim(),
      zoneCode: formData.zoneCode.trim().toUpperCase(),
      regionType: formData.regionType || 'Peninsular Malaysia',
      shippingTier: formData.shippingTier || 'Standard Domestic',
      isEnabled: Boolean(formData.isEnabled),
      postalPrefixes: prefixes
    };

    saveZoneSetting(finalZone);
    setIsModalOpen(false);
  };

  const getRegionBadge = (regionType: ZoneSetting['regionType']) => {
    switch (regionType) {
      case 'East Malaysia (Borneo)':
        return 'bg-amber-100 text-amber-800 border-amber-300 dark:bg-amber-950/70 dark:text-amber-300 dark:border-amber-800';
      case 'Federal Territory':
        return 'bg-purple-100 text-purple-800 border-purple-300 dark:bg-purple-950/70 dark:text-purple-300 dark:border-purple-800';
      case 'Singapore District':
        return 'bg-blue-100 text-blue-800 border-blue-300 dark:bg-blue-950/70 dark:text-blue-300 dark:border-blue-800';
      default:
        return 'bg-indigo-100 text-indigo-800 border-indigo-300 dark:bg-indigo-950/70 dark:text-indigo-300 dark:border-indigo-800';
    }
  };

  return (
    <div className="space-y-6">
      {/* Header Banner */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
        <div>
          <div className="flex items-center gap-2">
            <span className="px-2.5 py-0.5 text-[10px] font-bold uppercase rounded-full bg-blue-500/20 text-blue-700 dark:text-blue-300 border border-blue-500/30">
              osCommerce Core Architecture
            </span>
            <span className="text-xs text-neutral-500">States, Provinces & Regional Divisions</span>
          </div>
          <h2 className="text-lg sm:text-xl font-bold text-neutral-900 dark:text-white tracking-tight mt-1">
            Zones Setting
          </h2>
          <p className="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5 max-w-3xl">
            Configure country administrative sub-divisions (osCommerce <code className="font-mono text-indigo-600 dark:text-indigo-400">zones</code> table). Governs state dropdowns on checkout, connects postcode prefixes to courier rate tables, and defines Peninsular vs East Malaysia logistics routing.
          </p>
        </div>

        <button
          onClick={handleOpenAdd}
          className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold shadow-xs transition cursor-pointer self-start sm:self-auto"
        >
          <Plus className="w-4 h-4" />
          Add State / Zone
        </button>
      </div>

      {/* Filter and Country Selector Bar */}
      <div className="p-4 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs flex flex-col md:flex-row items-center justify-between gap-3">
        <div className="flex items-center gap-3 w-full md:w-auto">
          <label className="text-xs font-bold text-neutral-600 dark:text-neutral-400 whitespace-nowrap">
            Filter Country:
          </label>
          <select
            value={selectedCountryId}
            onChange={(e) => setSelectedCountryId(e.target.value)}
            className="px-3.5 py-2 text-xs font-semibold rounded-xl border bg-neutral-50 dark:bg-neutral-800 border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white"
          >
            <option value="all">All Countries ({zonesSetting.length} zones)</option>
            {countriesSetting.map(c => (
              <option key={c.id} value={c.id}>
                {c.flagEmoji} {c.name} ({zonesSetting.filter(z => z.countryId === c.id).length} zones)
              </option>
            ))}
          </select>
        </div>

        <div className="relative w-full md:w-80">
          <Search className="w-4 h-4 text-neutral-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
          <input
            type="text"
            placeholder="Search state, code, postcode prefix (e.g. 40, 50, SGR)..."
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            className="w-full pl-9 pr-4 py-2 text-xs rounded-xl border bg-neutral-50 dark:bg-neutral-800 border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white"
          />
        </div>
      </div>

      {/* Zones Table */}
      <div className="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 overflow-hidden shadow-xs">
        <div className="p-4 border-b border-neutral-200 dark:border-neutral-800 flex items-center justify-between">
          <h3 className="text-xs font-bold text-neutral-900 dark:text-white uppercase tracking-wider">
            Active States & Regional Zones ({filteredZones.length})
          </h3>
          <button
            onClick={() => setCurrentSection('shipping-malaysia')}
            className="text-xs text-indigo-600 dark:text-indigo-400 font-semibold hover:underline flex items-center gap-1"
          >
            <Truck className="w-3.5 h-3.5" />
            <span>Open Courier Zone Matrix →</span>
          </button>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs">
            <thead>
              <tr className="bg-neutral-50 dark:bg-neutral-800/60 text-neutral-500 dark:text-neutral-400 font-semibold border-b border-neutral-200 dark:border-neutral-800">
                <th className="py-3 px-4">State / Zone Name</th>
                <th className="py-3 px-4">Zone Code</th>
                <th className="py-3 px-4">Country</th>
                <th className="py-3 px-4">Region Type</th>
                <th className="py-3 px-4">Shipping Tier</th>
                <th className="py-3 px-4">Postcode Match Prefixes</th>
                <th className="py-3 px-4 text-center">Status</th>
                <th className="py-3 px-4 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-200 dark:divide-neutral-800">
              {filteredZones.map((z) => (
                <tr key={z.id} className="hover:bg-neutral-50/70 dark:hover:bg-neutral-800/40 transition-colors">
                  <td className="py-3.5 px-4 font-bold text-neutral-900 dark:text-white">
                    <div className="flex items-center gap-2">
                      <MapPin className="w-3.5 h-3.5 text-indigo-500" />
                      <span>{z.zoneName}</span>
                    </div>
                  </td>
                  <td className="py-3.5 px-4 font-mono font-bold text-neutral-700 dark:text-neutral-300">
                    {z.zoneCode}
                  </td>
                  <td className="py-3.5 px-4 text-neutral-600 dark:text-neutral-400">
                    {z.countryName}
                  </td>
                  <td className="py-3.5 px-4">
                    <span className={`inline-block px-2.5 py-0.5 rounded-full text-[10px] font-bold border ${getRegionBadge(z.regionType)}`}>
                      {z.regionType}
                    </span>
                  </td>
                  <td className="py-3.5 px-4 text-neutral-600 dark:text-neutral-400">
                    <span className="inline-flex items-center gap-1 bg-neutral-100 dark:bg-neutral-800 px-2 py-0.5 rounded-md text-[11px]">
                      <Truck className="w-2.5 h-2.5 text-neutral-500" />
                      {z.shippingTier}
                    </span>
                  </td>
                  <td className="py-3.5 px-4">
                    <div className="flex flex-wrap gap-1 max-w-xs">
                      {z.postalPrefixes.slice(0, 5).map(p => (
                        <span key={p} className="px-1.5 py-0.2 bg-neutral-100 dark:bg-neutral-800 rounded font-mono text-[10px] text-neutral-700 dark:text-neutral-300">
                          {p}*
                        </span>
                      ))}
                      {z.postalPrefixes.length > 5 && (
                        <span className="text-[10px] text-neutral-400">+{z.postalPrefixes.length - 5} more</span>
                      )}
                    </div>
                  </td>
                  <td className="py-3.5 px-4 text-center">
                    <button
                      onClick={() => toggleZoneStatus(z.id)}
                      className={`inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold border transition ${
                        z.isEnabled
                          ? 'bg-emerald-100 text-emerald-800 border-emerald-300 dark:bg-emerald-950 dark:text-emerald-300 dark:border-emerald-800'
                          : 'bg-neutral-100 text-neutral-500 border-neutral-300 dark:bg-neutral-800 dark:text-neutral-400 dark:border-neutral-700'
                      }`}
                    >
                      {z.isEnabled ? 'Active' : 'Disabled'}
                    </button>
                  </td>
                  <td className="py-3.5 px-4 text-right">
                    <div className="flex items-center justify-end gap-1.5">
                      <button
                        onClick={() => handleOpenEdit(z)}
                        className="p-1.5 text-neutral-500 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-950/50 rounded-lg transition"
                        title="Edit Zone"
                      >
                        <Edit2 className="w-3.5 h-3.5" />
                      </button>
                      <button
                        onClick={() => deleteZoneSetting(z.id)}
                        className="p-1.5 text-neutral-500 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/50 rounded-lg transition"
                        title="Delete Zone"
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

      {/* Edit / Add Zone Modal */}
      {isModalOpen && (
        <div className="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto">
          <div className="bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-3xl p-6 max-w-xl w-full shadow-2xl space-y-5 animate-in fade-in zoom-in-95 duration-150 my-8">
            <div className="flex items-center justify-between border-b border-neutral-200 dark:border-neutral-800 pb-3">
              <div>
                <h3 className="text-base font-bold text-neutral-900 dark:text-white">
                  {editingZone ? 'Edit Administrative Zone' : 'Add State / Province Zone'}
                </h3>
                <p className="text-xs text-neutral-500">osCommerce zone definition and postcode routing rules</p>
              </div>
              <button
                onClick={() => setIsModalOpen(false)}
                className="p-2 rounded-xl text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800 text-xs font-bold"
              >
                ✕
              </button>
            </div>

            <form onSubmit={handleSubmit} className="space-y-4">
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                <div className="space-y-1">
                  <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Parent Country</label>
                  <select
                    value={formData.countryId}
                    onChange={e => {
                      const c = countriesSetting.find(item => item.id === e.target.value);
                      setFormData({ ...formData, countryId: e.target.value, countryName: c?.name || '' });
                    }}
                    className="w-full px-3 py-2 rounded-xl border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs text-neutral-900 dark:text-white"
                  >
                    {countriesSetting.map(c => (
                      <option key={c.id} value={c.id}>{c.flagEmoji} {c.name}</option>
                    ))}
                  </select>
                </div>

                <div className="space-y-1">
                  <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Zone Code *</label>
                  <input
                    type="text"
                    required
                    placeholder="e.g. SGR, KUL, SBH"
                    value={formData.zoneCode || ''}
                    onChange={e => setFormData({ ...formData, zoneCode: e.target.value.toUpperCase() })}
                    className="w-full px-3 py-2 rounded-xl border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs font-mono uppercase text-neutral-900 dark:text-white"
                  />
                </div>
              </div>

              <div className="space-y-1">
                <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Zone / State Display Name *</label>
                <input
                  type="text"
                  required
                  placeholder="e.g. Selangor, Penang, Sabah"
                  value={formData.zoneName || ''}
                  onChange={e => setFormData({ ...formData, zoneName: e.target.value })}
                  className="w-full px-3 py-2 rounded-xl border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs text-neutral-900 dark:text-white"
                />
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                <div className="space-y-1">
                  <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Regional Classification</label>
                  <select
                    value={formData.regionType || 'Peninsular Malaysia'}
                    onChange={e => setFormData({ ...formData, regionType: e.target.value as any })}
                    className="w-full px-3 py-2 rounded-xl border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs text-neutral-900 dark:text-white"
                  >
                    <option value="Peninsular Malaysia">Peninsular Malaysia (West)</option>
                    <option value="East Malaysia (Borneo)">East Malaysia (Sabah/Sarawak)</option>
                    <option value="Federal Territory">Federal Territory</option>
                    <option value="Singapore District">Singapore District</option>
                    <option value="International State">International State</option>
                  </select>
                </div>

                <div className="space-y-1">
                  <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Shipping Logistics Tier</label>
                  <select
                    value={formData.shippingTier || 'Standard Domestic'}
                    onChange={e => setFormData({ ...formData, shippingTier: e.target.value as any })}
                    className="w-full px-3 py-2 rounded-xl border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs text-neutral-900 dark:text-white"
                  >
                    <option value="Standard Domestic">Standard Domestic Land Route</option>
                    <option value="Air Freight East MY">Air Freight East MY (Borneo)</option>
                    <option value="Express Metro">Express Metro Same-Day</option>
                    <option value="Duty Free Port">Duty Free Port (Labuan/Langkawi)</option>
                  </select>
                </div>
              </div>

              <div className="space-y-1.5 p-3.5 rounded-2xl bg-neutral-50 dark:bg-neutral-800/50 border border-neutral-200 dark:border-neutral-700">
                <div className="flex items-center justify-between">
                  <label className="text-xs font-bold text-neutral-900 dark:text-white">Postal Code Prefix Triggers</label>
                  <span className="text-[10px] text-neutral-500">Comma-separated e.g. 40, 41, 42, 46, 47</span>
                </div>
                <input
                  type="text"
                  placeholder="40, 41, 42, 43, 46, 47, 48"
                  value={postalPrefixInput}
                  onChange={e => setPostalPrefixInput(e.target.value)}
                  className="w-full px-3 py-2 rounded-xl border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs font-mono text-neutral-900 dark:text-white"
                />
              </div>

              <label className="flex items-center gap-2 text-xs text-neutral-700 dark:text-neutral-300 cursor-pointer pt-1">
                <input
                  type="checkbox"
                  checked={Boolean(formData.isEnabled)}
                  onChange={e => setFormData({ ...formData, isEnabled: e.target.checked })}
                  className="w-4 h-4 rounded text-indigo-600 accent-indigo-600 cursor-pointer"
                />
                <span>Active for Checkout & Courier Routing</span>
              </label>

              <div className="flex items-center justify-end gap-2 pt-3 border-t border-neutral-200 dark:border-neutral-800">
                <button
                  type="button"
                  onClick={() => setIsModalOpen(false)}
                  className="px-4 py-2 rounded-xl border border-neutral-300 dark:border-neutral-700 text-xs font-semibold text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold shadow-xs"
                >
                  Save Zone
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};
