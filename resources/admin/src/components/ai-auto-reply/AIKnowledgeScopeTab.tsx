import React, { useState } from 'react';
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

export const AIKnowledgeScopeTab: React.FC = () => {
  const {
    aiAutoReplyConfig,
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

  // New FAQ Form
  const [newFaqQuestion, setNewFaqQuestion] = useState('');
  const [newFaqAnswer, setNewFaqAnswer] = useState('');
  const [newFaqCategory, setNewFaqCategory] = useState<FAQKnowledgeItem['category']>('shipping');
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

  const handleSaveNewFaq = () => {
    if (!newFaqQuestion.trim() || !newFaqAnswer.trim()) {
      addToast({ type: 'warning', title: 'Missing Info', message: 'Question and Answer are required.' });
      return;
    }
    const keywordsArr = newFaqKeywords.split(',').map(k => k.trim().toLowerCase()).filter(Boolean);
    addFAQItem({
      category: newFaqCategory,
      question: newFaqQuestion.trim(),
      answer: newFaqAnswer.trim(),
      keywords: keywordsArr.length > 0 ? keywordsArr : [newFaqCategory],
      isActive: true
    });
    setNewFaqQuestion('');
    setNewFaqAnswer('');
    setNewFaqKeywords('');
    setIsAddingFaq(false);
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
              <p className="text-xs text-neutral-500 dark:text-neutral-400">
                Grounds the AI with official answers for shipping rates, returns, sizing charts, and payment methods.
              </p>
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
              onClick={() => setIsAddingFaq(true)}
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
            {['all', 'shipping', 'returns', 'sizing', 'payment', 'store_info'].map(cat => (
              <button
                key={cat}
                onClick={() => setFaqCategoryFilter(cat)}
                className={`px-2.5 py-1 text-[11px] font-medium rounded-lg capitalize whitespace-nowrap transition-colors ${
                  faqCategoryFilter === cat
                    ? 'bg-indigo-600 text-white'
                    : 'bg-neutral-100 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-300 hover:bg-neutral-200 dark:hover:bg-neutral-700'
                }`}
              >
                {cat === 'all' ? 'All FAQs' : cat.replace('_', ' ')}
              </button>
            ))}
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
        {isAddingFaq && (
          <div className="mt-4 p-4 rounded-xl bg-indigo-50/40 dark:bg-indigo-950/20 border border-indigo-200 dark:border-indigo-800 animate-in slide-in-from-top-2 duration-150">
            <h3 className="text-xs font-bold text-neutral-900 dark:text-white mb-3 flex items-center justify-between">
              <span>Add New FAQ Knowledge Node</span>
              <button
                onClick={() => setIsAddingFaq(false)}
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
                    value={newFaqCategory}
                    onChange={e => setNewFaqCategory(e.target.value as any)}
                    className="w-full text-xs px-3 py-2 bg-white dark:bg-neutral-800 border border-neutral-300 dark:border-neutral-700 rounded-lg text-neutral-900 dark:text-white"
                  >
                    <option value="shipping">Shipping & Postage</option>
                    <option value="returns">Returns & Exchanges</option>
                    <option value="sizing">Sizing & Measurement</option>
                    <option value="payment">Payment Methods</option>
                    <option value="store_info">Store Info & Location</option>
                    <option value="custom">Custom Policy</option>
                  </select>
                </div>
              </div>

              <div>
                <label className="block text-[11px] font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                  Official AI Grounded Answer
                </label>
                <textarea
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
                  value={newFaqKeywords}
                  onChange={e => setNewFaqKeywords(e.target.value)}
                  placeholder="e.g. atome, installment, 0% interest, buy now pay later"
                  className="w-full text-xs px-3 py-2 bg-white dark:bg-neutral-800 border border-neutral-300 dark:border-neutral-700 rounded-lg text-neutral-900 dark:text-white"
                />
              </div>

              <div className="flex items-center justify-end gap-2 pt-2">
                <button
                  onClick={() => setIsAddingFaq(false)}
                  className="px-3 py-1.5 text-xs text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800 rounded-lg"
                >
                  Cancel
                </button>
                <button
                  onClick={handleSaveNewFaq}
                  className="px-4 py-1.5 bg-indigo-600 text-white text-xs font-semibold rounded-lg hover:bg-indigo-700"
                >
                  Save to Knowledge Base
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
                    {faq.category}
                  </span>
                  <h4 className="text-xs font-bold text-neutral-900 dark:text-white">
                    {faq.question}
                  </h4>
                </div>

                <div className="flex items-center gap-1.5 flex-shrink-0">
                  <button
                    onClick={() => updateFAQItem(faq.id, { isActive: !faq.isActive })}
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
              <p className="text-xs text-neutral-500 dark:text-neutral-400">
                Control how the AI queries real customer order records and prevents sensitive address leakage.
              </p>
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
            {orderLookup.isEnabled ? 'Order Query Active' : 'Order Query Disabled'}
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
              <p className="text-[11px] text-neutral-600 dark:text-neutral-400 mt-1">
                Before providing tracking details or order summary, customer must verify their identity.
              </p>
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
              <p className="text-xs text-neutral-500 dark:text-neutral-400">
                Enables AI to query SKU inventory, fabric GSM weights, and recommend matching streetwear pieces.
              </p>
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
            {productCatalog.isEnabled ? 'Catalog Active' : 'Catalog Disabled'}
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
              <span className="text-[11px] text-neutral-500 dark:text-neutral-400 block mt-0.5">
                AI checks warehouse Available-to-Promise (ATP) stock before confirming size in-stock.
              </span>
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
              <span className="text-[11px] text-neutral-500 dark:text-neutral-400 block mt-0.5">
                Suggests matching cargo pants or caps when customer asks about a graphic t-shirt.
              </span>
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
              <span className="text-[11px] text-neutral-500 dark:text-neutral-400 block mt-0.5">
                Explains 280GSM fabric weight, drop-shoulder tailoring, and recommends size by height/weight.
              </span>
            </label>
          </div>

          <div className="p-4 rounded-xl bg-neutral-50 dark:bg-neutral-800/50 border border-neutral-200 dark:border-neutral-700 flex items-start gap-3">
            <div className="flex-1">
              <div className="flex items-center justify-between text-xs font-bold text-neutral-900 dark:text-white mb-1">
                <span>Max Product Recommendations</span>
                <span className="font-mono text-indigo-600 dark:text-indigo-400 font-bold">{productCatalog.maxRecommendations} items</span>
              </div>
              <p className="text-[11px] text-neutral-500 dark:text-neutral-400">
                Limits the number of product cards shown per reply to prevent chat screen clutter.
              </p>
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
                  {promotionsAndDiscounts.vouchers.length} Active Codes
                </span>
              </div>
              <p className="text-xs text-neutral-500 dark:text-neutral-400">
                Allow AI to provide legitimate campaign vouchers while strictly prohibiting custom price bargaining.
              </p>
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
                <span className="font-bold">Active</span>
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
              <p className="text-xs text-neutral-500 dark:text-neutral-400">
                Upload or edit markdown policy documents (e.g. 280GSM cotton care, wholesale B2B guidelines).
              </p>
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
    </div>
  );
};
