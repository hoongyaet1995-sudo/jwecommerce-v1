import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it, vi } from 'vitest';
import type { Order } from '../../types';
import { AmendDeliveryDetailsModal } from './AmendDeliveryDetailsModal';

const address={id:'1',isDefault:true,type:'shipping',firstName:'Tee',lastName:'Jing',address1:'Old address',city:'Shah Alam',province:'Selangor',country:'Malaysia',postalCode:'45600',phone:'0143323862'} as const;
const order={id:'4',orderNumber:'JW-1004',customerName:'Tee Jing',shippingAddress:address,billingAddress:address,fulfillmentStatus:'processing',paymentGatewayStatus:'paid'} as Order;

describe('AmendDeliveryDetailsModal source-fidelity boundary', () => {
  it('preserves the approved address form and audit inputs without prototype toggles', () => {
    const html=renderToStaticMarkup(<AmendDeliveryDetailsModal isOpen order={order} hasActiveShipment onClose={vi.fn()} onSave={vi.fn()} />);
    expect(html).toContain('Edit delivery details');
    expect(html).toContain('Save changes');
    expect(html).not.toContain('Simulate Permission Denied');
    expect(html).toContain('Internal note');
    expect(html).toContain('does not reroute');
    expect(html).toContain('role="switch"');
  });
});
