import React, { useState, useEffect } from 'react';
import { useAdmin } from '../../context/AdminContext';
import { Product, ProductOption, ProductVariant, VariantVisual } from '../../types';
import { readAdminBootstrap } from '../../integration/adminBootstrap';
import { ProductMediaManager } from './ProductMediaManager';
import { ProductExtraInfoEditor } from './ProductExtraInfoEditor';
import { guessColorCode, VariantVisualPickerModal } from './VariantVisualPickerModal';
import { uploadMediaAsset } from '../../integration/pageData';
import {
  X,
  Plus,
  Trash2,
  Sparkles,
  Layers,
  Image as ImageIcon,
  Palette,
  DollarSign,
  Package,
  Wand2,
  Check,
  Globe,
  Tag,
  Upload,
  Copy,
  AlertCircle
  , FileSpreadsheet
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
  const { categories, collections, products, saveProduct } = useAdmin();

  const [activeTab, setActiveTab] = useState<'basic' | 'variants' | 'media' | 'extra_info' | 'seo'>('basic');
  const [formData, setFormData] = useState<Product | null>(null);

  // New option tag input temporary states
  const [newOptionName, setNewOptionName] = useState('');
  const [newOptionValueInputs, setNewOptionValueInputs] = useState<Record<string, string>>({});

  // Batch action states for variant matrix
  const [batchPrice, setBatchPrice] = useState<string>('');
  const [batchInventory, setBatchInventory] = useState<string>('');
  const [batchSkuPrefix, setBatchSkuPrefix] = useState<string>('');
  const [visualTarget, setVisualTarget] = useState<{ optionName?: string; optionValue?: string; variantIndex?: number; preferredMode?: VariantVisual['visualType'] } | null>(null);

  useEffect(() => {
    if (product) {
      const hydrated = JSON.parse(JSON.stringify(product)) as Product;
      // Older catalog rows predate the persisted option-dimension document.
      // Reconstruct only the editor projection from their existing variant facts;
      // saving remains the point at which it becomes durable.
      if (hydrated.options.length === 0 && hydrated.variants.length > 0) {
        const valuesByName = new Map<string, string[]>();
        hydrated.variants.forEach(variant => Object.entries(variant.options).forEach(([name, value]) => {
          const values = valuesByName.get(name) || [];
          const normalized = String(value);
          if (!values.includes(normalized)) values.push(normalized);
          valuesByName.set(name, values);
        }));
        hydrated.options = Array.from(valuesByName, ([name, values], index) => ({
          id: `legacy-option-${index}`,
          name,
          values,
          displayType: name.toLowerCase().includes('color') ? 'color_palette' : 'pills',
        }));
      }
      setFormData(hydrated);
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
        images: [{
          path: 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=800&auto=format&fit=crop&q=80',
          alt: 'Product - View #1',
          position: 0,
        }],
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
        image: typeof formData?.images[0] === 'string' ? formData.images[0] : formData?.images[0]?.path,
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
    const guessed = guessColorCode(val);

    setFormData({
      ...formData,
      options: formData.options.map(o => {
        if (o.id !== optId) return o;
        if (o.values.includes(val)) return o;
        const newVisuals = { ...(o.valueVisuals || {}) };
        if (guessed && !newVisuals[val]) {
          newVisuals[val] = { visualType: 'color', colorCode: guessed };
        }
        return { ...o, values: [...o.values, val], valueVisuals: newVisuals };
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

  const updateOptionDisplayType = (optionId: string, displayType: ProductOption['displayType']) => {
    setFormData({ ...formData, options: formData.options.map(option => option.id === optionId ? { ...option, displayType } : option) });
  };

  const applyOptionVisual = (optionName: string, optionValue: string, visual: VariantVisual, applyToAllMatching: boolean) => {
    setFormData({
      ...formData,
      options: formData.options.map(option => option.name !== optionName ? option : {
        ...option,
        valueVisuals: { ...(option.valueVisuals || {}), [optionValue]: visual },
      }),
      variants: applyToAllMatching
        ? formData.variants.map(variant => variant.options[optionName] === optionValue ? { ...variant, ...visual } : variant)
        : formData.variants,
    });
  };

  const applyVariantVisual = (variantIndex: number, visual: VariantVisual) => {
    setFormData({ ...formData, variants: formData.variants.map((variant, index) => index === variantIndex ? { ...variant, ...visual } : variant) });
  };

  const autoDetectColors = () => {
    setFormData({
      ...formData,
      options: formData.options.map(option => ({
        ...option,
        displayType: option.name.toLowerCase().includes('color') ? 'color_palette' : option.displayType || 'pills',
        valueVisuals: Object.fromEntries(option.values.map(value => [value, guessColorCode(value) ? { visualType: 'color', colorCode: guessColorCode(value) } : option.valueVisuals?.[value] || { visualType: 'none' }])),
      })),
      variants: formData.variants.map(variant => {
        const colorValue = Object.entries(variant.options).find(([name]) => name.toLowerCase().includes('color'))?.[1];
        const colorCode = colorValue ? guessColorCode(String(colorValue)) : undefined;
        return colorCode ? { ...variant, visualType: 'color', colorCode, optionImage: undefined } : variant;
      }),
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
            onClick={() => setActiveTab('extra_info')}
            className={`py-3 px-3 text-xs font-semibold border-b-2 transition-all flex items-center gap-1.5 ${
              activeTab === 'extra_info'
                ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400'
                : 'border-transparent text-neutral-500 hover:text-neutral-900 dark:hover:text-white'
            }`}
          >
            <FileSpreadsheet className="w-3.5 h-3.5 text-indigo-500" />
            <span>4. PDP Rich Content & Specs</span>
            <span className="px-1.5 py-0.2 rounded-full bg-emerald-100 dark:bg-emerald-950/80 text-emerald-700 dark:text-emerald-300 text-[10px] font-mono font-bold">
              {(formData.pdpContent?.highlights?.length || 0) + (formData.pdpContent?.specifications?.length || 0)}
            </span>
          </button>

          <button
            onClick={() => setActiveTab('seo')}
            className={`py-3 px-3 text-xs font-semibold border-b-2 transition-all ${
              activeTab === 'seo'
                ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400'
                : 'border-transparent text-neutral-500 hover:text-neutral-900 dark:hover:text-white'
            }`}
          >
            5. SEO & Social Meta
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
                <div className="md:col-span-2">
                  <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                    URL Slug
                  </label>
                  <div className="flex rounded-xl border bg-neutral-50 dark:bg-neutral-800 border-neutral-200 dark:border-neutral-700 overflow-hidden focus-within:ring-2 focus-within:ring-indigo-500">
                    <span className="inline-flex items-center px-3.5 text-xs font-mono text-neutral-500 dark:text-neutral-400 border-r border-neutral-200 dark:border-neutral-700 whitespace-nowrap">
                      /products/
                    </span>
                    <input
                      type="text"
                      placeholder="auto-generated-from-product-title"
                      value={formData.slug}
                      onChange={(e) => setFormData({ ...formData, slug: e.target.value.toLowerCase().replace(/[^a-z0-9-]/g, '-') })}
                      className="min-w-0 flex-1 px-3.5 py-2.5 text-xs font-mono bg-transparent text-neutral-900 dark:text-white outline-none"
                    />
                  </div>
                  <p className="mt-1 text-[10px] text-neutral-500 dark:text-neutral-400">Leave blank to generate a unique URL slug from the product title.</p>
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

          {activeTab === 'extra_info' && (
            <ProductExtraInfoEditor
              formData={formData}
              onChange={setFormData}
              allProducts={products}
            />
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

                  <div className="flex items-center gap-2">
                    <button type="button" onClick={autoDetectColors} className="px-3 py-1.5 rounded-xl bg-amber-500/15 border border-amber-500/50 text-amber-700 dark:text-amber-300 hover:bg-amber-500 hover:text-white text-xs font-bold transition-all shadow-md flex items-center gap-1.5">
                      <Wand2 className="w-3.5 h-3.5" /><span>Auto-Detect Colors</span>
                    </button>
                    <button type="button" onClick={handleRegenerateMatrix} className="px-3 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold transition-all shadow-md flex items-center gap-1.5">
                      <Wand2 className="w-3.5 h-3.5" /><span>Calculate Cartesian Matrix</span>
                    </button>
                  </div>
                </div>

                {/* Dimensions List */}
                <div className="space-y-3">
                  {formData.options.map((opt) => {
                    const isColorDim = opt.name.toLowerCase().includes('color') || opt.name.toLowerCase().includes('colour');
                    return (
                      <div key={opt.id} className="p-3.5 rounded-xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 flex flex-col gap-3">
                        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-neutral-100 dark:border-neutral-800 pb-2">
                          <div className="flex items-center gap-2">
                            <span className="text-xs font-bold text-neutral-900 dark:text-white">{opt.name}</span>
                            <span className="px-2 py-0.5 rounded-full bg-neutral-100 dark:bg-neutral-800 text-[10px] text-neutral-500 font-mono">{opt.values.length} values</span>
                          </div>
                          <div className="flex items-center gap-2">
                            <span className="text-[11px] text-neutral-500">Display Mode:</span>
                            <select value={opt.displayType || (isColorDim ? 'color_palette' : 'pills')} onChange={(event) => updateOptionDisplayType(opt.id, event.target.value as ProductOption['displayType'])} className="px-2 py-1 text-[11px] font-medium rounded-lg border bg-neutral-50 dark:bg-neutral-800 border-neutral-200 dark:border-neutral-700 text-neutral-800 dark:text-neutral-200">
                              <option value="color_palette">🎨 Color Palette Swatches</option>
                              <option value="option_image">🖼️ Option Images</option>
                              <option value="pills">🏷️ Pill Buttons</option>
                              <option value="dropdown">▼ Dropdown Select</option>
                            </select>
                            <button type="button" onClick={() => removeOptionDimension(opt.id)} className="text-xs text-rose-500 hover:text-rose-700 p-1 flex items-center gap-1" title="Remove Dimension">
                              <Trash2 className="w-3.5 h-3.5" />
                            </button>
                          </div>
                        </div>
                        {/* Values with 2-choose-1 Visual Swatches */}
                        <div className="flex flex-wrap gap-2 items-center">
                          {opt.values.map(val => {
                            const valVisual = opt.valueVisuals?.[val];
                            const isColor = valVisual?.visualType === 'color' || (valVisual?.colorCode && !valVisual?.optionImage);
                            const isImage = valVisual?.visualType === 'image' || valVisual?.optionImage;
                            return (
                              <div key={val} className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl bg-neutral-50 dark:bg-neutral-800/80 text-xs font-medium text-neutral-800 dark:text-neutral-200 border border-neutral-200 dark:border-neutral-700 hover:border-indigo-400 dark:hover:border-indigo-500 transition-colors group">
                                <button type="button" onClick={() => setVisualTarget({ optionName: opt.name, optionValue: val, preferredMode: valVisual?.visualType || (isColorDim ? 'color' : 'none') })} className="flex items-center gap-1 cursor-pointer hover:opacity-80 transition-opacity" title="Configure Visual Swatch (Color Code OR Option Image)">
                                  {isColor && valVisual?.colorCode ? <span className="w-3.5 h-3.5 rounded-full border border-neutral-300 dark:border-neutral-600 shrink-0 shadow-2xs" style={{ backgroundColor: valVisual.colorCode }} /> : isImage && valVisual?.optionImage ? <img src={valVisual.optionImage} alt={val} className="w-3.5 h-3.5 rounded object-cover border border-neutral-300 dark:border-neutral-600 shrink-0" /> : <span className="w-3.5 h-3.5 rounded-full border border-dashed border-neutral-400 flex items-center justify-center text-[9px] text-neutral-400">+</span>}
                                  <span>{val}</span>
                                </button>
                                {valVisual?.colorCode && <span className="text-[10px] font-mono text-neutral-400 uppercase">{valVisual.colorCode}</span>}
                                <button type="button" onClick={() => removeOptionValue(opt.id, val)} className="text-neutral-400 hover:text-rose-500 ml-0.5" title="Remove Value"><X className="w-3 h-3" /></button>
                              </div>
                            );
                          })}
                          <div className="flex items-center gap-1">
                            <input type="text" placeholder="+ Add value" value={newOptionValueInputs[opt.id] || ''} onChange={(e) => setNewOptionValueInputs({ ...newOptionValueInputs, [opt.id]: e.target.value })} onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); addOptionValue(opt.id); } }} className="px-2.5 py-1 text-xs rounded-xl border bg-neutral-50 dark:bg-neutral-800 border-neutral-200 dark:border-neutral-700 w-28 text-neutral-900 dark:text-white" />
                            <button type="button" onClick={() => addOptionValue(opt.id)} className="p-1.5 rounded-xl bg-neutral-200 dark:bg-neutral-700 text-neutral-700 dark:text-neutral-200 text-xs hover:bg-neutral-300 dark:hover:bg-neutral-600" title="Add Value"><Plus className="w-3.5 h-3.5" /></button>
                          </div>
                        </div>
                      </div>
                    );
                  })}

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
                        <th className="py-2.5 px-3">Visual Swatch (2 选 1)</th>
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
                            <div className="flex items-center gap-1.5 min-w-34">
                              {variant.visualType === 'color' && variant.colorCode && <span className="inline-flex items-center gap-1.5 px-2 py-1 rounded-lg border border-neutral-300 dark:border-neutral-700 font-mono text-[10px]"><span className="w-4 h-4 rounded-full border border-white/40" style={{ backgroundColor: variant.colorCode }} />{variant.colorCode}</span>}
                              {variant.visualType === 'image' && variant.optionImage && <img src={variant.optionImage} className="w-8 h-8 rounded-lg object-cover border border-neutral-300 dark:border-neutral-700" alt="Variant swatch" />}
                              {!variant.visualType || variant.visualType === 'none' ? <span className="text-[10px] text-neutral-500">None</span> : null}
                              <button type="button" onClick={() => setVisualTarget({ variantIndex: vIdx, preferredMode: 'color' })} className="p-1.5 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700" aria-label={`Choose a color visual for ${variant.title}`}><Palette className="w-3.5 h-3.5" /></button>
                              <button type="button" onClick={() => setVisualTarget({ variantIndex: vIdx, preferredMode: 'image' })} className="p-1.5 rounded-lg border border-neutral-300 dark:border-neutral-700 text-neutral-600 dark:text-neutral-300 hover:text-indigo-500" aria-label={`Choose an image visual for ${variant.title}`}><ImageIcon className="w-3.5 h-3.5" /></button>
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
            <ProductMediaManager
              images={formData.images.map((image, position) => typeof image === 'string' ? { path: image, alt: `${formData.title || 'Product'} - View #${position + 1}`, position } : image)}
              onChange={(images) => setFormData({ ...formData, images })}
              productTitle={formData.title}
              bootstrap={readAdminBootstrap()}
            />
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
      {visualTarget && (
        <VariantVisualPickerModal
          isOpen
          targetTitle={visualTarget.optionValue || formData.variants[visualTarget.variantIndex ?? 0]?.title || 'Variant'}
          targetOptionName={visualTarget.optionName}
          targetOptionValue={visualTarget.optionValue}
          initialVisualType={visualTarget.preferredMode || (visualTarget.optionName && visualTarget.optionValue
            ? formData.options.find(option => option.name === visualTarget.optionName)?.valueVisuals?.[visualTarget.optionValue]?.visualType
            : formData.variants[visualTarget.variantIndex ?? 0]?.visualType)}
          initialColorCode={visualTarget.optionName && visualTarget.optionValue
            ? formData.options.find(option => option.name === visualTarget.optionName)?.valueVisuals?.[visualTarget.optionValue]?.colorCode
            : formData.variants[visualTarget.variantIndex ?? 0]?.colorCode}
          initialOptionImage={visualTarget.optionName && visualTarget.optionValue
            ? formData.options.find(option => option.name === visualTarget.optionName)?.valueVisuals?.[visualTarget.optionValue]?.optionImage
            : formData.variants[visualTarget.variantIndex ?? 0]?.optionImage}
          availableProductImages={formData.images.map(image => typeof image === 'string' ? image : image.path)}
          onUpload={(file) => uploadMediaAsset(readAdminBootstrap(), file)}
          onClose={() => setVisualTarget(null)}
          onSave={(visual) => {
            if (visualTarget.optionName && visualTarget.optionValue) {
              applyOptionVisual(visualTarget.optionName, visualTarget.optionValue, visual, Boolean(visual.applyToAllMatching));
            } else if (visualTarget.variantIndex !== undefined) {
              applyVariantVisual(visualTarget.variantIndex, visual);
            }
            setVisualTarget(null);
          }}
        />
      )}
    </div>
  );
};
