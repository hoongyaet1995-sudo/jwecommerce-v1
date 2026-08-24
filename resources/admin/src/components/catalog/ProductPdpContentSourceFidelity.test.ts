import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';

const editor = readFileSync(fileURLToPath(new URL('./ProductEditorModal.tsx', import.meta.url)), 'utf8');
const contentEditor = readFileSync(fileURLToPath(new URL('./ProductExtraInfoEditor.tsx', import.meta.url)), 'utf8');

describe('Product PDP content editor source fidelity', () => {
  it('keeps the ZIP-owned Tab 4 hierarchy and defers storefront consumption', () => {
    expect(editor).toContain('4. PDP Rich Content & Specs');
    expect(editor).toContain('5. SEO & Social Meta');
    expect(editor).toContain('<ProductExtraInfoEditor');
    expect(contentEditor).toContain('Signature Craftsmanship Highlights');
    expect(contentEditor).toContain('Technical Specifications Matrix');
    expect(contentEditor).toContain('Shipping & 30-Day Return Guarantee');
    expect(contentEditor).toContain('Custom Extensible Accordions');
    expect(contentEditor).toContain('Frequently Paired With This Model');
    expect(contentEditor).toContain('Buyer Reviews & PDP Social Proof Summary');
    expect(contentEditor).toContain('GripVertical');
    expect(contentEditor).toContain('HelpCircle');
    expect(contentEditor).toContain('Award');
    expect((contentEditor.match(/RefreshCw/g) ?? []).length).toBeGreaterThanOrEqual(4);
    expect(contentEditor).toContain('Select 1 to 4 complementary products');
    expect(contentEditor).not.toContain("from '../../storefront");
  });
});
