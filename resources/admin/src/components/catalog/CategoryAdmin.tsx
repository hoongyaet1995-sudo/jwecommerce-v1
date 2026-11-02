import { CategoryProducts } from './CategoryProducts';
import { CommerceAction } from '../commerce/CommerceUI';

import {EntitySearchPanel} from '../search/EntitySearchPanel';
import React, { useState, useEffect } from 'react';
import { useAdmin } from '../../context/AdminContext';
import { CatalogBannerControls } from './CatalogBannerControls';
import { DeleteConfirmationModal } from '../common/DeleteConfirmationModal';
import { Category } from '../../types';
import {
  Copy,
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
  const [copyMessage, setCopyMessage] = useState('');
  const [parentKey,setParentKey]=useState<string|null>(()=>typeof window==='undefined'?null:new URLSearchParams(window.location.search).get('parent'));
  const current=categories.find(c=>c.id===parentKey);
  const chain=(id:string|null):Category[]=>{const result:Category[]=[];const seen=new Set<string>();while(id&&!seen.has(id)){seen.add(id);const item=categories.find(c=>c.id===id);if(!item)break;result.unshift(item);id=item.parentId||null}return result};
  const ancestors=chain(current?.id||null);
  const go=(id:string|null)=>{const url=new URL(window.location.href);if(id)url.searchParams.set('parent',id);else url.searchParams.delete('parent');url.searchParams.delete('category');history.pushState({},'',url);setParentKey(id);setSearchTerm('')};
  useEffect(()=>{const sync=()=>{setParentKey(new URLSearchParams(location.search).get('parent'));setSearchTerm('')};window.addEventListener('popstate',sync);return()=>window.removeEventListener('popstate',sync)},[]);
  const validParent=(candidate:Category)=>{if(!editingCategory)return true;const subtreeHeight=(id:string,seen=new Set<string>()):number=>{if(seen.has(id))return 99;seen.add(id);return 1+Math.max(0,...categories.filter(c=>c.parentId===id).map(c=>subtreeHeight(c.id,new Set(seen))))};return !chain(candidate.id).some(c=>c.id===editingCategory.id)&&chain(candidate.id).length+subtreeHeight(editingCategory.id)<=3};

  const [editingCategory, setEditingCategory] = useState<Category | null>(null);
  const [editorKey, setEditorKey] = useState<string | null>(() => typeof window === 'undefined' ? null : new URLSearchParams(window.location.search).get('category'));
  const navigateEditor = (id: string | null, replace = false) => {
    const url = new URL(window.location.href);
    if (id) url.searchParams.set('category', id); else url.searchParams.delete('category');
    window.history[replace ? 'replaceState' : 'pushState']({}, '', url);
    setEditorKey(id);
    if (!id) setEditingCategory(null);
  };
  useEffect(() => {
    const sync = () => { setEditingCategory(null); setEditorKey(new URLSearchParams(window.location.search).get('category')); };
    window.addEventListener('popstate', sync);
  return () => window.removeEventListener('popstate', sync);
  }, []);
  useEffect(() => {
    if (!editorKey || editingCategory) return;
    if (editorKey === 'new') { createEditor(); return; }
    const found = categories.find(item => item.id === editorKey);
    if (found) setEditingCategory(JSON.parse(JSON.stringify(found)));
  }, [editorKey, categories, editingCategory]);
  const [pendingDeletion, setPendingDeletion] = useState<Category | null>(null);

  const filteredCategories = categories.filter(c => (c.parentId||null)===(current?.id||null)).filter(c =>
    c.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
    c.slug.toLowerCase().includes(searchTerm.toLowerCase())
  );

  const createEditor = () => {
    const newCat: Category = {
      id: 'cat-' + Date.now(),
      name: '',
      slug: '',
      parentId: current?.id||null,
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

  };
  const openCreateModal = () => { createEditor(); navigateEditor('new'); };

  const handleNameChange = (name: string) => {
    if (!editingCategory) return;
    setEditingCategory({
      ...editingCategory,
      name,
      seoTitle: editingCategory.seoTitle ? editingCategory.seoTitle : name
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

    if (editorKey) return (
    <div className="space-y-6 pb-12">
      <nav aria-label="Breadcrumb" className="flex items-center gap-3 text-xs text-neutral-500">
        <span>Products & Catalog</span><span>/</span>
        <button onClick={() => navigateEditor(null)} className="text-indigo-600 dark:text-indigo-400">Categories</button><span>/</span>
        <span>{editorKey === 'new' ? 'Create category' : editingCategory?.name || 'Edit category'}</span>
      </nav>
      {!editingCategory && <div className="p-6 rounded-xl border border-neutral-200 dark:border-neutral-800"><p>Loading category…</p><button onClick={() => navigateEditor(null)} className="mt-4 px-4 py-2 border rounded-xl text-xs">Back</button></div>}
      {editingCategory && (
        <div className="w-full">
          <div className="w-full bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-3xl p-6 shadow-sm">
            <div className="flex items-center justify-between pb-3 border-b border-neutral-100 dark:border-neutral-800 mb-4">
              <h3 className="text-sm font-bold text-neutral-900 dark:text-white">
                {editingCategory.name ? `Edit Category: ${editingCategory.name}` : 'Create New Category'}
              </h3>
              <button onClick={() => navigateEditor(null)} className="px-4 py-2 text-xs font-medium rounded-xl border border-neutral-300 dark:border-neutral-700 text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800">
                Back
              </button>
            </div>

            <form
              onSubmit={async (e) => {
                e.preventDefault();
                const saved = await saveCategory(editingCategory);
                if (saved) { setEditingCategory(saved); navigateEditor(saved.id, true); }
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
                  <div className="flex rounded-xl border bg-neutral-50 dark:bg-neutral-800 border-neutral-200 dark:border-neutral-700 overflow-hidden focus-within:ring-2 focus-within:ring-emerald-500">
                    <span className="inline-flex items-center px-3 text-xs font-mono text-neutral-500 dark:text-neutral-400 border-r border-neutral-200 dark:border-neutral-700 whitespace-nowrap">/categories/</span>
                    <input
                      type="text"
                      placeholder="auto-generated-from-category-name"
                      value={editingCategory.slug}
                      onChange={(e) => setEditingCategory({ ...editingCategory, slug: e.target.value.toLowerCase().replace(/[^a-z0-9-]/g, '-') })}
                      className="min-w-0 flex-1 px-3 py-2 text-xs font-mono bg-transparent text-neutral-900 dark:text-white outline-none"
                    />
                  </div>
                  <p className="mt-1 text-[10px] text-neutral-500 dark:text-neutral-400">Leave blank to generate a unique URL slug from the category name.</p>
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
                    {categories.filter(validParent).map(c => (
                      <option key={c.id} value={c.id}>{chain(c.id).map(p=>p.name).join(" / ")}</option>
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

              <CatalogBannerControls image={editingCategory.image} settings={editingCategory.imageSettings} onImage={(image) => setEditingCategory({ ...editingCategory, image })} onChange={imageSettings => setEditingCategory({ ...editingCategory, imageSettings })} />

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

              <EntitySearchPanel kind="category" id={editingCategory.id} onChanged={(seoTitle,seoDescription)=>setEditingCategory(previous=>previous?({...previous,seoTitle,seoDescription}):previous)}/>

              <div className="flex justify-end gap-2 pt-3 border-t border-neutral-100 dark:border-neutral-800">
                <button
                  type="button"
                  onClick={() => navigateEditor(null)}
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
  return (
    <div className="space-y-6 pb-12">
      <nav aria-label="Breadcrumb" className="flex flex-wrap items-center gap-3 text-xs text-neutral-500"><span>Products & Catalog</span><span>/</span><button onClick={()=>go(null)} className="text-indigo-600 dark:text-indigo-400">Categories</button>{ancestors.map(c=><React.Fragment key={c.id}><span>/</span><button onClick={()=>go(c.id)} className="text-indigo-600 dark:text-indigo-400">{c.name}</button></React.Fragment>)}</nav>
      <div className="flex items-center justify-between gap-4"><div><h2 className="text-2xl font-bold">{current?.name||'Categories'}</h2><p className="text-xs text-neutral-500 mt-2">Organise your catalogue, one level at a time.</p></div>{ancestors.length<3&&<button onClick={openCreateModal} className="px-4 py-2 rounded-xl bg-emerald-600 text-white text-xs font-bold flex gap-2 items-center"><Plus size={16}/>{current?'Create subcategory':'Create Category'}</button>}</div>
      {ancestors.length===3&&<p className="text-xs text-neutral-500">This is the final category level.</p>}
      <span role="status" aria-live="polite" className={copyMessage ? "text-xs text-neutral-500" : "sr-only"}>{copyMessage}</span>
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
                <th className="py-3 px-4">Subcategories</th>
                <th className="py-3 px-4">Products</th>

                <th className="py-3 px-4">Status</th>
                <th className="py-3 px-4 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800 text-neutral-800 dark:text-neutral-200">
              {filteredCategories.map((cat) => {


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
                            <button onClick={()=>go(cat.id)} className="font-bold text-neutral-900 dark:text-white text-xs hover:text-indigo-500">{cat.name}</button>
                          </div>
                          <p className="text-[11px] text-neutral-400 truncate max-w-xs">{cat.description || 'No description'}</p>
                        </div>
                      </div>
                    </td>

                    <td className="py-3.5 px-4 font-mono text-[11px] text-neutral-500 dark:text-neutral-400">
                      <div className="flex items-center gap-2">
                        <a href={`/categories/${encodeURIComponent(cat.slug)}`} target="_blank" rel="noopener noreferrer" className="hover:text-indigo-600 dark:hover:text-indigo-400 hover:underline" aria-label={`Open ${cat.name} on storefront`}>/categories/{cat.slug}</a>
                        <button type="button" title="Copy link" aria-label={`Copy link for ${cat.name}`} className="p-1 rounded-md hover:bg-neutral-100 dark:hover:bg-neutral-800 text-neutral-400 hover:text-indigo-500" onClick={async()=>{try{await navigator.clipboard.writeText(new URL(`/categories/${encodeURIComponent(cat.slug)}`,window.location.origin).href);setCopyMessage(`Link copied for ${cat.name}.`)}catch{setCopyMessage('Unable to copy. Please copy the link manually.')}}}><Copy size={13}/></button>
                      </div>
                    </td>

                    <td className="py-3.5 px-4">
                      {categories.filter(c=>c.parentId===cat.id).length}
                    </td>

                    <td className="py-3.5 px-4 font-semibold text-neutral-900 dark:text-white">
                      {cat.productCount}
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
                      <CommerceAction label={cat.name} items={[{label:"Edit Category",icon:<Edit2 size={15}/>,onClick:()=>{setEditingCategory(JSON.parse(JSON.stringify(cat)));navigateEditor(cat.id)}},{label:"Delete Category",icon:<Trash2 size={15}/>,danger:true,onClick:()=>setPendingDeletion(cat)}]}/>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      </div>

      {!filteredCategories.length&&<p className="text-xs text-neutral-500">{searchTerm?'No matching categories.':ancestors.length<3?'No subcategories yet.':''}</p>}
      {current&&<CategoryProducts key={current.id} id={current.id} name={current.name}/>}
      {/* Category Editor Modal with Live Google SERP SEO Preview */}
      {pendingDeletion && <DeleteConfirmationModal
        isOpen
        itemType="Category"
        itemName={pendingDeletion.name}
        itemDetails={`${pendingDeletion.productCount} linked product${pendingDeletion.productCount === 1 ? '' : 's'}`}
        warningMessage="Deleting this category removes its taxonomy node from the catalog. Linked products must be reassigned before they can remain categorized."
        impactItems={[
          `${pendingDeletion.productCount} linked product${pendingDeletion.productCount === 1 ? '' : 's'} may no longer have this category assignment.`,
          'Storefront menus or page sections linked to this category may no longer resolve.',
          'This action cannot be automatically reversed.',
        ]}
        onClose={() => setPendingDeletion(null)}
        onConfirm={async () => { await deleteCategory(pendingDeletion.id); setPendingDeletion(null); }}
      />}
    </div>
  );
};
