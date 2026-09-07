import React, { useState, useMemo, useRef } from 'react';
import { 
  X, 
  DownloadCloud, 
  AlertTriangle, 
  CheckCircle2, 
  Link2, 
  Info, 
  ArrowRight, 
  Package, 
  ShieldCheck, 
  EyeOff, 
  Boxes,
  HelpCircle
} from 'lucide-react';
import { MarketplaceStoreProduct, MarketplaceStoreVariant, ImportProductOptions } from '../../types';
import { useAdmin } from '../../context/AdminContext';
import { MarketplaceLogo } from './MarketplaceLogo';

interface ImportProductReviewModalProps {
  isOpen: boolean;
  onClose: () => void;
  productsToImport: MarketplaceStoreProduct[];
  onSuccess?: () => void;
}

type SkuAction = 'link_existing' | 'generate_suffix' | 'create_as_is';

interface VariantAnalysis {
  variant: MarketplaceStoreVariant;
  product: MarketplaceStoreProduct;
  hasConflict: boolean;
  conflictingJwProduct?: { id: string; title: string };
  conflictingJwVariant?: { id: string; sku: string; title: string; price: number; stock: number };
  isMissingSku: boolean;
  recommendedAction: SkuAction;
  customSkuOverride: string;
  selectedAction: SkuAction;
}

export const ImportProductReviewModal: React.FC<ImportProductReviewModalProps> = ({
  isOpen,
  onClose,
  productsToImport,
  onSuccess
}) => {
  const { products, importStoreProductToMaster, addToast, setActiveSection } = useAdmin();

  // Selected resolution actions for conflicting variants: [variantId]: SkuAction
  const [skuActions, setSkuActions] = useState<Record<string, SkuAction>>({});
  const [skuOverrides, setSkuOverrides] = useState<Record<string, string>>({});
  const [isCompleted, setIsCompleted] = useState(false);
  const [importedProductCount, setImportedProductCount] = useState(0);
  const [savedCounts,setSavedCounts]=useState({drafts:0,created:0,linked:0});
  const [isImporting, setIsImporting] = useState(false);
  const submitted = useRef(new Set<string>());
  const submitting = useRef(false);
  const assignedSkus = useRef(new Map<string,string>());

  // Analyze all variants across productsToImport
  const analysisList = useMemo(() => {
    const list: VariantAnalysis[] = [];

    productsToImport.forEach(prod => {
      prod.variants.forEach(variant => {
        const rawSku = (variant.channelSellerSku || '').trim();
        const isMissingSku = !rawSku;

        let conflictingJwProduct: { id: string; title: string } | undefined;
        let conflictingJwVariant: { id: string; sku: string; title: string; price: number; stock: number } | undefined;
        let hasConflict = false;

        if (!isMissingSku) {
          // Scan JW master catalog
          for (const p of products) {
            const matchedVar = p.variants.find(v => v.sku.toLowerCase() === rawSku.toLowerCase());
            if (matchedVar) {
              hasConflict = true;
              conflictingJwProduct = { id: p.id, title: p.title };
              conflictingJwVariant = {
                id: matchedVar.id,
                sku: matchedVar.sku,
                title: matchedVar.title,
                price: matchedVar.price,
                stock: matchedVar.inventoryQuantity
              };
              break;
            }
          }
        }

        const defaultAction: SkuAction = hasConflict ? 'link_existing' : 'create_as_is';
        const currentAction = skuActions[variant.id] || defaultAction;

        const sourceIdentity = JSON.stringify([prod.connectionId,prod.id,variant.id]);
        if (isMissingSku && !assignedSkus.current.has(sourceIdentity)) {
          assignedSkus.current.set(sourceIdentity,`JW-${prod.platform.toUpperCase().substring(0, 2)}-${Math.floor(1000 + Math.random() * 9000)}`);
        }
        const fallbackSku = isMissingSku ? assignedSkus.current.get(sourceIdentity)! : rawSku;

        list.push({
          variant,
          product: prod,
          hasConflict,
          conflictingJwProduct,
          conflictingJwVariant,
          isMissingSku,
          recommendedAction: defaultAction,
          customSkuOverride: skuOverrides[variant.id] || (hasConflict ? `${rawSku}-IMP` : fallbackSku),
          selectedAction: currentAction
        });
      });
    });

    return list;
  }, [productsToImport, products, skuActions, skuOverrides]);

  const conflictsCount = analysisList.filter(a => a.hasConflict).length;
  const missingSkuCount = analysisList.filter(a => a.isMissingSku).length;
  const linkExistingCount = analysisList.filter(a => a.selectedAction === 'link_existing' && a.hasConflict).length;
  const newVariantsCount = analysisList.filter(a => a.selectedAction !== 'link_existing').length;
  const draftProductIds = new Set(analysisList.filter(a => a.selectedAction !== 'link_existing').map(a => a.product.id));
  const newDraftCount = draftProductIds.size;

  const handleActionChange = (variantId: string, action: SkuAction) => {
    setSkuActions(prev => ({ ...prev, [variantId]: action }));
  };

  const handleCustomSkuChange = (variantId: string, value: string) => {
    setSkuOverrides(prev => ({ ...prev, [variantId]: value.trim() }));
  };

  const handleExecuteImport = async () => {
    if (submitting.current) return;
    submitting.current = true;
    setIsImporting(true);
    let failed = 0;
    const failures: string[] = [];
    try {
    for (const prod of productsToImport) {
      if (submitted.current.has(prod.id)) continue;
      const prodAnalysis = analysisList.filter(a => a.product.id === prod.id);

      const linkExistingVariantIds: Record<string, { productId: string; variantId: string }> = {};
      const variantSkuOverrides: Record<string, string> = {};

      prodAnalysis.forEach(a => {
        if (a.selectedAction === 'link_existing' && a.conflictingJwProduct && a.conflictingJwVariant) {
          linkExistingVariantIds[a.variant.id] = {
            productId: a.conflictingJwProduct.id,
            variantId: a.conflictingJwVariant.id
          };
        } else if (a.selectedAction === 'generate_suffix' || a.isMissingSku || skuOverrides[a.variant.id]) {
          variantSkuOverrides[a.variant.id] = a.customSkuOverride;
        }
      });

      const options: ImportProductOptions = {
        createDraftStatus: 'draft',
        zeroPhysicalStock: true,
        linkExistingVariantIds,
        skuOverrides: variantSkuOverrides
      };

      try {
        const result=await importStoreProductToMaster(prod.id, undefined, options);
        if(result) setSavedCounts(previous=>({drafts:previous.drafts+(result.productId===null?0:1),
          created:previous.created+result.createdVariants,linked:previous.linked+result.linkedVariants}));
        submitted.current.add(prod.id);
      } catch (error) {
        failed++;
        failures.push(`${prod.title}: ${error instanceof Error ? error.message : 'Import could not be confirmed.'}`);
      }
    }

    setImportedProductCount(submitted.current.size);
    if (failed) {
      addToast({type:'error',title:'Import needs review',message:`${submitted.current.size} saved, ${failed} failed. ${failures.slice(0,3).join(' ')}${failed>3?' Review remaining failed products individually.':''}`});
    } else {
      setIsCompleted(true);
      if (onSuccess) onSuccess();
    }
    } finally {submitting.current = false;setIsImporting(false);}
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 overflow-y-auto bg-slate-900/70 backdrop-blur-xs flex items-center justify-center p-3 sm:p-4 animate-in fade-in duration-150">
      <div 
        id="import-product-review-modal"
        className="bg-white dark:bg-slate-900 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-800 w-full max-w-4xl overflow-hidden flex flex-col max-h-[90vh]"
      >
        {/* Header */}
        <div className="px-6 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between bg-slate-50/50 dark:bg-slate-900/50">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl bg-emerald-500/10 dark:bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-bold">
              <DownloadCloud className="w-5 h-5" />
            </div>
            <div>
              <h3 className="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                {isCompleted ? 'Import Completed' : 'Review Import into JW Master Catalog'}
                <span className="text-xs font-semibold px-2 py-0.5 rounded-md bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300">
                  {productsToImport.length} Product{productsToImport.length > 1 ? 's' : ''}
                </span>
              </h3>
              <p className="text-xs text-slate-500 dark:text-slate-400">
                Creates JW drafts with zero warehouse stock. External listings remain unchanged.
              </p>
            </div>
          </div>
          <button
            type="button"
            onClick={onClose}
            className="p-2 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Body */}
        <div className="flex-1 overflow-y-auto p-6 space-y-6">
          {isCompleted ? (
            /* Success Completion View */
            <div className="py-6 px-4 text-center space-y-6">
              <div className="w-16 h-16 rounded-2xl bg-emerald-100 dark:bg-emerald-900/40 text-emerald-600 dark:text-emerald-400 mx-auto flex items-center justify-center">
                <CheckCircle2 className="w-9 h-9" />
              </div>

              <div className="max-w-md mx-auto space-y-2">
                <h4 className="text-lg font-bold text-slate-900 dark:text-white">
                  Review Saved Successfully
                </h4>
                <p className="text-sm text-slate-600 dark:text-slate-400">
                  {savedCounts.drafts} new draft product(s), {savedCounts.created} new variant(s), and {savedCounts.linked} existing SKU link(s). Existing warehouse stock is unchanged. New products use <strong className="text-slate-800 dark:text-slate-200">Draft</strong> status.
                </p>
              </div>

              {/* Status Reassurance Card */}
              <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 max-w-2xl mx-auto text-left">
                <div className="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/60">
                  <div className="flex items-center gap-2 text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">
                    <EyeOff className="w-3.5 h-3.5 text-amber-500" />
                    Draft Status
                  </div>
                  <div className="text-sm font-bold text-slate-900 dark:text-white">Unpublished</div>
                  <div className="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">Not visible on customer storefront</div>
                </div>

                <div className="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/60">
                  <div className="flex items-center gap-2 text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">
                    <Boxes className="w-3.5 h-3.5 text-blue-500" />
                    Warehouse Stock
                  </div>
                  <div className="text-sm font-bold text-slate-900 dark:text-white">0 Units Initialized</div>
                  <div className="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">Platform stock preserved as reference</div>
                </div>

                <div className="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/60">
                  <div className="flex items-center gap-2 text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">
                    <Link2 className="w-3.5 h-3.5 text-emerald-500" />
                    Channel Mapping
                  </div>
                  <div className="text-sm font-bold text-slate-900 dark:text-white">Auto-Linked</div>
                  <div className="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">1:1 External channel link established</div>
                </div>
              </div>

              <div className="flex flex-wrap items-center justify-center gap-3 pt-4">
                <button
                  type="button"
                  onClick={() => {
                    onClose();
                    setActiveSection('products');
                  }}
                  className="px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold transition-colors flex items-center gap-2 cursor-pointer shadow-xs"
                >
                  <Package className="w-4 h-4" />
                  View Drafts in Products Catalog
                </button>

                <button
                  type="button"
                  onClick={() => {
                    onClose();
                    setActiveSection('marketplace_inventory');
                  }}
                  className="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-sm font-semibold transition-colors flex items-center gap-2 cursor-pointer"
                >
                  <Boxes className="w-4 h-4" />
                  Receive Physical Stock in Inventory
                </button>

                <button
                  type="button"
                  onClick={onClose}
                  className="px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-300 text-sm font-semibold transition-colors cursor-pointer"
                >
                  Done & Return to Mapping
                </button>
              </div>
            </div>
          ) : (
            /* Review and Pre-Import Checklist View */
            <>
              {/* Educational Rule Banner */}
              <div className="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800/50 flex items-start gap-3.5">
                <ShieldCheck className="w-5 h-5 text-emerald-600 dark:text-emerald-400 shrink-0 mt-0.5" />
                <div className="text-xs text-emerald-900 dark:text-emerald-200 space-y-1">
                  <div className="font-bold text-emerald-950 dark:text-emerald-100">
                    Safe Catalog Import Policy
                  </div>
                  <p>
                    Importing external store products creates <strong>JW Draft products</strong> with <strong>0 local warehouse stock</strong>. Platform stock is captured as reference information, not local inventory. It does <strong>not</strong> publish to your storefront or alter remote Shopee/Lazada listings.
                  </p>
                </div>
              </div>

              {/* Summary Stats Pill Group */}
              <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div className="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700/50">
                  <span className="text-[11px] font-medium text-slate-500 dark:text-slate-400 block">Products to Import</span>
                  <span className="text-lg font-bold text-slate-900 dark:text-white mt-0.5 block">{newDraftCount} Drafts</span>
                  <span className="text-[10px] text-slate-500 dark:text-slate-400">No silent merges</span>
                </div>

                <div className="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700/50">
                  <span className="text-[11px] font-medium text-slate-500 dark:text-slate-400 block">New SKUs (0 Stock)</span>
                  <span className="text-lg font-bold text-emerald-600 dark:text-emerald-400 mt-0.5 block">{newVariantsCount}</span>
                  <span className="text-[10px] text-slate-500 dark:text-slate-400">Physical stock = 0</span>
                </div>

                <div className="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700/50">
                  <span className="text-[11px] font-medium text-slate-500 dark:text-slate-400 block">SKU Conflicts</span>
                  <span className={`text-lg font-bold mt-0.5 block ${conflictsCount > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-slate-700 dark:text-slate-300'}`}>
                    {conflictsCount}
                  </span>
                  <span className="text-[10px] text-slate-500 dark:text-slate-400">
                    {conflictsCount > 0 ? `${linkExistingCount} set to link` : 'None detected'}
                  </span>
                </div>

                <div className="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700/50">
                  <span className="text-[11px] font-medium text-slate-500 dark:text-slate-400 block">Currency & Store</span>
                  <span className="text-lg font-bold text-slate-900 dark:text-white mt-0.5 block">MYR (100% Match)</span>
                  <span className="text-[10px] text-slate-500 dark:text-slate-400">Base currency aligned</span>
                </div>
              </div>

              {/* Products & Variants Detailed Review */}
              <div className="space-y-4">
                <div className="flex items-center justify-between">
                  <h4 className="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                    Products & SKU Breakdown
                  </h4>
                  <span className="text-xs text-slate-500 dark:text-slate-400">
                    Review and resolve any SKU conflicts below:
                  </span>
                </div>

                {productsToImport.map(prod => {
                  const prodAnalysis = analysisList.filter(a => a.product.id === prod.id);

                  return (
                    <div 
                      key={prod.id} 
                      className="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-850 overflow-hidden"
                    >
                      {/* Product Header */}
                      <div className="p-4 bg-slate-50/70 dark:bg-slate-800/40 border-b border-slate-200 dark:border-slate-700 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div className="flex items-center gap-3">
                          <img
                            src={prod.imageUrl || 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=120&q=80'}
                            alt={prod.title}
                            className="w-12 h-12 rounded-lg object-cover border border-slate-200 dark:border-slate-700 shrink-0"
                          />
                          <div>
                            <div className="flex items-center gap-2">
                              <span className="w-5 h-5 flex items-center justify-center shrink-0">
                                <MarketplaceLogo platform={prod.platform} size={20} />
                              </span>
                              <h5 className="text-sm font-bold text-slate-900 dark:text-white line-clamp-1">
                                {prod.title}
                              </h5>
                            </div>
                            <div className="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400 mt-1">
                              <span>Shop: <strong className="text-slate-700 dark:text-slate-300">{prod.shopName}</strong></span>
                              <span>•</span>
                              <span>Category: {prod.categoryName}</span>
                              <span>•</span>
                              <span className="text-emerald-700 dark:text-emerald-400 font-medium">{draftProductIds.has(prod.id) ? 'Will create: New JW Draft' : 'Will link: Existing JW SKUs'}</span>
                            </div>
                          </div>
                        </div>

                        <div className="flex items-center gap-2 shrink-0 self-start sm:self-center">
                          <span className="px-2.5 py-1 rounded-md bg-amber-500/10 text-amber-700 dark:text-amber-400 border border-amber-500/20 text-xs font-semibold flex items-center gap-1">
                            <EyeOff className="w-3.5 h-3.5" />
                            {draftProductIds.has(prod.id) ? 'Draft on Import' : 'Link Existing'}
                          </span>
                        </div>
                      </div>

                      {/* Variants Table */}
                      <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs border-collapse">
                          <thead>
                            <tr className="bg-slate-100/50 dark:bg-slate-800/20 border-b border-slate-200 dark:border-slate-700/60 text-slate-600 dark:text-slate-300">
                              <th className="py-2.5 px-4 font-semibold">Channel Variant</th>
                              <th className="py-2.5 px-3 font-semibold">Channel SKU</th>
                              <th className="py-2.5 px-3 font-semibold">Channel Stock & Price</th>
                              <th className="py-2.5 px-3 font-semibold">JW Master Draft SKU</th>
                              <th className="py-2.5 px-3 font-semibold">JW Stock</th>
                              <th className="py-2.5 px-4 font-semibold text-right">Conflict & Action</th>
                            </tr>
                          </thead>
                          <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                            {prodAnalysis.map(item => {
                              const v = item.variant;

                              return (
                                <tr key={v.id} className="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors">
                                  {/* Variant title & spec */}
                                  <td className="py-3 px-4">
                                    <div className="font-semibold text-slate-900 dark:text-white">
                                      {v.title}
                                    </div>
                                    <div className="text-[11px] text-slate-500 dark:text-slate-400">
                                      ID: {v.channelSkuId}
                                    </div>
                                  </td>

                                  {/* Channel SKU */}
                                  <td className="py-3 px-3">
                                    {item.isMissingSku ? (
                                      <span className="inline-flex items-center gap-1 text-amber-700 dark:text-amber-400 font-mono text-[11px] bg-amber-50 dark:bg-amber-950/40 px-2 py-0.5 rounded">
                                        <AlertTriangle className="w-3 h-3" />
                                        Missing SKU
                                      </span>
                                    ) : (
                                      <span className="font-mono text-slate-800 dark:text-slate-200 text-xs">
                                        {v.channelSellerSku}
                                      </span>
                                    )}
                                  </td>

                                  {/* Channel Stock & Price */}
                                  <td className="py-3 px-3">
                                    <div className="font-semibold text-slate-900 dark:text-white">
                                      MYR {v.price.toFixed(2)}
                                    </div>
                                    <div className="text-[11px] text-slate-500 dark:text-slate-400 flex items-center gap-1">
                                      <span>Ref Stock: {v.stock} units</span>
                                      <span className="px-1 py-0.2 rounded text-[9px] bg-slate-100 dark:bg-slate-800 text-slate-500">Ref only</span>
                                    </div>
                                  </td>

                                  {/* Target Master Draft SKU */}
                                  <td className="py-3 px-3">
                                    {item.selectedAction === 'link_existing' ? (
                                      <div>
                                        <span className="font-mono text-indigo-700 dark:text-indigo-400 font-medium">
                                          {item.conflictingJwVariant?.sku}
                                        </span>
                                        <div className="text-[10px] text-indigo-600 dark:text-indigo-400">
                                          (Links to existing variant)
                                        </div>
                                      </div>
                                    ) : (
                                      <div className="space-y-1">
                                        <input
                                          type="text"
                                          value={item.customSkuOverride}
                                          onChange={(e) => handleCustomSkuChange(v.id, e.target.value)}
                                          className="w-32 px-2 py-1 text-xs font-mono rounded-md border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-1 focus:ring-emerald-500 focus:outline-hidden"
                                        />
                                        {item.isMissingSku && (
                                          <div className="text-[10px] text-emerald-700 dark:text-emerald-400">
                                            Auto-assigned draft SKU
                                          </div>
                                        )}
                                      </div>
                                    )}
                                  </td>

                                  {/* JW Initial Warehouse Stock */}
                                  <td className="py-3 px-3">
                                    {item.selectedAction === 'link_existing' ? (
                                      <div>
                                        <span className="font-semibold text-slate-800 dark:text-slate-200">
                                          {item.conflictingJwVariant?.stock} units
                                        </span>
                                        <div className="text-[10px] text-slate-500">Existing warehouse stock</div>
                                      </div>
                                    ) : (
                                      <div>
                                        <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300">
                                          0 units
                                        </span>
                                        <div className="text-[10px] text-slate-500 dark:text-slate-400 mt-0.5">Physical stock</div>
                                      </div>
                                    )}
                                  </td>

                                  {/* Conflict resolution / Action */}
                                  <td className="py-3 px-4 text-right">
                                    {item.hasConflict ? (
                                      <div className="flex flex-col items-end gap-1.5">
                                        <div className="flex items-center gap-1 text-[11px] text-amber-700 dark:text-amber-400 font-medium">
                                          <AlertTriangle className="w-3.5 h-3.5 text-amber-500" />
                                          SKU exists in JW Catalog:
                                        </div>
                                        <div className="text-[10px] text-slate-600 dark:text-slate-300 font-medium max-w-[200px] text-right truncate">
                                          {item.conflictingJwProduct?.title} ({item.conflictingJwVariant?.title})
                                        </div>

                                        <div className="flex items-center gap-1 mt-1">
                                          <button
                                            type="button"
                                            onClick={() => handleActionChange(v.id, 'link_existing')}
                                            className={`px-2 py-1 rounded text-[11px] font-semibold transition-colors cursor-pointer flex items-center gap-1 ${
                                              item.selectedAction === 'link_existing'
                                                ? 'bg-indigo-600 text-white shadow-xs'
                                                : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-200'
                                            }`}
                                          >
                                            <Link2 className="w-3 h-3" />
                                            Link to Existing
                                          </button>

                                          <button
                                            type="button"
                                            onClick={() => handleActionChange(v.id, 'generate_suffix')}
                                            className={`px-2 py-1 rounded text-[11px] font-semibold transition-colors cursor-pointer ${
                                              item.selectedAction === 'generate_suffix'
                                                ? 'bg-emerald-600 text-white shadow-xs'
                                                : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-200'
                                            }`}
                                          >
                                            Create with Suffix
                                          </button>
                                        </div>
                                      </div>
                                    ) : (
                                      <div className="flex items-center justify-end gap-1 text-emerald-700 dark:text-emerald-400 font-medium text-xs">
                                        <CheckCircle2 className="w-3.5 h-3.5" />
                                        Ready to create draft
                                      </div>
                                    )}
                                  </td>
                                </tr>
                              );
                            })}
                          </tbody>
                        </table>
                      </div>
                    </div>
                  );
                })}
              </div>
            </>
          )}
        </div>

        {/* Footer */}
        {!isCompleted && (
          <div className="px-6 py-4 border-t border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50 flex flex-col sm:flex-row items-center justify-between gap-3">
            <div className="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
              <Info className="w-4 h-4 text-slate-400 shrink-0" />
              <span>
                Confirms {newDraftCount} new JW draft item(s) and {linkExistingCount} existing SKU link(s). Does not publish to live storefront.
              </span>
            </div>

            <div className="flex items-center gap-2.5 shrink-0 w-full sm:w-auto justify-end">
              <button
                type="button"
                onClick={onClose}
                className="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 text-xs font-semibold transition-colors cursor-pointer"
              >
                Cancel
              </button>

              <button
                type="button"
                onClick={handleExecuteImport}
                disabled={isImporting}
                className="px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition-all shadow-xs flex items-center gap-2 cursor-pointer"
              >
                <DownloadCloud className="w-4 h-4" />
                {newDraftCount ? 'Confirm Import as JW Drafts' : 'Confirm SKU Links'}
              </button>
            </div>
          </div>
        )}
      </div>
    </div>
  );
};
