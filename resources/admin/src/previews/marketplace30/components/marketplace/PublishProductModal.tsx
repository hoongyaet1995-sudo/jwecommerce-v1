import React, { useState, useMemo } from 'react';
import { useAdmin } from '../../context/AdminContext';

import { MarketplaceLogo } from './MarketplaceLogo';
import { X, Search, UploadCloud, Store, Layers, CheckCircle2, AlertCircle, TrendingUp, Package, ShieldCheck, Sparkles } from 'lucide-react';

interface PublishProductModalProps {
  isOpen: boolean;
  onClose: () => void;
  preselectedProductId?: string;
  preselectedConnectionId?: string;
}

export const PublishProductModal: React.FC<PublishProductModalProps> = ({
  isOpen,
  onClose,
  preselectedProductId,
  preselectedConnectionId
}) => {
  const {
    products = [],
    connections = [],
    marketplaceStoreProducts = [],
    publishMasterProductToStore
  } = useAdmin();

  const [selectedProductId, setSelectedProductId] = useState<string>(preselectedProductId || '');
  const [selectedConnectionId, setSelectedConnectionId] = useState<string>(
    preselectedConnectionId || connections[0]?.id || ''
  );
  const [searchQuery, setSearchQuery] = useState('');
  const [markupPercent, setMarkupPercent] = useState<number>(5); // default 5% marketplace commission markup
  const [categoryName, setCategoryName] = useState('Consumer Electronics & Audio');
  const [isSubmitting, setIsSubmitting] = useState(false);

  React.useEffect(() => {
    if (preselectedProductId) {
      setSelectedProductId(preselectedProductId);
    } else if (!selectedProductId && products.length > 0) {
      setSelectedProductId(products[0].id);
    }
  }, [preselectedProductId, products]);

  React.useEffect(() => {
    if (preselectedConnectionId) {
      setSelectedConnectionId(preselectedConnectionId);
    } else if (!selectedConnectionId && connections.length > 0) {
      setSelectedConnectionId(connections[0].id);
    }
  }, [preselectedConnectionId, connections]);

  const targetConnection = useMemo(() => {
    return connections.find(c => c.id === selectedConnectionId) || connections[0];
  }, [connections, selectedConnectionId]);

  const targetProduct = useMemo(() => {
    return products.find(p => p.id === selectedProductId);
  }, [products, selectedProductId]);

  // Check if product is already listed on this connection
  const alreadyListed = useMemo(() => {
    if (!targetProduct || !targetConnection) return false;
    return marketplaceStoreProducts.some(sp => {
      if (sp.connectionId !== targetConnection.id) return false;
      return sp.variants.some(v => v.mappedJwProductId === targetProduct.id);
    });
  }, [targetProduct, targetConnection, marketplaceStoreProducts]);

  const filteredProducts = useMemo(() => {
    if (!searchQuery.trim()) return products;
    const q = searchQuery.toLowerCase();
    return products.filter(
      p =>
        p.title.toLowerCase().includes(q) ||
        p.brand?.toLowerCase().includes(q) ||
        p.variants.some(v => v.sku.toLowerCase().includes(q))
    );
  }, [products, searchQuery]);

  if (!isOpen) return null;

  const handlePublish = async () => {
    if (!targetProduct || !targetConnection) return;
    setIsSubmitting(true);
    await new Promise(r => setTimeout(r, 600));

    const priceMultiplier = 1 + markupPercent / 100;
    publishMasterProductToStore(targetProduct.id, targetConnection.id, {
      priceMultiplier,
      categoryName
    });

    setIsSubmitting(false);
    onClose();
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs animate-in fade-in duration-200">
      <div className="bg-slate-900 border border-slate-700/80 rounded-2xl w-full max-w-3xl max-h-[92vh] flex flex-col shadow-2xl overflow-hidden text-slate-100">
        {/* Header */}
        <div className="px-6 py-4 border-b border-slate-800 flex items-center justify-between bg-slate-900/90 sticky top-0 z-10">
          <div className="flex items-center gap-3">
            <div className="p-2.5 rounded-xl bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
              <UploadCloud className="w-5 h-5" />
            </div>
            <div>
              <h2 className="text-lg font-semibold text-white flex items-center gap-2">
                Push JW Master Product to Marketplace
                <span className="text-xs px-2 py-0.5 rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 font-medium">
                  Auto 1:1 Mapping
                </span>
              </h2>
              <p className="text-xs text-slate-400">
                Publish a JW Ecommerce catalog item to your connected external store and establish instant stock sync.
              </p>
            </div>
          </div>
          <button
            onClick={onClose}
            className="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition-colors"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Content */}
        <div className="p-6 overflow-y-auto flex-1 space-y-6">
          {/* Target Store Selection */}
          <div className="space-y-2">
            <label className="text-xs font-semibold text-slate-300 uppercase tracking-wider flex items-center gap-1.5">
              <Store className="w-4 h-4 text-indigo-400" />
              1. Select Destination Marketplace Store
            </label>
            <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-2.5">
              {connections.map(conn => {
                const isSelected = conn.id === selectedConnectionId;
                return (
                  <button
                    key={conn.id}
                    type="button"
                    onClick={() => setSelectedConnectionId(conn.id)}
                    className={`p-3 rounded-xl border text-left transition-all flex flex-col gap-1.5 ${
                      isSelected
                        ? 'bg-indigo-500/10 border-indigo-500 text-white shadow-sm ring-1 ring-indigo-500/30'
                        : 'bg-slate-800/60 border-slate-700/60 text-slate-300 hover:bg-slate-800 hover:border-slate-600'
                    }`}
                  >
                    <div className="flex items-center justify-between">
                      <MarketplaceLogo platform={conn.platform} variant="badge" />
                      {isSelected && <CheckCircle2 className="w-4 h-4 text-indigo-400" />}
                    </div>
                    <span className="text-sm font-medium truncate">{conn.shopName}</span>
                    <span className="text-[11px] text-slate-400 truncate">ID: {conn.shopId}</span>
                  </button>
                );
              })}
            </div>
          </div>

          {/* Master Product Selection */}
          <div className="space-y-2">
            <label className="text-xs font-semibold text-slate-300 uppercase tracking-wider flex items-center justify-between">
              <span className="flex items-center gap-1.5">
                <Package className="w-4 h-4 text-indigo-400" />
                2. Select JW Master Product to Push
              </span>
              <span className="text-[11px] text-slate-400 font-normal">
                {products.length} master products available
              </span>
            </label>

            {/* Search filter */}
            <div className="relative">
              <Search className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
              <input
                type="text"
                value={searchQuery}
                onChange={e => setSearchQuery(e.target.value)}
                placeholder="Search master product name, brand, or SKU..."
                className="w-full pl-9 pr-4 py-2 bg-slate-800/80 border border-slate-700 rounded-xl text-sm text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-1 focus:ring-indigo-500"
              />
            </div>

            {/* Product items scroll area */}
            <div className="max-h-48 overflow-y-auto border border-slate-800 rounded-xl divide-y divide-slate-800/80 bg-slate-950/40">
              {filteredProducts.map(p => {
                const isSelected = p.id === selectedProductId;
                const isProdListed = targetConnection && marketplaceStoreProducts.some(sp => {
                  return sp.connectionId === targetConnection.id && sp.variants.some(v => v.mappedJwProductId === p.id);
                });

                return (
                  <div
                    key={p.id}
                    onClick={() => setSelectedProductId(p.id)}
                    className={`p-3 flex items-center justify-between cursor-pointer transition-colors ${
                      isSelected
                        ? 'bg-indigo-500/15 text-white'
                        : 'hover:bg-slate-800/50 text-slate-300'
                    }`}
                  >
                    <div className="flex items-center gap-3 min-w-0">
                      <img
                        src={p.images[0] || 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=100'}
                        alt={p.title}
                        className="w-10 h-10 rounded-lg object-cover bg-slate-800 shrink-0 border border-slate-700"
                      />
                      <div className="min-w-0">
                        <div className="flex items-center gap-2">
                          <p className="text-sm font-medium truncate text-white">{p.title}</p>
                          {isProdListed && (
                            <span className="text-[10px] px-1.5 py-0.5 rounded bg-amber-500/10 text-amber-300 border border-amber-500/20 shrink-0">
                              Already on {targetConnection?.platform.toUpperCase()}
                            </span>
                          )}
                        </div>
                        <p className="text-xs text-slate-400">
                          {p.variants.length} variant(s) · Base RM {p.basePrice.toFixed(2)} · {p.totalInventory} in stock
                        </p>
                      </div>
                    </div>
                    <div className="shrink-0 flex items-center pl-2">
                      <div
                        className={`w-5 h-5 rounded-full border flex items-center justify-center ${
                          isSelected
                            ? 'bg-indigo-600 border-indigo-500 text-white'
                            : 'border-slate-600 bg-slate-800'
                        }`}
                      >
                        {isSelected && <CheckCircle2 className="w-3.5 h-3.5" />}
                      </div>
                    </div>
                  </div>
                );
              })}
            </div>
          </div>

          {/* Pricing & Category Configuration */}
          {targetProduct && (
            <div className="space-y-4 pt-2 border-t border-slate-800">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                {/* Platform Markup % */}
                <div className="bg-slate-800/50 p-3.5 rounded-xl border border-slate-700/60 space-y-2">
                  <div className="flex items-center justify-between">
                    <label className="text-xs font-semibold text-slate-300 flex items-center gap-1.5">
                      <TrendingUp className="w-4 h-4 text-emerald-400" />
                      Channel Price Markup
                    </label>
                    <span className="text-xs font-bold text-emerald-400">+{markupPercent}%</span>
                  </div>
                  <p className="text-[11px] text-slate-400">
                    Compensate for marketplace commission fees by setting a channel multiplier.
                  </p>
                  <div className="flex items-center gap-2 pt-1">
                    {[0, 5, 8, 12, 15].map(val => (
                      <button
                        key={val}
                        type="button"
                        onClick={() => setMarkupPercent(val)}
                        className={`px-2.5 py-1 text-xs rounded-lg border font-medium transition-colors ${
                          markupPercent === val
                            ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/40'
                            : 'bg-slate-800 text-slate-400 border-slate-700 hover:text-slate-200'
                        }`}
                      >
                        +{val}%
                      </button>
                    ))}
                  </div>
                </div>

                {/* Target Category */}
                <div className="bg-slate-800/50 p-3.5 rounded-xl border border-slate-700/60 space-y-2">
                  <label className="text-xs font-semibold text-slate-300 flex items-center gap-1.5">
                    <Layers className="w-4 h-4 text-indigo-400" />
                    Marketplace Category Name
                  </label>
                  <p className="text-[11px] text-slate-400">
                    Category taxonomy assigned to the listing on {targetConnection?.shopName}.
                  </p>
                  <input
                    type="text"
                    value={categoryName}
                    onChange={e => setCategoryName(e.target.value)}
                    className="w-full px-3 py-1.5 bg-slate-900 border border-slate-700 rounded-lg text-xs text-slate-200 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                  />
                </div>
              </div>

              {/* Variants Breakdown & Price Preview */}
              <div className="space-y-2">
                <span className="text-xs font-semibold text-slate-300 uppercase tracking-wider">
                  Variants & Channel Listing Preview ({targetProduct.variants.length} SKUs)
                </span>
                <div className="border border-slate-800 rounded-xl overflow-hidden">
                  <table className="w-full text-left text-xs">
                    <thead className="bg-slate-800/70 text-slate-400 font-medium border-b border-slate-800">
                      <tr>
                        <th className="py-2 px-3">Variant</th>
                        <th className="py-2 px-3">Master SKU</th>
                        <th className="py-2 px-3">Master Price</th>
                        <th className="py-2 px-3">Channel Price (+{markupPercent}%)</th>
                        <th className="py-2 px-3">Sync Stock</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-800/60 text-slate-300">
                      {targetProduct.variants.map(v => {
                        const channelPrice = +(v.price * (1 + markupPercent / 100)).toFixed(2);
                        return (
                          <tr key={v.id} className="hover:bg-slate-800/30">
                            <td className="py-2 px-3 font-medium text-white">{v.title}</td>
                            <td className="py-2 px-3 font-mono text-indigo-300">{v.sku}</td>
                            <td className="py-2 px-3 text-slate-400">RM {v.price.toFixed(2)}</td>
                            <td className="py-2 px-3 font-semibold text-emerald-400">RM {channelPrice.toFixed(2)}</td>
                            <td className="py-2 px-3">
                              <span className="px-2 py-0.5 rounded-full bg-slate-800 border border-slate-700 text-slate-200">
                                {v.inventoryQuantity} units
                              </span>
                            </td>
                          </tr>
                        );
                      })}
                    </tbody>
                  </table>
                </div>
              </div>

              {alreadyListed && (
                <div className="p-3 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-start gap-2.5 text-xs text-amber-300">
                  <AlertCircle className="w-4 h-4 shrink-0 mt-0.5" />
                  <div>
                    <span className="font-semibold">Listing Already Exists:</span> This JW Master Product is already mapped to an active listing on {targetConnection.shopName}. Pushing again will create an additional external listing.
                  </div>
                </div>
              )}
            </div>
          )}
        </div>

        {/* Footer */}
        <div className="px-6 py-4 border-t border-slate-800 bg-slate-900/90 flex items-center justify-between">
          <div className="text-xs text-slate-400 flex items-center gap-1.5">
            <ShieldCheck className="w-4 h-4 text-emerald-400" />
            Stock levels will immediately auto-broadcast upon creation.
          </div>
          <div className="flex items-center gap-2.5">
            <button
              type="button"
              onClick={onClose}
              className="px-4 py-2 text-xs font-medium text-slate-300 hover:text-white bg-slate-800 hover:bg-slate-700 border border-slate-700 rounded-xl transition-colors"
            >
              Cancel
            </button>
            <button
              type="button"
              onClick={handlePublish}
              disabled={!targetProduct || !targetConnection || isSubmitting}
              className="px-5 py-2 text-xs font-semibold text-white bg-indigo-600 hover:bg-indigo-500 rounded-xl transition-all shadow-sm flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {isSubmitting ? (
                <>Publishing...</>
              ) : (
                <>
                  <Sparkles className="w-4 h-4" />
                  Publish & Auto-Link
                </>
              )}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};

