import type { AIKnowledgeScopeConfig } from '../types/aiAutoReply';

export const DEFAULT_FAQ_CATEGORIES = [
  { id: 'others', label: 'Others', shortLabel: 'Others' },
  { id: 'shipping', label: 'Shipping & Postage', shortLabel: 'Shipping' },
  { id: 'returns', label: 'Returns & Exchanges', shortLabel: 'Returns' },
  { id: 'sizing', label: 'Sizing & Measurement', shortLabel: 'Sizing' },
  { id: 'payment', label: 'Payment Methods', shortLabel: 'Payment' },
  { id: 'store_info', label: 'Store Info & Location', shortLabel: 'Store Info' },
  { id: 'custom', label: 'Custom Policy', shortLabel: 'Custom Policy' },
];

export function faqCategories(saved: unknown, items: { category: string }[], deleted: string[] = []) {
  const categories = DEFAULT_FAQ_CATEGORIES.filter(c => c.id === 'others' || !deleted.includes(c.id));
  if (Array.isArray(saved)) for (const category of saved) {
    if (category && typeof category.id === 'string' && category.id !== 'all' && !deleted.includes(category.id) && typeof category.label === 'string' && category.label.trim() && !categories.some(c => c.id === category.id)) {
      categories.push({ id: category.id, label: category.label.trim(), shortLabel: category.label.trim() });
    }
  }
  for (const item of items) if (!deleted.includes(item.category) && !categories.some(c => c.id === item.category)) {
    categories.push({ id: item.category, label: item.category, shortLabel: item.category });
  }
  return categories;
}

export function removeFAQCategory(knowledge: AIKnowledgeScopeConfig['faqKnowledge'], id: string) {
  if (id === 'others' || id === 'all') return knowledge;
  return {
    ...knowledge,
    categories: (knowledge.categories || []).filter(category => category.id !== id),
    deletedCategoryIds: [...new Set([...(knowledge.deletedCategoryIds || []), id])],
    items: knowledge.items.map(item => item.category === id ? { ...item, category: 'others' } : item),
  };
}
