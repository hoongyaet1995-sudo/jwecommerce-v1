import React, { useState, useEffect } from 'react';
import { useAdmin } from '../../context/AdminContext';
import { Product, ProductOption, ProductVariant } from '../../types';
import {
  X,
  Plus,
  Trash2,
  Sparkles,
  Layers,
  Image as ImageIcon,
  DollarSign,
  Package,
  Wand2,
  Check,
  Globe,
  Tag,
  Upload,
  Copy,
  AlertCircle
} from 'lucide-react';

interface ProductEditorModalProps {
  product: Product | null;
  isOpen: boolean;
  onClose: () => void;
}

export const ProductEditorModal: React.FC<ProductEditorModalProps> = ({
  product,
  isOpen,
  onClose
}) => {
  const { categories, collections, saveProduct } = useAdmin();

  const [activeTab, setActiveTab] = useState<'basic' | 'variants' | 'media' | 'seo'>('basic');
  const [formData, setFormData] = useState<Product | null>(null);

  // New option tag input temporary states
  const [newOptionName, setNewOptionName] = useState('');
  const [newOptionValueInputs, setNewOptionValueInputs] = useState<Record<string, string>>({});

  // Batch action states for variant matrix
  const [batchPrice, setBatchPrice] = useState<string>('');
  const [batchInventory, setBatchInventory] = useState<string>('');
  const [batchSkuPrefix, setBatchSkuPrefix] = useState<string>('');

  useEffect(() => {
    if (product) {
      setFormData(JSON.parse(JSON.stringify(product)));
    } else {
      const defaultProd: Product = {
        id: 'prod-' + Date.now(),
        title: '',
        slug: '',
        brand: '',
        categoryId: categories[0]?.id || '',
        collectionIds: [],
        summary: '',
        descriptionHtml: '<p>Engineered with precision for modern lifestyle and professional reliability.</p>',
        images: [
          'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=800&auto=format&fit=crop&q=80'
        ],
        status: 'active',
        hasVariants: true,
        basePrice: 199,
        baseCompareAtPrice: 249,
        featured: false,
        tags: ['flagship', 'new-release'],
        totalInventory: 100,
        options: [
          { id: 'opt-1', name: 'Color', values: ['Midnight Black', 'Arctic White'] },
          { id: 'opt-2', name: 'Size', values: ['Regular', 'Max Edition'] }
        ],
        variants: [],
        createdAt: new Date().toISOString().split('T')[0],
        updatedAt: new Date().toISOString().split('T')[0]
      };
      // auto generate default variants
      defaultProd.variants = generateCartesianVariants(defaultProd.options, defaultProd.title || 'PROD', defaultProd.basePrice);
      setFormData(defaultProd);
    }
  }, [product, isOpen]);

  if (!isOpen || !formData) return null;

  // Cartesian Product Generator Algorithm
  function generateCartesianVariants(options: ProductOption[], baseTitle: string, price: number): ProductVariant[] {
    const validOptions = options.filter(o => o.name.trim() !== '' && o.values.length > 0);
    if (validOptions.length === 0) {
      return [{
        id: 'var-' + Date.now(),
        sku: (baseTitle.slice(0, 3).toUpperCase() || 'SKU') + '-DEF',
        title: 'Default Variant',
        options: {},
        price: price,
        compareAtPrice: price * 1.2,
        inventoryQuantity: 50,
        weightGrams: 300,
        enabled: true
      }];
    }

    // Cartesian product recursive helper
    const cartesian = (arrays: string[][]): string[][] => {
      return arrays.reduce<string[][]>((a, b) => a.flatMap(d => b.map(e => [...d, e])), [[]]);
    };

    const valueArrays = validOptions.map(o => o.values);
    const combinations = cartesian(valueArrays);

    const prefix = (formData?.brand?.slice(0, 3) || formData?.title?.slice(0, 3) || 'SKU').toUpperCase().replace(/[^A-Z]/g, '') || 'AUR';

    return combinations.map((comb, index) => {
      const optionMap: Record<string, string> = {};
      validOptions.forEach((opt, idx) => {
        optionMap[opt.name] = comb[idx];
      });

      const variantTitle = comb.join(' / ');
      const skuCode = `${prefix}-${comb.map(c => c.slice(0, 3).toUpperCase().replace(/[^A-Z0-9]/g, '')).join('-')}`;

      return {
        id: `var-${Date.now()}-${index}`,
        sku: skuCode,
        title: variantTitle,
        options: optionMap,
        price: price,
        compareAtPrice: Math.round(price * 1.2),
        costPrice: Math.round(price * 0.4),
        inventoryQuantity: 40,
        weightGrams: 350,
        image: formData?.images[0],
        enabled: true
      };
    });
  }

  const handleRegenerateMatrix = () => {
    if (!formData) return;
    const newVariants = generateCartesianVariants(formData.options, formData.title, formData.basePrice);
    const totalStock = newVariants.reduce((sum, v) => sum + v.inventoryQuantity, 0);
    setFormData({
      ...formData,
      variants: newVariants,
      totalInventory: totalStock
    });
  };

  // Option modifications
  const addOptionDimension = () => {
    if (!newOptionName.trim()) return;
    const newOpt: ProductOption = {
      id: 'opt-' + Date.now(),
      name: newOptionName.trim(),
      values: []
    };
    setFormData({
      ...formData,
      options: [...formData.options, newOpt]
    });
    setNewOptionName('');
  };

  const removeOptionDimension = (optId: string) => {
    setFormData({
      ...formData,
      options: formData.options.filter(o => o.id !== optId)
    });
  };

  const addOptionValue = (optId: string) => {
    const val = (newOptionValueInputs[optId] || '').trim();
    if (!val) return;
    setFormData({
      ...formData,
      options: formData.options.map(o => {
        if (o.id !== optId) return o;
        if (o.values.includes(val)) return o;
        return { ...o, values: [...o.values, val] };
      })
    });
    setNewOptionValueInputs(prev => ({ ...prev, [optId]: '' }));
  };

  const removeOptionValue = (optId: string, valueToRemove: string) => {
    setFormData({
      ...formData,
      options: formData.options.map(o => {
        if (o.id !== optId) return o;
        return { ...o, values: o.values.filter(v => v !== valueToRemove) };
      })
    });
  };

  // Batch Variant Matrix updates
  const applyBatchPrice = () => {
    const priceNum = parseFloat(batchPrice);
    if (isNaN(priceNum)) return;
    setFormData({
      ...formData,
      variants: formData.variants.map(v => ({ ...v, price: priceNum, compareAtPrice: Math.round(priceNum * 1.2) }))
    });
    setBatchPrice('');
  };

  const applyBatchInventory = () => {
    const qtyNum = parseInt(batchInventory, 10);
    if (isNaN(qtyNum)) return;
    const updated = formData.variants.map(v => ({ ...v, inventoryQuantity: qtyNum }));
    setFormData({
      ...formData,
      variants: updated,
      totalInventory: updated.reduce((sum, v) => sum + v.inventoryQuantity, 0)
    });
    setBatchInventory('');
  };

  const applyBatchSkuPrefix = () => {
    if (!batchSkuPrefix.trim()) return;
    const cleanPrefix = batchSkuPrefix.trim().toUpperCase();
    setFormData({
      ...formData,
      variants: formData.variants.map((v, idx) => ({
        ...v,
        sku: `${cleanPrefix}-${Object.values(v.options).map(c => String(c).slice(0, 3).toUpperCase()).join('-') || idx + 1}`
      }))
    });
    setBatchSkuPrefix('');
  };

  const updateVariantRow = (index: number, key: keyof ProductVariant, val: any) => {
    const updated = [...formData.variants];
    updated[index] = { ...updated[index], [key]: val };
    const totalStock = updated.reduce((sum, v) => sum + (v.inventoryQuantity || 0), 0);
    setFormData({
      ...formData,
      variants: updated,
      totalInventory: totalStock
    });
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    const saved = await saveProduct(formData);
    if (saved) onClose();
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-6 bg-black/70 backdrop-blur-xs animate-in fade-in">
      <div
        id="product-editor-modal"
        className="w-full max-w-5xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-3xl shadow-2xl overflow-hidden flex flex-col max-h-[92vh]"
      >
        {/* Modal Header */}
        <div className="px-6 py-4 border-b border-neutral-200 dark:border-neutral-800 flex items-center justify-between bg-white dark:bg-neutral-900 sticky top-0 z-20">
          <div className="flex items-center gap-3">
            <div className="w-9 h-9 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center">
              <Package className="w-5 h-5" />
            </div>
            <div>
              <h2 className="text-base font-bold text-neutral-900 dark:text-white">
                {formData.title ? `Product: ${formData.title}` : 'New Product & Variant Matrix'}
              </h2>
              <p className="text-[11px] text-neutral-500">
                {formData.variants.length} SKUs in Cartesian Matrix • Status: {formData.status}
              </p>
            </div>
          </div>

          <div className="flex items-center gap-2">
            <button
              onClick={onClose}
              className="p-2 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200 rounded-xl"
            >
              <X className="w-5 h-5" />
            </button>
          </div>
        </div>

        {/* Tab Navigation */}
        <div className="px-6 border-b border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-950 flex gap-2">
          <button
            onClick={() => setActiveTab('basic')}
            className={`py-3 px-3 text-xs font-semibold border-b-2 transition-all ${
              activeTab === 'basic'
                ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400'
                : 'border-transparent text-neutral-500 hover:text-neutral-900 dark:hover:text-white'
            }`}
          >
            1. Basic Info & Pricing
          </button>

          <button
            onClick={() => setActiveTab('variants')}
            className={`py-3 px-3 text-xs font-semibold border-b-2 transition-all flex items-center gap-1.5 ${
              activeTab === 'variants'
                ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400'
                : 'border-transparent text-neutral-500 hover:text-neutral-900 dark:hover:text-white'
            }`}
          >
            <Sparkles className="w-3.5 h-3.5" />
            <span>2. Variant Matrix Generator</span>
            <span className="px-1.5 py-0.2 rounded-full bg-indigo-100 dark:bg-indigo-900/60 text-[10px] font-mono">
              {formData.variants.length}
            </span>
          </button>

          <button
            onClick={() => setActiveTab('media')}
            className={`py-3 px-3 text-xs font-semibold border-b-2 transition-all ${
              activeTab === 'media'
                ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400'
                : 'border-transparent text-neutral-500 hover:text-neutral-900 dark:hover:text-white'
            }`}
          >
            3. Media Gallery ({formData.images.length})
          </button>

          <button
            onClick={() => setActiveTab('seo')}
            className={`py-3 px-3 text-xs font-semibold border-b-2 transition-all ${
              activeTab === 'seo'
                ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400'
                : 'border-transparent text-neutral-500 hover:text-neutral-900 dark:hover:text-white'
            }`}
          >
            4. SEO & Social Meta
          </button>
        </div>

        {/* Modal Body Content */}
        <div className="flex-1 overflow-y-auto p-6 space-y-6">
          {/* TAB 1: BASIC INFO */}
          {activeTab === 'basic' && (
            <div className="space-y-4 animate-in fade-in duration-150">
              <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div className="md:col-span-2">
                  <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                    Product Title *
                  </label>
                  <input
                    type="text"
                    required
                    placeholder="e.g. Aurora Studio Pro Wireless Headphones"
                    value={formData.title}
                    onChange={(e) => {
                      const title = e.target.value;
                      const slug = title.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');
                      setFormData({
                        ...formData,
                        title,
                        slug: formData.slug || slug,
                        seoTitle: formData.seoTitle || `${title} | Aurora Commerce`
                      });
                    }}
                    className="w-full px-3.5 py-2.5 text-xs rounded-xl border bg-neutral-50 dark:bg-neutral-800 border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white focus:ring-2 focus:ring-indigo-500"
                  />
                </div>

                <div>
                  <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                    Brand / Manufacturer
                  </label>
                  <input
                    type="text"
                    placeholder="e.g. AcousticLab"
                    value={formData.brand}
                    onChange={(e) => setFormData({ ...formData, brand: e.target.value })}
                    className="w-full px-3.5 py-2.5 text-xs rounded-xl border bg-neutral-50 dark:bg-neutral-800 border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white"
                  />
                </div>
              </div>

              <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                  <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                    Primary Category
                  </label>
                  <select
                    value={formData.categoryId}
                    onChange={(e) => setFormData({ ...formData, categoryId: e.target.value })}
                    className="w-full px-3 py-2 text-xs rounded-xl border bg-neutral-50 dark:bg-neutral-800 border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white"
                  >
                    {categories.map(c => (
                      <option key={c.id} value={c.id}>{c.name}</option>
                    ))}
                  </select>
                </div>

                <div>
                  <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                    Base Retail Price ($)
                  </label>
                  <input
                    type="number"
                    step="0.01"
                    value={formData.basePrice}
                    onChange={(e) => setFormData({ ...formData, basePrice: parseFloat(e.target.value) || 0 })}
                    className="w-full px-3 py-2 text-xs rounded-xl border bg-neutral-50 dark:bg-neutral-800 border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white font-mono"
                  />
                </div>

                <div>
                  <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                    Status
                  </label>
                  <select
                    value={formData.status}
                    onChange={(e) => setFormData({ ...formData, status: e.target.value as Product['status'] })}
                    className="w-full px-3 py-2 text-xs rounded-xl border bg-neutral-50 dark:bg-neutral-800 border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white"
                  >
                    <option value="active">Active (Published)</option>
                    <option value="draft">Draft (Unpublished)</option>
                    <option value="archived">Archived</option>
                  </select>
                </div>
              </div>

              <div>
                <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                  Product Summary / Tagline
                </label>
                <input
                  type="text"
                  placeholder="Short one-line selling point displayed on cards..."
                  value={formData.summary}
                  onChange={(e) => setFormData({ ...formData, summary: e.target.value })}
                  className="w-full px-3.5 py-2 text-xs rounded-xl border bg-neutral-50 dark:bg-neutral-800 border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                  HTML Description
                </label>
                <textarea
                  rows={4}
                  value={formData.descriptionHtml}
                  onChange={(e) => setFormData({ ...formData, descriptionHtml: e.target.value })}
                  className="w-full p-3 text-xs font-mono rounded-xl border bg-neutral-50 dark:bg-neutral-800 border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                  Tags (comma separated)
                </label>
                <input
                  type="text"
                  placeholder="audio, bluetooth, anc, summer"
                  value={formData.tags.join(', ')}
                  onChange={(e) => setFormData({ ...formData, tags: e.target.value.split(',').map(t => t.trim()).filter(Boolean) })}
                  className="w-full px-3.5 py-2 text-xs rounded-xl border bg-neutral-50 dark:bg-neutral-800 border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white"
                />
              </div>
            </div>
          )}

          {/* TAB 2: VARIANT MATRIX GENERATOR (CORE FOCUS) */}
          {activeTab === 'variants' && (
            <div className="space-y-6 animate-in fade-in duration-150">
              {/* Option Dimensions Card */}
              <div className="p-5 rounded-2xl bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-700 space-y-4">
                <div className="flex items-center justify-between">
                  <div>
                    <h3 className="text-xs font-bold text-neutral-900 dark:text-white uppercase tracking-wider">
                      Specification Dimensions (Options)
                    </h3>
                    <p className="text-[11px] text-neutral-400">
                      Add dimensions (e.g. Color, Size, Switch Type) to compute the Cartesian product SKU matrix.
                    </p>
                  </div>

                  <button
                    type="button"
                    onClick={handleRegenerateMatrix}
                    className="px-3 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold transition-all shadow-md flex items-center gap-1.5"
                  >
                    <Wand2 className="w-3.5 h-3.5" />
                    <span>Calculate Cartesian Matrix</span>
                  </button>
                </div>

                {/* Dimensions List */}
                <div className="space-y-3">
                  {formData.options.map((opt) => (
                    <div
                      key={opt.id}
                      className="p-3.5 rounded-xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 flex flex-col sm:flex-row sm:items-center justify-between gap-3"
                    >
                      <div className="flex items-center gap-3">
                        <span className="text-xs font-bold text-neutral-900 dark:text-white w-24 flex-shrink-0">
                          {opt.name}:
                        </span>
                        <div className="flex flex-wrap gap-1.5 items-center">
                          {opt.values.map(val => (
                            <span
                              key={val}
                              className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-lg bg-neutral-100 dark:bg-neutral-800 text-xs font-medium text-neutral-800 dark:text-neutral-200 border border-neutral-300 dark:border-neutral-700"
                            >
                              {val}
                              <button
                                type="button"
                                onClick={() => removeOptionValue(opt.id, val)}
                                className="text-neutral-400 hover:text-rose-500"
                              >
                                <X className="w-3 h-3" />
                              </button>
                            </span>
                          ))}

                          {/* Add value input inline */}
                          <div className="flex items-center gap-1">
                            <input
                              type="text"
                              placeholder="+ Add value"
                              value={newOptionValueInputs[opt.id] || ''}
                              onChange={(e) => setNewOptionValueInputs({ ...newOptionValueInputs, [opt.id]: e.target.value })}
                              onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); addOptionValue(opt.id); } }}
                              className="px-2 py-0.5 text-xs rounded-lg border bg-neutral-50 dark:bg-neutral-800 border-neutral-200 dark:border-neutral-700 w-24"
                            />
                            <button
                              type="button"
                              onClick={() => addOptionValue(opt.id)}
                              className="p-1 rounded bg-neutral-200 dark:bg-neutral-700 text-neutral-700 dark:text-neutral-200 text-xs hover:bg-neutral-300"
                            >
                              <Plus className="w-3 h-3" />
                            </button>
                          </div>
                        </div>
                      </div>

                      <button
                        type="button"
                        onClick={() => removeOptionDimension(opt.id)}
                        className="text-xs text-rose-500 hover:text-rose-700 p-1 flex items-center gap-1 self-end sm:self-auto"
                      >
                        <Trash2 className="w-3.5 h-3.5" />
                        <span>Remove Dimension</span>
                      </button>
                    </div>
                  ))}

                  {/* Add New Dimension Input */}
                  <div className="flex items-center gap-2 pt-1">
                    <input
                      type="text"
                      placeholder="New Option Dimension (e.g. Material, Edition, Voltage)"
                      value={newOptionName}
                      onChange={(e) => setNewOptionName(e.target.value)}
                      onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); addOptionDimension(); } }}
                      className="flex-1 px-3 py-2 text-xs rounded-xl border bg-white dark:bg-neutral-900 border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white"
                    />
                    <button
                      type="button"
                      onClick={addOptionDimension}
                      className="px-3 py-2 text-xs font-semibold rounded-xl bg-neutral-200 dark:bg-neutral-800 text-neutral-800 dark:text-neutral-200 hover:bg-neutral-300 dark:hover:bg-neutral-700 flex items-center gap-1"
                    >
                      <Plus className="w-3.5 h-3.5" />
                      <span>Add Option</span>
                    </button>
                  </div>
                </div>
              </div>

              {/* Bulk Apply Bar */}
              <div className="p-3 rounded-2xl bg-indigo-50/70 dark:bg-indigo-950/30 border border-indigo-200 dark:border-indigo-800/60 flex flex-wrap items-center justify-between gap-3 text-xs">
                <span className="font-bold text-indigo-900 dark:text-indigo-300 flex items-center gap-1.5">
                  <Sparkles className="w-3.5 h-3.5 text-indigo-500" />
                  Bulk Matrix Actions:
                </span>

                <div className="flex flex-wrap items-center gap-2">
                  <div className="flex items-center gap-1">
                    <input
                      type="number"
                      placeholder="Set Price ($)"
                      value={batchPrice}
                      onChange={(e) => setBatchPrice(e.target.value)}
                      className="w-24 px-2 py-1 text-xs rounded-lg border bg-white dark:bg-neutral-900 border-indigo-300 dark:border-indigo-800 font-mono"
                    />
                    <button
                      type="button"
                      onClick={applyBatchPrice}
                      className="px-2 py-1 rounded-lg bg-indigo-600 text-white font-semibold text-[11px] hover:bg-indigo-700"
                    >
                      Apply
                    </button>
                  </div>

                  <div className="flex items-center gap-1">
                    <input
                      type="number"
                      placeholder="Set Stock"
                      value={batchInventory}
                      onChange={(e) => setBatchInventory(e.target.value)}
                      className="w-20 px-2 py-1 text-xs rounded-lg border bg-white dark:bg-neutral-900 border-indigo-300 dark:border-indigo-800 font-mono"
                    />
                    <button
                      type="button"
                      onClick={applyBatchInventory}
                      className="px-2 py-1 rounded-lg bg-indigo-600 text-white font-semibold text-[11px] hover:bg-indigo-700"
                    >
                      Apply
                    </button>
                  </div>

                  <div className="flex items-center gap-1">
                    <input
                      type="text"
                      placeholder="SKU Prefix (e.g. AUR)"
                      value={batchSkuPrefix}
                      onChange={(e) => setBatchSkuPrefix(e.target.value)}
                      className="w-28 px-2 py-1 text-xs rounded-lg border bg-white dark:bg-neutral-900 border-indigo-300 dark:border-indigo-800 font-mono uppercase"
                    />
                    <button
                      type="button"
                      onClick={applyBatchSkuPrefix}
                      className="px-2 py-1 rounded-lg bg-indigo-600 text-white font-semibold text-[11px] hover:bg-indigo-700"
                    >
                      Gen SKUs
                    </button>
                  </div>
                </div>
              </div>

              {/* SKU Variant Matrix Table */}
              <div className="border border-neutral-200 dark:border-neutral-800 rounded-2xl overflow-hidden shadow-sm">
                <div className="overflow-x-auto">
                  <table className="w-full text-left text-xs border-collapse">
                    <thead>
                      <tr className="bg-neutral-50 dark:bg-neutral-800 border-b border-neutral-200 dark:border-neutral-800 text-[10px] uppercase font-bold text-neutral-500 tracking-wider">
                        <th className="py-2.5 px-3">Variant (Combination)</th>
                        <th className="py-2.5 px-3">SKU Code</th>
                        <th className="py-2.5 px-3">Price ($)</th>
                        <th className="py-2.5 px-3">Compare At ($)</th>
                        <th className="py-2.5 px-3">Cost ($)</th>
                        <th className="py-2.5 px-3">Stock Qty</th>
                        <th className="py-2.5 px-3">Weight (g)</th>
                        <th className="py-2.5 px-3 text-center">Active</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800 text-neutral-800 dark:text-neutral-200">
                      {formData.variants.map((variant, vIdx) => (
                        <tr key={variant.id} className="hover:bg-neutral-50/50 dark:hover:bg-neutral-800/50">
                          <td className="py-2.5 px-3 font-semibold text-neutral-900 dark:text-white">
                            <div className="flex items-center gap-2">
                              <span className="w-2 h-2 rounded-full bg-indigo-500"></span>
                              <span>{variant.title}</span>
                            </div>
                          </td>

                          <td className="py-2.5 px-3">
                            <input
                              type="text"
                              value={variant.sku}
                              onChange={(e) => updateVariantRow(vIdx, 'sku', e.target.value)}
                              className="w-28 px-2 py-1 text-xs rounded border bg-white dark:bg-neutral-900 border-neutral-200 dark:border-neutral-700 font-mono uppercase"
                            />
                          </td>

                          <td className="py-2.5 px-3">
                            <input
                              type="number"
                              step="0.01"
                              value={variant.price}
                              onChange={(e) => updateVariantRow(vIdx, 'price', parseFloat(e.target.value) || 0)}
                              className="w-20 px-2 py-1 text-xs rounded border bg-white dark:bg-neutral-900 border-neutral-200 dark:border-neutral-700 font-mono"
                            />
                          </td>

                          <td className="py-2.5 px-3">
                            <input
                              type="number"
                              step="0.01"
                              value={variant.compareAtPrice || ''}
                              onChange={(e) => updateVariantRow(vIdx, 'compareAtPrice', parseFloat(e.target.value) || undefined)}
                              className="w-20 px-2 py-1 text-xs rounded border bg-white dark:bg-neutral-900 border-neutral-200 dark:border-neutral-700 font-mono text-neutral-400"
                            />
                          </td>

                          <td className="py-2.5 px-3">
                            <input
                              type="number"
                              step="0.01"
                              value={variant.costPrice || ''}
                              onChange={(e) => updateVariantRow(vIdx, 'costPrice', parseFloat(e.target.value) || undefined)}
                              className="w-18 px-2 py-1 text-xs rounded border bg-white dark:bg-neutral-900 border-neutral-200 dark:border-neutral-700 font-mono text-neutral-400"
                            />
                          </td>

                          <td className="py-2.5 px-3">
                            <input
                              type="number"
                              value={variant.inventoryQuantity}
                              onChange={(e) => updateVariantRow(vIdx, 'inventoryQuantity', parseInt(e.target.value, 10) || 0)}
                              className={`w-18 px-2 py-1 text-xs rounded border bg-white dark:bg-neutral-900 font-mono font-bold ${
                                variant.inventoryQuantity < 30 ? 'border-rose-400 text-rose-500' : 'border-neutral-200 dark:border-neutral-700'
                              }`}
                            />
                          </td>

                          <td className="py-2.5 px-3">
                            <input
                              type="number"
                              value={variant.weightGrams || ''}
                              onChange={(e) => updateVariantRow(vIdx, 'weightGrams', parseInt(e.target.value, 10) || undefined)}
                              className="w-18 px-2 py-1 text-xs rounded border bg-white dark:bg-neutral-900 border-neutral-200 dark:border-neutral-700 font-mono"
                            />
                          </td>

                          <td className="py-2.5 px-3 text-center">
                            <input
                              type="checkbox"
                              checked={variant.enabled}
                              onChange={(e) => updateVariantRow(vIdx, 'enabled', e.target.checked)}
                              className="rounded border-neutral-300 text-indigo-600 focus:ring-indigo-500 w-4 h-4"
                            />
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          )}

          {/* TAB 3: MEDIA GALLERY */}
          {activeTab === 'media' && (
            <div className="space-y-4 animate-in fade-in duration-150">
              <div className="flex items-center justify-between">
                <p className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Product Images & Media Assets</p>
                <span className="text-[11px] text-neutral-400">First image will be used as primary thumbnail</span>
              </div>

              <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
                {formData.images.map((img, idx) => (
                  <div key={idx} className="group relative rounded-2xl overflow-hidden border border-neutral-200 dark:border-neutral-700 bg-neutral-100 dark:bg-neutral-800 aspect-square">
                    <img src={img} alt="Product" className="w-full h-full object-cover" />
                    <div className="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2">
                      <button
                        type="button"
                        onClick={() => {
                          const updated = formData.images.filter((_, i) => i !== idx);
                          setFormData({ ...formData, images: updated });
                        }}
                        className="p-2 rounded-xl bg-rose-600 text-white"
                      >
                        <Trash2 className="w-4 h-4" />
                      </button>
                    </div>
                    {idx === 0 && (
                      <span className="absolute top-2 left-2 px-2 py-0.5 rounded-md bg-black/70 text-white text-[9px] font-bold">
                        PRIMARY
                      </span>
                    )}
                  </div>
                ))}
              </div>

              <div className="pt-2">
                <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1">Add Image URL</label>
                <div className="flex gap-2">
                  <input
                    type="text"
                    placeholder="https://images.unsplash.com/..."
                    id="input-new-img"
                    className="flex-1 px-3 py-2 text-xs rounded-xl border bg-neutral-50 dark:bg-neutral-800 border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white"
                  />
                  <button
                    type="button"
                    onClick={() => {
                      const input = document.getElementById('input-new-img') as HTMLInputElement;
                      if (input && input.value.trim()) {
                        setFormData({ ...formData, images: [...formData.images, input.value.trim()] });
                        input.value = '';
                      }
                    }}
                    className="px-4 py-2 rounded-xl bg-neutral-800 dark:bg-neutral-700 text-white text-xs font-semibold hover:bg-neutral-700"
                  >
                    Add Image
                  </button>
                </div>
              </div>
            </div>
          )}

          {/* TAB 4: SEO */}
          {activeTab === 'seo' && (
            <div className="space-y-4 animate-in fade-in duration-150">
              <div className="p-4 rounded-2xl bg-neutral-50 dark:bg-neutral-800/70 border border-neutral-200 dark:border-neutral-700 space-y-3">
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-1.5 text-xs font-bold text-neutral-900 dark:text-white">
                    <Globe className="w-3.5 h-3.5 text-indigo-500" />
                    <span>Search Engine Snippet Preview</span>
                  </div>
                  <span className="text-[10px] text-neutral-400">Google SERP</span>
                </div>

                <div className="p-3 bg-white dark:bg-neutral-900 rounded-xl border border-neutral-200 dark:border-neutral-800 shadow-inner">
                  <div className="text-[11px] text-neutral-500 flex items-center gap-1">
                    <span>https://auroracommerce.io</span>
                    <span>›</span>
                    <span className="text-neutral-600 dark:text-neutral-400">products</span>
                    <span>›</span>
                    <span className="text-neutral-800 dark:text-neutral-200 font-semibold">{formData.slug || 'product-slug'}</span>
                  </div>
                  <h4 className="text-sm font-semibold text-blue-600 dark:text-blue-400 hover:underline cursor-pointer mt-0.5">
                    {formData.seoTitle || `${formData.title} | Aurora Commerce`}
                  </h4>
                  <p className="text-xs text-neutral-600 dark:text-neutral-400 mt-1 leading-relaxed">
                    {formData.seoDescription || formData.summary || 'Shop reference-grade products with fast express shipping and lifetime warranty.'}
                  </p>
                </div>

                <div className="space-y-3 pt-1">
                  <div>
                    <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1">SEO Title Tag</label>
                    <input
                      type="text"
                      value={formData.seoTitle || ''}
                      onChange={(e) => setFormData({ ...formData, seoTitle: e.target.value })}
                      className="w-full px-3 py-2 text-xs rounded-xl border bg-white dark:bg-neutral-900 border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white"
                    />
                  </div>

                  <div>
                    <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1">SEO Meta Description</label>
                    <textarea
                      rows={2}
                      value={formData.seoDescription || ''}
                      onChange={(e) => setFormData({ ...formData, seoDescription: e.target.value })}
                      className="w-full px-3 py-2 text-xs rounded-xl border bg-white dark:bg-neutral-900 border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white"
                    />
                  </div>
                </div>
              </div>
            </div>
          )}
        </div>

        {/* Modal Footer Actions */}
        <div className="px-6 py-4 border-t border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-950 flex items-center justify-between">
          <div className="text-xs text-neutral-500">
            Total Inventory in Matrix: <span className="font-bold text-neutral-900 dark:text-white font-mono">{formData.totalInventory} units</span>
          </div>

          <div className="flex items-center gap-2">
            <button
              type="button"
              onClick={onClose}
              className="px-4 py-2 text-xs font-medium rounded-xl border border-neutral-300 dark:border-neutral-700 text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800"
            >
              Discard Changes
            </button>
            <button
              type="button"
              onClick={handleSubmit}
              className="px-5 py-2 text-xs font-bold rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white shadow-lg shadow-indigo-500/20"
            >
              Save Product & SKUs
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};
