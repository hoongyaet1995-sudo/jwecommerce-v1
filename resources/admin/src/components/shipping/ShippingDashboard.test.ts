import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const source = readFileSync(resolve(process.cwd(), 'src/components/shipping/ShippingDashboard.tsx'), 'utf8');

describe('ShippingDashboard provider action binding', () => {
  it('binds the existing EasyParcel top-up action from AdminContext before passing it to source UI controls', () => {
    expect(source).toContain('topUpEasyParcelWallet,');
  });
});
