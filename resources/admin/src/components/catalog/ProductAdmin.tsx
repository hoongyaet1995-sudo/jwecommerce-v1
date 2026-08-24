import React, { useState } from 'react';
import { useAdmin } from '../../context/AdminContext';
import { Product } from '../../types';
import { ProductEditorModal } from './ProductEditorModal';
import { DeleteConfirmationModal } from '../common/DeleteConfirmationModal';
import {
  Package,
  Plus,
  Search,
  Filter,
  Trash2,
  Edit,
  Copy,
  Layers,
  Sparkles,
  DollarSign,
  CheckCircle2,
  AlertTriangle,
  ArrowUpDown,
  Download,
  Upload
} from 'lucide-react';

export const ProductAdmin: React.FC = () => {
  const { products, categories, deleteProduct, saveProduct, addToast } = useAdmin();

  const [searchTerm, setSearchTerm] = useState('');
  const [selectedCategory, setSelectedCategory] = useState<string>('all');
  const [selectedStatus, setSelectedStatus] = useState<string>('all');
  const [sortField, setSortField] = useState<'title' | 'basePrice' | 'totalInventory'>('title');
  const [sortAsc, setSortAsc] = useState(true);

  // Editor Modal State
  const [isEditorOpen, setIsEditorOpen] = useState(false);
  const [selectedProduct, setSelectedProduct] = useState<Product | null>(null);
  const [pendingDeletion, setPendingDeletion] = useState<Product | null>(null);

  // Filter products
  const filteredProducts = products.filter(p => {
    const matchesSearch =
      p.title.toLowerCase().includes(searchTerm.toLowerCase()) ||
      p.brand.toLowerCase().includes(searchTerm.toLowerCase()) ||
      p.variants.some(v => v.sku.toLowerCase().includes(searchTerm.toLowerCase()));

    const matchesCat = selectedCategory === 'all' || p.categoryId === selectedCategory;
    const matchesStatus = selectedStatus === 'all' || p.status === selectedStatus;

    return matchesSearch && matchesCat && matchesStatus;
  }).sort((a, b) => {
    let comparison = 0;
    if (sortField === 'title') comparison = a.title.localeCompare(b.title);
    if (sortField === 'basePrice') comparison = a.basePrice - b.basePrice;
    if (sortField === 'totalInventory') comparison = a.totalInventory - b.totalInventory;
    return sortAsc ? comparison : -comparison;
  });

  const handleCreateNew = () => {
    setSelectedProduct(null);
    setIsEditorOpen(true);
  };

  const handleEdit = (prod: Product) => {
    setSelectedProduct(prod);
    setIsEditorOpen(true);
  };

  const handleDuplicate = (prod: Product) => {
    const copy: Product = {
      ...JSON.parse(JSON.stringify(prod)),
      id: 'prod-' + Date.now(),
      title: `${prod.title} (Copy)`,
      slug: `${prod.slug}-copy-${Date.now().toString().slice(-4)}`,
      createdAt: new Date().toISOString().split('T')[0],
      updatedAt: new Date().toISOString().split('T')[0],
      variants: prod.variants.map((v, i) => ({
        ...v,
        id: `var-${Date.now()}-${i}`,
        sku: `${v.sku}-CP`
      }))
    };
    saveProduct(copy);
    addToast('success', `Product duplicated: ${copy.title}`);
  };

  return (
    <div className="space-y-6 pb-12">
      {/* Header Bar */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-5 rounded-3xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-sm">
        <div>
          <div className="flex items-center gap-2">
            <span className="px-2.5 py-0.5 text-[10px] font-bold uppercase rounded-full bg-indigo-500/20 text-indigo-700 dark:text-indigo-300 border border-indigo-500/30">
              SKU & Variant Engine
            </span>
            <span className="text-xs text-neutral-500">Universal Catalog Architecture</span>
          </div>
          <h2 className="text-lg sm:text-xl font-bold text-neutral-900 dark:text-white tracking-tight mt-1">
            Product Catalog & SKU Matrix Admin
          </h2>
          <p className="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">
            Manage physical and digital products, stock quantities, and generate Cartesian combination variant matrices.
          </p>
        </div>

        <div className="flex items-center gap-2">
          <button
            onClick={handleCreateNew}
            className="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold transition-all shadow-md shadow-indigo-500/20 flex items-center gap-1.5"
          >
            <Plus className="w-4 h-4" />
            <span>Add Product</span>
          </button>
        </div>
      </div>

      {/* Filter & Search Toolbar */}
      <div className="p-4 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs flex flex-col md:flex-row items-center justify-between gap-3">
        <div className="relative w-full md:w-80">
          <Search className="w-4 h-4 text-neutral-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
          <input
            type="text"
            placeholder="Search title, brand, or SKU..."
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            className="w-full pl-9 pr-4 py-2 text-xs rounded-xl border bg-neutral-50 dark:bg-neutral-800 border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white focus:ring-2 focus:ring-indigo-500"
          />
        </div>

        <div className="flex flex-wrap items-center gap-2 w-full md:w-auto">
          {/* Category Filter */}
          <select
            value={selectedCategory}
            onChange={(e) => setSelectedCategory(e.target.value)}
            className="px-3 py-2 text-xs rounded-xl border bg-neutral-50 dark:bg-neutral-800 border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white"
          >
            <option value="all">All Categories</option>
            {categories.map(c => (
              <option key={c.id} value={c.id}>{c.name}</option>
            ))}
          </select>

          {/* Status Filter */}
          <select
            value={selectedStatus}
            onChange={(e) => setSelectedStatus(e.target.value)}
            className="px-3 py-2 text-xs rounded-xl border bg-neutral-50 dark:bg-neutral-800 border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white"
          >
            <option value="all">All Statuses</option>
            <option value="active">Active</option>
            <option value="draft">Draft</option>
            <option value="archived">Archived</option>
          </select>

          {/* Sort Switch */}
          <button
            onClick={() => setSortAsc(!sortAsc)}
            className="px-3 py-2 text-xs rounded-xl border bg-neutral-50 dark:bg-neutral-800 border-neutral-200 dark:border-neutral-700 text-neutral-700 dark:text-neutral-300 flex items-center gap-1.5"
          >
            <ArrowUpDown className="w-3.5 h-3.5" />
            <span>Sort {sortAsc ? 'Asc' : 'Desc'}</span>
          </button>
        </div>
      </div>

      {/* Products Table */}
      <div className="bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-3xl overflow-hidden shadow-sm">
        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs border-collapse">
            <thead>
              <tr className="border-b border-neutral-200 dark:border-neutral-800 bg-neutral-50/70 dark:bg-neutral-800/60 text-[10px] uppercase font-bold text-neutral-500 tracking-wider">
                <th className="py-3.5 px-4">Product Details</th>
                <th className="py-3.5 px-4">Category</th>
                <th className="py-3.5 px-4">Base Retail</th>
                <th className="py-3.5 px-4">Variant Matrix</th>
                <th className="py-3.5 px-4">Total Stock</th>
                <th className="py-3.5 px-4">Status</th>
                <th className="py-3.5 px-4 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800 text-neutral-800 dark:text-neutral-200">
              {filteredProducts.map((prod) => {
                const category = categories.find(c => c.id === prod.categoryId);
                const isLowStock = prod.totalInventory < 50;
                const primaryImage = typeof prod.images[0] === 'string'
                  ? prod.images[0]
                  : prod.images[0]?.path;

                return (
                  <tr key={prod.id} className="hover:bg-neutral-50/60 dark:hover:bg-neutral-800/50 transition-colors">
                    {/* Thumbnail & Title */}
                    <td className="py-3.5 px-4">
                      <div className="flex items-center gap-3.5">
                        <img
                          src={primaryImage}
                          alt={prod.title}
                          className="w-11 h-11 rounded-xl object-cover ring-1 ring-neutral-200 dark:ring-neutral-700 flex-shrink-0"
                        />
                        <div className="min-w-0">
                          <div className="flex items-center gap-2">
                            <span className="font-bold text-neutral-900 dark:text-white text-xs truncate max-w-xs sm:max-w-md">
                              {prod.title}
                            </span>
                            {prod.featured && (
                              <span className="text-[9px] font-bold px-1.5 py-0.2 rounded bg-amber-100 dark:bg-amber-950 text-amber-700 dark:text-amber-300">
                                Featured
                              </span>
                            )}
                          </div>
                          <div className="flex items-center gap-2 text-[11px] text-neutral-400 mt-0.5 font-mono truncate">
                            <span>{prod.brand || 'Aurora Design'}</span>
                            <span>•</span>
                            <span>/products/{prod.slug}</span>
                          </div>
                        </div>
                      </div>
                    </td>

                    {/* Category */}
                    <td className="py-3.5 px-4">
                      <span className="px-2.5 py-1 rounded-lg bg-neutral-100 dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300 font-medium text-[11px]">
                        {category?.name || 'Uncategorized'}
                      </span>
                    </td>

                    {/* Base Retail Price */}
                    <td className="py-3.5 px-4 font-mono font-bold text-neutral-900 dark:text-white">
                      ${prod.basePrice.toFixed(2)}
                      {prod.baseCompareAtPrice && (
                        <span className="text-[10px] text-neutral-400 line-through ml-1.5 font-normal">
                          ${prod.baseCompareAtPrice.toFixed(2)}
                        </span>
                      )}
                    </td>

                    {/* Variant Matrix Summary */}
                    <td className="py-3.5 px-4">
                      <div className="space-y-1">
                        <div className="flex items-center gap-1.5">
                          <span className="px-2 py-0.5 rounded-md bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 font-semibold font-mono text-[11px]">
                            {prod.variants.length} SKUs
                          </span>
                          <span className="text-[10px] text-neutral-400">
                            ({prod.options.map(o => o.name).join(' × ') || 'Single'})
                          </span>
                        </div>

                        {/* Visual Swatches Preview */}
                        {prod.variants.some(v => v.colorCode || v.optionImage) && (
                          <div className="flex items-center gap-1 pt-0.5">
                            {(Array.from(new Set(prod.variants.map(v => v.colorCode || v.optionImage).filter(Boolean))) as string[]).slice(0, 5).map((visualVal, idx) => {
                              const isHex = visualVal.startsWith('#');
                              return isHex ? (
                                <span
                                  key={idx}
                                  className="w-3 h-3 rounded-full border border-white/60 dark:border-neutral-700 shadow-2xs shrink-0"
                                  style={{ backgroundColor: visualVal }}
                                  title={visualVal}
                                />
                              ) : (
                                <img
                                  key={idx}
                                  src={visualVal}
                                  alt="Swatch"
                                  className="w-3 h-3 rounded-xs object-cover border border-neutral-300 dark:border-neutral-600 shrink-0"
                                />
                              );
                            })}
                            {new Set(prod.variants.map(v => v.colorCode || v.optionImage).filter(Boolean)).size > 5 && (
                              <span className="text-[9px] text-neutral-400 font-mono">
                                +{new Set(prod.variants.map(v => v.colorCode || v.optionImage).filter(Boolean)).size - 5}
                              </span>
                            )}
                          </div>
                        )}
                      </div>
                    </td>

                    {/* Total Stock */}
                    <td className="py-3.5 px-4">
                      <div className="flex items-center gap-1.5">
                        <span
                          className={`font-mono font-bold text-xs ${
                            isLowStock ? 'text-amber-600 dark:text-amber-400' : 'text-neutral-900 dark:text-white'
                          }`}
                        >
                          {prod.totalInventory} units
                        </span>
                        {isLowStock && (
                          <AlertTriangle className="w-3 h-3 text-amber-500" title="Low Inventory Warning" />
                        )}
                      </div>
                    </td>

                    {/* Status */}
                    <td className="py-3.5 px-4">
                      <span
                        className={`text-[10px] font-bold px-2 py-0.5 rounded-full capitalize ${
                          prod.status === 'active'
                            ? 'bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800'
                            : prod.status === 'draft'
                            ? 'bg-amber-100 dark:bg-amber-950 text-amber-700 dark:text-amber-300'
                            : 'bg-neutral-200 dark:bg-neutral-800 text-neutral-500'
                        }`}
                      >
                        {prod.status}
                      </span>
                    </td>

                    {/* Actions */}
                    <td className="py-3.5 px-4 text-right">
                      <div className="flex items-center justify-end gap-1">
                        <button
                          onClick={() => handleEdit(prod)}
                          className="p-1.5 rounded-lg text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200 hover:bg-neutral-100 dark:hover:bg-neutral-800"
                          title="Edit Product & Matrix"
                        >
                          <Edit className="w-3.5 h-3.5" />
                        </button>

                        <button
                          onClick={() => handleDuplicate(prod)}
                          className="p-1.5 rounded-lg text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200 hover:bg-neutral-100 dark:hover:bg-neutral-800"
                          title="Duplicate Product"
                        >
                          <Copy className="w-3.5 h-3.5" />
                        </button>

                        <button
                          onClick={() => setPendingDeletion(prod)}
                          className="p-1.5 rounded-lg text-rose-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/30"
                          title="Delete Product"
                        >
                          <Trash2 className="w-3.5 h-3.5" />
                        </button>
                      </div>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      </div>

      {/* Product & Variant Matrix Modal */}
      <ProductEditorModal
        product={selectedProduct}
        isOpen={isEditorOpen}
        onClose={() => setIsEditorOpen(false)}
      />
      {pendingDeletion && <DeleteConfirmationModal
        isOpen
        itemType="Product"
        itemName={pendingDeletion.title}
        itemDetails={`${pendingDeletion.variants.length} SKU${pendingDeletion.variants.length === 1 ? '' : 's'} • $${pendingDeletion.basePrice.toFixed(2)}`}
        warningMessage="Archiving this product removes it from active storefront listings while retaining its order history."
        impactItems={[
          `${pendingDeletion.variants.length} SKU variant${pendingDeletion.variants.length === 1 ? '' : 's'} will no longer be available for new purchases.`,
          'The product will be removed from active catalog and storefront listings.',
          'Existing order history remains intact.',
        ]}
        confirmButtonText="Yes, Archive Product"
        onClose={() => setPendingDeletion(null)}
        onConfirm={async () => { await deleteProduct(pendingDeletion.id); setPendingDeletion(null); }}
      />}
    </div>
  );
};
