import { expect, it } from 'vitest';
import { faqCategories, removeFAQCategory } from './faqCategories';
import { DEFAULT_AI_AUTO_REPLY_CONFIG } from './defaultAIAutoReply';

it('moves affected FAQs to Others without deleting content or mutating the original', () => {
  const original = { ...DEFAULT_AI_AUTO_REPLY_CONFIG.knowledgeScope.faqKnowledge, items: [{ id: "one", category: "shipping", question: "Shipping?", answer: "An answer", keywords: [], isActive: true, lastUpdatedAt: "2026-10-03" }] };
  const result = removeFAQCategory(original, 'shipping');
  expect(result.items).toHaveLength(original.items.length);
  expect(result.items.find(item => item.id === original.items[0].id)).toEqual({ ...original.items[0], category: 'others' });
  expect(original.items[0].category).toBe('shipping');
  expect(faqCategories(result.categories, result.items, result.deletedCategoryIds).some(c => c.id === 'shipping')).toBe(false);
  expect(faqCategories(result.categories, result.items, result.deletedCategoryIds).some(c => c.id === 'others')).toBe(true);
});

it('keeps deleted categories absent after serialization and protects Others', () => {
  const original = { ...DEFAULT_AI_AUTO_REPLY_CONFIG.knowledgeScope.faqKnowledge, categories: [{ id: 'care', label: 'Care' }] };
  const restored = JSON.parse(JSON.stringify(removeFAQCategory(original, 'care')));
  expect(faqCategories(restored.categories, restored.items, restored.deletedCategoryIds).some(c => c.id === 'care')).toBe(false);
  expect(removeFAQCategory(original, 'others')).toBe(original);
});

it('uses saved labels without injecting default categories', () => {
  const result = faqCategories([{ id: 'shipping', label: 'Delivery Policy' }], []);
  expect(result).toEqual([{ id: 'others', label: 'Others', shortLabel: 'Others' }, { id: 'shipping', label: 'Delivery Policy', shortLabel: 'Delivery Policy' }]);
  expect(faqCategories([], []).map(c => c.id)).toEqual(['others']);
  expect(faqCategories(undefined, []).find(c => c.id === 'shipping')?.shortLabel).toBe('Shipping & Postage');
});
