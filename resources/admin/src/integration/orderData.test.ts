import { afterEach, describe, expect, it, vi } from 'vitest';
import type { LaravelAdminBootstrap, Order } from '../types';
import { reviewPaymentProofData } from './orderData';

const bootstrap: LaravelAdminBootstrap = {
  user: { name: 'Payments Admin', email: 'payments@example.test', avatar: null },
  csrfToken: 'csrf-token',
  logoutUrl: '/logout',
  ordersUrl: '/admin/api/orders',
};

const order = { id: '17', paymentGatewayStatus: 'paid', paymentProof: { status: 'verified' } } as Order;

afterEach(() => vi.unstubAllGlobals());

describe('Laravel payment-proof order adapter', () => {
  it('posts a CSRF-protected verification decision to the order-scoped proof endpoint', async () => {
    const request = vi.fn().mockResolvedValue(new Response(JSON.stringify({ data: order }), {
      status: 200,
      headers: { 'Content-Type': 'application/json' },
    }));
    vi.stubGlobal('window', { fetch: request });

    await expect(reviewPaymentProofData(bootstrap, '17', '29', 'verified', 'Matched against merchant statement.')).resolves.toEqual(order);

    expect(request).toHaveBeenCalledWith('/admin/api/orders/17/payments/29/proof/review', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': 'csrf-token',
      },
      body: JSON.stringify({ decision: 'verified', reason: 'Matched against merchant statement.' }),
    });
  });
});
