import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it, vi } from 'vitest';
import {
  StockIntakeAdjustmentModal,
  calculateStoreStockChange,
} from './StockIntakeAdjustmentModal';

const item: any = {
  id: 'variant:7',
  masterSku: 'SKU-MAPPED-1',
  productName: 'Mapped product',
  variantName: 'Default',
  imageUrl: '',
  totalPhysicalStock: 30,
  reservedStock: 2,
  mappedStoreIds: ['11', '14'],
};

const stores: any[] = [
  { id: 'website', name: 'JW Ecom Storefront', nickname: 'JW Storefront', platform: 'webstore', platformDisplayName: 'JW Ecom Storefront', connectionState: 'connected', inventorySyncState: 'active' },
  { id: '11', name: 'Mapped Shopee', nickname: 'Mapped Shopee', platform: 'shopee', platformDisplayName: 'Shopee', connectionState: 'connected', inventorySyncState: 'active' },
  { id: '12', name: 'Unmapped Shopee', nickname: 'Unmapped Shopee', platform: 'shopee', platformDisplayName: 'Shopee', connectionState: 'connected', inventorySyncState: 'active' },
  { id: '13', name: 'Disconnected Lazada', nickname: 'Disconnected Lazada', platform: 'lazada', platformDisplayName: 'Lazada', connectionState: 'disconnected', inventorySyncState: 'active' },
  { id: '14', name: 'Setup Required Lazada', nickname: 'Setup Required Lazada', platform: 'lazada', platformDisplayName: 'Lazada', connectionState: 'connected', inventorySyncState: 'setup_required' },
];

describe('StockIntakeAdjustmentModal', () => {
  it('only offers the website and mapped stores that are ready for inventory sync', () => {
    const html = renderToStaticMarkup(
      <StockIntakeAdjustmentModal
        isOpen
        onClose={vi.fn()}
        item={item}
        mode="independent"
        stores={stores}
        storeBalances={{}}
        onSave={vi.fn()}
      />,
    );

    expect(html).toContain('JW Ecom Storefront');
    expect(html).toContain('Mapped Shopee');
    expect(html).not.toContain('Unmapped Shopee');
    expect(html).not.toContain('Disconnected Lazada');
    expect(html).not.toContain('Setup Required Lazada');
  });

  it('calculates an audit delta from the selected store ledger instead of the SKU aggregate', () => {
    expect(calculateStoreStockChange('adjustment', 9, 5)).toEqual({
      deltaQuantity: 4,
      newPhysicalStock: 9,
    });
  });
});
