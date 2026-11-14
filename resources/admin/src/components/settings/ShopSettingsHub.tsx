import {SearchVisibilitySettings} from '../search/SearchVisibilitySettings';
import React, { useState, useEffect, useRef } from 'react';
import { useAdmin } from '../../context/AdminContext';
import { NavSection } from '../../types';
import { DEFAULT_JW_STUDIO_LOGO } from '../../data/defaultLogo';
import { UserLoginSettingAdmin } from './UserLoginSettingAdmin';
import { SocialLinkSettingAdmin } from './SocialLinkSettingAdmin';
import { PaymentSettingAdmin } from './PaymentSettingAdmin';
import { OrderStatusSettingAdmin } from './OrderStatusSettingAdmin';
import { CountriesSettingAdmin } from './CountriesSettingAdmin';
import { ZonesSettingAdmin } from './ZonesSettingAdmin';
import { CurrenciesSettingAdmin } from './CurrenciesSettingAdmin';
import { OrderTotalSettingAdmin } from './OrderTotalSettingAdmin';
import { TaxClassesSettingAdmin } from './TaxClassesSettingAdmin';
import { CouriersSettingAdmin } from './CouriersSettingAdmin';
import { NotificationTemplateSettingAdmin } from './NotificationTemplateSettingAdmin';
import { ReturnPolicySettingCard } from './ReturnPolicySettingCard';
import { AIAutoReplyStudioHub } from '../ai-auto-reply/AIAutoReplyStudioHub';
import {
  Store,
  Award,
  FileText,
  Mail,
  Share2,
  Sliders,
  Truck,
  Users,
  Clock,
  Globe,
  Map,
  DollarSign,
  Calculator,
  Percent,
  CheckCircle2,
  Save,
  Building,
  CreditCard,
  Send,
  HelpCircle,
  Sparkles,
  Upload,
  Image as ImageIcon,
  RotateCcw,
  Trash2,
  Check,
  Phone,
  Bot
} from 'lucide-react';

export const SETTING_TABS: { id: NavSection; label: string; icon: React.ElementType }[] = [
  { id: 'settings-users', label: 'User Login Setting', icon: Users },
  { id: 'settings-search', label: 'Search Visibility', icon: Globe },
  { id: 'settings-shop', label: 'My Shop Setting', icon: Store },
  { id: 'settings-invoice', label: 'Invoice Information Setting', icon: FileText },
  { id: 'settings-email', label: 'Auto Email & WhatsApp Template', icon: Mail },
  { id: 'settings-ai-agent', label: 'AI Auto Reply & Bot', icon: Bot },
  { id: 'settings-social', label: 'Social Link Setting', icon: Share2 },
  { id: 'settings-general', label: 'General Setting', icon: Sliders },
  { id: 'settings-agent-group', label: 'Agent Group Setting', icon: Users },
  { id: 'settings-orders-status', label: 'Order Status', icon: Clock },
  { id: 'settings-shipment-status', label: 'Shipment Status', icon: Clock },
  { id: 'settings-countries', label: 'Countries Setting', icon: Globe },
  { id: 'settings-zones', label: 'Zones Setting', icon: Map },
  { id: 'settings-currencies', label: 'Currencies', icon: DollarSign },
  { id: 'settings-order-total', label: 'Order Total', icon: Calculator },
  { id: 'settings-tax-classes', label: 'Tax Classes', icon: Percent },
];

export const SettingNavBar: React.FC<{ currentSection: NavSection; setCurrentSection: (s: NavSection) => void }> = ({
  currentSection,
  setCurrentSection
}) => {
  return (
    <div className="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 p-2 shadow-xs overflow-x-auto scrollbar-thin">
      <div className="flex items-center gap-1.5 min-w-max">
        {SETTING_TABS.map(tab => {
          const Icon = tab.icon;
          const isActive = currentSection === tab.id;
          return (
            <button
              key={tab.id}
              onClick={() => setCurrentSection(tab.id)}
              className={`flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-semibold transition cursor-pointer ${
                isActive
                  ? 'bg-indigo-600 text-white shadow-xs'
                  : 'text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800'
              }`}
            >
              <Icon className="w-4 h-4" />
              <span>{tab.label}</span>
            </button>
          );
        })}
      </div>
    </div>
  );
};

export const ShopSettingsHub: React.FC = () => {
  const { currentSection, setCurrentSection, shopSettings, saveShopSettings, addToast, catalogListingSettings, saveCatalogListingSettings } = useAdmin();

  // Local form state for General & Shop Settings
  const [formData, setFormData] = useState({ ...shopSettings });
  const fileInputRef = useRef<HTMLInputElement>(null);
  const [isDragOver, setIsDragOver] = useState(false);
  const [savingProfile, setSavingProfile] = useState(false);
  const [catalogListingForm, setCatalogListingForm] = useState(catalogListingSettings);

  useEffect(() => {
    setFormData({ ...shopSettings });
  }, [shopSettings]);

  useEffect(() => {
    setCatalogListingForm(catalogListingSettings);
  }, [catalogListingSettings]);

  const handleCatalogListingSave = async () => {
    await saveCatalogListingSettings(catalogListingForm);
  };

  const handleFileUpload = (file: File) => {
    if (!file) return;
    if (!['image/png', 'image/jpeg', 'image/svg+xml', 'image/webp'].includes(file.type) || file.size > 5 * 1024 * 1024) {
      addToast({
        title: 'Invalid File Type',
          message: 'Please upload a PNG, JPG, SVG, or WebP image up to 5 MB.',
        type: 'error'
      });
      return;
    }

    const reader = new FileReader();
    reader.onload = (e) => {
      const dataUrl = e.target?.result as string;
      if (dataUrl) {
        setFormData(prev => ({ ...prev, shopLogo: dataUrl }));
        addToast({
          title: 'Logo Uploaded',
          message: 'Company logo loaded successfully. Click Save Shop Profile to apply.',
          type: 'success'
        });
      }
    };
    reader.readAsDataURL(file);
  };

  const handleSave = async (e: React.FormEvent) => {
    e.preventDefault();
    if (savingProfile) return;
    setSavingProfile(true);
    try {
    await saveShopSettings(formData);
    addToast({
      title: 'Shop Profile Saved',
      message: `${formData.shopName || 'JW Studio'} profile and company logo updated successfully.`,
      type: 'success'
    });
    } catch (error) {
      addToast({ type: 'error', title: 'Shop Profile Not Saved', message: error instanceof Error ? error.message : 'Please try again.' });
    } finally { setSavingProfile(false); }
  };

  // Dedicated Setting Sub-Views
  if (currentSection === 'settings-search') return <div className="space-y-6"><SettingNavBar currentSection={currentSection} setCurrentSection={setCurrentSection}/><SearchVisibilitySettings/></div>;

  if (currentSection === 'settings-users') {
    return (
      <UserLoginSettingAdmin
        settingsNavigation={<SettingNavBar currentSection={currentSection} setCurrentSection={setCurrentSection} />}
      />
    );
  }

  if (currentSection === 'settings-payment') {
    return <PaymentSettingAdmin />;
  }

  if (currentSection === 'settings-email') {
    return (
      <div className="p-4 sm:p-6 lg:p-8 space-y-6 max-w-7xl mx-auto">
        <SettingNavBar currentSection={currentSection} setCurrentSection={setCurrentSection} />
        <NotificationTemplateSettingAdmin />
      </div>
    );
  }

  if ((currentSection === 'settings-orders-status' || currentSection === 'settings-shipment-status')) {
    return (
      <div className="p-4 sm:p-6 lg:p-8 space-y-6 max-w-7xl mx-auto">
        <SettingNavBar currentSection={currentSection} setCurrentSection={setCurrentSection} />
        <OrderStatusSettingAdmin scope={currentSection === 'settings-shipment-status' ? 'shipment' : 'order'} />
      </div>
    );
  }

  if (currentSection === 'settings-countries') {
    return (
      <div className="p-4 sm:p-6 lg:p-8 space-y-6 max-w-7xl mx-auto">
        <SettingNavBar currentSection={currentSection} setCurrentSection={setCurrentSection} />
        <CountriesSettingAdmin />
      </div>
    );
  }

  if (currentSection === 'settings-zones') {
    return (
      <div className="p-4 sm:p-6 lg:p-8 space-y-6 max-w-7xl mx-auto">
        <SettingNavBar currentSection={currentSection} setCurrentSection={setCurrentSection} />
        <ZonesSettingAdmin />
      </div>
    );
  }

  if (currentSection === 'settings-currencies') {
    return (
      <div className="p-4 sm:p-6 lg:p-8 space-y-6 max-w-7xl mx-auto">
        <SettingNavBar currentSection={currentSection} setCurrentSection={setCurrentSection} />
        <CurrenciesSettingAdmin />
      </div>
    );
  }

  if (currentSection === 'settings-order-total') {
    return (
      <div className="p-4 sm:p-6 lg:p-8 space-y-6 max-w-7xl mx-auto">
        <SettingNavBar currentSection={currentSection} setCurrentSection={setCurrentSection} />
        <OrderTotalSettingAdmin />
      </div>
    );
  }

  if (currentSection === 'settings-tax-classes' || currentSection === 'settings-tax-zones') {
    return (
      <div className="p-4 sm:p-6 lg:p-8 space-y-6 max-w-7xl mx-auto">
        <SettingNavBar currentSection="settings-tax-classes" setCurrentSection={setCurrentSection} />
        <TaxClassesSettingAdmin />
      </div>
    );
  }

  if (currentSection === 'settings-courier') {
    return (
      <div className="p-4 sm:p-6 lg:p-8 space-y-6 max-w-7xl mx-auto">
        <SettingNavBar currentSection={currentSection} setCurrentSection={setCurrentSection} />
        <CouriersSettingAdmin />
      </div>
    );
  }

  return (
    <div className="p-4 sm:p-6 lg:p-8 space-y-6 max-w-7xl mx-auto">
      {/* Top Setting Navigation Bar */}
      <div className="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 p-2 shadow-xs overflow-x-auto scrollbar-thin">
        <div className="flex items-center gap-1.5 min-w-max">
          {SETTING_TABS.map(tab => {
            const Icon = tab.icon;
            const isActive = currentSection === tab.id;
            return (
              <button
                key={tab.id}
                onClick={() => setCurrentSection(tab.id)}
                className={`flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-semibold transition cursor-pointer ${
                  isActive
                    ? 'bg-indigo-600 text-white shadow-xs'
                    : 'text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800'
                }`}
              >
                <Icon className="w-4 h-4" />
                <span>{tab.label}</span>
              </button>
            );
          })}
        </div>
      </div>

      {/* Dynamic Sub-Settings Panels */}
      {currentSection === 'settings-ai-agent' && (
        <AIAutoReplyStudioHub />
      )}

      {currentSection === 'settings-shop' && (
        <>
        <div className="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 p-6 sm:p-8 shadow-sm space-y-6">
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-neutral-200 dark:border-neutral-800">
            <div>
              <h2 className="text-xl font-bold text-neutral-900 dark:text-white flex items-center gap-2">
                <Store className="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
                My Shop Setting
              </h2>
              <p className="text-xs text-neutral-500 mt-0.5">Manage store brand profile, company logo, registered address, and official customer contacts.</p>
            </div>
            <span className="self-start sm:self-auto inline-flex items-center gap-1.5 px-3 py-1 text-xs font-semibold rounded-full bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
              <span className="w-2 h-2 rounded-full bg-emerald-500 animate-pulse" />
              Live In Sidebar & Header
            </span>
          </div>

          <form onSubmit={handleSave} className="space-y-6 max-w-3xl">
            {/* Company Logo Upload & Visual Preview */}
            <div className="p-5 rounded-2xl bg-neutral-50 dark:bg-neutral-950/50 border border-neutral-200 dark:border-neutral-800 space-y-4">
              <div className="flex items-center justify-between">
                <div>
                  <label className="text-xs font-bold text-neutral-900 dark:text-white flex items-center gap-1.5">
                    <ImageIcon className="w-4 h-4 text-indigo-600 dark:text-indigo-400" />
                    Company Logo
                  </label>
                  <p className="text-[11px] text-neutral-500 mt-0.5">
                    This logo appears in the top-left sidebar header, invoices, and brand navigation.
                  </p>
                </div>
                <button
                  type="button"
                  onClick={() => setFormData(prev => ({ ...prev, shopLogo: DEFAULT_JW_STUDIO_LOGO }))}
                  className="inline-flex items-center gap-1.5 px-2.5 py-1 text-[11px] font-medium text-neutral-600 dark:text-neutral-400 hover:text-indigo-600 dark:hover:text-indigo-400 hover:bg-neutral-200/60 dark:hover:bg-neutral-800 rounded-lg transition"
                >
                  <RotateCcw className="w-3.5 h-3.5" />
                  Reset to JW Studio Logo
                </button>
              </div>

              {/* Logo Preview & Upload Dropzone */}
              <div className="grid grid-cols-1 md:grid-cols-12 gap-4 items-center">
                {/* Logo Preview Cards */}
                <div className="md:col-span-4 flex items-center gap-3">
                  <div className="flex flex-col items-center gap-1">
                    <div className="w-20 h-20 rounded-2xl bg-neutral-950 border border-neutral-800 p-2 flex items-center justify-center shadow-lg shadow-black/20 relative group overflow-hidden">
                      {formData.shopLogo ? (
                        <img
                          src={formData.shopLogo}
                          alt="Logo Preview (Dark)"
                          className="w-full h-full object-contain"
                          referrerPolicy="no-referrer"
                        />
                      ) : (
                        <ImageIcon className="w-8 h-8 text-neutral-600" />
                      )}
                      <span className="absolute bottom-1 right-1 px-1 py-0.2 rounded bg-black/80 text-[8px] text-neutral-400 font-mono">
                        Dark
                      </span>
                    </div>
                    <span className="text-[10px] text-neutral-500">Dark Sidebar</span>
                  </div>

                  <div className="flex flex-col items-center gap-1">
                    <div className="w-20 h-20 rounded-2xl bg-white border border-neutral-200 p-2 flex items-center justify-center shadow-xs relative group overflow-hidden">
                      {formData.shopLogo ? (
                        <img
                          src={formData.shopLogo}
                          alt="Logo Preview (Light)"
                          className="w-full h-full object-contain"
                          referrerPolicy="no-referrer"
                        />
                      ) : (
                        <ImageIcon className="w-8 h-8 text-neutral-400" />
                      )}
                      <span className="absolute bottom-1 right-1 px-1 py-0.2 rounded bg-neutral-100 text-[8px] text-neutral-600 font-mono">
                        Light
                      </span>
                    </div>
                    <span className="text-[10px] text-neutral-500">Light View</span>
                  </div>
                </div>

                {/* Upload Action Area */}
                <div className="md:col-span-8 flex flex-col justify-center space-y-2">
                  <input
                    type="file"
                    ref={fileInputRef}
                    onChange={(e) => {
                      if (e.target.files && e.target.files[0]) {
                        handleFileUpload(e.target.files[0]);
                      }
                    }}
                    accept="image/png,image/jpeg,image/svg+xml,image/webp,image/gif"
                    className="hidden"
                  />

                  <div
                    onDragOver={(e) => {
                      e.preventDefault();
                      setIsDragOver(true);
                    }}
                    onDragLeave={() => setIsDragOver(false)}
                    onDrop={(e) => {
                      e.preventDefault();
                      setIsDragOver(false);
                      if (e.dataTransfer.files && e.dataTransfer.files[0]) {
                        handleFileUpload(e.dataTransfer.files[0]);
                      }
                    }}
                    onClick={() => fileInputRef.current?.click()}
                    className={`border-2 border-dashed rounded-xl p-3.5 flex items-center justify-center gap-3 cursor-pointer transition-all ${
                      isDragOver
                        ? 'border-indigo-500 bg-indigo-50/50 dark:bg-indigo-950/30'
                        : 'border-neutral-300 dark:border-neutral-700 hover:border-indigo-400 dark:hover:border-indigo-600 hover:bg-white dark:hover:bg-neutral-900'
                    }`}
                  >
                    <div className="p-2 rounded-lg bg-indigo-100 dark:bg-indigo-950 text-indigo-600 dark:text-indigo-400">
                      <Upload className="w-4 h-4" />
                    </div>
                    <div>
                      <div className="text-xs font-semibold text-neutral-800 dark:text-neutral-200">
                        Click to upload or drag & drop logo file
                      </div>
                      <p className="text-[10px] text-neutral-500">Supports PNG, SVG, JPG or WebP (square aspect ratio recommended)</p>
                    </div>
                  </div>

                  <div className="flex items-center gap-2 pt-1">
                    <span className="text-[10px] text-neutral-500 flex-shrink-0 font-medium">Or Image URL:</span>
                    <input
                      type="text"
                      placeholder="https://example.com/logo.png"
                      value={formData.shopLogo?.startsWith('data:') ? '' : formData.shopLogo}
                      onChange={e => setFormData({ ...formData, shopLogo: e.target.value })}
                      className="flex-1 px-2.5 py-1 text-xs rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 placeholder-neutral-400"
                    />
                  </div>
                </div>
              </div>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div className="space-y-1.5">
                <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Shop Name / Brand Name</label>
                <input
                  type="text"
                  value={formData.shopName}
                  onChange={e => setFormData({ ...formData, shopName: e.target.value })}
                  placeholder="e.g. JW Studio"
                  className="w-full px-3.5 py-2 rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs font-medium"
                />
              </div>
              <div className="space-y-1.5">
                <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Storefront Currency</label>
                <input
                  type="text"
                  value={formData.currency}
                  onChange={e => setFormData({ ...formData, currency: e.target.value })}
                  className="w-full px-3.5 py-2 rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs"
                />
              </div>
            </div>

            <div className="space-y-1.5">
              <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Brand Tagline</label>
              <input
                type="text"
                value={formData.tagline}
                onChange={e => setFormData({ ...formData, tagline: e.target.value })}
                placeholder="e.g. Enterprise AI & Omnichannel Commerce Studio"
                className="w-full px-3.5 py-2 rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs"
              />
            </div>

            <div className="p-5 rounded-2xl bg-neutral-50 dark:bg-neutral-950/50 border border-neutral-200 dark:border-neutral-800 space-y-4">
              <div>
                <label className="text-xs font-bold text-neutral-900 dark:text-white flex items-center gap-1.5">
                  <Store className="w-4 h-4 text-indigo-600 dark:text-indigo-400" />
                  Public Catalog Listing Copy
                </label>
                <p className="text-[11px] text-neutral-500 mt-0.5">This controls the eyebrow, title, and introduction on the global /shop page. Collection pages use their own collection settings.</p>
              </div>
              <div className="space-y-4">
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div className="space-y-1.5">
                    <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Listing Eyebrow</label>
                    <input type="text" value={catalogListingForm.eyebrow} onChange={e => setCatalogListingForm({ ...catalogListingForm, eyebrow: e.target.value })} placeholder="Catalog & Product Listing" className="w-full px-3.5 py-2 rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs" />
                  </div>
                  <div className="space-y-1.5">
                    <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Listing Title</label>
                    <input type="text" value={catalogListingForm.title} onChange={e => setCatalogListingForm({ ...catalogListingForm, title: e.target.value })} placeholder="All JW Studio Collections" className="w-full px-3.5 py-2 rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs" />
                  </div>
                </div>
                <div className="space-y-1.5">
                  <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Listing Description</label>
                  <textarea rows={3} value={catalogListingForm.description} onChange={e => setCatalogListingForm({ ...catalogListingForm, description: e.target.value })} placeholder="Describe the global catalog selection." className="w-full px-3.5 py-2 rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs" />
                </div>
                <button type="button" onClick={() => void handleCatalogListingSave()} className="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold transition cursor-pointer shadow-md shadow-indigo-500/20">
                  <Save className="w-4 h-4" />
                  Save Public Catalog Copy
                </button>
              </div>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div className="space-y-1.5">
                <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300 flex items-center gap-1.5">
                  <Mail className="w-3.5 h-3.5 text-neutral-500" />
                  Support Email
                </label>
                <input
                  type="email"
                  value={formData.email}
                  onChange={e => setFormData({ ...formData, email: e.target.value })}
                  placeholder="joshua@jwaistudio.online"
                  className="w-full px-3.5 py-2 rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs"
                />
              </div>
              <div className="space-y-1.5">
                <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300 flex items-center gap-1.5">
                  <Phone className="w-3.5 h-3.5 text-neutral-500" />
                  Telephone / Hotline
                </label>
                <input
                  type="text"
                  value={formData.phone || formData.whatsapp}
                  onChange={e => setFormData({ ...formData, phone: e.target.value, whatsapp: e.target.value })}
                  placeholder="+0142244200"
                  className="w-full px-3.5 py-2 rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs"
                />
              </div>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div className="space-y-1.5">
                <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Business Registration No.</label>
                <input
                  type="text"
                  value={formData.businessRegNo || ''}
                  onChange={e => setFormData({ ...formData, businessRegNo: e.target.value })}
                  placeholder="e.g. 202601008899 (1456789-W)"
                  className="w-full px-3.5 py-2 rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs font-mono"
                />
              </div>
              <div className="space-y-1.5">
                <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">SST Number</label>
                <input
                  type="text"
                  value={formData.sstNumber || ''}
                  onChange={e => setFormData({ ...formData, sstNumber: e.target.value })}
                  placeholder="e.g. W10-2401-32000456"
                  className="w-full px-3.5 py-2 rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs font-mono"
                />
              </div>
            </div>

            <div className="space-y-1.5">
              <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Registered Business Address</label>
              <textarea
                rows={3}
                value={formData.address}
                onChange={e => setFormData({ ...formData, address: e.target.value })}
                placeholder="e.g. Level 28, Menara Horizon, 8 Jalan Bangsar Utama, 59000 Kuala Lumpur"
                className="w-full px-3.5 py-2 rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs"
              />
            </div>

            <div className="flex items-center gap-3 pt-2">
              <button
                type="submit"
                disabled={savingProfile}
                className="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold transition cursor-pointer shadow-md shadow-indigo-500/20"
              >
                <Save className="w-4 h-4" />
                {savingProfile ? 'Saving Profile & Logo…' : 'Save Shop Profile & Logo'}
              </button>
            </div>
          </form>
        </div>
        <ReturnPolicySettingCard />
        </>
      )}

      {currentSection === 'settings-invoice' && (
        <div className="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 p-6 sm:p-8 shadow-sm space-y-6">
          <div>
            <h2 className="text-xl font-bold text-neutral-900 dark:text-white">Invoice Information Setting</h2>
            <p className="text-xs text-neutral-500 mt-0.5">Official company registration numbers, SST tax credentials, and PDF invoice layout templates.</p>
          </div>

          <form onSubmit={handleSave} className="space-y-5 max-w-2xl">
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div className="space-y-1.5">
                <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Company Registration No (SSM)</label>
                <input
                  type="text"
                  value={formData.businessRegNo}
                  onChange={e => setFormData({ ...formData, businessRegNo: e.target.value })}
                  className="w-full px-3.5 py-2 rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs"
                />
              </div>
              <div className="space-y-1.5">
                <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">SST Tax ID / TIN</label>
                <input
                  type="text"
                  value={formData.sstNumber}
                  onChange={e => setFormData({ ...formData, sstNumber: e.target.value })}
                  className="w-full px-3.5 py-2 rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs"
                />
              </div>
            </div>

            <div className="space-y-1.5">
              <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Invoice Number Prefix</label>
              <input
                type="text"
                value={formData.orderPrefix}
                onChange={e => setFormData({ ...formData, orderPrefix: e.target.value })}
                className="w-full px-3.5 py-2 rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs"
              />
            </div>

            <button
              type="submit"
              className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold transition cursor-pointer"
            >
              <Save className="w-4 h-4" />
              Save Invoice Settings
            </button>
          </form>
        </div>
      )}

      {/* Social Link Setting (1:1 with uploaded screenshot) */}
      {currentSection === 'settings-social' && (
        <SocialLinkSettingAdmin />
      )}

      {/* Fallback for other modular setting views */}
      {!['settings-ai-agent', 'settings-shop', 'settings-points', 'settings-invoice', 'settings-users', 'settings-social', 'settings-courier'].includes(currentSection) && (
        <div className="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 p-8 shadow-sm text-center space-y-4">
          <div className="w-12 h-12 rounded-2xl bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400 flex items-center justify-center mx-auto">
            <Sliders className="w-6 h-6" />
          </div>
          <div>
            <h3 className="text-base font-bold text-neutral-900 dark:text-white capitalize">
              {currentSection.replace('settings-', '').replace('-', ' ')} Setting
            </h3>
            <p className="text-xs text-neutral-500 max-w-md mx-auto mt-1">
              This configuration module is active and synced with your Aurora Ecommerce Engine. You can switch to <strong className="text-indigo-600 dark:text-indigo-400 cursor-pointer" onClick={() => setCurrentSection('settings-users')}>User Login Setting</strong> to add and manage staff RBAC access.
            </p>
          </div>
          <button
            type="button"
            onClick={() => setCurrentSection('settings-users')}
            className="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-indigo-900 text-white text-xs font-semibold hover:bg-indigo-950 transition cursor-pointer"
          >
            <Users className="w-4 h-4" />
            Manage Staff Logins
          </button>
        </div>
      )}
    </div>
  );
};
