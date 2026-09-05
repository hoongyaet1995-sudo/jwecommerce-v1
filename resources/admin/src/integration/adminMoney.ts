import type { CurrencySetting } from '../types/oscommerceSettings';

// Admin inputs and catalog amounts are already in base major units. Formatting
// must not apply the storefront FX multiplier to editable amounts.
export function currencyFor(settings: CurrencySetting[], code?: string): CurrencySetting | undefined {
  return code ? settings.find(c => c.code === code.toUpperCase())
    : settings.find(c => c.isDefault && c.isEnabled) ?? settings.find(c => c.isEnabled);
}

export function formatAdminMoney(amount: number | null | undefined, settings: CurrencySetting[], code?: string, snapshotRate = 1): string {
  if (amount == null || !Number.isFinite(amount)) return '—';
  const currency = currencyFor(settings, code);
  const value = (amount * snapshotRate).toLocaleString('en-US', {
    minimumFractionDigits: currency?.decimalPlaces ?? 2,
    maximumFractionDigits: currency?.decimalPlaces ?? 2,
  });
  return currency ? `${currency.symbolLeft}${value}${currency.symbolRight}` : `${code ?? ''} ${value}`.trim();
}
