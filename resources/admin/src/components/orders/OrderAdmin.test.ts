import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const source = readFileSync(resolve(process.cwd(), 'src/components/orders/OrderAdmin.tsx'), 'utf8');

describe('OrderAdmin manual fulfillment source fidelity', () => {
  it('retains the approved manual dispatch controls and awaits the persisted tracking adapter', () => {
    expect(source).toContain('Carrier / hand-off partner');
    expect(source).toContain('Tracking reference');
    expect(source).toContain('Record dispatch');
    expect(source).toContain('Records a manual shipment and append-only dispatch event. No courier label is purchased.');
    expect(source).toContain('const saved = await updateOrderTracking(selectedOrder.id, carrier, tracking);');
    expect(source).toContain('if (saved) {');
  });
});
