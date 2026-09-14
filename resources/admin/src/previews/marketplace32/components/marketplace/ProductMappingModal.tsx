import React, { useState, useMemo } from 'react';
import {MarketplaceRequestError} from '../../../../integration/marketplaceData';
import { useAdmin } from '../../context/AdminContext';
import { 
  MarketplaceStoreProduct, 
  MarketplaceStoreVariant, 
  Product, 
  ProductVariant 
} from '../../types';
import { MarketplaceLogo } from './MarketplaceLogo';
import { 
  X, 
  Search, 
  Check, 
  Link2, 
  Unlink, 
  AlertCircle, 
  AlertTriangle,
  Layers, 
  ArrowRight, 
  Tag, 
  CheckCircle2, 
  Store,
  ChevronDown,
  ChevronUp,
  PackageCheck,
  ShieldCheck,
  Info,
  Plus,
  DownloadCloud
} from 'lucide-react';

interface ProductMappingModalProps {
  isOpen: boolean;
  onClose: () => void;
  storeProduct: MarketplaceStoreProduct;
  storeVariant: MarketplaceStoreVariant;
  startWithImport?: boolean;
}

export const ProductMappingModal: React.FC<ProductMappingModalProps> = ({
  isOpen,
  onClose,
  storeProduct,
  storeVariant,
  startWithImport = false,
}) => {
  const { 
    products = [], 
    categories = [],
    mapStoreVariantToJwSku, 
    unmapStoreVariant,
    marketplaceStoreProducts = [],
    importStoreProductToMaster,
    addToast
  } = useAdmin();

  const [searchQuery, setSearchQuery] = useState('');
  const [isCreating, setIsCreating] = useState(false);
  const [isSavingMapping, setIsSavingMapping] = useState(false);
  const [selectedCategoryId, setSelectedCategoryId] = useState<string>('all');
  const [selectedProductId, setSelectedProductId] = useState<string>(storeVariant.mappedJwProductId || '');
  const [selectedVariantId, setSelectedVariantId] = useState<string>(storeVariant.mappedJwVariantId || '');
  const [expandedProductIds, setExpandedProductIds] = useState<Record<string, boolean>>(() => {
    if (storeVariant.mappedJwProductId) {
      return { [storeVariant.mappedJwProductId]: true };
    }
    return {};
  });

  // State for "Create Master SKU & Link" drawer
  const [isCreateDrawerOpen, setIsCreateDrawerOpen] = useState(startWithImport);
  const [createMode, setCreateMode] = useState<'new_product' | 'append_variant'>('new_product');
  const [targetAppendProductId, setTargetAppendProductId] = useState<string>('');
  const [customMasterSku, setCustomMasterSku] = useState(storeVariant.channelSellerSku || '');

  // Detect whether storeVariant.channelSellerSku already exists in JW catalog
  const existingSkuConflict = useMemo(() => {
    const rawSku = (storeVariant.channelSellerSku || '').trim().toLowerCase();
    if (!rawSku) return null;
    for (const p of products) {
      const v = p.variants.find(item => item.sku.toLowerCase() === rawSku);
      if (v) {
        return { product: p, variant: v };
      }
    }
    return null;
  }, [storeVariant.channelSellerSku, products]);

  // If there is a suggested match and no selection yet, expand that product by default
  React.useEffect(() => {
    if (!storeVariant.mappedJwProductId && storeVariant.suggestedMatch) {
      setSelectedProductId(storeVariant.suggestedMatch.productId);
      setSelectedVariantId(storeVariant.suggestedMatch.variantId);
      setExpandedProductIds({ [storeVariant.suggestedMatch.productId]: true });
    } else if (storeVariant.mappedJwProductId) {
      setSelectedProductId(storeVariant.mappedJwProductId);
      setSelectedVariantId(storeVariant.mappedJwVariantId || '');
      setExpandedProductIds({ [storeVariant.mappedJwProductId]: true });
    }
  }, [storeVariant]);

  // Track where each JW Variant is mapped:
  // 1. Same-Store mappings (cannot be reused within the same store!)
  // 2. Different-Store mappings (allowed cross-store)
  const variantMappingContext = useMemo(() => {
    const sameStore = new Map<string, { storeProductTitle: string; storeVariantTitle: string; channelSku: string; variantId: string }>();
    const otherStores = new Map<string, Array<{ shopName: string; platform: string }>>();

    marketplaceStoreProducts.forEach(prod => {
      const isSameStore = prod.connectionId === storeProduct.connectionId;

      prod.variants.forEach(v => {
        if (v.mappingStatus === 'mapped' && v.mappedJwVariantId) {
          if (isSameStore) {
            // If in same store and NOT this exact variant we are currently editing:
            if (v.id !== storeVariant.id) {
              sameStore.set(v.mappedJwVariantId, {
                storeProductTitle: prod.title,
                storeVariantTitle: v.title,
                channelSku: v.channelSellerSku,
                variantId: v.id
              });
            }
          } else {
            // Across different stores (allowed)
            const list = otherStores.get(v.mappedJwVariantId) || [];
            list.push({ shopName: prod.shopName, platform: prod.platform });
            otherStores.set(v.mappedJwVariantId, list);
          }
        }
      });
    });

    return { sameStore, otherStores };
  }, [marketplaceStoreProducts, storeProduct.connectionId, storeVariant.id]);

  // Compute how many channel stores each JW SKU is currently mapped to
  const jwSkuMappingCounts = useMemo(() => {
    const counts: Record<string, number> = {};
    marketplaceStoreProducts.forEach(prod => {
      prod.variants.forEach(v => {
        if (v.mappingStatus === 'mapped' && v.mappedJwSku) {
          counts[v.mappedJwSku] = (counts[v.mappedJwSku] || 0) + 1;
        }
      });
    });
    return counts;
  }, [marketplaceStoreProducts]);

  // Filter products by search query and category
  const filteredProducts = useMemo(() => {
    return products.filter(p => {
      // Category filter
      if (selectedCategoryId !== 'all' && p.categoryId !== selectedCategoryId) {
        return false;
      }
      // Search query
      if (!searchQuery.trim()) return true;
      const q = searchQuery.toLowerCase();
      const matchTitle = p.title.toLowerCase().includes(q);
      const matchBrand = p.brand?.toLowerCase().includes(q);
      const matchVariant = p.variants?.some(v => 
        v.title.toLowerCase().includes(q) || 
        v.sku.toLowerCase().includes(q) ||
        (v.barcode && v.barcode.toLowerCase().includes(q))
      );
      return matchTitle || matchBrand || matchVariant;
    });
  }, [products, selectedCategoryId, searchQuery]);

  // Selected Product and Variant objects
  const selectedProduct = useMemo(() => {
    return products.find(p => p.id === selectedProductId) || null;
  }, [products, selectedProductId]);

  const selectedVariant = useMemo(() => {
    if (!selectedProduct) return null;
    return selectedProduct.variants?.find(v => v.id === selectedVariantId) || null;
  }, [selectedProduct, selectedVariantId]);

  const selectedSameStoreConflict = useMemo(() => {
    if (!selectedVariantId) return null;
    return variantMappingContext.sameStore.get(selectedVariantId) || null;
  }, [selectedVariantId, variantMappingContext.sameStore]);

  if (!isOpen) return null;

  const toggleExpand = (productId: string) => {
    setExpandedProductIds(prev => ({
      ...prev,
      [productId]: !prev[productId]
    }));
  };

  const handleSelectVariant = (product: Product, variant: ProductVariant) => {
    setSelectedProductId(product.id);
    setSelectedVariantId(variant.id);
    setExpandedProductIds(prev => ({ ...prev, [product.id]: true }));
  };

  const saveMappingAction = async (operation: () => void | Promise<void>) => {
    if (isSavingMapping) return;
    setIsSavingMapping(true);
    try {await operation();onClose();}
    catch {addToast({type:'error',title:'Mapping failed',message:'The change was not confirmed. Refresh and check for conflicts before retrying.'});}
    finally {setIsSavingMapping(false);}
  };
  const handleSaveMapping = () => {
    if (!selectedProductId || !selectedVariantId || selectedSameStoreConflict) return;
    void saveMappingAction(()=>mapStoreVariantToJwSku(storeProduct.id, storeVariant.id, selectedProductId, selectedVariantId));
  };

  const handleUnmap = () => {
    void saveMappingAction(()=>unmapStoreVariant(storeProduct.id, storeVariant.id));
  };

  const platformBadgeColor = {
    tiktok: 'bg-black text-white dark:bg-neutral-800 dark:border-neutral-700',
    shopee: 'bg-orange-500 text-white',
    lazada: 'bg-blue-600 text-white',
    shopify: 'bg-emerald-600 text-white',
    direct: 'bg-neutral-700 text-white'
  }[storeProduct.platform] || 'bg-neutral-800 text-white';

  const platformLabel = {
    tiktok: 'TikTok Shop',
    shopee: 'Shopee',
    lazada: 'Lazada',
    shopify: 'Shopify',
    direct: 'Direct Web'
  }[storeProduct.platform];

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-fadeIn">
      <div 
        id="product-mapping-modal"
        className="relative w-full max-w-4xl max-h-[90vh] flex flex-col bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-2xl shadow-2xl overflow-hidden"
      >
        {/* Header */}
        <div className="flex items-center justify-between px-6 py-4 border-b border-neutral-200 dark:border-neutral-800 bg-neutral-50/50 dark:bg-neutral-900/50">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-200 dark:border-indigo-800 flex items-center justify-center text-indigo-600 dark:text-indigo-400">
              <Link2 className="w-5 h-5" />
            </div>
            <div>
              <div className="flex items-center gap-2.5">
                <h2 className="text-lg font-black text-neutral-900 dark:text-white">
                  Map Product Variant
                </h2>
                <MarketplaceLogo platform={storeProduct.platform} variant="badge" />
              </div>
              <p className="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">
                Store: {storeProduct.shopName} · Product ID: {storeProduct.channelProductId}
              </p>
            </div>
          </div>

          <button
            id="btn-close-mapping-modal"
            onClick={onClose}
            className="p-2 rounded-xl text-neutral-400 hover:text-neutral-700 dark:hover:text-white hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors cursor-pointer"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Modal Body: Two-tier layout */}
        <div className="flex-1 overflow-y-auto p-6 space-y-6">
          {/* Target Store Variant Card */}
          <div className="p-4 rounded-xl border border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-800/50">
            <div className="flex items-start justify-between gap-4">
              <div className="flex items-start gap-3">
                <img
                  src={storeVariant.imageUrl || storeProduct.imageUrl}
                  alt={storeVariant.title}
                  className="w-14 h-14 rounded-xl object-cover border border-neutral-200 dark:border-neutral-700 flex-shrink-0"
                />
                <div>
                  <div className="flex items-center gap-2 flex-wrap">
                    <span className="text-xs font-semibold text-neutral-500 dark:text-neutral-400">
                      Target Channel Variant:
                    </span>
                    <h3 className="text-sm font-bold text-neutral-900 dark:text-white">
                      {storeVariant.title}
                    </h3>
                  </div>
                  <p className="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">
                    Store Listing: {storeProduct.title}
                  </p>
                  <div className="flex items-center gap-3 mt-2 flex-wrap text-xs">
                    <span className="inline-flex items-center gap-1 font-mono text-[11px] px-2 py-0.5 rounded bg-neutral-200/80 dark:bg-neutral-700 text-neutral-800 dark:text-neutral-200">
                      <Tag className="w-3 h-3 text-neutral-400" />
                      Channel SKU: {storeVariant.channelSellerSku}
                    </span>
                    <span className="font-semibold text-neutral-700 dark:text-neutral-300">
                      Channel Price: {storeVariant.currency} {typeof storeVariant.price === 'number' && Number.isFinite(storeVariant.price) ? storeVariant.price.toFixed(2) : 'Not provided'}
                    </span>
                    <span className="text-neutral-500">
                      Channel Stock: {storeVariant.stock == null ? 'Not provided' : `${storeVariant.stock} units`}
                    </span>
                  </div>
                </div>
              </div>

              {/* Status badge */}
              <div className="flex-shrink-0 text-right">
                {storeVariant.mappingStatus === 'mapped' && (
                  <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-100 dark:bg-emerald-950/70 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                    <CheckCircle2 className="w-3.5 h-3.5" />
                    Mapped
                  </span>
                )}
                {storeVariant.mappingStatus === 'unmapped' && (
                  <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-amber-100 dark:bg-amber-950/70 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                    <AlertCircle className="w-3.5 h-3.5" />
                    Unmapped
                  </span>
                )}
                {storeVariant.mappingStatus === 'conflict' && (
                  <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-rose-100 dark:bg-rose-950/70 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800">
                    <AlertCircle className="w-3.5 h-3.5" />
                    Conflict / Invalid
                  </span>
                )}
              </div>
            </div>

            {/* Smart Suggestion banner if available */}
            {storeVariant.suggestedMatch && storeVariant.mappingStatus === 'unmapped' && (
              <div className="mt-3 pt-3 border-t border-neutral-200 dark:border-neutral-700/60 flex items-center justify-between gap-2 flex-wrap">
                <div className="flex items-center gap-2">
                  <span className="w-2 h-2 rounded-full bg-indigo-500 animate-pulse" />
                  <span className="text-xs font-bold text-indigo-700 dark:text-indigo-400">
                    AI Match Recommendation ({storeVariant.suggestedMatch.confidenceScore}% match)
                  </span>
                  <span className="text-xs text-neutral-600 dark:text-neutral-400">
                    Suggested SKU: <strong>{storeVariant.suggestedMatch.sku}</strong> ({storeVariant.suggestedMatch.variantTitle})
                  </span>
                </div>
                <button
                  id="btn-apply-suggestion-modal"
                  type="button"
                  onClick={() => {
                    if (storeVariant.suggestedMatch) {
                      setSelectedProductId(storeVariant.suggestedMatch.productId);
                      setSelectedVariantId(storeVariant.suggestedMatch.variantId);
                      setExpandedProductIds({ [storeVariant.suggestedMatch.productId]: true });
                    }
                  }}
                  className="text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:underline cursor-pointer"
                >
                  Select This Variant
                </button>
              </div>
            )}

            {/* Conflict explanation if any */}
            {storeVariant.mappingStatus === 'conflict' && storeVariant.conflictReason && (
              <div className="mt-3 p-2.5 rounded-lg bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900 text-xs text-rose-700 dark:text-rose-300 flex items-center gap-2">
                <AlertCircle className="w-4 h-4 flex-shrink-0" />
                <span>{storeVariant.conflictReason}</span>
              </div>
            )}
          </div>

          {/* Direct Create to Master Option if not in catalog */}
          {storeVariant.mappingStatus === 'unmapped' && (
            <div className="rounded-xl border border-emerald-500/30 bg-emerald-500/5 p-4 space-y-3">
              <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div className="flex items-center gap-3">
                  <div className="p-2.5 rounded-xl bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 border border-emerald-500/25 shrink-0">
                    <DownloadCloud className="w-5 h-5" />
                  </div>
                  <div>
                    <h5 className="text-xs font-bold text-neutral-900 dark:text-white flex items-center gap-2">
                      Variant Not in Master Catalog Yet?
                      <span className="text-[10px] font-semibold px-2 py-0.5 rounded-md bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300">
                        Zero Local Stock Draft
                      </span>
                    </h5>
                    <p className="text-[11px] text-neutral-500 dark:text-neutral-400">
                      Import this external variant into a JW Master draft and auto-link. Legacy stock starts at 0; physical warehouse stock remains unknown until counted.
                    </p>
                  </div>
                </div>

                {!isCreateDrawerOpen ? (
                  <button
                    type="button"
                    onClick={() => {
                      setIsCreateDrawerOpen(true);
                      setCustomMasterSku(storeVariant.channelSellerSku || '');
                    }}
                    className="px-3.5 py-2 rounded-xl text-xs font-bold bg-emerald-600 hover:bg-emerald-700 text-white shadow-xs transition-colors shrink-0 flex items-center gap-1.5 cursor-pointer"
                  >
                    <Plus className="w-4 h-4" />
                    Create Master SKU & Link...
                  </button>
                ) : (
                  <button
                    type="button"
                    onClick={() => setIsCreateDrawerOpen(false)}
                    className="px-3 py-1.5 rounded-lg text-xs font-semibold text-neutral-500 hover:text-neutral-700 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors cursor-pointer"
                  >
                    Close Drawer
                  </button>
                )}
              </div>

              {/* Expandable Creation & Mapping Review Panel */}
              {isCreateDrawerOpen && (
                <div className="pt-3 border-t border-emerald-500/20 space-y-4 animate-in fade-in slide-in-from-top-1">
                  {/* SKU Conflict Detection Banner */}
                  {existingSkuConflict ? (
                    <div className="p-3.5 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-300 dark:border-amber-800/60 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                      <div className="flex items-start gap-2.5">
                        <AlertTriangle className="w-4 h-4 text-amber-600 shrink-0 mt-0.5" />
                        <div className="text-xs text-amber-900 dark:text-amber-200">
                          <strong className="block text-amber-950 dark:text-amber-100 font-bold">
                            SKU Conflict Detected:
                          </strong>
                          Master SKU <code className="font-mono bg-amber-100 dark:bg-amber-900/60 px-1 py-0.5 rounded font-bold">{storeVariant.channelSellerSku}</code> already exists in JW catalog under <em>"{existingSkuConflict.product.title}" ({existingSkuConflict.variant.title})</em>.
                        </div>
                      </div>

                      <button
                        type="button"
                        onClick={() => {
                          setSelectedProductId(existingSkuConflict.product.id);
                          setSelectedVariantId(existingSkuConflict.variant.id);
                          setExpandedProductIds({ [existingSkuConflict.product.id]: true });
                          setIsCreateDrawerOpen(false);
                        }}
                        className="px-3 py-1.5 rounded-lg text-xs font-bold bg-amber-600 hover:bg-amber-700 text-white shadow-xs shrink-0 flex items-center gap-1.5 cursor-pointer"
                      >
                        <Link2 className="w-3.5 h-3.5" />
                        Link to Existing Variant Instead
                      </button>
                    </div>
                  ) : (
                    <div className="p-3 rounded-lg bg-emerald-100/50 dark:bg-emerald-950/30 text-xs text-emerald-900 dark:text-emerald-200 flex items-center gap-2">
                      <ShieldCheck className="w-4 h-4 text-emerald-600 shrink-0" />
                      <span>SKU availability check passed: <strong className="font-mono">{storeVariant.channelSellerSku || 'Draft SKU will be generated'}</strong> is ready for creation.</span>
                    </div>
                  )}

                  {/* Mode Selector */}
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <label 
                      onClick={() => setCreateMode('new_product')}
                      className={`p-3 rounded-xl border transition-all cursor-pointer flex items-start gap-3 ${
                        createMode === 'new_product'
                          ? 'border-emerald-600 bg-white dark:bg-slate-800 shadow-xs'
                          : 'border-neutral-200 dark:border-neutral-800 bg-neutral-50/50 dark:bg-neutral-900/30 opacity-70'
                      }`}
                    >
                      <input
                        type="radio"
                        name="createMode"
                        checked={createMode === 'new_product'}
                        onChange={() => setCreateMode('new_product')}
                        className="mt-0.5 text-emerald-600 focus:ring-emerald-500"
                      />
                      <div>
                        <div className="text-xs font-bold text-neutral-900 dark:text-white">
                          Create as New Master Product Draft
                        </div>
                        <div className="text-[11px] text-neutral-500 dark:text-neutral-400 mt-0.5">
                          Creates "{storeProduct.title}" with this variant. Never silently merges.
                        </div>
                      </div>
                    </label>

                    <label 
                      onClick={() => setCreateMode('append_variant')}
                      className={`p-3 rounded-xl border transition-all cursor-pointer flex items-start gap-3 ${
                        createMode === 'append_variant'
                          ? 'border-emerald-600 bg-white dark:bg-slate-800 shadow-xs'
                          : 'border-neutral-200 dark:border-neutral-800 bg-neutral-50/50 dark:bg-neutral-900/30 opacity-70'
                      }`}
                    >
                      <input
                        type="radio"
                        name="createMode"
                        checked={createMode === 'append_variant'}
                        onChange={() => setCreateMode('append_variant')}
                        className="mt-0.5 text-emerald-600 focus:ring-emerald-500"
                      />
                      <div>
                        <div className="text-xs font-bold text-neutral-900 dark:text-white">
                          Add as Variant to Existing JW Product
                        </div>
                        <div className="text-[11px] text-neutral-500 dark:text-neutral-400 mt-0.5">
                          Append this variant specification into an existing product catalog item.
                        </div>
                      </div>
                    </label>
                  </div>

                  {/* Target Existing Product Selector if in append mode */}
                  {createMode === 'append_variant' && (
                    <div className="space-y-1.5">
                      <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">
                        Choose Target JW Master Product:
                      </label>
                      <select
                        value={targetAppendProductId}
                        onChange={(e) => setTargetAppendProductId(e.target.value)}
                        className="w-full px-3 py-2 text-xs rounded-xl border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-slate-800 text-neutral-900 dark:text-white focus:ring-1 focus:ring-emerald-500"
                      >
                        <option value="">-- Select Master Product --</option>
                        {products.map(p => (
                          <option key={p.id} value={p.id}>
                            {p.title} — #{p.id} ({p.variants.length} existing variant{p.variants.length > 1 ? 's' : ''})
                          </option>
                        ))}
                      </select>
                    </div>
                  )}

                  {/* SKU & Stock Relationship Specs */}
                  <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 p-3 rounded-xl bg-white dark:bg-slate-800/80 border border-neutral-200 dark:border-neutral-700">
                    <div>
                      <span className="text-[10px] font-semibold text-neutral-500 uppercase block">Master SKU Name</span>
                      <input
                        type="text"
                        value={customMasterSku}
                        onChange={(e) => setCustomMasterSku(e.target.value)}
                        className="mt-1 w-full px-2.5 py-1 text-xs font-mono rounded-lg border border-neutral-300 dark:border-neutral-600 bg-neutral-50 dark:bg-neutral-900 text-neutral-900 dark:text-white"
                        placeholder="e.g. JW-SKU-01"
                      />
                    </div>

                    <div>
                      <span className="text-[10px] font-semibold text-neutral-500 uppercase block">Initial Warehouse Stock</span>
                      <div className="mt-1 flex items-center gap-1.5">
                        <span className="px-2.5 py-1 rounded-md bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300 font-bold text-xs">
                          0 units
                        </span>
                        <span className="text-[11px] text-neutral-400">(Zero physical stock)</span>
                      </div>
                    </div>

                    <div>
                      <span className="text-[10px] font-semibold text-neutral-500 uppercase block">Channel Reference Stock</span>
                      <div className="mt-1 text-xs font-semibold text-neutral-700 dark:text-neutral-300">
                        {storeVariant.stock == null ? 'Not provided' : `${storeVariant.stock} units`} <span className="text-[10px] text-neutral-400 font-normal">(Ref info only)</span>
                      </div>
                    </div>
                  </div>

                  {/* Reassurance text & confirm button */}
                  <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-1">
                    <div className="text-[11px] text-neutral-500 dark:text-neutral-400">
                      Creates an unpublished JW Draft catalog item and establishes a 1:1 auto-link. Does not affect live store listings.
                    </div>

                    <button
                      type="button"
                      disabled={isCreating || (createMode === 'append_variant' && !targetAppendProductId)}
                      onClick={async () => {
                        if (isCreating) return;
                        setIsCreating(true);
                        try {
                        await importStoreProductToMaster(storeProduct.id, storeVariant.id, {
                          createDraftStatus: 'draft',
                          zeroPhysicalStock: true,
                          targetProductId: createMode === 'append_variant' ? targetAppendProductId : undefined,
                          skuOverrides: customMasterSku ? { [storeVariant.id]: customMasterSku } : undefined
                        });
                        onClose();
                        } catch (error) {
                          addToast({type:'error', title:'Import failed', message:error instanceof MarketplaceRequestError && [409,422].includes(error.status)
                            ? error.message : 'The SKU was not confirmed as imported. Refresh the catalog to check the saved result before retrying.'});
                        } finally {
                          setIsCreating(false);
                        }
                      }}
                      className="px-4 py-2 rounded-xl text-xs font-bold bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white shadow-xs transition-colors shrink-0 flex items-center gap-1.5 cursor-pointer"
                    >
                      <CheckCircle2 className="w-4 h-4" />
                      Confirm Master SKU Creation & Link
                    </button>
                  </div>
                </div>
              )}
            </div>
          )}

          {/* Section: Select JW Product & Variant */}
          <div className="space-y-3">
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
              <div>
                <h4 className="text-sm font-bold text-neutral-900 dark:text-white flex items-center gap-2">
                  <PackageCheck className="w-4 h-4 text-indigo-600 dark:text-indigo-400" />
                  Select JW Master Product & Variant
                </h4>
                <p className="text-xs text-neutral-500 dark:text-neutral-400">
                  Mappings must target a specific SKU/variant (e.g. size, color). One master SKU can be mapped to multiple channel stores.
                </p>
              </div>
              <span className="text-xs text-neutral-500">
                Master Catalog: {products.length} Products
              </span>
            </div>

            {/* Filter and Search Bar */}
            <div className="flex flex-col sm:flex-row items-center gap-2.5">
              <div className="relative flex-1 w-full">
                <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-neutral-400" />
                <input
                  id="input-search-jw-product"
                  type="text"
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                  placeholder="Search by product name, brand, or SKU (e.g. AUR-BLK, keyboard)..."
                  className="w-full pl-9 pr-8 py-2 text-xs rounded-xl bg-neutral-50 dark:bg-neutral-800/80 border border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white placeholder-neutral-400 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                />
                {searchQuery && (
                  <button 
                    onClick={() => setSearchQuery('')}
                    className="absolute right-2.5 top-1/2 -translate-y-1/2 text-neutral-400 hover:text-neutral-600 cursor-pointer"
                  >
                    <X className="w-3.5 h-3.5" />
                  </button>
                )}
              </div>

              {/* Category Pills */}
              <div className="flex items-center gap-1.5 overflow-x-auto w-full sm:w-auto py-1">
                <button
                  type="button"
                  onClick={() => setSelectedCategoryId('all')}
                  className={`text-xs px-3 py-1.5 rounded-lg font-medium whitespace-nowrap transition-colors cursor-pointer ${
                    selectedCategoryId === 'all'
                      ? 'bg-neutral-900 text-white dark:bg-white dark:text-neutral-900 font-bold'
                      : 'bg-neutral-100 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400 hover:bg-neutral-200 dark:hover:bg-neutral-700'
                  }`}
                >
                  All Categories
                </button>
                {categories.slice(0, 5).map(cat => (
                  <button
                    key={cat.id}
                    type="button"
                    onClick={() => setSelectedCategoryId(cat.id)}
                    className={`text-xs px-3 py-1.5 rounded-lg font-medium whitespace-nowrap transition-colors cursor-pointer ${
                      selectedCategoryId === cat.id
                        ? 'bg-neutral-900 text-white dark:bg-white dark:text-neutral-900 font-bold'
                        : 'bg-neutral-100 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400 hover:bg-neutral-200 dark:hover:bg-neutral-700'
                    }`}
                  >
                    {cat.name}
                  </button>
                ))}
              </div>
            </div>

            {/* Product & Variant Tree List */}
            <div className="border border-neutral-200 dark:border-neutral-800 rounded-xl divide-y divide-neutral-200 dark:divide-neutral-800 max-h-72 overflow-y-auto bg-white dark:bg-neutral-900">
              {filteredProducts.length === 0 ? (
                <div className="p-8 text-center text-neutral-500">
                  <Search className="w-8 h-8 mx-auto mb-2 text-neutral-300 dark:text-neutral-700" />
                  <p className="text-sm font-semibold">No matching master products found</p>
                  <p className="text-xs mt-1">Try another search keyword or clear category filter</p>
                </div>
              ) : (
                filteredProducts.map(product => {
                  const isExpanded = expandedProductIds[product.id];
                  const hasSelectedVariant = selectedProductId === product.id;
                  const variantsCount = product.variants?.length || 0;

                  return (
                    <div key={product.id} className="transition-colors">
                      {/* Product Header Row */}
                      <div 
                        onClick={() => toggleExpand(product.id)}
                        className={`flex items-center justify-between p-3.5 hover:bg-neutral-50 dark:hover:bg-neutral-800/40 cursor-pointer ${
                          hasSelectedVariant ? 'bg-indigo-50/40 dark:bg-indigo-950/20' : ''
                        }`}
                      >
                        <div className="flex items-center gap-3">
                          <img
                            src={product.images?.[0] || 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=100'}
                            alt={product.title}
                            className="w-10 h-10 rounded-lg object-cover border border-neutral-200 dark:border-neutral-700 flex-shrink-0"
                          />
                          <div>
                            <div className="flex items-center gap-2">
                              <h5 className="text-xs font-bold text-neutral-900 dark:text-white">
                                {product.title}
                              </h5>
                              {product.brand && (
                                <span className="text-[10px] px-1.5 py-0.5 rounded bg-neutral-100 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-300">
                                  {product.brand}
                                </span>
                              )}
                            </div>
                            <p className="text-[11px] text-neutral-500 dark:text-neutral-400 mt-0.5">
                              Base Price: RM {product.basePrice.toFixed(2)} · {variantsCount} variants
                            </p>
                          </div>
                        </div>

                        <div className="flex items-center gap-2">
                          <span className="text-xs font-medium text-indigo-600 dark:text-indigo-400">
                            {isExpanded ? 'Hide Variants' : 'Show Variants'}
                          </span>
                          {isExpanded ? (
                            <ChevronUp className="w-4 h-4 text-neutral-400" />
                          ) : (
                            <ChevronDown className="w-4 h-4 text-neutral-400" />
                          )}
                        </div>
                      </div>

                      {/* Variants List when expanded */}
                      {isExpanded && (
                        <div className="bg-neutral-50/60 dark:bg-neutral-800/30 px-3.5 py-2 space-y-1.5 border-t border-neutral-100 dark:border-neutral-800">
                          {product.variants?.map(variant => {
                            const isSelected = selectedProductId === product.id && selectedVariantId === variant.id;
                            const mappedStoreCount = jwSkuMappingCounts[variant.sku] || 0;
                            const sameStoreConflict = variantMappingContext.sameStore.get(variant.id);
                            const otherStoresList = variantMappingContext.otherStores.get(variant.id);

                            return (
                              <div
                                key={variant.id}
                                id={`variant-option-${variant.sku}`}
                                onClick={() => handleSelectVariant(product, variant)}
                                className={`flex items-center justify-between p-2.5 rounded-xl border transition-all cursor-pointer ${
                                  isSelected
                                    ? sameStoreConflict
                                      ? 'bg-amber-50/80 dark:bg-amber-950/40 border-amber-400 dark:border-amber-700 shadow-sm'
                                      : 'bg-indigo-50 dark:bg-indigo-950/50 border-indigo-500 dark:border-indigo-400 shadow-sm'
                                    : 'bg-white dark:bg-neutral-900 border-neutral-200 dark:border-neutral-800 hover:border-neutral-300 dark:hover:border-neutral-700'
                                }`}
                              >
                                <div className="flex items-center gap-3">
                                  <div className={`w-4 h-4 rounded-full border flex items-center justify-center transition-colors ${
                                    isSelected 
                                      ? sameStoreConflict 
                                        ? 'border-amber-500 bg-amber-500 text-white'
                                        : 'border-indigo-600 bg-indigo-600 text-white' 
                                      : 'border-neutral-300 dark:border-neutral-600'
                                  }`}>
                                    {isSelected && <Check className="w-3 h-3 stroke-[3]" />}
                                  </div>

                                  <img
                                    src={variant.image || product.images?.[0]}
                                    alt={variant.title}
                                    className="w-8 h-8 rounded-lg object-cover border border-neutral-200 dark:border-neutral-700"
                                  />

                                  <div>
                                    <div className="flex items-center gap-2 flex-wrap">
                                      <span className="text-xs font-bold text-neutral-900 dark:text-white">
                                        {variant.title}
                                      </span>
                                      <span className="font-mono text-[10px] px-1.5 py-0.5 rounded bg-neutral-100 dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300 font-semibold">
                                        SKU: {variant.sku}
                                      </span>
                                      {sameStoreConflict && (
                                        <span className="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded bg-amber-100 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300 border border-amber-300 dark:border-amber-800">
                                          <AlertTriangle className="w-2.5 h-2.5 text-amber-600" />
                                          Already linked in this store
                                        </span>
                                      )}
                                      {!sameStoreConflict && otherStoresList && otherStoresList.length > 0 && (
                                        <span className="inline-flex items-center gap-1 text-[10px] font-medium px-2 py-0.5 rounded bg-blue-50 dark:bg-blue-950/50 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800">
                                          <Store className="w-2.5 h-2.5 text-blue-500" />
                                          In {otherStoresList.length} other store(s)
                                        </span>
                                      )}
                                    </div>

                                    {/* Option tags */}
                                    <div className="flex items-center gap-2 mt-0.5 text-[11px] text-neutral-500">
                                      <span>Stock: <strong className={variant.inventoryQuantity > 10 ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600'}>{variant.inventoryQuantity}</strong> units</span>
                                      <span>·</span>
                                      <span>Price: RM {variant.price.toFixed(2)}</span>
                                      {mappedStoreCount > 0 && (
                                        <>
                                          <span>·</span>
                                          <span className="text-indigo-600 dark:text-indigo-400">
                                            Total {mappedStoreCount} link(s)
                                          </span>
                                        </>
                                      )}
                                    </div>
                                  </div>
                                </div>

                                <div className="text-right">
                                  <span className={`text-xs font-bold px-2.5 py-1 rounded-lg transition-colors ${
                                    isSelected
                                      ? sameStoreConflict
                                        ? 'bg-amber-500 text-white'
                                        : 'bg-indigo-600 text-white'
                                      : 'bg-neutral-100 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400'
                                  }`}>
                                    {isSelected ? (sameStoreConflict ? 'Conflict' : 'Selected') : 'Select'}
                                  </span>
                                </div>
                              </div>
                            );
                          })}
                        </div>
                      )}
                    </div>
                  );
                })
              )}
            </div>
          </div>

          {/* Side-by-Side Review Confirmation Panel */}
          {selectedVariant && selectedProduct && (
            <div className="space-y-3">
              {/* Conflict banner if same-store duplicate */}
              {selectedSameStoreConflict && (
                <div className="p-3.5 rounded-xl bg-amber-50 dark:bg-amber-950/40 border-2 border-amber-400 dark:border-amber-700/80 text-xs text-amber-900 dark:text-amber-200 space-y-1.5">
                  <div className="flex items-center gap-2 font-bold text-amber-900 dark:text-amber-200 text-sm">
                    <AlertTriangle className="w-4 h-4 text-amber-600 dark:text-amber-400 shrink-0" />
                    Same-Store Mapping Conflict
                  </div>
                  <p className="leading-relaxed">
                    This JW Variant (<span className="font-mono font-bold text-amber-950 dark:text-amber-100">{selectedVariant.sku}</span> · {selectedVariant.title}) is already linked to:
                    <strong className="text-amber-900 dark:text-amber-200 ml-1">
                      "{selectedSameStoreConflict.storeProductTitle} ({selectedSameStoreConflict.storeVariantTitle})"
                    </strong> within this same store.
                  </p>
                  <p className="text-[11px] text-amber-700 dark:text-amber-400">
                    <strong>Rule:</strong> Within the same store, each JW variant can only be used once (to prevent double-deducting stock). Across different stores, reuse is allowed. To use this SKU here, first unlink it from the existing listing.
                  </p>
                </div>
              )}

              <div className="p-4 rounded-xl bg-gradient-to-r from-indigo-50/70 via-neutral-50 to-emerald-50/70 dark:from-indigo-950/30 dark:via-neutral-900 dark:to-emerald-950/30 border border-indigo-200 dark:border-indigo-900 space-y-3">
                <div className="flex items-center gap-2 text-xs font-bold text-neutral-900 dark:text-white">
                  <ShieldCheck className="w-4 h-4 text-indigo-600 dark:text-indigo-400" />
                  Mapping Relationship Preview:
                </div>

                <div className="grid grid-cols-1 md:grid-cols-7 gap-3 items-center">
                  {/* Store Variant */}
                  <div className="md:col-span-3 p-3 rounded-lg bg-white dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700">
                    <div className="text-[11px] font-semibold text-neutral-400 uppercase tracking-wider mb-1">
                      Channel Variant ({platformLabel})
                    </div>
                    <div className="text-xs font-bold text-neutral-900 dark:text-white truncate">
                      {storeVariant.title}
                    </div>
                    <div className="font-mono text-[11px] text-neutral-500 mt-0.5">
                      Channel SKU: {storeVariant.channelSellerSku}
                    </div>
                    <div className="text-[11px] text-neutral-600 dark:text-neutral-400 mt-1">
                      Store Price: {storeVariant.currency} {typeof storeVariant.price === 'number' && Number.isFinite(storeVariant.price) ? storeVariant.price.toFixed(2) : 'Not provided'}
                    </div>
                  </div>

                  {/* Arrow */}
                  <div className="md:col-span-1 flex flex-col items-center justify-center text-indigo-600 dark:text-indigo-400">
                    <div className="w-8 h-8 rounded-full bg-indigo-100 dark:bg-indigo-900/60 flex items-center justify-center">
                      <ArrowRight className="w-4 h-4" />
                    </div>
                    <span className="text-[10px] font-bold mt-1">1:1 Mapping</span>
                  </div>

                  {/* Internal JW Variant */}
                  <div className={`md:col-span-3 p-3 rounded-lg bg-white dark:bg-neutral-800 border shadow-sm ${
                    selectedSameStoreConflict 
                      ? 'border-amber-400 dark:border-amber-700' 
                      : 'border-indigo-300 dark:border-indigo-700/60'
                  }`}>
                    <div className="text-[11px] font-semibold text-indigo-600 dark:text-indigo-400 uppercase tracking-wider mb-1 flex items-center justify-between">
                      <span>JW Master Variant</span>
                      <span className="text-emerald-600 dark:text-emerald-400 font-bold">In Stock: {selectedVariant.inventoryQuantity}</span>
                    </div>
                    <div className="text-xs font-bold text-neutral-900 dark:text-white truncate">
                      {selectedProduct.title} - {selectedVariant.title}
                    </div>
                    <div className="font-mono text-[11px] text-indigo-700 dark:text-indigo-300 font-bold mt-0.5">
                      Master SKU: {selectedVariant.sku}
                    </div>
                    <div className="text-[11px] text-neutral-600 dark:text-neutral-400 mt-1">
                      Master Price: RM {selectedVariant.price.toFixed(2)}
                    </div>
                  </div>
                </div>

                {/* Informational Disclaimer */}
                <div className="flex items-start gap-2 pt-1 text-[11px] text-neutral-500 dark:text-neutral-400">
                  <Info className="w-3.5 h-3.5 text-neutral-400 flex-shrink-0 mt-0.5" />
                  <span>
                    This links the channel variant to the selected JW master SKU. Linking does not enable automatic order deductions or change local inventory, marketplace prices, or platform stock.
                  </span>
                </div>
              </div>
            </div>
          )}
        </div>

        {/* Footer Actions */}
        <div className="flex items-center justify-between px-6 py-4 border-t border-neutral-200 dark:border-neutral-800 bg-neutral-50/50 dark:bg-neutral-900/50">
          <div>
            {storeVariant.mappingStatus === 'mapped' ? (
              <button
                id="btn-unlink-variant"
                type="button"
                onClick={handleUnmap}
                disabled={isSavingMapping || isCreating}
                className="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40 border border-rose-200 dark:border-rose-900 transition-colors cursor-pointer"
              >
                <Unlink className="w-3.5 h-3.5" />
                Unlink Current Mapping
              </button>
            ) : (
              <span className="text-xs text-neutral-400">
                Select a JW master variant above to create link
              </span>
            )}
          </div>

          <div className="flex items-center gap-3">
            <button
              id="btn-cancel-mapping-modal"
              type="button"
              onClick={onClose}
              className="px-4 py-2.5 rounded-xl border border-neutral-200 dark:border-neutral-700 text-neutral-700 dark:text-neutral-300 text-xs font-bold hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors cursor-pointer"
            >
              Cancel
            </button>
            <button
              id="btn-confirm-mapping"
              type="button"
              disabled={isSavingMapping || isCreating || !selectedProductId || !selectedVariantId || !!selectedSameStoreConflict}
              onClick={handleSaveMapping}
              className={`inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-xs font-bold transition-all cursor-pointer ${
                selectedSameStoreConflict
                  ? 'bg-amber-100 dark:bg-amber-950/40 text-amber-700 dark:text-amber-400 border border-amber-300 dark:border-amber-800 cursor-not-allowed'
                  : selectedProductId && selectedVariantId
                  ? 'bg-indigo-600 hover:bg-indigo-700 text-white shadow-md shadow-indigo-500/20'
                  : 'bg-neutral-200 dark:bg-neutral-800 text-neutral-400 dark:text-neutral-600 cursor-not-allowed'
              }`}
            >
              {selectedSameStoreConflict ? (
                <>
                  <AlertTriangle className="w-4 h-4 text-amber-600" />
                  Cannot Link — Same-Store Conflict
                </>
              ) : (
                <>
                  <Check className="w-4 h-4" />
                  Confirm Variant Mapping
                </>
              )}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};
