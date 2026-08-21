import React, { useState } from 'react';
import { useAdmin } from '../../context/AdminContext';
import { Category } from '../../types';
import {
  FolderTree,
  Plus,
  Trash2,
  Edit2,
  Search,
  Globe,
  Tag,
  Laptop,
  Headphones,
  Watch,
  Shirt,
  Briefcase,
  Layers,
  Sparkles,
  X,
  CheckCircle2,
  ArrowRight
} from 'lucide-react';

const AVAILABLE_ICONS = [
  'Laptop',
  'Headphones',
  'Watch',
  'Shirt',
  'Briefcase',
  'Layers',
  'Sparkles',
  'Tag',
  'FolderTree',
  'Globe'
];

export const CategoryAdmin: React.FC = () => {
  const { categories, saveCategory, deleteCategory } = useAdmin();
  const [searchTerm, setSearchTerm] = useState('');
  const [editingCategory, setEditingCategory] = useState<Category | null>(null);
  const [isModalOpen, setIsModalOpen] = useState(false);

  const filteredCategories = categories.filter(c =>
    c.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
    c.slug.toLowerCase().includes(searchTerm.toLowerCase())
  );

  const openCreateModal = () => {
    const newCat: Category = {
      id: 'cat-' + Date.now(),
      name: '',
      slug: '',
      parentId: null,
      icon: 'Laptop',
      image: 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=500&auto=format&fit=crop&q=80',
      description: '',
      seoTitle: '',
      seoDescription: '',
      status: 'active',
      sortOrder: categories.length + 1,
      productCount: 0,
      createdAt: new Date().toISOString().split('T')[0]
    };
    setEditingCategory(newCat);
    setIsModalOpen(true);
  };

  const handleNameChange = (name: string) => {
    if (!editingCategory) return;
    const slug = name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');
    setEditingCategory({
      ...editingCategory,
      name,
      slug: editingCategory.slug ? editingCategory.slug : slug,
      seoTitle: editingCategory.seoTitle ? editingCategory.seoTitle : `${name} | Shop Online at Aurora`
    });
  };

  const renderIcon = (iconName: string) => {
    switch (iconName) {
      case 'Laptop': return <Laptop className="w-4 h-4 text-indigo-500" />;
      case 'Headphones': return <Headphones className="w-4 h-4 text-cyan-500" />;
      case 'Watch': return <Watch className="w-4 h-4 text-emerald-500" />;
      case 'Shirt': return <Shirt className="w-4 h-4 text-amber-500" />;
      case 'Briefcase': return <Briefcase className="w-4 h-4 text-rose-500" />;
      default: return <FolderTree className="w-4 h-4 text-indigo-500" />;
    }
  };

  return (
    <div className="space-y-6 pb-12">
      {/* Header Bar */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-5 rounded-3xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-sm">
        <div>
          <div className="flex items-center gap-2">
            <span className="px-2.5 py-0.5 text-[10px] font-bold uppercase rounded-full bg-emerald-500/20 text-emerald-700 dark:text-emerald-300 border border-emerald-500/30">
              Taxonomy Structure
            </span>
            <span className="text-xs text-neutral-500">Categories & Hierarchies</span>
          </div>
          <h2 className="text-lg sm:text-xl font-bold text-neutral-900 dark:text-white tracking-tight mt-1">
            Category Management (CRUD)
          </h2>
          <p className="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">
            Define hierarchical product categories, parent-child relations, SERP SEO tags, and storefront icons.
          </p>
        </div>

        <div className="flex items-center gap-2">
          <button
            onClick={openCreateModal}
            className="px-3.5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition-all shadow-md shadow-emerald-500/20 flex items-center gap-1.5"
          >
            <Plus className="w-4 h-4" />
            <span>Create Category</span>
          </button>
        </div>
      </div>

      {/* Search & Filter */}
      <div className="flex items-center gap-3">
        <div className="relative flex-1 max-w-md">
          <Search className="w-4 h-4 text-neutral-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
          <input
            type="text"
            placeholder="Search categories by name or slug..."
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            className="w-full pl-9 pr-4 py-2 text-xs rounded-xl border bg-white dark:bg-neutral-900 border-neutral-200 dark:border-neutral-800 text-neutral-900 dark:text-white"
          />
        </div>
        <span className="text-xs text-neutral-400 font-mono">{filteredCategories.length} categories</span>
      </div>

      {/* Category Cards / Data Table */}
      <div className="bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-3xl overflow-hidden shadow-sm">
        <div className="overflow-x-auto">
          <table className="w-full text-left border-collapse text-xs">
            <thead>
              <tr className="border-b border-neutral-200 dark:border-neutral-800 bg-neutral-50/50 dark:bg-neutral-800/50 text-neutral-500 uppercase text-[10px] font-bold tracking-wider">
                <th className="py-3 px-4">Category</th>
                <th className="py-3 px-4">Slug & URL</th>
                <th className="py-3 px-4">Parent Level</th>
                <th className="py-3 px-4">Products</th>
                <th className="py-3 px-4">SEO Snippet</th>
                <th className="py-3 px-4">Status</th>
                <th className="py-3 px-4 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800 text-neutral-800 dark:text-neutral-200">
              {filteredCategories.map((cat) => {
                const parent = categories.find(p => p.id === cat.parentId);

                return (
                  <tr key={cat.id} className="hover:bg-neutral-50/50 dark:hover:bg-neutral-800/50 transition-colors">
                    <td className="py-3.5 px-4">
                      <div className="flex items-center gap-3">
                        <img
                          src={cat.image}
                          alt={cat.name}
                          className="w-9 h-9 rounded-xl object-cover ring-1 ring-neutral-200 dark:ring-neutral-700"
                        />
                        <div>
                          <div className="flex items-center gap-1.5">
                            {renderIcon(cat.icon)}
                            <span className="font-bold text-neutral-900 dark:text-white text-xs">{cat.name}</span>
                          </div>
                          <p className="text-[11px] text-neutral-400 truncate max-w-xs">{cat.description || 'No description'}</p>
                        </div>
                      </div>
                    </td>

                    <td className="py-3.5 px-4 font-mono text-[11px] text-neutral-500 dark:text-neutral-400">
                      /categories/{cat.slug}
                    </td>

                    <td className="py-3.5 px-4">
                      {parent ? (
                        <span className="px-2 py-0.5 rounded-md bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 font-medium text-[11px]">
                          ↳ {parent.name}
                        </span>
                      ) : (
                        <span className="text-neutral-400 text-[11px]">Root (Top Level)</span>
                      )}
                    </td>

                    <td className="py-3.5 px-4 font-semibold text-neutral-900 dark:text-white">
                      {cat.productCount} SKUs
                    </td>

                    <td className="py-3.5 px-4 max-w-xs">
                      <div className="text-[11px] font-semibold text-indigo-600 dark:text-indigo-400 truncate">
                        {cat.seoTitle || cat.name}
                      </div>
                      <p className="text-[10px] text-neutral-400 truncate">{cat.seoDescription || 'Default storefront meta description'}</p>
                    </td>

                    <td className="py-3.5 px-4">
                      <span
                        className={`text-[10px] font-bold px-2 py-0.5 rounded-full capitalize ${
                          cat.status === 'active'
                            ? 'bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800'
                            : 'bg-neutral-200 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400'
                        }`}
                      >
                        {cat.status}
                      </span>
                    </td>

                    <td className="py-3.5 px-4 text-right">
                      <div className="flex items-center justify-end gap-1.5">
                        <button
                          onClick={() => {
                            setEditingCategory(cat);
                            setIsModalOpen(true);
                          }}
                          className="p-1.5 rounded-lg text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200 hover:bg-neutral-100 dark:hover:bg-neutral-800"
                        >
                          <Edit2 className="w-3.5 h-3.5" />
                        </button>
                        <button
                          onClick={() => deleteCategory(cat.id)}
                          className="p-1.5 rounded-lg text-rose-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/30"
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

      {/* Category Editor Modal with Live Google SERP SEO Preview */}
      {isModalOpen && editingCategory && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs animate-in fade-in">
          <div className="w-full max-w-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-3xl p-6 shadow-2xl max-h-[90vh] overflow-y-auto">
            <div className="flex items-center justify-between pb-3 border-b border-neutral-100 dark:border-neutral-800 mb-4">
              <h3 className="text-sm font-bold text-neutral-900 dark:text-white">
                {editingCategory.name ? `Edit Category: ${editingCategory.name}` : 'Create New Category'}
              </h3>
              <button onClick={() => setIsModalOpen(false)} className="text-neutral-400 hover:text-neutral-600">
                <X className="w-4 h-4" />
              </button>
            </div>

            <form
              onSubmit={async (e) => {
                e.preventDefault();
                const saved = await saveCategory(editingCategory);
                if (saved) setIsModalOpen(false);
              }}
              className="space-y-4"
            >
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1">Category Name</label>
                  <input
                    type="text"
                    required
                    placeholder="e.g. Smart Wearables & Rings"
                    value={editingCategory.name}
                    onChange={(e) => handleNameChange(e.target.value)}
                    className="w-full px-3 py-2 text-xs rounded-xl border bg-neutral-50 dark:bg-neutral-800 border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white focus:ring-2 focus:ring-emerald-500"
                  />
                </div>

                <div>
                  <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1">URL Slug</label>
                  <input
                    type="text"
                    required
                    placeholder="smart-wearables"
                    value={editingCategory.slug}
                    onChange={(e) => setEditingCategory({ ...editingCategory, slug: e.target.value })}
                    className="w-full px-3 py-2 text-xs rounded-xl border bg-neutral-50 dark:bg-neutral-800 border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white font-mono"
                  />
                </div>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                  <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1">Parent Category</label>
                  <select
                    value={editingCategory.parentId || ''}
                    onChange={(e) => setEditingCategory({ ...editingCategory, parentId: e.target.value || null })}
                    className="w-full px-3 py-2 text-xs rounded-xl border bg-neutral-50 dark:bg-neutral-800 border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white"
                  >
                    <option value="">None (Top-Level Root)</option>
                    {categories.filter(c => c.id !== editingCategory.id).map(c => (
                      <option key={c.id} value={c.id}>{c.name}</option>
                    ))}
                  </select>
                </div>

                <div>
                  <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1">Icon Identifier</label>
                  <select
                    value={editingCategory.icon}
                    onChange={(e) => setEditingCategory({ ...editingCategory, icon: e.target.value })}
                    className="w-full px-3 py-2 text-xs rounded-xl border bg-neutral-50 dark:bg-neutral-800 border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white"
                  >
                    {AVAILABLE_ICONS.map(ic => (
                      <option key={ic} value={ic}>{ic}</option>
                    ))}
                  </select>
                </div>

                <div>
                  <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1">Status</label>
                  <select
                    value={editingCategory.status}
                    onChange={(e) => setEditingCategory({ ...editingCategory, status: e.target.value as 'active' | 'draft' })}
                    className="w-full px-3 py-2 text-xs rounded-xl border bg-neutral-50 dark:bg-neutral-800 border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white"
                  >
                    <option value="active">Active (Visible)</option>
                    <option value="draft">Draft (Hidden)</option>
                  </select>
                </div>
              </div>

              <div>
                <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1">Image URL</label>
                <input
                  type="text"
                  placeholder="https://images.unsplash.com/..."
                  value={editingCategory.image || ''}
                  onChange={(e) => setEditingCategory({ ...editingCategory, image: e.target.value })}
                  className="w-full px-3 py-2 text-xs rounded-xl border bg-neutral-50 dark:bg-neutral-800 border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1">Category Description</label>
                <textarea
                  rows={2}
                  placeholder="Brief summary displayed on category landing pages..."
                  value={editingCategory.description || ''}
                  onChange={(e) => setEditingCategory({ ...editingCategory, description: e.target.value })}
                  className="w-full px-3 py-2 text-xs rounded-xl border bg-neutral-50 dark:bg-neutral-800 border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white"
                />
              </div>

              {/* SERP SEO Preview Card */}
              <div className="p-4 rounded-2xl bg-neutral-50 dark:bg-neutral-800/70 border border-neutral-200 dark:border-neutral-700 space-y-3">
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-1.5 text-xs font-bold text-neutral-900 dark:text-white">
                    <Globe className="w-3.5 h-3.5 text-emerald-500" />
                    <span>Search Engine Listing Preview (Google SERP)</span>
                  </div>
                  <span className="text-[10px] text-neutral-400">SEO & Metadata</span>
                </div>

                <div className="p-3 bg-white dark:bg-neutral-900 rounded-xl border border-neutral-200 dark:border-neutral-800 shadow-inner">
                  <div className="text-[11px] text-neutral-500 flex items-center gap-1">
                    <span>https://auroracommerce.io</span>
                    <span>›</span>
                    <span className="text-neutral-600 dark:text-neutral-400">categories</span>
                    <span>›</span>
                    <span className="text-neutral-800 dark:text-neutral-200 font-semibold">{editingCategory.slug || 'slug'}</span>
                  </div>
                  <h4 className="text-sm font-semibold text-blue-600 dark:text-blue-400 hover:underline cursor-pointer mt-0.5">
                    {editingCategory.seoTitle || `${editingCategory.name || 'Category'} | Aurora Commerce`}
                  </h4>
                  <p className="text-xs text-neutral-600 dark:text-neutral-400 mt-1 leading-relaxed">
                    {editingCategory.seoDescription || editingCategory.description || 'Shop high quality curated items in this category with fast global delivery and 2-year warranty.'}
                  </p>
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 pt-1">
                  <div>
                    <label className="block text-[11px] font-medium text-neutral-500 mb-1">SEO Title Tag</label>
                    <input
                      type="text"
                      placeholder="Page Title (<title>)"
                      value={editingCategory.seoTitle || ''}
                      onChange={(e) => setEditingCategory({ ...editingCategory, seoTitle: e.target.value })}
                      className="w-full px-2.5 py-1.5 text-xs rounded-lg border bg-white dark:bg-neutral-900 border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white"
                    />
                  </div>
                  <div>
                    <label className="block text-[11px] font-medium text-neutral-500 mb-1">SEO Meta Description</label>
                    <input
                      type="text"
                      placeholder="Meta Description (<meta>)"
                      value={editingCategory.seoDescription || ''}
                      onChange={(e) => setEditingCategory({ ...editingCategory, seoDescription: e.target.value })}
                      className="w-full px-2.5 py-1.5 text-xs rounded-lg border bg-white dark:bg-neutral-900 border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white"
                    />
                  </div>
                </div>
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
                  className="px-4 py-2 text-xs font-bold rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white shadow-md shadow-emerald-500/20"
                >
                  Save Category
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};
