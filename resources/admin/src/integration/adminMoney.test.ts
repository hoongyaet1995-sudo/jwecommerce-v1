import { describe, expect, it } from 'vitest';
import { formatAdminMoney } from './adminMoney';
import type { CurrencySetting } from '../types/oscommerceSettings';

const settings = [
  { code: 'MYR', symbolLeft: 'RM ', symbolRight: '', decimalPlaces: 2, isDefault: true, isEnabled: true, exchangeRate: 1 },
  { code: 'USD', symbolLeft: '$', symbolRight: '', decimalPlaces: 2, isDefault: false, isEnabled: true, exchangeRate: 0.22 },
] as CurrencySetting[];

describe('Admin currency display contract', () => {
  it('uses the configured default without applying FX to editable base amounts', () => {
    expect(formatAdminMoney(270.3, settings)).toBe('RM 270.30');
    expect(formatAdminMoney(270.3, settings.map(c => ({...c, isDefault: c.code === 'USD'})))).toBe('$270.30');
  });
  it('uses historical currency and its saved rate independently of the current default and rate', () => {
    expect(formatAdminMoney(100, settings, 'USD', 0.25)).toBe('$25.00');
    expect(formatAdminMoney(100, settings.map(c => ({...c, isDefault: c.code === 'USD'})), 'MYR', 1)).toBe('RM 100.00');
  });
  it('supports right-side symbols and configured decimal places, and never labels missing data as zero', () => {
    expect(formatAdminMoney(1234.5, [{...settings[0],symbolLeft:'',symbolRight:' kr',decimalPlaces:0}])).toBe('1,235 kr');
    expect(formatAdminMoney(undefined, settings)).toBe('—');
    expect(formatAdminMoney(12, settings, 'GBP')).toBe('GBP 12.00');
  });
});
