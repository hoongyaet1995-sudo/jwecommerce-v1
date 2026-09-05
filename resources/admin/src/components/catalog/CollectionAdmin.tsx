import { useAdminCurrency } from '../../context/useAdminCurrency';
import React, { useState } from 'react';
import { useAdmin } from '../../context/AdminContext';
import { CatalogImageUploadField } from './CatalogImageUploadField';
import { DeleteConfirmationModal } from '../common/DeleteConfirmationModal';
import { primaryProductImage } from '../../catalogMedia';
import { Collection, DynamicRule, CollectionRuleField, CollectionRuleOperator } from '../../types';
import {
  Sparkles,
  Plus,
  Trash2,
  Edit2,
  Sliders,
  CheckCircle2,
  X,
  Package,
  Layers,
  Search,
  Filter
} from 'lucide-react';

export const CollectionAdmin: React.FC = () => {
  const { money, currencyCode } = useAdminCurrency();
  const { collections, saveCollection, deleteCollection, products } = useAdmin();
  const [editingCollection, setEditingCollection] = useState<Collection | null>(null);
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [pendingDeletion, setPendingDeletion] = useState<Collection | null>(null);

  const openCreateModal = () => {
    const newCol: Collection = {
      id: 'col-' + Date.now(),
      title: '',
      slug: '',
      description: '',
      subtitle: '',
      badge: '',
      coverImage: 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=800&auto=format&fit=crop&q=80',
      type: 'dynamic',
      matchType: 'all',
      dynamicRules: [
        { id: 'r-' + Date.now(), field: 'price', operator: 'greater_than', value: '50' }
      ],
      manualProductIds: [],
      status: 'active',
      productCount: 0,
      createdAt: new Date().toISOString().split('T')[0]
    };
    setEditingCollection(newCol);
    setIsModalOpen(true);
  };

  const addDynamicRule = () => {
    if (!editingCollection) return;
    const newRule: DynamicRule = {
      id: 'r-' + Date.now(),
      field: 'tag',
      operator: 'equals',
      value: 'summer'
    };
    setEditingCollection({
      ...editingCollection,
      dynamicRules: [...(editingCollection.dynamicRules || []), newRule]
    });
  };

  const removeDynamicRule = (ruleId: string) => {
    if (!editingCollection) return;
    setEditingCollection({
      ...editingCollection,
      dynamicRules: (editingCollection.dynamicRules || []).filter(r => r.id !== ruleId)
    });
  };

  const toggleManualProduct = (prodId: string) => {
    if (!editingCollection) return;
    const current = editingCollection.manualProductIds || [];
    const exists = current.includes(prodId);
    const updated = exists ? current.filter(id => id !== prodId) : [...current, prodId];
    setEditingCollection({
      ...editingCollection,
      manualProductIds: updated,
      productCount: updated.length
    });
  };

  return (
    <div className="space-y-6 pb-12">
      {/* Header Bar */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-5 rounded-3xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-sm">
        <div>
          <div className="flex items-center gap-2">
            <span className="px-2.5 py-0.5 text-[10px] font-bold uppercase rounded-full bg-purple-500/20 text-purple-700 dark:text-purple-300 border border-purple-500/30">
              Curations & Campaigns
            </span>
            <span className="text-xs text-neutral-500">Dynamic Automated vs Manual Collections</span>
          </div>
          <h2 className="text-lg sm:text-xl font-bold text-neutral-900 dark:text-white tracking-tight mt-1">
            Collection Management (CRUD)
          </h2>
          <p className="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">
            Group products by promotional campaigns, seasonal sales, or smart automated rules (e.g. Price &gt; {money(50)} AND Tag = &apos;summer&apos;).
          </p>
        </div>

        <div className="flex items-center gap-2">
          <button
            onClick={openCreateModal}
            className="px-3.5 py-2 rounded-xl bg-purple-600 hover:bg-purple-700 text-white text-xs font-bold transition-all shadow-md shadow-purple-500/20 flex items-center gap-1.5"
          >
            <Plus className="w-4 h-4" />
            <span>Create Collection</span>
          </button>
        </div>
      </div>

      {/* Collections Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        {collections.map((col) => (
          <div
            key={col.id}
            className="group rounded-3xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 overflow-hidden shadow-sm hover:shadow-md transition-all flex flex-col justify-between"
          >
            <div>
              {/* Cover Banner */}
              <div className="h-36 w-full relative overflow-hidden bg-neutral-800">
                <img
                  src={col.coverImage}
                  alt={col.title}
                  className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                />
                <div className="absolute inset-0 bg-gradient-to-t from-black/80 via-black/30 to-transparent flex items-end p-4">
                  <div className="flex items-center justify-between w-full">
                    <span className="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full bg-white/20 text-white backdrop-blur-md border border-white/20">
                      {col.type === 'dynamic' ? '⚡ Dynamic Smart Rules' : '✋ Handpicked Manual'}
                    </span>
                    <span className="text-xs font-bold text-white bg-black/50 px-2 py-0.5 rounded-md font-mono">
                      {col.type === 'manual' ? col.manualProductIds?.length || 0 : col.productCount} Items
                    </span>
                  </div>
                </div>
              </div>

              {/* Body */}
              <div className="p-5 space-y-3">
                <div className="flex items-center justify-between">
                  <h3 className="text-sm font-bold text-neutral-900 dark:text-white leading-snug">
                    {col.title}
                  </h3>
                  <span
                    className={`text-[10px] font-bold px-2 py-0.5 rounded-full capitalize ${
                      col.status === 'active'
                        ? 'bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300'
                        : 'bg-neutral-100 dark:bg-neutral-800 text-neutral-500'
                    }`}
                  >
                    {col.status}
                  </span>
                </div>

                <p className="text-xs text-neutral-500 dark:text-neutral-400 line-clamp-2">
                  {col.description || 'No promotional description specified.'}
                </p>

                {col.type === 'dynamic' && col.dynamicRules && (
                  <div className="p-2.5 rounded-xl bg-neutral-50 dark:bg-neutral-800/50 border border-neutral-100 dark:border-neutral-800 text-[11px] space-y-1">
                    <div className="font-semibold text-neutral-500 uppercase text-[10px]">
                      Match: <span className="text-purple-600 dark:text-purple-400 font-bold">{col.matchType === 'all' ? 'ALL conditions' : 'ANY condition'}</span>
                    </div>
                    <ul className="space-y-0.5 text-neutral-600 dark:text-neutral-300 font-mono text-[10px]">
                      {col.dynamicRules.map(r => (
                        <li key={r.id} className="truncate">
                          • {r.field} {r.operator} &quot;{r.value}&quot;
                        </li>
                      ))}
                    </ul>
                  </div>
                )}
              </div>
            </div>

            {/* Actions Footer */}
            <div className="p-4 border-t border-neutral-100 dark:border-neutral-800 bg-neutral-50/50 dark:bg-neutral-800/30 flex items-center justify-between text-xs">
              <span className="font-mono text-[11px] text-neutral-400">/collections/{col.slug}</span>
              <div className="flex items-center gap-1.5">
                <button
                  onClick={() => {
                    setEditingCollection(col);
                    setIsModalOpen(true);
                  }}
                  className="p-1.5 rounded-lg text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200 hover:bg-neutral-100 dark:hover:bg-neutral-800"
                >
                  <Edit2 className="w-3.5 h-3.5" />
                </button>
                <button
                  onClick={() => setPendingDeletion(col)}
                  className="p-1.5 rounded-lg text-rose-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/30"
                >
                  <Trash2 className="w-3.5 h-3.5" />
                </button>
              </div>
            </div>
          </div>
        ))}
      </div>

      {/* Collection Editor Modal */}
      {isModalOpen && editingCollection && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs animate-in fade-in">
          <div className="w-full max-w-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-3xl p-6 shadow-2xl max-h-[90vh] overflow-y-auto">
            <div className="flex items-center justify-between pb-3 border-b border-neutral-100 dark:border-neutral-800 mb-4">
              <h3 className="text-sm font-bold text-neutral-900 dark:text-white">
                {editingCollection.title ? `Edit Collection: ${editingCollection.title}` : 'Create New Collection'}
              </h3>
              <button onClick={() => setIsModalOpen(false)} className="text-neutral-400 hover:text-neutral-600">
                <X className="w-4 h-4" />
              </button>
            </div>

            <form
              onSubmit={async (e) => {
                e.preventDefault();
                const saved = await saveCollection(editingCollection);
                if (saved) setIsModalOpen(false);
              }}
              className="space-y-4"
            >
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1">Collection Title</label>
                  <input
                    type="text"
                    required
                    placeholder="e.g. Summer Tech Essentials 2026"
                    value={editingCollection.title}
                    onChange={(e) => {
                      const title = e.target.value;
                      setEditingCollection({ ...editingCollection, title });
                    }}
                    className="w-full px-3 py-2 text-xs rounded-xl border bg-neutral-50 dark:bg-neutral-800 border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white focus:ring-2 focus:ring-purple-500"
                  />
                </div>

                <div>
                  <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1">URL Slug</label>
                  <div className="flex rounded-xl border bg-neutral-50 dark:bg-neutral-800 border-neutral-200 dark:border-neutral-700 overflow-hidden focus-within:ring-2 focus-within:ring-purple-500">
                    <span className="inline-flex items-center px-3 text-xs font-mono text-neutral-500 dark:text-neutral-400 border-r border-neutral-200 dark:border-neutral-700 whitespace-nowrap">/collections/</span>
                    <input
                      type="text"
                      placeholder="auto-generated-from-collection-title"
                      value={editingCollection.slug}
                      onChange={(e) => setEditingCollection({ ...editingCollection, slug: e.target.value.toLowerCase().replace(/[^a-z0-9-]/g, '-') })}
                      className="min-w-0 flex-1 px-3 py-2 text-xs font-mono bg-transparent text-neutral-900 dark:text-white outline-none"
                    />
                  </div>
                  <p className="mt-1 text-[10px] text-neutral-500 dark:text-neutral-400">Leave blank to generate a unique URL slug from the collection title.</p>
                </div>
              </div>

              <CatalogImageUploadField label="Cover Image URL" value={editingCollection.coverImage || ''} onChange={(coverImage) => setEditingCollection({ ...editingCollection, coverImage })} />

              <div>
                <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1">Description / Campaign Hook</label>
                <textarea
                  rows={2}
                  placeholder="Campaign subtitle and promotion terms..."
                  value={editingCollection.description || ''}
                  onChange={(e) => setEditingCollection({ ...editingCollection, description: e.target.value })}
                  className="w-full px-3 py-2 text-xs rounded-xl border bg-neutral-50 dark:bg-neutral-800 border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white"
                />
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1">Public Listing Eyebrow</label>
                  <input type="text" value={editingCollection.badge || ''} onChange={(e) => setEditingCollection({ ...editingCollection, badge: e.target.value })} placeholder="e.g. Limited release" className="w-full px-3 py-2 text-xs rounded-xl border bg-neutral-50 dark:bg-neutral-800 border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white" />
                </div>
                <div>
                  <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1">Public Listing Introduction</label>
                  <input type="text" value={editingCollection.subtitle || ''} onChange={(e) => setEditingCollection({ ...editingCollection, subtitle: e.target.value })} placeholder="Short collection introduction" className="w-full px-3 py-2 text-xs rounded-xl border bg-neutral-50 dark:bg-neutral-800 border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white" />
                </div>
              </div>

              {/* Collection Type Selector */}
              <div className="p-4 rounded-2xl bg-neutral-50 dark:bg-neutral-800/70 border border-neutral-200 dark:border-neutral-700 space-y-3">
                <label className="block text-xs font-bold text-neutral-900 dark:text-white">Collection Association Type</label>
                <div className="grid grid-cols-2 gap-3">
                  <label className={`flex items-start gap-3 p-3 rounded-xl border cursor-pointer transition-all ${editingCollection.type === 'dynamic' ? 'bg-purple-50 dark:bg-purple-950/40 border-purple-500' : 'bg-white dark:bg-neutral-900 border-neutral-200 dark:border-neutral-700'}`}>
                    <input
                      type="radio"
                      name="col-type"
                      checked={editingCollection.type === 'dynamic'}
                      onChange={() => setEditingCollection({ ...editingCollection, type: 'dynamic' })}
                      className="mt-0.5 text-purple-600 focus:ring-purple-500"
                    />
                    <div>
                      <div className="text-xs font-bold text-neutral-900 dark:text-white">⚡ Dynamic Automated Rules</div>
                      <div className="text-[10px] text-neutral-500">Products match conditions automatically</div>
                    </div>
                  </label>

                  <label className={`flex items-start gap-3 p-3 rounded-xl border cursor-pointer transition-all ${editingCollection.type === 'manual' ? 'bg-purple-50 dark:bg-purple-950/40 border-purple-500' : 'bg-white dark:bg-neutral-900 border-neutral-200 dark:border-neutral-700'}`}>
                    <input
                      type="radio"
                      name="col-type"
                      checked={editingCollection.type === 'manual'}
                      onChange={() => setEditingCollection({ ...editingCollection, type: 'manual' })}
                      className="mt-0.5 text-purple-600 focus:ring-purple-500"
                    />
                    <div>
                      <div className="text-xs font-bold text-neutral-900 dark:text-white">✋ Handpicked Manual SKUs</div>
                      <div className="text-[10px] text-neutral-500">Select individual products from catalog</div>
                    </div>
                  </label>
                </div>

                {/* If Dynamic: Rule Builder */}
                {editingCollection.type === 'dynamic' && (
                  <div className="space-y-3 pt-2">
                    <div className="flex items-center justify-between">
                      <div className="flex items-center gap-2">
                        <span className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Conditions:</span>
                        <select
                          value={editingCollection.matchType || 'all'}
                          onChange={(e) => setEditingCollection({ ...editingCollection, matchType: e.target.value as 'all' | 'any' })}
                          className="px-2 py-1 text-xs rounded-lg border bg-white dark:bg-neutral-900 border-neutral-200 dark:border-neutral-700"
                        >
                          <option value="all">Match ALL conditions</option>
                          <option value="any">Match ANY condition</option>
                        </select>
                      </div>

                      <button
                        type="button"
                        onClick={addDynamicRule}
                        className="text-xs font-semibold text-purple-600 dark:text-purple-400 hover:underline flex items-center gap-1"
                      >
                        <Plus className="w-3 h-3" /> Add Condition
                      </button>
                    </div>

                    <div className="space-y-2">
                      {(editingCollection.dynamicRules || []).map((rule, rIdx) => (
                        <div key={rule.id} className="flex items-center gap-2">
                          <select
                            value={rule.field}
                            onChange={(e) => {
                              const updated = [...(editingCollection.dynamicRules || [])];
                              updated[rIdx].field = e.target.value as CollectionRuleField;
                              setEditingCollection({ ...editingCollection, dynamicRules: updated });
                            }}
                            className="px-2.5 py-1.5 text-xs rounded-lg border bg-white dark:bg-neutral-900 border-neutral-200 dark:border-neutral-700"
                          >
                            <option value="price">Product Price</option>
                            <option value="tag">Product Tag</option>
                            <option value="category">Category</option>
                            <option value="inventory">Inventory Stock</option>
                          </select>

                          <select
                            value={rule.operator}
                            onChange={(e) => {
                              const updated = [...(editingCollection.dynamicRules || [])];
                              updated[rIdx].operator = e.target.value as CollectionRuleOperator;
                              setEditingCollection({ ...editingCollection, dynamicRules: updated });
                            }}
                            className="px-2.5 py-1.5 text-xs rounded-lg border bg-white dark:bg-neutral-900 border-neutral-200 dark:border-neutral-700"
                          >
                            <option value="equals">is equal to</option>
                            <option value="greater_than">is greater than (&gt;)</option>
                            <option value="less_than">is less than (&lt;)</option>
                            <option value="contains">contains keyword</option>
                          </select>

                          <input
                            type="text"
                            placeholder="Value (e.g. 50, summer)"
                            value={rule.value}
                            onChange={(e) => {
                              const updated = [...(editingCollection.dynamicRules || [])];
                              updated[rIdx].value = e.target.value;
                              setEditingCollection({ ...editingCollection, dynamicRules: updated });
                            }}
                            className="flex-1 px-2.5 py-1.5 text-xs rounded-lg border bg-white dark:bg-neutral-900 border-neutral-200 dark:border-neutral-700"
                          />

                          <button
                            type="button"
                            onClick={() => removeDynamicRule(rule.id)}
                            className="p-1.5 text-rose-400 hover:text-rose-600"
                          >
                            <Trash2 className="w-3.5 h-3.5" />
                          </button>
                        </div>
                      ))}
                    </div>
                  </div>
                )}

                {/* If Manual: Product Picker */}
                {editingCollection.type === 'manual' && (
                  <div className="space-y-2 pt-2">
                    <p className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Select Products for this Collection:</p>
                    <div className="max-h-44 overflow-y-auto space-y-1.5 border border-neutral-200 dark:border-neutral-700 rounded-xl p-2 bg-white dark:bg-neutral-900">
                      {products.map(p => {
                        const isSelected = (editingCollection.manualProductIds || []).includes(p.id);
                        return (
                          <div
                            key={p.id}
                            onClick={() => toggleManualProduct(p.id)}
                            className={`flex items-center justify-between p-2 rounded-lg cursor-pointer transition-colors text-xs ${isSelected ? 'bg-purple-50 dark:bg-purple-950/40 border border-purple-300 dark:border-purple-800' : 'hover:bg-neutral-50 dark:hover:bg-neutral-800'}`}
                          >
                            <div className="flex items-center gap-2">
                              <img src={primaryProductImage(p)} alt={p.title} className="w-6 h-6 rounded-md object-cover" />
                              <span className="font-medium text-neutral-900 dark:text-white truncate">{p.title}</span>
                            </div>
                            <div className="flex items-center gap-2">
                              <span className="text-neutral-400 font-mono">{money(p.basePrice)}</span>
                              <input
                                type="checkbox"
                                checked={isSelected}
                                readOnly
                                className="rounded text-purple-600 focus:ring-purple-500"
                              />
                            </div>
                          </div>
                        );
                      })}
                    </div>
                  </div>
                )}
              </div>

              <div className="flex justify-end gap-2 pt-3 border-t border-neutral-100 dark:border-neutral-800">
                <button
                  type="button"
                  onClick={() => setIsModalOpen(false)}
                  className="px-4 py-2 text-xs font-medium rounded-xl border border-neutral-300 dark:border-neutral-700 text-neutral-700 dark:text-neutral-300"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="px-4 py-2 text-xs font-bold rounded-xl bg-purple-600 hover:bg-purple-700 text-white shadow-md shadow-purple-500/20"
                >
                  Save Collection
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
      {pendingDeletion && <DeleteConfirmationModal
        isOpen
        itemType="Collection"
        itemName={pendingDeletion.title}
        itemDetails={`${pendingDeletion.productCount} linked product${pendingDeletion.productCount === 1 ? '' : 's'} • ${pendingDeletion.type} collection`}
        warningMessage="Deleting this collection removes its curated grouping from the catalog and storefront references."
        impactItems={[
          `${pendingDeletion.productCount} product association${pendingDeletion.productCount === 1 ? '' : 's'} will be removed from this collection.`,
          'Storefront menus or CMS sections linked to this collection may no longer resolve.',
          'This action cannot be automatically reversed.',
        ]}
        onClose={() => setPendingDeletion(null)}
        onConfirm={async () => { await deleteCollection(pendingDeletion.id); setPendingDeletion(null); }}
      />}
    </div>
  );
};
