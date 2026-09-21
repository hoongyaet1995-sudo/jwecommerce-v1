import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it } from 'vitest';
import type { ProductVariant } from '../../types';
import { VariantStockControl } from './ProductEditorModal';

const item = (overrides: Partial<ProductVariant> = {}): ProductVariant => ({
  id: '8', sku: 'SKU-8', title: 'Default', options: {}, price: 10,
  inventoryQuantity: 0, enabled: true, ...overrides,
});

describe('Product Variant inventory ownership', () => {
  it('keeps quantity editable before First Count', () => {
    const html = renderToStaticMarkup(<VariantStockControl variant={item({ inventoryEditable: true })} onChange={() => undefined} />);
    expect(html).toContain('type="number"');
    expect(html).not.toContain('Edit in Inventory Management');
  });

  it('shows verified zero as read-only with the inventory CTA', () => {
    const html = renderToStaticMarkup(<VariantStockControl variant={item({ inventoryVerified: true, inventoryEditable: false })} onChange={() => undefined} />);
    expect(html).not.toContain('type="number"');
    expect(html).toContain('0 units');
    expect(html).toContain('Edit in Inventory Management');
  });

  it('does not unlock an existing variant whose ownership state is unknown', () => {
    const html = renderToStaticMarkup(<VariantStockControl variant={item({ inventoryEditable: undefined })} onChange={() => undefined} />);
    expect(html).not.toContain('type="number"');
    expect(html).toContain('Edit in Inventory Management');
  });
});
