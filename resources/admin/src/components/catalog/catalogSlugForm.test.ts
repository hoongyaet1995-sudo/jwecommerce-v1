import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';

const categoryEditor = readFileSync(fileURLToPath(new URL('./CategoryAdmin.tsx', import.meta.url)), 'utf8');
const collectionEditor = readFileSync(fileURLToPath(new URL('./CollectionAdmin.tsx', import.meta.url)), 'utf8');
const productEditor = readFileSync(fileURLToPath(new URL('./ProductEditorModal.tsx', import.meta.url)), 'utf8');

describe('catalog URL slug form contract', () => {
  it('uses the same non-editable public path prefixes and URL Slug naming in every catalog editor', () => {
    expect(categoryEditor).toContain('/categories/');
    expect(collectionEditor).toContain('/collections/');
    expect(productEditor).toContain('URL Slug');
    expect(productEditor).not.toContain('URL Key');
  });

  it('allows an empty category or collection slug so the server can allocate it', () => {
    expect(categoryEditor).toContain('Leave blank to generate a unique URL slug from the category name.');
    expect(collectionEditor).toContain('Leave blank to generate a unique URL slug from the collection title.');
    expect(categoryEditor).not.toMatch(/placeholder="smart-wearables"\s+value=\{editingCategory\.slug\}\s+onChange=\{[^}]+\}\s+required/);
    expect(collectionEditor).not.toMatch(/placeholder="summer-tech-essentials"\s+value=\{editingCollection\.slug\}\s+onChange=\{[^}]+\}\s+required/);
  });
});
