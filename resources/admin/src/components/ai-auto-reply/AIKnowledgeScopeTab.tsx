import React, { useState, useRef } from 'react';
import { useAdmin } from '../../context/AdminContext';
import {
  Database,
  HelpCircle,
  Package,
  ShoppingBag,
  Tag,
  FileText,
  Plus,
  Trash2,
  Edit2,
  Check,
  Search,
  Lock,
  Eye,
  EyeOff,
  CheckCircle2,
  ShieldCheck,
  Percent,
  Sliders,
  ExternalLink,
  ChevronDown
} from 'lucide-react';
import { FAQKnowledgeItem, VoucherRule, CustomKnowledgeDoc } from '../../types/aiAutoReply';
import { faqCategories, removeFAQCategory } from '../../data/faqCategories';

export const AIKnowledgeScopeTab: React.FC = () => {
  const {
    aiAutoReplyConfig,
    saveAIAutoReplyConfig,
    updateAIAutoReplyConfig,
    addFAQItem,
    updateFAQItem,
    deleteFAQItem,
    addVoucherRule,
    updateVoucherRule,
    deleteVoucherRule,
    addKnowledgeDoc,
    updateKnowledgeDoc,
    deleteKnowledgeDoc,
    addToast
  } = useAdmin();

  const { faqKnowledge, orderLookup, productCatalog, promotionsAndDiscounts, customKnowledgeDocs } =
    aiAutoReplyConfig.knowledgeScope;

  // Local state for modals & search
  const [faqCategoryFilter, setFaqCategoryFilter] = useState<string>('all');
  const [faqSearchQuery, setFaqSearchQuery] = useState<string>('');
  const [isAddingFaq, setIsAddingFaq] = useState(false);
  const [editingFaqId, setEditingFaqId] = useState<string | null>(null);
  const [faqFormSaving, setFaqFormSaving] = useState(false);
  const faqToggleLock = useRef(false);
  const [faqToggleSaving, setFaqToggleSaving] = useState(false);
  const toggleFaqActive = async (faq: FAQKnowledgeItem) => {
    if (faqToggleLock.current) return;
    faqToggleLock.current = true; setFaqToggleSaving(true);
    try {
      await saveAIAutoReplyConfig({...aiAutoReplyConfig, knowledgeScope: {...aiAutoReplyConfig.knowledgeScope, faqKnowledge: {...faqKnowledge, items: faqKnowledge.items.map(item => item.id === faq.id ? {...item, isActive: !faq.isActive} : item)}}});
    } finally { faqToggleLock.current = false; setFaqToggleSaving(false); }
  };

  const closeFaqForm = () => { setIsAddingFaq(false); setEditingFaqId(null); setNewFaqQuestion(''); setNewFaqAnswer(''); setNewFaqKeywords(''); };
  const editFaq = (faq: FAQKnowledgeItem) => { setEditingFaqId(faq.id); setNewFaqQuestion(faq.question); setNewFaqAnswer(faq.answer); setNewFaqCategory(faq.category); setNewFaqKeywords(faq.keywords.join(', ')); setIsAddingFaq(true); };

  const [isAddingCategory, setIsAddingCategory] = useState(false);
  const [newCategoryName, setNewCategoryName] = useState('');
  const categories = faqCategories(faqKnowledge.categories, faqKnowledge.items, faqKnowledge.deletedCategoryIds);
  const [deletingCategoryId, setDeletingCategoryId] = useState<string | null>(null);
  const deletingCategory = categories.find(category => category.id === deletingCategoryId);
  const confirmDeleteCategory = () => {
    if (!deletingCategory || deletingCategory.id === 'others') return;
    const id = deletingCategory.id;
    updateAIAutoReplyConfig(prev => ({ ...prev, knowledgeScope: { ...prev.knowledgeScope, faqKnowledge: removeFAQCategory(prev.knowledgeScope.faqKnowledge, id) } }));
    if (faqCategoryFilter === id) setFaqCategoryFilter('others');
    if (newFaqCategory === id) setNewFaqCategory('others');
    setDeletingCategoryId(null);
    addToast({ type: 'success', title: 'Category Deleted', message: 'Affected FAQs moved to Others.' });
  };

  const handleAddCategory = () => {
    const label = newCategoryName.trim().replace(/\s+/g, ' ');
    if (!label) return;
    if (categories.some(c => [c.label, c.shortLabel].some(name => name.toLocaleLowerCase() === label.toLocaleLowerCase())) || label.toLocaleLowerCase() === 'all faqs') {
      addToast({ type: 'warning', title: 'Category Exists', message: 'Choose a different category name.' });
      return;
    }
    const id = 'faq-category-' + crypto.randomUUID();
    updateAIAutoReplyConfig(prev => ({ ...prev, knowledgeScope: { ...prev.knowledgeScope, faqKnowledge: { ...prev.knowledgeScope.faqKnowledge, categories: [...(prev.knowledgeScope.faqKnowledge.categories || []), { id, label }] } } }));
    setNewFaqCategory(id);
    setNewCategoryName('');
    setIsAddingCategory(false);
    addToast({ type: 'success', title: 'Category Added', message: label });
  };

  // New FAQ Form
  const [newFaqQuestion, setNewFaqQuestion] = useState('');
  const [newFaqAnswer, setNewFaqAnswer] = useState('');
  const [newFaqCategory, setNewFaqCategory] = useState<FAQKnowledgeItem['category']>(() => categories.some(category => category.id === 'shipping') ? 'shipping' : 'others');
  const [newFaqKeywords, setNewFaqKeywords] = useState('');

  // Voucher Form State
  const [isAddingVoucher, setIsAddingVoucher] = useState(false);
  const [newVoucherCode, setNewVoucherCode] = useState('');
  const [newVoucherType, setNewVoucherType] = useState<'percentage' | 'fixed_myr' | 'free_shipping'>('percentage');
  const [newVoucherValue, setNewVoucherValue] = useState<number>(10);
  const [newVoucherMinSpend, setNewVoucherMinSpend] = useState<number>(150);
  const [newVoucherDesc, setNewVoucherDesc] = useState('');

  // Doc Form State
  const [isAddingDoc, setIsAddingDoc] = useState(false);
  const [newDocTitle, setNewDocTitle] = useState('');
  const [newDocCategory, setNewDocCategory] = useState('Product Care');
  const [newDocContent, setNewDocContent] = useState('');
  const [newDocTags, setNewDocTags] = useState('');

  // Filtered FAQs
  const filteredFaqs = faqKnowledge.items.filter(item => {
    const matchesCat = faqCategoryFilter === 'all' || item.category === faqCategoryFilter;
    const matchesSearch =
      item.question.toLowerCase().includes(faqSearchQuery.toLowerCase()) ||
      item.answer.toLowerCase().includes(faqSearchQuery.toLowerCase()) ||
      item.keywords.some(k => k.toLowerCase().includes(faqSearchQuery.toLowerCase()));
    return matchesCat && matchesSearch;
  });

  const handleSaveNewFaq = async () => {
    if (faqFormSaving) return;
    if (!newFaqQuestion.trim() || !newFaqAnswer.trim()) { addToast({ type: 'warning', title: 'Missing Info', message: 'Question and Answer are required.' }); return; }
    const keywords = newFaqKeywords.split(',').map(k => k.trim().toLowerCase()).filter(Boolean);
    const changes = { category: newFaqCategory, question: newFaqQuestion.trim(), answer: newFaqAnswer.trim(), keywords, lastUpdatedAt: new Date().toISOString().slice(0, 10) };
    const items = editingFaqId ? faqKnowledge.items.map(faq => faq.id === editingFaqId ? { ...faq, ...changes } : faq) : [...faqKnowledge.items, { ...changes, id: 'faq-' + crypto.randomUUID(), isActive: true }];
    setFaqFormSaving(true);
    try { if (await saveAIAutoReplyConfig({ ...aiAutoReplyConfig, knowledgeScope: { ...aiAutoReplyConfig.knowledgeScope, faqKnowledge: { ...faqKnowledge, items } } })) closeFaqForm(); }
    finally { setFaqFormSaving(false); }
  };

  const handleSaveNewVoucher = () => {
    if (!newVoucherCode.trim()) {
      addToast({ type: 'warning', title: 'Missing Code', message: 'Voucher code is required.' });
      return;
    }
    addVoucherRule({
      code: newVoucherCode.toUpperCase().trim(),
      discountType: newVoucherType,
      value: Number(newVoucherValue),
      minSpend: Number(newVoucherMinSpend),
      description: newVoucherDesc.trim() || `${newVoucherType === 'percentage' ? `${newVoucherValue}% OFF` : `RM ${newVoucherValue} OFF`} on orders over RM ${newVoucherMinSpend}`,
      expiresAt: '2026-12-31',
      isActive: true,
      publiclyDisclosable: true
    });
    setNewVoucherCode('');
    setNewVoucherDesc('');
    setIsAddingVoucher(false);
  };

  const handleSaveNewDoc = () => {
    if (!newDocTitle.trim() || !newDocContent.trim()) {
      addToast({ type: 'warning', title: 'Missing Content', message: 'Title and Markdown content are required.' });
      return;
    }
    const tagsArr = newDocTags.split(',').map(t => t.trim().toLowerCase()).filter(Boolean);
    addKnowledgeDoc({
      title: newDocTitle.trim(),
      category: newDocCategory.trim(),
      contentMarkdown: newDocContent.trim(),
      tags: tagsArr.length > 0 ? tagsArr : ['general'],
      isActive: true
    });
    setNewDocTitle('');
    setNewDocContent('');
    setNewDocTags('');
    setIsAddingDoc(false);
  };

  return (
    <div id="ai-knowledge-scope-tab-container" className="space-y-6 animate-in fade-in duration-150">
      {/* 1. FAQ Knowledge Base Section */}
      <section id="faq-knowledge-scope-card" className="bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-xl p-5 shadow-xs">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-neutral-100 dark:border-neutral-800">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 border border-blue-200 dark:border-blue-800 flex items-center justify-center flex-shrink-0">
              <HelpCircle className="w-5 h-5" />
            </div>
            <div>
              <div className="flex items-center gap-2">
                <h2 className="text-sm font-semibold text-neutral-900 dark:text-white">
                  FAQ & Store Policies Knowledge Base
                </h2>
                <span className="px-2 py-0.5 text-[10px] font-bold bg-blue-100 dark:bg-blue-950 text-blue-700 dark:text-blue-300 rounded-full">
                  {faqKnowledge.items.length} Questions Indexed
                </span>
              </div>
            </div>
          </div>

          <div className="flex items-center gap-2.5">
            <button
              onClick={() => {
                updateAIAutoReplyConfig(prev => ({
                  ...prev,
                  knowledgeScope: {
                    ...prev.knowledgeScope,
                    faqKnowledge: {
                      ...prev.knowledgeScope.faqKnowledge,
                      isEnabled: !prev.knowledgeScope.faqKnowledge.isEnabled
                    }
                  }
                }));
              }}
              className={`px-3 py-1.5 text-xs font-semibold rounded-lg border transition-colors flex items-center gap-1.5 ${
                faqKnowledge.isEnabled
                  ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border-emerald-300 dark:border-emerald-800'
                  : 'bg-neutral-100 dark:bg-neutral-800 text-neutral-500 border-neutral-300 dark:border-neutral-700'
              }`}
            >
              {faqKnowledge.isEnabled ? <CheckCircle2 className="w-3.5 h-3.5" /> : <Lock className="w-3.5 h-3.5" />}
              {faqKnowledge.isEnabled ? 'FAQ Scope Active' : 'FAQ Scope Disabled'}
            </button>

            <button
              onClick={() => { closeFaqForm(); setIsAddingFaq(true); }}
              className="px-3 py-1.5 bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 text-xs font-semibold rounded-lg hover:opacity-90 transition-opacity flex items-center gap-1.5 shadow-xs"
            >
              <Plus className="w-3.5 h-3.5" />
              Add FAQ
            </button>
          </div>
        </div>

        {/* Filter & Search Bar */}
        <div className="flex flex-col sm:flex-row items-center justify-between gap-3 pt-4">
          <div className="flex items-center gap-1.5 overflow-x-auto w-full sm:w-auto pb-1 sm:pb-0">
            {[{ id: 'all', label: 'All FAQs', shortLabel: 'All FAQs' }, ...categories].map(category => (
              <div key={category.id} className={`inline-flex items-center shrink-0 rounded-lg overflow-hidden transition-colors ${faqCategoryFilter === category.id ? 'bg-indigo-600 text-white' : 'bg-neutral-100 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-300'}`}>
              <button
                onClick={() => setFaqCategoryFilter(category.id)}
                className="px-2.5 py-1 text-[11px] font-medium capitalize whitespace-nowrap hover:opacity-80 transition-opacity"
              >
                {category.shortLabel}
              </button>
              {category.id !== 'all' && category.id !== 'others' && <button type="button" aria-label={`Delete ${category.shortLabel} category`} onClick={() => setDeletingCategoryId(category.id)} className="pr-2 pl-0.5 py-1 opacity-60 hover:opacity-100 hover:text-rose-500 transition-colors"><Trash2 className="w-3 h-3" /></button>}
              </div>
            ))}
            <button type="button" onClick={() => setIsAddingCategory(!isAddingCategory)} aria-label="Add FAQ Category" className="px-2.5 py-1 text-[11px] font-medium rounded-lg whitespace-nowrap bg-neutral-100 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-300 hover:bg-neutral-200 dark:hover:bg-neutral-700 flex items-center gap-1"><Plus className="w-3.5 h-3.5" />Add Category</button>
          </div>

          <div className="relative w-full sm:w-64">
            <Search className="w-3.5 h-3.5 text-neutral-400 absolute left-2.5 top-2.5" />
            <input
              type="text"
              value={faqSearchQuery}
              onChange={e => setFaqSearchQuery(e.target.value)}
              placeholder="Search FAQ keywords..."
              className="w-full text-xs pl-8 pr-3 py-1.5 bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg text-neutral-900 dark:text-white placeholder-neutral-400 focus:outline-hidden focus:ring-1 focus:ring-indigo-500"
            />
          </div>
        </div>

        {/* FAQ Add Form Modal / Inline Box */}
        {deletingCategory && <div role="dialog" aria-modal="true" aria-labelledby="delete-faq-category-title" className="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4" onKeyDown={e => { if (e.key === 'Escape') setDeletingCategoryId(null); }}>
          <div className="w-full max-w-md bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-xl p-5 shadow-xs">
            <h3 id="delete-faq-category-title" className="text-sm font-semibold text-neutral-900 dark:text-white">Delete “{deletingCategory.shortLabel}”?</h3>
            <p className="mt-3 text-xs text-neutral-600 dark:text-neutral-300">{faqKnowledge.items.filter(item => item.category === deletingCategory.id).length} FAQ(s) will move to Others. FAQ content will be kept.</p>
            <div className="mt-5 flex justify-end gap-2">
              <button type="button" autoFocus onClick={() => setDeletingCategoryId(null)} className="px-3 py-2 text-xs font-semibold rounded-lg bg-neutral-100 dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300">Cancel</button>
              <button type="button" onClick={confirmDeleteCategory} className="px-3 py-2 text-xs font-semibold rounded-lg bg-rose-600 text-white">Delete Category</button>
            </div>
          </div>
        </div>}
        {isAddingCategory && <form onSubmit={e => { e.preventDefault(); handleAddCategory(); }} className="mt-4 p-4 rounded-xl bg-indigo-50/40 dark:bg-indigo-950/20 border border-indigo-200 dark:border-indigo-800 flex flex-wrap items-center gap-2">
          <input aria-label="FAQ Category Name" value={newCategoryName} onChange={e => setNewCategoryName(e.target.value)} maxLength={60} placeholder="Category name" autoFocus className="flex-1 min-w-0 text-xs px-3 py-2 bg-white dark:bg-neutral-800 border border-neutral-300 dark:border-neutral-700 rounded-lg text-neutral-900 dark:text-white" />
          <button type="submit" disabled={!newCategoryName.trim()} className="px-3 py-2 bg-indigo-600 text-white text-xs font-semibold rounded-lg disabled:opacity-50">Add Category</button>
          <button type="button" onClick={() => { setIsAddingCategory(false); setNewCategoryName(''); }} className="px-3 py-2 text-xs text-neutral-500 dark:text-neutral-400">Cancel</button>
        </form>}
        {isAddingFaq && (
          <div className="mt-4 p-4 rounded-xl bg-indigo-50/40 dark:bg-indigo-950/20 border border-indigo-200 dark:border-indigo-800 animate-in slide-in-from-top-2 duration-150">
            <h3 className="text-xs font-bold text-neutral-900 dark:text-white mb-3 flex items-center justify-between">
              <span>{editingFaqId ? 'Edit FAQ' : 'Add New FAQ Knowledge Node'}</span>
              <button
                onClick={closeFaqForm}
                className="text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200"
              >
                ✕
              </button>
            </h3>
            <div className="space-y-3">
              <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div className="sm:col-span-2">
                  <label className="block text-[11px] font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                    Customer Question
                  </label>
                  <input
                    aria-label="FAQ Question" maxLength={1000}
                    type="text"
                    value={newFaqQuestion}
                    onChange={e => setNewFaqQuestion(e.target.value)}
                    placeholder="e.g. Can I pay via Atome 3-month installments?"
                    className="w-full text-xs px-3 py-2 bg-white dark:bg-neutral-800 border border-neutral-300 dark:border-neutral-700 rounded-lg text-neutral-900 dark:text-white"
                  />
                </div>
                <div>
                  <label className="block text-[11px] font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                    Category
                  </label>
                  <select
                    aria-label="FAQ Category"
                    value={newFaqCategory}
                    onChange={e => setNewFaqCategory(e.target.value as any)}
                    className="w-full text-xs px-3 py-2 bg-white dark:bg-neutral-800 border border-neutral-300 dark:border-neutral-700 rounded-lg text-neutral-900 dark:text-white"
                  >
                    {categories.map(category => <option key={category.id} value={category.id}>{category.label}</option>)}
                  </select>
                </div>
              </div>

              <div>
                <label className="block text-[11px] font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                  Official AI Grounded Answer
                </label>
                <textarea
                  aria-label="FAQ Answer" maxLength={10000}
                  rows={3}
                  value={newFaqAnswer}
                  onChange={e => setNewFaqAnswer(e.target.value)}
                  placeholder="Provide the exact factual response the AI will synthesize."
                  className="w-full text-xs p-3 bg-white dark:bg-neutral-800 border border-neutral-300 dark:border-neutral-700 rounded-lg text-neutral-900 dark:text-white"
                />
              </div>

              <div>
                <label className="block text-[11px] font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                  Semantic Keyword Triggers (Comma-separated)
                </label>
                <input
                  type="text"
                  aria-label="FAQ Keywords"
                  value={newFaqKeywords}
                  onChange={e => setNewFaqKeywords(e.target.value)}
                  placeholder="e.g. atome, installment, 0% interest, buy now pay later"
                  className="w-full text-xs px-3 py-2 bg-white dark:bg-neutral-800 border border-neutral-300 dark:border-neutral-700 rounded-lg text-neutral-900 dark:text-white"
                />
              </div>

              <div className="flex items-center justify-end gap-2 pt-2">
                <button
                  onClick={closeFaqForm}
                  className="px-3 py-1.5 text-xs text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800 rounded-lg"
                >
                  Cancel
                </button>
                <button
                  onClick={handleSaveNewFaq}
                  disabled={faqFormSaving}
                  className="px-4 py-1.5 bg-indigo-600 text-white text-xs font-semibold rounded-lg hover:bg-indigo-700"
                >
                  {faqFormSaving ? 'Saving…' : 'Save to Knowledge Base'}
                </button>
              </div>
            </div>
          </div>
        )}

        {/* FAQ List */}
        <div className="mt-4 space-y-3">
          {filteredFaqs.map(faq => (
            <div
              key={faq.id}
              className="p-4 rounded-xl bg-neutral-50/70 dark:bg-neutral-800/40 border border-neutral-200 dark:border-neutral-800 hover:border-neutral-300 dark:hover:border-neutral-700 transition-colors flex flex-col gap-2.5"
            >
              <div className="flex items-start justify-between gap-3">
                <div className="flex items-start gap-2.5">
                  <span className="px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider bg-neutral-200 dark:bg-neutral-700 text-neutral-700 dark:text-neutral-300 mt-0.5">
                    {categories.find(category => category.id === faq.category)?.shortLabel || faq.category}
                  </span>
                  <h4 className="text-xs font-bold text-neutral-900 dark:text-white">
                    {faq.question}
                  </h4>
                </div>

                <div className="flex items-center gap-1.5 flex-shrink-0">
                  <button type="button" aria-label={`Edit FAQ: ${faq.question}`} onClick={() => editFaq(faq)} className="p-1.5 rounded-lg text-neutral-400 hover:text-indigo-600 dark:hover:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-950/40"><Edit2 className="w-3.5 h-3.5" /></button>
                  <button
                    type="button"
                    disabled={faqToggleSaving}
                    aria-label={`${faq.isActive ? 'Deactivate' : 'Activate'} FAQ: ${faq.question}`}
                    onClick={() => void toggleFaqActive(faq)}
                    title={faq.isActive ? 'Active in AI Scope' : 'Disabled'}
                    className={`p-1.5 rounded-lg border text-xs ${
                      faq.isActive
                        ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 border-emerald-200 dark:border-emerald-800'
                        : 'bg-neutral-100 dark:bg-neutral-800 text-neutral-400 border-neutral-200 dark:border-neutral-700'
                    }`}
                  >
                    {faq.isActive ? <Eye className="w-3.5 h-3.5" /> : <EyeOff className="w-3.5 h-3.5" />}
                  </button>
                  <button
                    onClick={() => deleteFAQItem(faq.id)}
                    className="p-1.5 rounded-lg text-neutral-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition-colors"
                  >
                    <Trash2 className="w-3.5 h-3.5" />
                  </button>
                </div>
              </div>

              <p className="text-xs text-neutral-600 dark:text-neutral-300 leading-relaxed pl-1">
                {faq.answer}
              </p>

              <div className="flex flex-wrap items-center gap-1.5 pt-1 border-t border-neutral-100 dark:border-neutral-800/80">
                <span className="text-[10px] text-neutral-400 font-medium mr-1">Triggers:</span>
                {faq.keywords.map((kw, i) => (
                  <span
                    key={i}
                    className="text-[10px] px-2 py-0.5 rounded-md bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 text-neutral-600 dark:text-neutral-400"
                  >
                    {kw}
                  </span>
                ))}
              </div>
            </div>
          ))}
        </div>
      </section>

      <fieldset disabled className="space-y-6"><p className="text-xs text-neutral-500">Order, product, voucher and document sources: Not connected</p>
      {/* 2. Order Lookup & OMS Scope Access Boundaries */}
      <section id="order-lookup-scope-card" className="bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-xl p-5 shadow-xs">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-neutral-100 dark:border-neutral-800">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 border border-amber-200 dark:border-amber-800 flex items-center justify-center flex-shrink-0">
              <Package className="w-5 h-5" />
            </div>
            <div>
              <h2 className="text-sm font-semibold text-neutral-900 dark:text-white">
                Order Tracking & OMS Access Boundaries (订单询问权限)
              </h2>
            </div>
          </div>

          <button
            onClick={() => {
              updateAIAutoReplyConfig(prev => ({
                ...prev,
                knowledgeScope: {
                  ...prev.knowledgeScope,
                  orderLookup: {
                    ...prev.knowledgeScope.orderLookup,
                    isEnabled: !prev.knowledgeScope.orderLookup.isEnabled
                  }
                }
              }));
            }}
            className={`px-3 py-1.5 text-xs font-semibold rounded-lg border transition-colors flex items-center gap-1.5 ${
              orderLookup.isEnabled
                ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border-emerald-300 dark:border-emerald-800'
                : 'bg-neutral-100 dark:bg-neutral-800 text-neutral-500 border-neutral-300 dark:border-neutral-700'
            }`}
          >
            {orderLookup.isEnabled ? <CheckCircle2 className="w-3.5 h-3.5" /> : <Lock className="w-3.5 h-3.5" />}
            Not connected
          </button>
        </div>

        <div className="space-y-4 pt-5">
          {/* Security Gate Verification */}
          <div className="p-4 rounded-xl bg-amber-50/40 dark:bg-amber-950/20 border border-amber-200/60 dark:border-amber-800/60 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
              <div className="flex items-center gap-2">
                <ShieldCheck className="w-4 h-4 text-amber-600 dark:text-amber-400" />
                <span className="text-xs font-bold text-neutral-900 dark:text-white">Security Verification Gate</span>
              </div>
            </div>

            <div className="flex items-center gap-3">
              <select
                value={orderLookup.verificationMethod}
                onChange={e => {
                  updateAIAutoReplyConfig(prev => ({
                    ...prev,
                    knowledgeScope: {
                      ...prev.knowledgeScope,
                      orderLookup: {
                        ...prev.knowledgeScope.orderLookup,
                        verificationMethod: e.target.value as any
                      }
                    }
                  }));
                }}
                className="text-xs px-3 py-1.5 bg-white dark:bg-neutral-800 border border-neutral-300 dark:border-neutral-700 rounded-lg text-neutral-900 dark:text-white"
              >
                <option value="phone_last4">Match Last 4 Digits of Phone (Recommended)</option>
                <option value="full_phone">Match Full Phone Number</option>
                <option value="email_match">Match Customer Email Address</option>
              </select>
            </div>
          </div>

          {/* Allowed Fields Checkboxes */}
          <div>
            <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-2">
              Allowed Field Disclosures in Chat Response
            </label>
            <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
              {[
                { key: 'orderStatus', label: 'Order Status (e.g. Shipped / Packing)' },
                { key: 'trackingNumberAndCourier', label: 'Courier & AWB Tracking Number' },
                { key: 'estimatedDeliveryDate', label: 'Estimated Delivery Date (EDD)' },
                { key: 'itemSummary', label: 'Purchased Item & Variant List' },
                { key: 'shippingAddressMasked', label: 'Masked Shipping Address (Privacy)' },
                { key: 'paymentStatus', label: 'Payment Method & Paid Status' }
              ].map(field => {
                const isChecked = (orderLookup.allowedFields as any)[field.key];
                return (
                  <label
                    key={field.key}
                    className="p-3 rounded-lg bg-neutral-50 dark:bg-neutral-800/50 border border-neutral-200 dark:border-neutral-700 flex items-center gap-2.5 cursor-pointer text-xs"
                  >
                    <input
                      type="checkbox"
                      checked={isChecked}
                      onChange={e => {
                        updateAIAutoReplyConfig(prev => ({
                          ...prev,
                          knowledgeScope: {
                            ...prev.knowledgeScope,
                            orderLookup: {
                              ...prev.knowledgeScope.orderLookup,
                              allowedFields: {
                                ...prev.knowledgeScope.orderLookup.allowedFields,
                                [field.key]: e.target.checked
                              }
                            }
                          }
                        }));
                      }}
                      className="rounded-sm text-indigo-600"
                    />
                    <span className="text-neutral-800 dark:text-neutral-200">{field.label}</span>
                  </label>
                );
              })}
            </div>
          </div>
        </div>
      </section>

      {/* 3. Product Catalog & Live Stock Boundaries */}
      <section id="product-catalog-scope-card" className="bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-xl p-5 shadow-xs">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-neutral-100 dark:border-neutral-800">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400 border border-purple-200 dark:border-purple-800 flex items-center justify-center flex-shrink-0">
              <ShoppingBag className="w-5 h-5" />
            </div>
            <div>
              <h2 className="text-sm font-semibold text-neutral-900 dark:text-white">
                Product Matrix & Live Inventory Scope (产品询问与库存)
              </h2>
            </div>
          </div>

          <button
            onClick={() => {
              updateAIAutoReplyConfig(prev => ({
                ...prev,
                knowledgeScope: {
                  ...prev.knowledgeScope,
                  productCatalog: {
                    ...prev.knowledgeScope.productCatalog,
                    isEnabled: !prev.knowledgeScope.productCatalog.isEnabled
                  }
                }
              }));
            }}
            className={`px-3 py-1.5 text-xs font-semibold rounded-lg border transition-colors flex items-center gap-1.5 ${
              productCatalog.isEnabled
                ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border-emerald-300 dark:border-emerald-800'
                : 'bg-neutral-100 dark:bg-neutral-800 text-neutral-500 border-neutral-300 dark:border-neutral-700'
            }`}
          >
            {productCatalog.isEnabled ? <CheckCircle2 className="w-3.5 h-3.5" /> : <Lock className="w-3.5 h-3.5" />}
            Not connected
          </button>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-4 pt-5">
          <div className="p-4 rounded-xl bg-neutral-50 dark:bg-neutral-800/50 border border-neutral-200 dark:border-neutral-700 flex items-start gap-3">
            <input
              type="checkbox"
              id="check-live-stock"
              checked={productCatalog.canCheckLiveStock}
              onChange={e => {
                updateAIAutoReplyConfig(prev => ({
                  ...prev,
                  knowledgeScope: {
                    ...prev.knowledgeScope,
                    productCatalog: {
                      ...prev.knowledgeScope.productCatalog,
                      canCheckLiveStock: e.target.checked
                    }
                  }
                }));
              }}
              className="mt-0.5 rounded-sm text-indigo-600"
            />
            <label htmlFor="check-live-stock" className="cursor-pointer">
              <span className="text-xs font-bold text-neutral-900 dark:text-white block">Real-time Stock & Size Availability</span>
            </label>
          </div>

          <div className="p-4 rounded-xl bg-neutral-50 dark:bg-neutral-800/50 border border-neutral-200 dark:border-neutral-700 flex items-start gap-3">
            <input
              type="checkbox"
              id="check-upsell"
              checked={productCatalog.canRecommendUpsells}
              onChange={e => {
                updateAIAutoReplyConfig(prev => ({
                  ...prev,
                  knowledgeScope: {
                    ...prev.knowledgeScope,
                    productCatalog: {
                      ...prev.knowledgeScope.productCatalog,
                      canRecommendUpsells: e.target.checked
                    }
                  }
                }));
              }}
              className="mt-0.5 rounded-sm text-indigo-600"
            />
            <label htmlFor="check-upsell" className="cursor-pointer">
              <span className="text-xs font-bold text-neutral-900 dark:text-white block">Smart Upsell & Style Pairing</span>
            </label>
          </div>

          <div className="p-4 rounded-xl bg-neutral-50 dark:bg-neutral-800/50 border border-neutral-200 dark:border-neutral-700 flex items-start gap-3">
            <input
              type="checkbox"
              id="check-materials"
              checked={productCatalog.canExplainSizingAndMaterials}
              onChange={e => {
                updateAIAutoReplyConfig(prev => ({
                  ...prev,
                  knowledgeScope: {
                    ...prev.knowledgeScope,
                    productCatalog: {
                      ...prev.knowledgeScope.productCatalog,
                      canExplainSizingAndMaterials: e.target.checked
                    }
                  }
                }));
              }}
              className="mt-0.5 rounded-sm text-indigo-600"
            />
            <label htmlFor="check-materials" className="cursor-pointer">
              <span className="text-xs font-bold text-neutral-900 dark:text-white block">Heavyweight Cotton & Fit Guide Explainer</span>
            </label>
          </div>

          <div className="p-4 rounded-xl bg-neutral-50 dark:bg-neutral-800/50 border border-neutral-200 dark:border-neutral-700 flex items-start gap-3">
            <div className="flex-1">
              <div className="flex items-center justify-between text-xs font-bold text-neutral-900 dark:text-white mb-1">
                <span>Max Product Recommendations</span>
                <span className="font-mono text-indigo-600 dark:text-indigo-400 font-bold">{productCatalog.maxRecommendations} items</span>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* 4. Promotions, Vouchers & Discounts Scope */}
      <section id="promotions-scope-card" className="bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-xl p-5 shadow-xs">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-neutral-100 dark:border-neutral-800">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800 flex items-center justify-center flex-shrink-0">
              <Tag className="w-5 h-5" />
            </div>
            <div>
              <div className="flex items-center gap-2">
                <h2 className="text-sm font-semibold text-neutral-900 dark:text-white">
                  Promotions, Vouchers & Discounts Scope (优惠券询问与规则)
                </h2>
                <span className="px-2 py-0.5 text-[10px] font-bold bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300 rounded-full">
                  Not connected
                </span>
              </div>
            </div>
          </div>

          <div className="flex items-center gap-2.5">
            <button
              onClick={() => setIsAddingVoucher(true)}
              className="px-3 py-1.5 bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 text-xs font-semibold rounded-lg hover:opacity-90 transition-opacity flex items-center gap-1.5 shadow-xs"
            >
              <Plus className="w-3.5 h-3.5" />
              Add Voucher
            </button>
          </div>
        </div>

        {/* Voucher Add Modal */}
        {isAddingVoucher && (
          <div className="mt-4 p-4 rounded-xl bg-emerald-50/40 dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-800 animate-in slide-in-from-top-2 duration-150">
            <h3 className="text-xs font-bold text-neutral-900 dark:text-white mb-3 flex items-center justify-between">
              <span>Register Active Campaign Voucher</span>
              <button
                onClick={() => setIsAddingVoucher(false)}
                className="text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200"
              >
                ✕
              </button>
            </h3>
            <div className="grid grid-cols-1 sm:grid-cols-4 gap-3">
              <div>
                <label className="block text-[11px] font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                  Voucher Code
                </label>
                <input
                  type="text"
                  value={newVoucherCode}
                  onChange={e => setNewVoucherCode(e.target.value)}
                  placeholder="e.g. MERDEKA67"
                  className="w-full text-xs px-3 py-2 uppercase font-mono bg-white dark:bg-neutral-800 border border-neutral-300 dark:border-neutral-700 rounded-lg text-neutral-900 dark:text-white"
                />
              </div>

              <div>
                <label className="block text-[11px] font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                  Discount Type
                </label>
                <select
                  value={newVoucherType}
                  onChange={e => setNewVoucherType(e.target.value as any)}
                  className="w-full text-xs px-3 py-2 bg-white dark:bg-neutral-800 border border-neutral-300 dark:border-neutral-700 rounded-lg text-neutral-900 dark:text-white"
                >
                  <option value="percentage">Percentage OFF (%)</option>
                  <option value="fixed_myr">Fixed Rebate (RM)</option>
                  <option value="free_shipping">Free Shipping Waiver</option>
                </select>
              </div>

              <div>
                <label className="block text-[11px] font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                  Discount Value
                </label>
                <input
                  type="number"
                  value={newVoucherValue}
                  onChange={e => setNewVoucherValue(Number(e.target.value))}
                  className="w-full text-xs px-3 py-2 bg-white dark:bg-neutral-800 border border-neutral-300 dark:border-neutral-700 rounded-lg text-neutral-900 dark:text-white"
                />
              </div>

              <div>
                <label className="block text-[11px] font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                  Min Spend (RM)
                </label>
                <input
                  type="number"
                  value={newVoucherMinSpend}
                  onChange={e => setNewVoucherMinSpend(Number(e.target.value))}
                  className="w-full text-xs px-3 py-2 bg-white dark:bg-neutral-800 border border-neutral-300 dark:border-neutral-700 rounded-lg text-neutral-900 dark:text-white"
                />
              </div>

              <div className="sm:col-span-4">
                <label className="block text-[11px] font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                  Public Promotion Description
                </label>
                <input
                  type="text"
                  value={newVoucherDesc}
                  onChange={e => setNewVoucherDesc(e.target.value)}
                  placeholder="e.g. 15% OFF Merdeka Special on orders over RM 200"
                  className="w-full text-xs px-3 py-2 bg-white dark:bg-neutral-800 border border-neutral-300 dark:border-neutral-700 rounded-lg text-neutral-900 dark:text-white"
                />
              </div>
            </div>

            <div className="flex items-center justify-end gap-2 pt-3">
              <button
                onClick={() => setIsAddingVoucher(false)}
                className="px-3 py-1.5 text-xs text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800 rounded-lg"
              >
                Cancel
              </button>
              <button
                onClick={handleSaveNewVoucher}
                className="px-4 py-1.5 bg-emerald-600 text-white text-xs font-semibold rounded-lg hover:bg-emerald-700"
              >
                Register Voucher
              </button>
            </div>
          </div>
        )}

        {/* Voucher Cards Grid */}
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3.5 pt-4">
          {promotionsAndDiscounts.vouchers.map(v => (
            <div
              key={v.id}
              className="p-3.5 rounded-xl border border-emerald-200/70 dark:border-emerald-800/60 bg-emerald-50/30 dark:bg-emerald-950/20 flex flex-col justify-between"
            >
              <div>
                <div className="flex items-center justify-between gap-2 mb-2">
                  <span className="font-mono text-xs font-bold text-emerald-800 dark:text-emerald-300 px-2 py-0.5 bg-emerald-100 dark:bg-emerald-900/60 rounded-md border border-emerald-300 dark:border-emerald-700">
                    {v.code}
                  </span>
                  <button
                    onClick={() => deleteVoucherRule(v.id)}
                    className="text-neutral-400 hover:text-rose-600 transition-colors p-1"
                  >
                    <Trash2 className="w-3.5 h-3.5" />
                  </button>
                </div>
                <p className="text-xs font-semibold text-neutral-900 dark:text-white mb-1">
                  {v.description}
                </p>
                <div className="text-[11px] text-neutral-500 space-y-0.5">
                  <div>Min Spend: <span className="font-medium text-neutral-700 dark:text-neutral-300">RM {v.minSpend}</span></div>
                  <div>Expires: <span className="font-mono">{v.expiresAt}</span></div>
                </div>
              </div>

              <div className="mt-3 pt-2 border-t border-emerald-100 dark:border-emerald-900/40 flex items-center justify-between text-[10px] text-emerald-700 dark:text-emerald-400">
                <span>AI Auto-Disclose</span>
                <span className="font-bold">Not connected</span>
              </div>
            </div>
          ))}
        </div>
      </section>

      {/* 5. Custom Knowledge Docs Section */}
      <section id="custom-docs-scope-card" className="bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-xl p-5 shadow-xs">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-neutral-100 dark:border-neutral-800">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800 flex items-center justify-center flex-shrink-0">
              <FileText className="w-5 h-5" />
            </div>
            <div>
              <h2 className="text-sm font-semibold text-neutral-900 dark:text-white">
                Custom Knowledge Documents & Sizing Manuals
              </h2>
            </div>
          </div>

          <button
            onClick={() => setIsAddingDoc(true)}
            className="px-3 py-1.5 bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 text-xs font-semibold rounded-lg hover:opacity-90 transition-opacity flex items-center gap-1.5 shadow-xs"
          >
            <Plus className="w-3.5 h-3.5" />
            Add Document
          </button>
        </div>

        {/* Add Doc Form */}
        {isAddingDoc && (
          <div className="mt-4 p-4 rounded-xl bg-indigo-50/40 dark:bg-indigo-950/20 border border-indigo-200 dark:border-indigo-800 animate-in slide-in-from-top-2 duration-150">
            <h3 className="text-xs font-bold text-neutral-900 dark:text-white mb-3 flex items-center justify-between">
              <span>Embed New Policy / Care Manual Document</span>
              <button
                onClick={() => setIsAddingDoc(false)}
                className="text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200"
              >
                ✕
              </button>
            </h3>
            <div className="space-y-3">
              <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div className="sm:col-span-2">
                  <label className="block text-[11px] font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                    Document Title
                  </label>
                  <input
                    type="text"
                    value={newDocTitle}
                    onChange={e => setNewDocTitle(e.target.value)}
                    placeholder="e.g. Heavyweight Cotton Garment Care & Washing Guide"
                    className="w-full text-xs px-3 py-2 bg-white dark:bg-neutral-800 border border-neutral-300 dark:border-neutral-700 rounded-lg text-neutral-900 dark:text-white"
                  />
                </div>
                <div>
                  <label className="block text-[11px] font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                    Category
                  </label>
                  <input
                    type="text"
                    value={newDocCategory}
                    onChange={e => setNewDocCategory(e.target.value)}
                    placeholder="e.g. Product Care"
                    className="w-full text-xs px-3 py-2 bg-white dark:bg-neutral-800 border border-neutral-300 dark:border-neutral-700 rounded-lg text-neutral-900 dark:text-white"
                  />
                </div>
              </div>

              <div>
                <label className="block text-[11px] font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                  Markdown Content
                </label>
                <textarea
                  rows={4}
                  value={newDocContent}
                  onChange={e => setNewDocContent(e.target.value)}
                  placeholder="### Care Guidelines&#10;- Wash inside out with cold water below 30°C&#10;- Hang dry in shade"
                  className="w-full font-mono text-xs p-3 bg-white dark:bg-neutral-800 border border-neutral-300 dark:border-neutral-700 rounded-lg text-neutral-900 dark:text-white"
                />
              </div>

              <div>
                <label className="block text-[11px] font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                  Tags (Comma-separated)
                </label>
                <input
                  type="text"
                  value={newDocTags}
                  onChange={e => setNewDocTags(e.target.value)}
                  placeholder="e.g. washing, cotton care, shrinkage, dry flat"
                  className="w-full text-xs px-3 py-2 bg-white dark:bg-neutral-800 border border-neutral-300 dark:border-neutral-700 rounded-lg text-neutral-900 dark:text-white"
                />
              </div>

              <div className="flex items-center justify-end gap-2 pt-2">
                <button
                  onClick={() => setIsAddingDoc(false)}
                  className="px-3 py-1.5 text-xs text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800 rounded-lg"
                >
                  Cancel
                </button>
                <button
                  onClick={handleSaveNewDoc}
                  className="px-4 py-1.5 bg-indigo-600 text-white text-xs font-semibold rounded-lg hover:bg-indigo-700"
                >
                  Embed Document
                </button>
              </div>
            </div>
          </div>
        )}

        {/* Doc List */}
        <div className="mt-4 space-y-3">
          {customKnowledgeDocs.map(doc => (
            <div
              key={doc.id}
              className="p-4 rounded-xl bg-neutral-50/70 dark:bg-neutral-800/40 border border-neutral-200 dark:border-neutral-800 flex flex-col gap-2"
            >
              <div className="flex items-center justify-between gap-3">
                <div className="flex items-center gap-2">
                  <span className="px-2 py-0.5 rounded text-[10px] font-bold bg-neutral-200 dark:bg-neutral-700 text-neutral-700 dark:text-neutral-300">
                    {doc.category}
                  </span>
                  <h4 className="text-xs font-bold text-neutral-900 dark:text-white">
                    {doc.title}
                  </h4>
                </div>

                <button
                  onClick={() => deleteKnowledgeDoc(doc.id)}
                  className="text-neutral-400 hover:text-rose-600 transition-colors p-1"
                >
                  <Trash2 className="w-3.5 h-3.5" />
                </button>
              </div>

              <div className="bg-white dark:bg-neutral-900 p-3 rounded-lg border border-neutral-200 dark:border-neutral-700 text-[11px] font-mono text-neutral-700 dark:text-neutral-300 whitespace-pre-wrap">
                {doc.contentMarkdown}
              </div>

              <div className="flex items-center justify-between text-[10px] text-neutral-400 pt-1">
                <div className="flex items-center gap-1.5">
                  <span>Tags:</span>
                  {doc.tags.map((t, i) => (
                    <span key={i} className="px-1.5 py-0.5 rounded bg-neutral-200 dark:bg-neutral-700 text-neutral-700 dark:text-neutral-300">
                      #{t}
                    </span>
                  ))}
                </div>
                <span>{doc.characterCount} chars • Updated {doc.lastUpdatedAt}</span>
              </div>
            </div>
          ))}
        </div>
      </section>
      </fieldset>
    </div>
  );
};
