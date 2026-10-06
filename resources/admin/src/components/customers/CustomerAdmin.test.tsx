import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it, vi } from 'vitest';
vi.mock('../../context/AdminContext', () => ({ useAdmin: () => ({ customers: [{ id: '1', name: 'Social buyer', email: '', phone: '+60123456789', avatar: '', totalOrders: 0, totalSpent: 0, registeredAt: '2026-10-06', status: 'active', tags: [], addresses: [], platforms: ['facebook','instagram','whatsapp'] }], saveCustomer: vi.fn(), deleteCustomer: vi.fn(), orders: [] }) }));
vi.mock('../../context/useAdminCurrency', () => ({ useAdminCurrency: () => ({ money: () => 'RM 0.00', currencyCode: 'MYR' }) }));
vi.mock('../common/DeleteConfirmationModal', () => ({ DeleteConfirmationModal: () => null }));
import { CustomerAdmin } from './CustomerAdmin';
describe('Customer platform directory', () => {
 it('renders multi-platform icons and a telephone when email is absent', () => {
  const html = renderToStaticMarkup(<CustomerAdmin />);
  expect(html).toContain('>Platform</th>');
  for (const label of ['Facebook','Instagram','WhatsApp']) expect(html).toContain(`aria-label="${label}"`);
  expect(html).toContain('+60123456789');expect(html).toContain('Filter by platform');expect(html).toContain('Filter by tag');
 });
});
