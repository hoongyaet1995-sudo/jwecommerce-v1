import { describe, expect, it } from 'vitest';
import type { Product, ProductVariant } from '../../types';
import { applyEditableInventoryQuantity, catalogStockPayload } from './catalogStockPayload';

const variant = (overrides: Partial<ProductVariant> = {}): ProductVariant => ({
  id: '7', sku: 'SKU-7', title: 'Default', options: {}, price: 10,
  inventoryQuantity: 10, enabled: true, inventoryEditable: true,
  inventoryRevision: 'a'.repeat(64), ...overrides,
});

const product = (item: ProductVariant): Product => ({
  id: '9', title: 'Product', slug: 'product', brand: '', categoryId: '', collectionIds: [],
  summary: '', descriptionHtml: '', images: [], status: 'active', hasVariants: false,
  basePrice: 10, featured: false, tags: [], totalInventory: item.inventoryQuantity,
  options: [], variants: [item], createdAt: '', updatedAt: '',
});

describe('catalogStockPayload', () => {
  it('omits unchanged persisted quantity so metadata saves cannot rewrite stock', () => {
    const initial = product(variant());
    const payload = catalogStockPayload(product(variant()), initial);
    expect(payload.variants[0]).not.toHaveProperty('inventoryQuantity');
    expect(payload.variants[0]).not.toHaveProperty('inventoryRevision');
  });

  it('includes changed editable quantity with its ledger revision', () => {
    const initial = product(variant());
    const payload = catalogStockPayload(product(variant({ inventoryQuantity: 15 })), initial);
    expect(payload.variants[0]).toMatchObject({ inventoryQuantity: 15, inventoryRevision: 'a'.repeat(64) });
  });

  it('omits locked or unknown persisted quantity even if local data changed', () => {
    const initial = product(variant());
    const locked = catalogStockPayload(product(variant({ inventoryQuantity: 2, inventoryEditable: false })), initial);
    const unknown = catalogStockPayload(product(variant({ inventoryQuantity: 3, inventoryEditable: undefined })), initial);
    expect(locked.variants[0]).not.toHaveProperty('inventoryQuantity');
    expect(unknown.variants[0]).not.toHaveProperty('inventoryQuantity');
  });

  it('keeps opening quantity for a new SKU', () => {
    const payload = catalogStockPayload(product(variant({ id: 'var-new', inventoryQuantity: 0, inventoryEditable: undefined })), null);
    expect(payload.variants[0]).toMatchObject({ inventoryQuantity: 0 });
  });

  it('bulk stock changes only editable and new rows', () => {
    const rows = [
      variant({ id: '1', inventoryEditable: true }),
      variant({ id: '2', inventoryEditable: false }),
      variant({ id: 'var-new', inventoryEditable: undefined }),
    ];
    const updated = applyEditableInventoryQuantity(rows, 25);
    expect(updated.map(row => row.inventoryQuantity)).toEqual([25, 10, 25]);
  });
});
