import { useAdminCurrency } from '../../context/useAdminCurrency';
import React, { useState } from 'react';
import { CatalogImageUploadField } from './CatalogImageUploadField';
import { ThemeImageFocus } from '../cms/ThemeImageFocus';
import { useAdmin } from '../../context/AdminContext';
import {
  Product,
  ProductHighlightItem,
  ProductSpecItem,
  ProductShippingPolicy,
  ProductCustomAccordion,
  ProductSeedReview
} from '../../types';
import {
  CheckCircle2,
  Sparkles,
  Shield,
  Zap,
  Award,
  Truck,
  Plus,
  Trash2,
  GripVertical,
  HelpCircle,
  Package,
  Layers,
  Star,
  RefreshCw,
  Sliders,
  ChevronDown,
  ChevronUp,
  Info,
  Check,
  Tag,
  FileText,
  ShieldCheck,
  ExternalLink,
  Clock,
  MessageSquare,
} from 'lucide-react';

interface ProductExtraInfoEditorProps {
  formData: Product;
  onChange: (updated: Product) => void;
  allProducts: Product[];
}

export const ProductExtraInfoEditor: React.FC<ProductExtraInfoEditorProps> = ({
  formData,
  onChange,
  allProducts
}) => {
  const { money, currencyCode } = useAdminCurrency();
  const { reviews: allModerationReviews = [], setReviewFilterProductId, setCurrentSection } = useAdmin();
  const [subSection, setSubSection] = useState<'highlights' | 'specs' | 'shipping' | 'accordions' | 'cross_sell' | 'reviews' | 'story'>('highlights');

  // Temporary inputs
  const [newHighlightText, setNewHighlightText] = useState('');
  const [newHighlightBadge, setNewHighlightBadge] = useState('');

  const [newSpecLabel, setNewSpecLabel] = useState('');
  const [newSpecValue, setNewSpecValue] = useState('');
  const [newSpecGroup, setNewSpecGroup] = useState('');

  const [newAccordionTitle, setNewAccordionTitle] = useState('');
  const [newAccordionContent, setNewAccordionContent] = useState('');

  type EditorData = Product & {
    highlights?: ProductHighlightItem[];
    specifications?: ProductSpecItem[];
    shippingPolicy?: ProductShippingPolicy;
    customAccordions?: ProductCustomAccordion[];
    crossSellProductIds?: string[];
    reviews?: ProductSeedReview[];
    ratingScore?: number | null;
    reviewCount?: number;
  };
  const persisted = formData.pdpContent ?? {
    highlights: [], specifications: [], useStoreDefaultShipping: true, shippingPolicy: null, customAccordions: [], reviewCount: 0,
  };
  const editorData: EditorData = {
    ...formData,
    highlights: persisted.highlights ?? [],
    specifications: persisted.specifications ?? [],
    shippingPolicy: persisted.shippingPolicy ? { ...persisted.shippingPolicy, useStoreDefault: persisted.useStoreDefaultShipping } : undefined,
    customAccordions: persisted.customAccordions ?? [],
    crossSellProductIds: formData.crossSellProductIds ?? [],
    reviews: formData.reviews ?? [],
    ratingScore: persisted.ratingScore ?? null,
    reviewCount: persisted.reviewCount ?? 0,
  };
  const persistChange = (updated: EditorData): void => {
    const { highlights = [], specifications = [], shippingPolicy, customAccordions = [], crossSellProductIds = [], reviews = [], ratingScore = null, reviewCount = 0, ...product } = updated;
    onChange({
      ...product,
      pdpContent: {
        ...persisted,
        highlights,
        specifications,
        useStoreDefaultShipping: shippingPolicy?.useStoreDefault ?? persisted.useStoreDefaultShipping,
        shippingPolicy: shippingPolicy ?? persisted.shippingPolicy ?? null,
        customAccordions,
        ratingScore,
        reviewCount,
      },
      crossSellProductIds,
      reviews,
    });
  };
  // ----------------------------------------------------
  // 1. Signature Highlights Handlers
  // ----------------------------------------------------
  const highlights: ProductHighlightItem[] = editorData.highlights || [];

  const handleAddHighlight = () => {
    if (!newHighlightText.trim()) return;
    const newItem: ProductHighlightItem = {
      id: 'hl-' + Date.now(),
      text: newHighlightText.trim(),
      badge: newHighlightBadge.trim() || undefined,
      icon: 'CheckCircle2'
    };
    persistChange({
      ...editorData,
      highlights: [...highlights, newItem]
    });
    setNewHighlightText('');
    setNewHighlightBadge('');
  };

  const handleRemoveHighlight = (id: string) => {
    persistChange({
      ...editorData,
      highlights: highlights.filter(h => h.id !== id)
    });
  };

  const handleUpdateHighlight = (id: string, text: string, badge?: string) => {
    persistChange({
      ...editorData,
      highlights: highlights.map(h => h.id === id ? { ...h, text, badge } : h)
    });
  };

  const handleLoadSampleHighlights = () => {
    const samples: ProductHighlightItem[] = [
      { id: 'hl-1', text: 'Crafted from Japanese Beta-Titanium & Precision Hardware', badge: 'Aerospace Grade' },
      { id: 'hl-2', text: 'Carl Zeiss Vision polarized nylon lenses with anti-reflective coating', badge: 'Zeiss Optics' },
      { id: 'hl-3', text: 'Screwless integrated barrel hinge mechanism tested to 50,000 cycles', badge: 'Patented' },
      { id: 'hl-4', text: 'Medical-grade hypoallergenic silicone nosepads with micro-cushioning', badge: 'Ergonomic' }
    ];
    persistChange({
      ...editorData,
      highlights: samples
    });
  };

  // ----------------------------------------------------
  // 2. Technical Specifications Matrix Handlers
  // ----------------------------------------------------
  const specifications: ProductSpecItem[] = editorData.specifications || [];

  const handleAddSpec = () => {
    if (!newSpecLabel.trim() || !newSpecValue.trim()) return;
    const newItem: ProductSpecItem = {
      id: 'spec-' + Date.now(),
      label: newSpecLabel.trim(),
      value: newSpecValue.trim(),
      group: newSpecGroup.trim() || undefined
    };
    persistChange({
      ...editorData,
      specifications: [...specifications, newItem]
    });
    setNewSpecLabel('');
    setNewSpecValue('');
  };

  const handleRemoveSpec = (id: string) => {
    persistChange({
      ...editorData,
      specifications: specifications.filter(s => s.id !== id)
    });
  };

  const handleUpdateSpec = (id: string, field: 'label' | 'value' | 'group', val: string) => {
    persistChange({
      ...editorData,
      specifications: specifications.map(s => s.id === id ? { ...s, [field]: val } : s)
    });
  };

  const handleLoadSampleSpecs = () => {
    const samples: ProductSpecItem[] = [
      { id: 'sp-1', label: 'Frame Material', value: 'High-Purity Japanese Beta-Titanium alloy', group: 'Materials' },
      { id: 'sp-2', label: 'Lens Specification', value: 'Carl Zeiss Tri-Pel Polarized Nylon, Category 3 UV400', group: 'Optics' },
      { id: 'sp-3', label: 'Hinge Technology', value: 'Screwless Friction Barrel Hinge (Tested 50k flexes)', group: 'Engineering' },
      { id: 'sp-4', label: 'Total Weight', value: '18.4 grams (Ultra-lightweight endurance profile)', group: 'Dimensions' },
      { id: 'sp-5', label: 'Dimensions (mm)', value: '54mm (Lens) — 18mm (Bridge) — 145mm (Temple)', group: 'Dimensions' },
      { id: 'sp-6', label: 'Included Accessories', value: 'Full-grain Italian leather vault case, microfiber cloth', group: 'Package' }
    ];
    persistChange({
      ...editorData,
      specifications: samples
    });
  };

  // ----------------------------------------------------
  // 3. Shipping & Return Guarantee Policy Handlers
  // ----------------------------------------------------
  const shippingPolicy: ProductShippingPolicy = editorData.shippingPolicy || {
    useStoreDefault: true,
    leadTimeText: 'Order in the next 3 hrs 12 mins for dispatch today. Estimated arrival: 3–5 business days.',
    shippingBadgeText: 'Complimentary Express Shipping Included',
    policyTitle: 'Shipping & 30-Day Return Guarantee',
    policyContentHtml: 'Enjoy complimentary express courier delivery on all orders. Each piece is meticulously packed in custom rigid packaging with tamper-evident seals. If you are not completely satisfied, return within 30 days in original condition for a full refund.',
    freeShippingThreshold: 150
  };

  const handleUpdateShipping = (partial: Partial<ProductShippingPolicy>) => {
    persistChange({
      ...editorData,
      shippingPolicy: {
        ...shippingPolicy,
        ...partial
      }
    });
  };

  // ----------------------------------------------------
  // 4. Custom Extensible Accordions Handlers
  // ----------------------------------------------------
  const customAccordions: ProductCustomAccordion[] = editorData.customAccordions || [];

  const handleAddAccordion = () => {
    if (!newAccordionTitle.trim() || !newAccordionContent.trim()) return;
    const newItem: ProductCustomAccordion = {
      id: 'acc-' + Date.now(),
      title: newAccordionTitle.trim(),
      content: newAccordionContent.trim(),
      defaultOpen: false
    };
    persistChange({
      ...editorData,
      customAccordions: [...customAccordions, newItem]
    });
    setNewAccordionTitle('');
    setNewAccordionContent('');
  };

  const handleRemoveAccordion = (id: string) => {
    persistChange({
      ...editorData,
      customAccordions: customAccordions.filter(a => a.id !== id)
    });
  };

  const handleLoadSampleAccordions = () => {
    const samples: ProductCustomAccordion[] = [
      {
        id: 'acc-box',
        title: 'What’s In The Box',
        content: '• 1x Precision Handcrafted Sunglasses\n• 1x Magnetic Italian Leather Hard Case\n• 1x High-Density Microfiber Lens Cleaning Cloth\n• 1x Certificate of Authenticity & Batch Serial Card'
      },
      {
        id: 'acc-care',
        title: 'Care & Maintenance Guide',
        content: 'Rinse with lukewarm water before wiping with the included microfiber cloth. Avoid leaving in extreme heat or submerged in saltwater. Keep inside the leather vault case when not in use.'
      },
      {
        id: 'acc-fit',
        title: 'Size & Fit Advisory',
        content: 'Designed with a universal medium fit that complements oval, round, and square facial geometries. The flexible beta-titanium temples gently contour to the head without excessive temple pressure.'
      }
    ];
    persistChange({
      ...editorData,
      customAccordions: samples
    });
  };

  // ----------------------------------------------------
  // 5. Frequently Paired With (Cross-Sell / Synergy)
  // ----------------------------------------------------
  const crossSellIds: string[] = editorData.crossSellProductIds || [];
  const otherProducts = allProducts
    .filter(p => p.id !== editorData.id && p.status === 'active')
    .map(p => ({
      ...p,
      images: p.images.map(image => typeof image === 'string' ? image : image.path),
    }));

  const toggleCrossSellProduct = (pId: string) => {
    const exists = crossSellIds.includes(pId);
    if (!exists && crossSellIds.length >= 4) return;
    const updated = exists ? crossSellIds.filter(id => id !== pId) : [...crossSellIds, pId];
    persistChange({
      ...editorData,
      crossSellProductIds: updated
    });
  };

  // ----------------------------------------------------
  // 6. Live Moderated Product Reviews Summary
  // ----------------------------------------------------
  const productModeratedReviews = allModerationReviews.filter((review) => review.productId === formData.id);
  const publishedProductReviews = productModeratedReviews.filter((review) => review.status === 'published');
  const pendingProductReviews = productModeratedReviews.filter((review) => review.status === 'pending');
  const rejectedProductReviews = productModeratedReviews.filter((review) => review.status === 'rejected');
  const calculatedAvgRating = publishedProductReviews.length > 0
    ? (publishedProductReviews.reduce((sum, review) => sum + review.rating, 0) / publishedProductReviews.length).toFixed(1)
    : '0.0';
  const liveReviewCount = publishedProductReviews.length;

  const handleOpenModerationHub = () => {
    setReviewFilterProductId(formData.id);
    setCurrentSection('catalog-reviews');
  };

  return (
    <div className="space-y-6">
      {/* Sub-tab Navigation Header */}
      <button type="button" onClick={() => setSubSection('story')} className="px-3 py-2 rounded-xl border text-xs font-semibold">Product Story</button>
      {subSection === 'story' && <section className="space-y-4 rounded-xl border p-5">
        <h3 className="font-semibold">Product Story</h3><p className="text-xs text-neutral-500">Shared product content. Each supported theme presents it in its own layout. Leave empty to hide.</p>
        {(['label','heading','description','alt'] as const).map(key => <label key={key} className="block text-xs capitalize">{key === 'alt' ? 'Image description' : key}<textarea rows={key === 'description' ? 4 : 1} maxLength={key === 'description' ? 5000 : key === 'heading' ? 255 : key === 'alt' ? 200 : 100} value={persisted.story?.[key] ?? ''} onChange={e => onChange({...formData,pdpContent:{...persisted,story:{...persisted.story,[key]:e.target.value}}})} className="block w-full border rounded bg-transparent p-2 mt-2"/></label>)}
        <CatalogImageUploadField label="Story image" value={persisted.story?.image} onChange={image => onChange({...formData,pdpContent:{...persisted,story:{...persisted.story,image}}})}/>
        <ThemeImageFocus image={persisted.story?.image} focus={persisted.story?.focus} focusY={persisted.story?.focusY} onChange={(focus,focusY) => onChange({...formData,pdpContent:{...persisted,story:{...persisted.story,focus,focusY}}})}/>
      </section>}
      <div className="flex flex-wrap items-center gap-1.5 p-1.5 rounded-2xl bg-neutral-100 dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800">
        <button
          type="button"
          onClick={() => setSubSection('highlights')}
          className={`px-3 py-1.5 rounded-xl text-xs font-semibold transition-all flex items-center gap-1.5 ${
            subSection === 'highlights'
              ? 'bg-white dark:bg-neutral-800 text-indigo-600 dark:text-indigo-400 shadow-xs'
              : 'text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white'
          }`}
        >
          <Sparkles className="w-3.5 h-3.5" />
          <span>Signature Highlights</span>
          <span className="px-1.5 py-0.2 rounded-full bg-neutral-200 dark:bg-neutral-700 text-[10px] font-mono">
            {highlights.length}
          </span>
        </button>

        <button
          type="button"
          onClick={() => setSubSection('specs')}
          className={`px-3 py-1.5 rounded-xl text-xs font-semibold transition-all flex items-center gap-1.5 ${
            subSection === 'specs'
              ? 'bg-white dark:bg-neutral-800 text-indigo-600 dark:text-indigo-400 shadow-xs'
              : 'text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white'
          }`}
        >
          <Sliders className="w-3.5 h-3.5" />
          <span>Specifications Matrix</span>
          <span className="px-1.5 py-0.2 rounded-full bg-neutral-200 dark:bg-neutral-700 text-[10px] font-mono">
            {specifications.length}
          </span>
        </button>

        <button
          type="button"
          onClick={() => setSubSection('shipping')}
          className={`px-3 py-1.5 rounded-xl text-xs font-semibold transition-all flex items-center gap-1.5 ${
            subSection === 'shipping'
              ? 'bg-white dark:bg-neutral-800 text-indigo-600 dark:text-indigo-400 shadow-xs'
              : 'text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white'
          }`}
        >
          <Truck className="w-3.5 h-3.5" />
          <span>Shipping & Guarantee</span>
        </button>

        <button
          type="button"
          onClick={() => setSubSection('accordions')}
          className={`px-3 py-1.5 rounded-xl text-xs font-semibold transition-all flex items-center gap-1.5 ${
            subSection === 'accordions'
              ? 'bg-white dark:bg-neutral-800 text-indigo-600 dark:text-indigo-400 shadow-xs'
              : 'text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white'
          }`}
        >
          <Layers className="w-3.5 h-3.5" />
          <span>Custom Accordions</span>
          <span className="px-1.5 py-0.2 rounded-full bg-neutral-200 dark:bg-neutral-700 text-[10px] font-mono">
            {customAccordions.length}
          </span>
        </button>

        <button
          type="button"
          onClick={() => setSubSection('cross_sell')}
          className={`px-3 py-1.5 rounded-xl text-xs font-semibold transition-all flex items-center gap-1.5 ${
            subSection === 'cross_sell'
              ? 'bg-white dark:bg-neutral-800 text-indigo-600 dark:text-indigo-400 shadow-xs'
              : 'text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white'
          }`}
        >
          <Package className="w-3.5 h-3.5" />
          <span>Frequently Paired</span>
          <span className="px-1.5 py-0.2 rounded-full bg-neutral-200 dark:bg-neutral-700 text-[10px] font-mono">
            {crossSellIds.length}
          </span>
        </button>

        <button
          type="button"
          onClick={() => setSubSection('reviews')}
          className={`px-3 py-1.5 rounded-xl text-xs font-semibold transition-all flex items-center gap-1.5 ${
            subSection === 'reviews'
              ? 'bg-white dark:bg-neutral-800 text-indigo-600 dark:text-indigo-400 shadow-xs'
              : 'text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white'
          }`}
        >
          <Star className="w-3.5 h-3.5 text-amber-500" />
          <span>Reviews ({liveReviewCount})</span>
          {pendingProductReviews.length > 0 && (
            <span className="px-1.5 py-0.2 rounded-full bg-amber-500/20 text-amber-600 dark:text-amber-300 text-[10px] font-bold">
              {pendingProductReviews.length} new
            </span>
          )}
        </button>
      </div>

      {/* ---------------------------------------------------------------- */}
      {/* 1. SIGNATURE HIGHLIGHTS SECTION */}
      {/* ---------------------------------------------------------------- */}
      {subSection === 'highlights' && (
        <div className="space-y-4 animate-in fade-in duration-150">
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-4 rounded-2xl bg-neutral-50 dark:bg-neutral-800/50 border border-neutral-200 dark:border-neutral-700">
            <div>
              <h3 className="text-xs font-bold text-neutral-900 dark:text-white uppercase tracking-wider flex items-center gap-2">
                <Sparkles className="w-4 h-4 text-emerald-500" />
                <span>Signature Craftsmanship Highlights</span>
              </h3>
              <p className="text-[11px] text-neutral-500 dark:text-neutral-400 mt-0.5">
                Appears on the Storefront PDP right below the product gallery (2-column checkmark grid).
              </p>
            </div>

            <button
              type="button"
              onClick={handleLoadSampleHighlights}
              className="px-3 py-1.5 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 hover:bg-indigo-100 text-xs font-semibold flex items-center gap-1.5 shrink-0"
            >
              <RefreshCw className="w-3.5 h-3.5" />
              <span>Load Luxury Template</span>
            </button>
          </div>

          {/* Highlights List */}
          <div className="space-y-2">
            {highlights.map((hl, idx) => (
              <div
                key={hl.id}
                className="flex items-center gap-3 p-3 rounded-xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 group hover:border-emerald-400 dark:hover:border-emerald-500 transition-colors"
              >
                <div className="w-6 h-6 rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0">
                  <Check className="w-3.5 h-3.5 stroke-[2.5]" />
                </div>

                <div className="flex-1 min-w-0 flex flex-col sm:flex-row sm:items-center gap-2">
                  <input
                    type="text"
                    value={hl.text}
                    onChange={(e) => handleUpdateHighlight(hl.id, e.target.value, hl.badge)}
                    className="flex-1 px-3 py-1.5 text-xs rounded-lg border bg-neutral-50 dark:bg-neutral-800 border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white"
                    placeholder="e.g. Crafted from Japanese Beta-Titanium"
                  />
                  <input
                    type="text"
                    value={hl.badge || ''}
                    onChange={(e) => handleUpdateHighlight(hl.id, hl.text, e.target.value)}
                    className="w-32 px-2.5 py-1.5 text-xs rounded-lg border bg-neutral-50 dark:bg-neutral-800 border-neutral-200 dark:border-neutral-700 text-neutral-600 dark:text-neutral-400 text-center uppercase font-mono text-[10px]"
                    placeholder="Badge (Optional)"
                  />
                </div>

                <button
                  type="button"
                  onClick={() => handleRemoveHighlight(hl.id)}
                  className="p-1.5 text-neutral-400 hover:text-rose-500 rounded-lg hover:bg-rose-50 dark:hover:bg-rose-950/40"
                  title="Remove"
                >
                  <Trash2 className="w-4 h-4" />
                </button>
              </div>
            ))}

            {highlights.length === 0 && (
              <div className="text-center py-8 border border-dashed border-neutral-300 dark:border-neutral-700 rounded-2xl">
                <Sparkles className="w-8 h-8 text-neutral-400 mx-auto mb-2" />
                <p className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">No Signature Highlights Added</p>
                <p className="text-[11px] text-neutral-400 mt-1">Add bullet points highlighting your key selling points and materials.</p>
              </div>
            )}
          </div>

          {/* Add New Highlight Form */}
          <div className="p-3.5 rounded-2xl bg-neutral-50 dark:bg-neutral-800/40 border border-neutral-200 dark:border-neutral-700 flex flex-col sm:flex-row gap-2 items-center">
            <input
              type="text"
              value={newHighlightText}
              onChange={(e) => setNewHighlightText(e.target.value)}
              onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); handleAddHighlight(); } }}
              placeholder="New highlight point (e.g. Screwless integrated barrel hinge mechanism)..."
              className="flex-1 px-3.5 py-2 text-xs rounded-xl border bg-white dark:bg-neutral-900 border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white"
            />
            <input
              type="text"
              value={newHighlightBadge}
              onChange={(e) => setNewHighlightBadge(e.target.value)}
              onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); handleAddHighlight(); } }}
              placeholder="Badge (e.g. Patented)"
              className="w-36 px-3 py-2 text-xs rounded-xl border bg-white dark:bg-neutral-900 border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white uppercase font-mono text-[10px]"
            />
            <button
              type="button"
              onClick={handleAddHighlight}
              className="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold transition-all shadow-sm flex items-center gap-1.5 shrink-0"
            >
              <Plus className="w-3.5 h-3.5" />
              <span>Add Point</span>
            </button>
          </div>
        </div>
      )}

      {/* ---------------------------------------------------------------- */}
      {/* 2. SPECIFICATIONS MATRIX SECTION */}
      {/* ---------------------------------------------------------------- */}
      {subSection === 'specs' && (
        <div className="space-y-4 animate-in fade-in duration-150">
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-4 rounded-2xl bg-neutral-50 dark:bg-neutral-800/50 border border-neutral-200 dark:border-neutral-700">
            <div>
              <h3 className="text-xs font-bold text-neutral-900 dark:text-white uppercase tracking-wider flex items-center gap-2">
                <Sliders className="w-4 h-4 text-indigo-500" />
                <span>Technical Specifications Matrix</span>
              </h3>
              <p className="text-[11px] text-neutral-500 dark:text-neutral-400 mt-0.5">
                Renders inside the "TECHNICAL SPECIFICATIONS MATRIX" accordion on the PDP.
              </p>
            </div>

            <button
              type="button"
              onClick={handleLoadSampleSpecs}
              className="px-3 py-1.5 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 hover:bg-indigo-100 text-xs font-semibold flex items-center gap-1.5 shrink-0"
            >
              <RefreshCw className="w-3.5 h-3.5" />
              <span>Load Eyewear/Tech Template</span>
            </button>
          </div>

          {/* Specs Table */}
          <div className="border border-neutral-200 dark:border-neutral-800 rounded-2xl overflow-hidden shadow-xs">
            <table className="w-full text-left text-xs border-collapse">
              <thead>
                <tr className="bg-neutral-50 dark:bg-neutral-800/80 border-b border-neutral-200 dark:border-neutral-800 text-[10px] uppercase font-bold text-neutral-500">
                  <th className="py-2.5 px-4 w-1/4">Specification Parameter (Label)</th>
                  <th className="py-2.5 px-4 w-1/2">Technical Value / Material Details</th>
                  <th className="py-2.5 px-4 w-1/5">Category Group</th>
                  <th className="py-2.5 px-3 text-center w-12">Action</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800 text-neutral-800 dark:text-neutral-200 bg-white dark:bg-neutral-900">
                {specifications.map((spec) => (
                  <tr key={spec.id} className="hover:bg-neutral-50/60 dark:hover:bg-neutral-800/40">
                    <td className="py-2.5 px-4 font-semibold text-neutral-900 dark:text-white">
                      <input
                        type="text"
                        value={spec.label}
                        onChange={(e) => handleUpdateSpec(spec.id, 'label', e.target.value)}
                        className="w-full px-2.5 py-1 text-xs rounded-lg border bg-neutral-50 dark:bg-neutral-800 border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white"
                      />
                    </td>
                    <td className="py-2.5 px-4">
                      <input
                        type="text"
                        value={spec.value}
                        onChange={(e) => handleUpdateSpec(spec.id, 'value', e.target.value)}
                        className="w-full px-2.5 py-1 text-xs rounded-lg border bg-neutral-50 dark:bg-neutral-800 border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white"
                      />
                    </td>
                    <td className="py-2.5 px-4">
                      <input
                        type="text"
                        value={spec.group || ''}
                        onChange={(e) => handleUpdateSpec(spec.id, 'group', e.target.value)}
                        placeholder="General"
                        className="w-full px-2.5 py-1 text-xs rounded-lg border bg-neutral-50 dark:bg-neutral-800 border-neutral-200 dark:border-neutral-700 text-neutral-500 font-mono text-[11px]"
                      />
                    </td>
                    <td className="py-2.5 px-3 text-center">
                      <button
                        type="button"
                        onClick={() => handleRemoveSpec(spec.id)}
                        className="p-1 text-neutral-400 hover:text-rose-500"
                        title="Delete spec"
                      >
                        <Trash2 className="w-3.5 h-3.5" />
                      </button>
                    </td>
                  </tr>
                ))}

                {specifications.length === 0 && (
                  <tr>
                    <td colSpan={4} className="py-8 text-center text-xs text-neutral-400">
                      No technical specifications configured. Add parameters below or load template.
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>

          {/* Add New Spec Form */}
          <div className="p-3.5 rounded-2xl bg-neutral-50 dark:bg-neutral-800/40 border border-neutral-200 dark:border-neutral-700 grid grid-cols-1 sm:grid-cols-4 gap-2 items-center">
            <input
              type="text"
              value={newSpecLabel}
              onChange={(e) => setNewSpecLabel(e.target.value)}
              placeholder="Parameter (e.g. Frame Material)"
              className="px-3 py-2 text-xs rounded-xl border bg-white dark:bg-neutral-900 border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white"
            />
            <input
              type="text"
              value={newSpecValue}
              onChange={(e) => setNewSpecValue(e.target.value)}
              placeholder="Value (e.g. Japanese Beta-Titanium)"
              className="sm:col-span-2 px-3 py-2 text-xs rounded-xl border bg-white dark:bg-neutral-900 border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white"
            />
            <button
              type="button"
              onClick={handleAddSpec}
              className="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold transition-all shadow-sm flex items-center justify-center gap-1.5"
            >
              <Plus className="w-3.5 h-3.5" />
              <span>Add Spec Row</span>
            </button>
          </div>
        </div>
      )}

      {/* ---------------------------------------------------------------- */}
      {/* 3. SHIPPING & RETURN GUARANTEE SECTION */}
      {/* ---------------------------------------------------------------- */}
      {subSection === 'shipping' && (
        <div className="space-y-5 animate-in fade-in duration-150">
          <div className="p-4 rounded-2xl bg-indigo-50/70 dark:bg-indigo-950/30 border border-indigo-200 dark:border-indigo-800/60 flex items-start gap-3">
            <Truck className="w-5 h-5 text-indigo-600 dark:text-indigo-400 shrink-0 mt-0.5" />
            <div>
              <h4 className="text-xs font-bold text-indigo-950 dark:text-indigo-200">
                Storefront PDP Shipping & Dispatch Presentation
              </h4>
              <p className="text-[11px] text-indigo-800/80 dark:text-indigo-300/80 mt-0.5">
                Controls the real-time shipping badge under the Buy Button and the expandable "Shipping & 30-Day Return Guarantee" accordion.
              </p>
            </div>
          </div>

          <div className="space-y-4">
            <div className="flex items-center justify-between p-3.5 rounded-xl bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700">
              <div>
                <span className="text-xs font-bold text-neutral-900 dark:text-white block">
                  Policy Inheritance
                </span>
                <span className="text-[11px] text-neutral-500">
                  Use standard storewide shipping terms or customize for this specific product.
                </span>
              </div>

              <div className="flex items-center gap-2">
                <button
                  type="button"
                  onClick={() => handleUpdateShipping({ useStoreDefault: true })}
                  className={`px-3 py-1.5 rounded-lg text-xs font-semibold transition-all ${
                    shippingPolicy.useStoreDefault
                      ? 'bg-indigo-600 text-white shadow-xs'
                      : 'bg-neutral-200 dark:bg-neutral-700 text-neutral-700 dark:text-neutral-300'
                  }`}
                >
                  Global Store Default
                </button>
                <button
                  type="button"
                  onClick={() => handleUpdateShipping({ useStoreDefault: false })}
                  className={`px-3 py-1.5 rounded-lg text-xs font-semibold transition-all ${
                    !shippingPolicy.useStoreDefault
                      ? 'bg-indigo-600 text-white shadow-xs'
                      : 'bg-neutral-200 dark:bg-neutral-700 text-neutral-700 dark:text-neutral-300'
                  }`}
                >
                  Custom Override
                </button>
              </div>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                  Shipping Badge Headline (Above Countdown)
                </label>
                <input
                  type="text"
                  value={shippingPolicy.shippingBadgeText || ''}
                  onChange={(e) => handleUpdateShipping({ shippingBadgeText: e.target.value })}
                  placeholder="e.g. Complimentary Express Shipping Included"
                  className="w-full px-3.5 py-2 text-xs rounded-xl border bg-white dark:bg-neutral-900 border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                  Dispatch & Delivery Lead-Time Text
                </label>
                <input
                  type="text"
                  value={shippingPolicy.leadTimeText || ''}
                  onChange={(e) => handleUpdateShipping({ leadTimeText: e.target.value })}
                  placeholder="e.g. Order in the next 3 hrs 12 mins for dispatch today..."
                  className="w-full px-3.5 py-2 text-xs rounded-xl border bg-white dark:bg-neutral-900 border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white"
                />
              </div>
            </div>

            <div>
              <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                Full Return Guarantee & Courier Policy (Detailed Accordion Body)
              </label>
              <textarea
                rows={4}
                value={shippingPolicy.policyContentHtml || ''}
                onChange={(e) => handleUpdateShipping({ policyContentHtml: e.target.value })}
                placeholder="Write detailed courier partners, customs policy, and return window terms..."
                className="w-full px-3.5 py-2 text-xs rounded-xl border bg-white dark:bg-neutral-900 border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white"
              />
            </div>
          </div>
        </div>
      )}

      {/* ---------------------------------------------------------------- */}
      {/* 4. CUSTOM ACCORDIONS SECTION */}
      {/* ---------------------------------------------------------------- */}
      {subSection === 'accordions' && (
        <div className="space-y-4 animate-in fade-in duration-150">
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-4 rounded-2xl bg-neutral-50 dark:bg-neutral-800/50 border border-neutral-200 dark:border-neutral-700">
            <div>
              <h3 className="text-xs font-bold text-neutral-900 dark:text-white uppercase tracking-wider flex items-center gap-2">
                <Layers className="w-4 h-4 text-purple-500" />
                <span>Custom Extensible Accordions (FAQ, Care, Box Contents)</span>
              </h3>
              <p className="text-[11px] text-neutral-500 dark:text-neutral-400 mt-0.5">
                Add category-specific collapsible drawers directly onto the PDP.
              </p>
            </div>

            <button
              type="button"
              onClick={handleLoadSampleAccordions}
              className="px-3 py-1.5 rounded-xl bg-purple-50 dark:bg-purple-950/60 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-800 hover:bg-purple-100 text-xs font-semibold flex items-center gap-1.5 shrink-0"
            >
              <RefreshCw className="w-3.5 h-3.5" />
              <span>Load 3 Preset Drawers</span>
            </button>
          </div>

          <div className="space-y-3">
            {customAccordions.map((acc, idx) => (
              <div
                key={acc.id}
                className="p-4 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 space-y-3"
              >
                <div className="flex items-center justify-between gap-3">
                  <div className="flex items-center gap-2 flex-1">
                    <span className="w-5 h-5 rounded-lg bg-neutral-100 dark:bg-neutral-800 text-neutral-500 flex items-center justify-center text-[10px] font-mono font-bold">
                      {idx + 1}
                    </span>
                    <input
                      type="text"
                      value={acc.title}
                      onChange={(e) => {
                        const newTitle = e.target.value;
                        persistChange({
                          ...editorData,
                          customAccordions: customAccordions.map(a => a.id === acc.id ? { ...a, title: newTitle } : a)
                        });
                      }}
                      className="flex-1 px-3 py-1.5 text-xs font-bold rounded-xl border bg-neutral-50 dark:bg-neutral-800 border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white"
                      placeholder="Drawer Title (e.g. What's In The Box)"
                    />
                  </div>

                  <button
                    type="button"
                    onClick={() => handleRemoveAccordion(acc.id)}
                    className="p-1.5 text-neutral-400 hover:text-rose-500 rounded-lg hover:bg-rose-50 dark:hover:bg-rose-950/40"
                    title="Remove accordion"
                  >
                    <Trash2 className="w-4 h-4" />
                  </button>
                </div>

                <textarea
                  rows={3}
                  value={acc.content}
                  onChange={(e) => {
                    const newContent = e.target.value;
                    persistChange({
                      ...editorData,
                      customAccordions: customAccordions.map(a => a.id === acc.id ? { ...a, content: newContent } : a)
                    });
                  }}
                  className="w-full px-3.5 py-2 text-xs rounded-xl border bg-neutral-50 dark:bg-neutral-800 border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white"
                  placeholder="Markdown or bullet points for this section..."
                />
              </div>
            ))}
          </div>

          {/* Add Accordion Input */}
          <div className="p-4 rounded-2xl bg-neutral-50 dark:bg-neutral-800/40 border border-neutral-200 dark:border-neutral-700 space-y-3">
            <h4 className="text-xs font-bold text-neutral-900 dark:text-white">+ Add New Custom Collapsible Section</h4>
            <div className="flex flex-col sm:flex-row gap-2">
              <input
                type="text"
                value={newAccordionTitle}
                onChange={(e) => setNewAccordionTitle(e.target.value)}
                placeholder="Drawer Heading (e.g. Sustainable Materials / Carbon Neutrality)"
                className="flex-1 px-3.5 py-2 text-xs rounded-xl border bg-white dark:bg-neutral-900 border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white"
              />
            </div>
            <textarea
              rows={2}
              value={newAccordionContent}
              onChange={(e) => setNewAccordionContent(e.target.value)}
              placeholder="Content details (supports multi-line bullets)..."
              className="w-full px-3.5 py-2 text-xs rounded-xl border bg-white dark:bg-neutral-900 border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white"
            />
            <button
              type="button"
              onClick={handleAddAccordion}
              className="px-4 py-2 rounded-xl bg-purple-600 hover:bg-purple-700 text-white text-xs font-bold transition-all shadow-sm flex items-center gap-1.5"
            >
              <Plus className="w-3.5 h-3.5" />
              <span>Create Accordion Section</span>
            </button>
          </div>
        </div>
      )}

      {/* ---------------------------------------------------------------- */}
      {/* 5. FREQUENTLY PAIRED WITH (CROSS-SELL & MODULAR SYNERGY) */}
      {/* ---------------------------------------------------------------- */}
      {subSection === 'cross_sell' && (
        <div className="space-y-4 animate-in fade-in duration-150">
          <div className="p-4 rounded-2xl bg-amber-500/10 border border-amber-500/20 flex items-start gap-3">
            <Package className="w-5 h-5 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" />
            <div>
              <h4 className="text-xs font-bold text-amber-950 dark:text-amber-200">
                Frequently Paired With This Model (Modular Synergy)
              </h4>
              <p className="text-[11px] text-amber-900/80 dark:text-amber-300/80 mt-0.5">
                Select 1 to 4 complementary products that will appear directly at the bottom of the PDP with a quick "Add Pair" button.
              </p>
            </div>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
            {otherProducts.map((p) => {
              const isSelected = crossSellIds.includes(p.id);
              return (
                <div
                  key={p.id}
                  onClick={() => toggleCrossSellProduct(p.id)}
                  className={`p-3 rounded-2xl border transition-all cursor-pointer flex items-center gap-3 ${
                    isSelected
                      ? 'bg-amber-50/80 dark:bg-amber-950/40 border-amber-500 ring-2 ring-amber-500/20'
                      : 'bg-white dark:bg-neutral-900 border-neutral-200 dark:border-neutral-800 hover:border-neutral-300'
                  }`}
                >
                  <img
                    src={p.images[0] || 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=200'}
                    alt={p.title}
                    className="w-12 h-12 rounded-xl object-cover border border-neutral-200 dark:border-neutral-700 shrink-0"
                  />
                  <div className="min-w-0 flex-1">
                    <span className="text-[10px] text-neutral-400 font-mono block uppercase truncate">{p.brand}</span>
                    <h5 className="text-xs font-bold text-neutral-900 dark:text-white truncate">{p.title}</h5>
                    <span className="text-xs font-bold text-indigo-600 dark:text-indigo-400 font-mono">{money(p.basePrice)}</span>
                  </div>

                  <div className={`w-5 h-5 rounded-full border flex items-center justify-center shrink-0 ${
                    isSelected ? 'bg-amber-500 border-amber-500 text-white' : 'border-neutral-300 dark:border-neutral-700'
                  }`}>
                    {isSelected && <Check className="w-3 h-3 stroke-[3]" />}
                  </div>
                </div>
              );
            })}
          </div>
        </div>
      )}

      {/* ---------------------------------------------------------------- */}
      {/* 6. AUTHENTIC BUYER REVIEWS & SOCIAL PROOF SUMMARY (READ-ONLY) */}
      {/* ---------------------------------------------------------------- */}
      {subSection === 'reviews' && (
        <div className="space-y-5 animate-in fade-in duration-150">
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-5 rounded-2xl bg-gradient-to-r from-amber-500/10 via-orange-500/5 to-transparent border border-amber-500/20">
            <div>
              <div className="flex items-center gap-2">
                <span className="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/15 text-amber-700 dark:text-amber-300 border border-amber-500/30 uppercase tracking-wider">
                  Live Moderated Ratings
                </span>
                <span className="text-xs text-neutral-400 font-mono">Product ID: {formData.id}</span>
              </div>
              <h3 className="text-sm font-bold text-neutral-900 dark:text-white mt-1 flex items-center gap-2">
                <Star className="w-4 h-4 text-amber-500 fill-amber-500" />
                <span>Buyer Reviews & PDP Social Proof Summary</span>
              </h3>
              <p className="text-xs text-neutral-600 dark:text-neutral-400 mt-0.5 max-w-xl leading-relaxed">
                Rating metrics are computed in real-time from verified post-fulfillment buyer feedback. Reviews cannot be manually fabricated by staff.
              </p>
            </div>

            <button
              type="button"
              onClick={handleOpenModerationHub}
              className="px-4 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-600 text-white text-xs font-bold transition-all shadow-xs flex items-center gap-2 shrink-0 cursor-pointer"
            >
              <ShieldCheck className="w-4 h-4" />
              <span>Moderate Product Reviews</span>
              <ExternalLink className="w-3.5 h-3.5" />
            </button>
          </div>

          <div className="grid grid-cols-2 sm:grid-cols-4 gap-3.5">
            <div className="p-4 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
              <span className="text-[10px] uppercase font-bold text-neutral-400 block tracking-wider">
                Average Buyer Rating
              </span>
              <div className="flex items-baseline gap-2 mt-1.5">
                <span className="text-2xl font-bold text-neutral-900 dark:text-white font-mono">
                  {calculatedAvgRating}
                </span>
                <div className="flex items-center text-amber-400">
                  {Array.from({ length: 5 }).map((_, i) => (
                    <Star
                      key={i}
                      className={`w-3 h-3 ${
                        i < Math.round(Number(calculatedAvgRating))
                          ? 'fill-amber-400 text-amber-400'
                          : 'text-neutral-300 dark:text-neutral-700'
                      }`}
                    />
                  ))}
                </div>
              </div>
              <span className="text-[11px] text-neutral-400 mt-1 block">Live score displayed on PDP</span>
            </div>

            <div className="p-4 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
              <span className="text-[10px] uppercase font-bold text-neutral-400 block tracking-wider">
                Published & Visible
              </span>
              <div className="flex items-baseline gap-2 mt-1.5">
                <span className="text-2xl font-bold text-emerald-600 dark:text-emerald-400 font-mono">
                  {publishedProductReviews.length}
                </span>
                <span className="text-xs text-neutral-400">Approved</span>
              </div>
              <span className="text-[11px] text-neutral-400 mt-1 block">Active customer testimonials</span>
            </div>

            <div className="p-4 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
              <span className="text-[10px] uppercase font-bold text-neutral-400 block tracking-wider">
                Pending Moderation
              </span>
              <div className="flex items-baseline gap-2 mt-1.5">
                <span className="text-2xl font-bold text-amber-600 dark:text-amber-400 font-mono">
                  {pendingProductReviews.length}
                </span>
                {pendingProductReviews.length > 0 && (
                  <span className="text-[10px] font-bold px-1.5 py-0.2 rounded-full bg-amber-500/15 text-amber-700 dark:text-amber-300">
                    Action Req.
                  </span>
                )}
              </div>
              <span className="text-[11px] text-neutral-400 mt-1 block">Awaiting staff review</span>
            </div>

            <div className="p-4 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
              <span className="text-[10px] uppercase font-bold text-neutral-400 block tracking-wider">
                Rejected / Filtered
              </span>
              <div className="flex items-baseline gap-2 mt-1.5">
                <span className="text-2xl font-bold text-rose-600 dark:text-rose-400 font-mono">
                  {rejectedProductReviews.length}
                </span>
                <span className="text-xs text-neutral-400">Audited</span>
              </div>
              <span className="text-[11px] text-neutral-400 mt-1 block">Withheld from storefront</span>
            </div>
          </div>

          <div className="p-4 rounded-2xl bg-neutral-50 dark:bg-neutral-800/40 border border-neutral-200 dark:border-neutral-700 flex items-start gap-3">
            <Info className="w-4 h-4 text-neutral-500 dark:text-neutral-400 shrink-0 mt-0.5" />
            <div className="text-xs text-neutral-600 dark:text-neutral-400 leading-relaxed">
              <span className="font-bold text-neutral-900 dark:text-white">Strict Social Proof Integrity: </span>
              “Verified Buyer” status is system-validated against real fulfilled order IDs. Staff cannot create artificial buyer reviews or manually modify star scores in this form. To approve or reject customer feedback submissions, please use the dedicated Product Reviews moderation workflow.
            </div>
          </div>

          <div className="space-y-3">
            <div className="flex items-center justify-between">
              <h4 className="text-xs font-bold text-neutral-900 dark:text-white uppercase tracking-wider flex items-center gap-2">
                <MessageSquare className="w-3.5 h-3.5 text-neutral-400" />
                <span>Live Approved Reviews for this Product ({publishedProductReviews.length})</span>
              </h4>
              <button
                type="button"
                onClick={handleOpenModerationHub}
                className="text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:underline flex items-center gap-1"
              >
                <span>View all in moderation hub</span>
                <ExternalLink className="w-3 h-3" />
              </button>
            </div>

            {publishedProductReviews.length === 0 ? (
              <div className="p-8 text-center rounded-2xl bg-neutral-50/50 dark:bg-neutral-900/50 border border-neutral-200 dark:border-neutral-800 space-y-2">
                <p className="text-xs text-neutral-500 dark:text-neutral-400">
                  No published reviews yet for this product. Reviews submitted by buyers post-delivery will appear in the Moderation Hub for approval.
                </p>
                {pendingProductReviews.length > 0 && (
                  <button
                    type="button"
                    onClick={handleOpenModerationHub}
                    className="px-3.5 py-1.5 rounded-xl bg-amber-500 hover:bg-amber-600 text-white text-xs font-bold transition shadow-xs inline-flex items-center gap-1.5 mt-2"
                  >
                    <Clock className="w-3.5 h-3.5" />
                    <span>Review {pendingProductReviews.length} Pending Submission{pendingProductReviews.length > 1 ? 's' : ''}</span>
                  </button>
                )}
              </div>
            ) : (
              publishedProductReviews.map((rev) => (
                <div
                  key={rev.id}
                  className="p-4 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 space-y-2"
                >
                  <div className="flex items-center justify-between gap-3">
                    <div className="flex items-center gap-2 flex-wrap">
                      <span className="text-xs font-bold text-neutral-900 dark:text-white">{rev.buyerName}</span>
                      {rev.verifiedBuyer && (
                        <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-100 dark:bg-emerald-950/60 text-[10px] font-bold text-emerald-700 dark:text-emerald-300">
                          <ShieldCheck className="w-3 h-3 text-emerald-600 dark:text-emerald-400" />
                          <span>Verified Buyer</span>
                        </span>
                      )}
                      <span className="text-[11px] text-neutral-400 font-mono">
                        Order: {rev.orderNumber}
                      </span>
                    </div>

                    <span className="text-[10px] font-mono text-neutral-400">
                      {new Date(rev.submittedAt).toLocaleDateString()}
                    </span>
                  </div>

                  <div className="flex items-center gap-1 text-amber-400">
                    {Array.from({ length: 5 }).map((_, i) => (
                      <Star
                        key={i}
                        className={`w-3.5 h-3.5 ${i < rev.rating ? 'fill-amber-400 text-amber-400' : 'text-neutral-300 dark:text-neutral-700'}`}
                      />
                    ))}
                    <span className="text-xs font-bold text-neutral-900 dark:text-white ml-1.5">{rev.title}</span>
                  </div>

                  <p className="text-xs text-neutral-600 dark:text-neutral-400 leading-relaxed">
                    {rev.comment}
                  </p>

                  {rev.variantTitle && (
                    <span className="inline-block text-[10px] text-neutral-400 font-mono">
                      Purchased: {rev.variantTitle}
                    </span>
                  )}

                  {rev.photos.length > 0 && (
                    <div className="flex items-center gap-2 pt-1">
                      {rev.photos.map((photo) => (
                        <img
                          key={photo.id}
                          src={photo.url}
                          alt="Customer photo"
                          className="w-10 h-10 rounded-lg object-cover border border-neutral-200 dark:border-neutral-800"
                          referrerPolicy="no-referrer"
                        />
                      ))}
                    </div>
                  )}
                </div>
              ))
            )}
          </div>
        </div>
      )}
    </div>
  );
};
