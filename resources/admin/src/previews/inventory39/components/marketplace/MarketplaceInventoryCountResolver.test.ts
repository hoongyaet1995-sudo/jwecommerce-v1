import { expect, it } from 'vitest';
import { resolveFirstCountItem } from './MarketplaceInventoryPage';

it('uses the selected catalog SKU when it has no existing inventory balance', () => {
  const selected: any = { id: '', variantId: 5, masterSku: 'SKU-SOLIS-WAL-STD' };

  expect(resolveFirstCountItem([], selected, 'SKU-SOLIS-WAL-STD')).toBe(selected);
});
