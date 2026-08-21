import React, { useState } from 'react';
import { useAdmin } from '../../context/AdminContext';
import { CountrySetting } from '../../types/oscommerceSettings';
import {
  Globe,
  Plus,
  Edit2,
  CheckCircle2,
  XCircle,
  Search,
  MapPin,
  FileText,
  Eye,
  Sliders,
  Check,
  AlertCircle,
  ArrowRight
} from 'lucide-react';

export const CountriesSettingAdmin: React.FC = () => {
  const { countriesSetting, saveCountrySetting, toggleCountryStatus, setCurrentSection, addToast } = useAdmin();

  const [searchTerm, setSearchTerm] = useState('');
  const [filterActive, setFilterActive] = useState<'all' | 'enabled' | 'disabled'>('all');
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [editingCountry, setEditingCountry] = useState<CountrySetting | null>(null);
  const [addressPreviewModal, setAddressPreviewModal] = useState<CountrySetting | null>(null);

  // Form state
  const [formData, setFormData] = useState<Partial<CountrySetting>>({
    id: '',
    name: '',
    isoCode2: '',
    isoCode3: '',
    dialCode: '+60',
    currencyCode: 'MYR',
    addressFormat: '{street}\n{postcode} {city}\n{zone}, {country}',
    postcodeRequired: true,
    isEnabled: true,
    flagEmoji: '🌐',
    totalZones: 0
  });

  const filteredCountries = countriesSetting.filter(c => {
    const matchesSearch =
      c.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
      c.isoCode2.toLowerCase().includes(searchTerm.toLowerCase()) ||
      c.isoCode3.toLowerCase().includes(searchTerm.toLowerCase()) ||
      c.dialCode.includes(searchTerm);

    const matchesStatus =
      filterActive === 'all' ||
      (filterActive === 'enabled' && c.isEnabled) ||
      (filterActive === 'disabled' && !c.isEnabled);

    return matchesSearch && matchesStatus;
  });

  const handleOpenAdd = () => {
    setEditingCountry(null);
    setFormData({
      id: '',
      name: '',
      isoCode2: '',
      isoCode3: '',
      dialCode: '+',
      currencyCode: 'USD',
      addressFormat: '{street}\n{city}, {zone} {postcode}\n{country}',
      postcodeRequired: true,
      isEnabled: true,
      flagEmoji: '🌐',
      totalZones: 0
    });
    setIsModalOpen(true);
  };

  const handleOpenEdit = (country: CountrySetting) => {
    setEditingCountry(country);
    setFormData({ ...country });
    setIsModalOpen(true);
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!formData.name?.trim() || !formData.isoCode2?.trim()) {
      addToast({ type: 'error', title: 'Fields Required', message: 'Please enter Country Name and ISO Alpha-2 Code.' });
      return;
    }

    const iso2 = formData.isoCode2.trim().toUpperCase();
    const finalCountry: CountrySetting = {
      id: formData.id || iso2,
      name: formData.name.trim(),
      isoCode2: iso2,
      isoCode3: (formData.isoCode3 || iso2 + 'X').trim().toUpperCase(),
      dialCode: formData.dialCode?.trim() || '+',
      currencyCode: (formData.currencyCode || 'USD').trim().toUpperCase(),
      addressFormat: formData.addressFormat || '{street}\n{city}, {zone} {postcode}\n{country}',
      postcodeRequired: Boolean(formData.postcodeRequired),
      isEnabled: Boolean(formData.isEnabled),
      isPopular: Boolean(formData.isPopular),
      totalZones: Number(formData.totalZones) || 0,
      flagEmoji: formData.flagEmoji || '🌐'
    };

    saveCountrySetting(finalCountry);
    setIsModalOpen(false);
  };

  // Helper to render address format demo
  const renderFormattedAddress = (country: CountrySetting) => {
    return country.addressFormat
      .replace('{street}', 'No. 18, Jalan Telawi 3, Bangsar Baru')
      .replace('{city}', 'Kuala Lumpur')
      .replace('{postcode}', '59100')
      .replace('{zone}', 'WP Kuala Lumpur')
      .replace('{country}', country.name);
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
        <div>
          <div className="flex items-center gap-2">
            <span className="px-2.5 py-0.5 text-[10px] font-bold uppercase rounded-full bg-emerald-500/20 text-emerald-700 dark:text-emerald-300 border border-emerald-500/30">
              osCommerce Core Architecture
            </span>
            <span className="text-xs text-neutral-500">Countries & Address Protocols</span>
          </div>
          <h2 className="text-lg sm:text-xl font-bold text-neutral-900 dark:text-white tracking-tight mt-1">
            Countries Setting
          </h2>
          <p className="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5 max-w-3xl">
            Manage global destination countries (osCommerce <code className="font-mono text-indigo-600 dark:text-indigo-400">countries</code> table). Controls checkout shipping eligibility, international dial codes, postal code validation rules, and country-specific invoice address layout formats.
          </p>
        </div>

        <button
          onClick={handleOpenAdd}
          className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold shadow-xs transition cursor-pointer self-start sm:self-auto"
        >
          <Plus className="w-4 h-4" />
          Add Destination Country
        </button>
      </div>

      {/* Filter and Search Bar */}
      <div className="p-4 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs flex flex-col md:flex-row items-center justify-between gap-3">
        <div className="relative w-full md:w-80">
          <Search className="w-4 h-4 text-neutral-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
          <input
            type="text"
            placeholder="Search country, ISO code, dial prefix..."
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            className="w-full pl-9 pr-4 py-2 text-xs rounded-xl border bg-neutral-50 dark:bg-neutral-800 border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white"
          />
        </div>

        <div className="flex items-center gap-2 w-full md:w-auto">
          <select
            value={filterActive}
            onChange={(e) => setFilterActive(e.target.value as any)}
            className="px-3 py-2 text-xs rounded-xl border bg-neutral-50 dark:bg-neutral-800 border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white"
          >
            <option value="all">All Statuses ({countriesSetting.length})</option>
            <option value="enabled">Enabled for Checkout ({countriesSetting.filter(c => c.isEnabled).length})</option>
            <option value="disabled">Disabled / Restricted ({countriesSetting.filter(c => !c.isEnabled).length})</option>
          </select>
        </div>
      </div>

      {/* Countries Grid / Table */}
      <div className="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 overflow-hidden shadow-xs">
        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs">
            <thead>
              <tr className="bg-neutral-50 dark:bg-neutral-800/60 text-neutral-500 dark:text-neutral-400 font-semibold border-b border-neutral-200 dark:border-neutral-800">
                <th className="py-3 px-4">Country & Flag</th>
                <th className="py-3 px-4">ISO-2 / ISO-3</th>
                <th className="py-3 px-4">Dial Code</th>
                <th className="py-3 px-4">Currency</th>
                <th className="py-3 px-4">Address Format Protocol</th>
                <th className="py-3 px-4 text-center">Zones (States)</th>
                <th className="py-3 px-4 text-center">Checkout Status</th>
                <th className="py-3 px-4 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-200 dark:divide-neutral-800">
              {filteredCountries.map((c) => (
                <tr key={c.id} className="hover:bg-neutral-50/70 dark:hover:bg-neutral-800/40 transition-colors">
                  <td className="py-3.5 px-4">
                    <div className="flex items-center gap-2.5">
                      <span className="text-xl" role="img" aria-label={c.name}>{c.flagEmoji}</span>
                      <div>
                        <div className="font-bold text-neutral-900 dark:text-white flex items-center gap-1.5">
                          {c.name}
                          {c.isPopular && (
                            <span className="px-1.5 py-0.2 text-[9px] font-bold rounded bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                              Primary
                            </span>
                          )}
                        </div>
                        <div className="text-[10px] text-neutral-400">
                          {c.postcodeRequired ? 'Postal Code Mandatory' : 'Postal Code Optional'}
                        </div>
                      </div>
                    </div>
                  </td>
                  <td className="py-3.5 px-4 font-mono font-semibold text-neutral-700 dark:text-neutral-300">
                    {c.isoCode2} / {c.isoCode3}
                  </td>
                  <td className="py-3.5 px-4 font-mono text-neutral-600 dark:text-neutral-400">
                    {c.dialCode}
                  </td>
                  <td className="py-3.5 px-4 font-mono text-xs font-bold text-indigo-600 dark:text-indigo-400">
                    {c.currencyCode}
                  </td>
                  <td className="py-3.5 px-4">
                    <button
                      onClick={() => setAddressPreviewModal(c)}
                      className="inline-flex items-center gap-1 text-[11px] text-neutral-600 dark:text-neutral-400 hover:text-indigo-600 dark:hover:text-indigo-400 font-mono bg-neutral-100 dark:bg-neutral-800 px-2 py-1 rounded-lg transition"
                    >
                      <Eye className="w-3 h-3 text-indigo-500" />
                      <span>Preview Format</span>
                    </button>
                  </td>
                  <td className="py-3.5 px-4 text-center">
                    <button
                      onClick={() => setCurrentSection('settings-zones')}
                      className="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-neutral-100 hover:bg-neutral-200 dark:bg-neutral-800 dark:hover:bg-neutral-700 text-xs font-bold text-neutral-700 dark:text-neutral-300 transition"
                      title="Manage States / Zones for this country"
                    >
                      <span>{c.totalZones}</span>
                      <ArrowRight className="w-3 h-3 text-neutral-400" />
                    </button>
                  </td>
                  <td className="py-3.5 px-4 text-center">
                    <button
                      onClick={() => toggleCountryStatus(c.id)}
                      className={`inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold border transition ${
                        c.isEnabled
                          ? 'bg-emerald-100 text-emerald-800 border-emerald-300 dark:bg-emerald-950 dark:text-emerald-300 dark:border-emerald-800'
                          : 'bg-neutral-100 text-neutral-500 border-neutral-300 dark:bg-neutral-800 dark:text-neutral-400 dark:border-neutral-700'
                      }`}
                    >
                      {c.isEnabled ? <CheckCircle2 className="w-3 h-3" /> : <XCircle className="w-3 h-3" />}
                      <span>{c.isEnabled ? 'Enabled' : 'Disabled'}</span>
                    </button>
                  </td>
                  <td className="py-3.5 px-4 text-right">
                    <button
                      onClick={() => handleOpenEdit(c)}
                      className="p-1.5 text-neutral-500 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-950/50 rounded-lg transition"
                      title="Edit Country Properties"
                    >
                      <Edit2 className="w-3.5 h-3.5" />
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      {/* Edit / Add Country Modal */}
      {isModalOpen && (
        <div className="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto">
          <div className="bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-3xl p-6 max-w-xl w-full shadow-2xl space-y-5 animate-in fade-in zoom-in-95 duration-150 my-8">
            <div className="flex items-center justify-between border-b border-neutral-200 dark:border-neutral-800 pb-3">
              <div>
                <h3 className="text-base font-bold text-neutral-900 dark:text-white">
                  {editingCountry ? 'Edit Country Configuration' : 'Add New Destination Country'}
                </h3>
                <p className="text-xs text-neutral-500">osCommerce country definition and address layout protocol</p>
              </div>
              <button
                onClick={() => setIsModalOpen(false)}
                className="p-2 rounded-xl text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800 text-xs font-bold"
              >
                ✕
              </button>
            </div>

            <form onSubmit={handleSubmit} className="space-y-4">
              <div className="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
                <div className="sm:col-span-2 space-y-1">
                  <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Country Name *</label>
                  <input
                    type="text"
                    required
                    placeholder="e.g. Malaysia"
                    value={formData.name || ''}
                    onChange={e => setFormData({ ...formData, name: e.target.value })}
                    className="w-full px-3 py-2 rounded-xl border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs text-neutral-900 dark:text-white"
                  />
                </div>

                <div className="space-y-1">
                  <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Flag Emoji</label>
                  <input
                    type="text"
                    placeholder="🇲🇾"
                    value={formData.flagEmoji || ''}
                    onChange={e => setFormData({ ...formData, flagEmoji: e.target.value })}
                    className="w-full px-3 py-2 rounded-xl border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs text-center"
                  />
                </div>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div className="space-y-1">
                  <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">ISO Code (2-Letter) *</label>
                  <input
                    type="text"
                    maxLength={2}
                    required
                    placeholder="MY"
                    value={formData.isoCode2 || ''}
                    onChange={e => setFormData({ ...formData, isoCode2: e.target.value.toUpperCase() })}
                    className="w-full px-3 py-2 rounded-xl border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs font-mono uppercase"
                  />
                </div>

                <div className="space-y-1">
                  <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">ISO Code (3-Letter)</label>
                  <input
                    type="text"
                    maxLength={3}
                    placeholder="MYS"
                    value={formData.isoCode3 || ''}
                    onChange={e => setFormData({ ...formData, isoCode3: e.target.value.toUpperCase() })}
                    className="w-full px-3 py-2 rounded-xl border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs font-mono uppercase"
                  />
                </div>

                <div className="space-y-1">
                  <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Dialing Code</label>
                  <input
                    type="text"
                    placeholder="+60"
                    value={formData.dialCode || ''}
                    onChange={e => setFormData({ ...formData, dialCode: e.target.value })}
                    className="w-full px-3 py-2 rounded-xl border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs font-mono"
                  />
                </div>
              </div>

              {/* Address Format Protocol */}
              <div className="space-y-1.5 p-3.5 rounded-2xl bg-neutral-50 dark:bg-neutral-800/50 border border-neutral-200 dark:border-neutral-700">
                <div className="flex items-center justify-between">
                  <label className="text-xs font-bold text-neutral-900 dark:text-white">Address Format Template</label>
                  <span className="text-[10px] text-neutral-500">Tokens: &#123;street&#125;, &#123;city&#125;, &#123;zone&#125;, &#123;postcode&#125;, &#123;country&#125;</span>
                </div>
                <textarea
                  rows={3}
                  value={formData.addressFormat || ''}
                  onChange={e => setFormData({ ...formData, addressFormat: e.target.value })}
                  className="w-full px-3 py-2 rounded-xl border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs font-mono text-neutral-900 dark:text-white"
                />
              </div>

              {/* Toggles */}
              <div className="flex flex-wrap gap-4 pt-1">
                <label className="flex items-center gap-2 text-xs text-neutral-700 dark:text-neutral-300 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={Boolean(formData.isEnabled)}
                    onChange={e => setFormData({ ...formData, isEnabled: e.target.checked })}
                    className="w-4 h-4 rounded text-indigo-600 accent-indigo-600 cursor-pointer"
                  />
                  <span>Enable for Customer Storefront Checkout</span>
                </label>

                <label className="flex items-center gap-2 text-xs text-neutral-700 dark:text-neutral-300 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={Boolean(formData.postcodeRequired)}
                    onChange={e => setFormData({ ...formData, postcodeRequired: e.target.checked })}
                    className="w-4 h-4 rounded text-indigo-600 accent-indigo-600 cursor-pointer"
                  />
                  <span>Require Postal Code</span>
                </label>

                <label className="flex items-center gap-2 text-xs text-neutral-700 dark:text-neutral-300 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={Boolean(formData.isPopular)}
                    onChange={e => setFormData({ ...formData, isPopular: e.target.checked })}
                    className="w-4 h-4 rounded text-indigo-600 accent-indigo-600 cursor-pointer"
                  />
                  <span>Top Suggested Destination</span>
                </label>
              </div>

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
                  Save Country
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Address Format Preview Modal */}
      {addressPreviewModal && (
        <div className="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-3xl p-6 max-w-md w-full shadow-2xl space-y-4 animate-in fade-in zoom-in-95 duration-150">
            <div className="flex items-center justify-between border-b border-neutral-200 dark:border-neutral-800 pb-3">
              <div className="flex items-center gap-2">
                <span className="text-2xl">{addressPreviewModal.flagEmoji}</span>
                <div>
                  <h3 className="text-sm font-bold text-neutral-900 dark:text-white">
                    {addressPreviewModal.name} Address Protocol
                  </h3>
                  <p className="text-[11px] text-neutral-500">osCommerce formatted output on invoices and thermal waybills</p>
                </div>
              </div>
              <button
                onClick={() => setAddressPreviewModal(null)}
                className="text-neutral-400 hover:text-neutral-600 text-xs font-bold"
              >
                ✕
              </button>
            </div>

            <div className="p-4 rounded-2xl bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 space-y-3">
              <div className="text-[11px] font-semibold text-neutral-500">Template Definition:</div>
              <pre className="text-xs bg-white dark:bg-neutral-900 p-2.5 rounded-xl border border-neutral-200 dark:border-neutral-700 text-indigo-600 dark:text-indigo-400 font-mono">
                {addressPreviewModal.addressFormat}
              </pre>

              <div className="text-[11px] font-semibold text-neutral-500 pt-1">Sample Rendered Label:</div>
              <div className="text-xs bg-white dark:bg-neutral-900 p-3 rounded-xl border border-neutral-200 dark:border-neutral-700 text-neutral-800 dark:text-neutral-200 font-sans whitespace-pre-line leading-relaxed shadow-xs">
                {renderFormattedAddress(addressPreviewModal)}
              </div>
            </div>

            <button
              onClick={() => setAddressPreviewModal(null)}
              className="w-full py-2 rounded-xl bg-neutral-100 hover:bg-neutral-200 dark:bg-neutral-800 dark:hover:bg-neutral-700 text-xs font-semibold text-neutral-800 dark:text-neutral-200"
            >
              Close
            </button>
          </div>
        </div>
      )}
    </div>
  );
};
