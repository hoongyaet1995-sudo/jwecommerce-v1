import React from 'react';
import {renderToStaticMarkup} from 'react-dom/server';
import {describe, expect, it} from 'vitest';
import {OrderInspectionDrawer} from './OrderInspectionDrawer';
import type {MarketplaceOrderDetail} from '../../integration/marketplaceOperations';

describe('real order inspection drawer', () => {
  it('renders real identity and unmapped lines without invented warehouse, price or AWB completion', () => {
    const order: MarketplaceOrderDetail = {
      id: 1, store_id: 7, external_order_id: 'SANDBOX-ORDER', platform_status: 'READY_TO_SHIP',
      platform: 'shopee', shop_name: 'Sandbox Shop', currency: 'MYR', total_amount: '1407.31',
      platform_updated_at: 1788685411, fetched_at: '2026-09-06T09:04:00Z', shipping_carrier: null,
      packages: [], capabilities: {fulfillment: false, airway_bill: false},
      lines: [{external_product_id: '846055463', external_variant_id: '12983425', title: 'PS5',
        variant_title: 'White,500GB', external_sku: 'JWSTORE1PS5-500GB', quantity: 1, unit_price: null,
        mapping_status: 'unmapped', jw_variant_id: null, jw_sku: null}],
    };
    const html = renderToStaticMarkup(<OrderInspectionDrawer order={order} onClose={() => {}}/>);
    expect(html).toContain('SANDBOX-ORDER');
    expect(html).toContain('PS5');
    expect(html).toContain('Unmapped');
    expect(html).not.toContain('Warehouse Alpha');
    expect(html).not.toContain('Generated (Ready)');
    expect(html).not.toContain('NaN');
    expect(html).not.toContain('0.00');
    expect(html.match(/disabled=""/g)).toHaveLength(2);
  });
});
