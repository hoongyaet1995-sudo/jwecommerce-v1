import React from 'react';
import type { Product } from '../../types';

export function ProductPurchaseSettingsFields({ product, onChange }: { product: Product; onChange: (next: Product) => void }) {
  const mode = product.purchaseMode ?? 'buy';
  const price = product.priceDisplay ?? 'exact';
  return <section className="space-y-4 border-t border-neutral-200 dark:border-neutral-800 pt-5" data-product-purchase-settings>
    <fieldset>
      <legend className="text-sm font-semibold text-neutral-900 dark:text-white">How can customers buy?</legend>
      <p className="text-xs text-neutral-500 mt-1 mb-3">Choose what customers can do with this product.</p>
      <div className="space-y-2">
        {([
          ['buy', 'Buy online', 'Customers add this product to their cart and check out.'],
          ['quote', 'Request a quote', 'Customers send an enquiry before you confirm the price or arrangements.'],
          ['display', 'Display only', 'Show product information without a purchase or enquiry button.'],
        ] as const).map(([value, title, description]) => <label key={value} className={`flex items-start gap-3 p-4 rounded-xl border cursor-pointer ${mode === value ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-500/10' : 'border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/50'}`}>
          <input type="radio" name="product-purchase-mode" value={value} checked={mode === value} onChange={() => onChange({ ...product, purchaseMode: value })} className="mt-0.5 w-4 h-4 accent-indigo-500 shrink-0" />
          <span><span className="block text-xs font-semibold text-neutral-900 dark:text-white">{title}{value === 'buy' && <span className="ml-2 text-[10px] font-normal text-neutral-500">Default</span>}</span><span className="block text-xs text-neutral-500 mt-1">{description}</span></span>
        </label>)}
      </div>
    </fieldset>
    {mode === 'quote' && <fieldset className="p-4 border border-neutral-200 dark:border-neutral-700 rounded-xl">
      <legend className="px-1 text-xs font-semibold text-neutral-900 dark:text-white">How should the price appear?</legend>
      <div className="space-y-3">{([['exact', 'Show price'], ['from', 'Show starting price'], ['hide', 'Hide price']] as const).map(([value, title]) => <label key={value} className="flex items-center gap-2 text-xs text-neutral-700 dark:text-neutral-300 cursor-pointer"><input type="radio" name="product-price-display" checked={price === value} onChange={() => onChange({ ...product, priceDisplay: value })} className="w-4 h-4 accent-indigo-500" />{title}</label>)}</div>
      <p className="text-xs text-neutral-500 mt-3">Uses the existing product / variant price. Hiding a price does not change its value.</p>
    </fieldset>}
    {mode !== 'buy' && !window.__JW_ADMIN__?.productEnquiriesEnabled && <p role={product.status !== 'draft' ? 'alert' : 'status'} className="text-xs rounded-lg border border-amber-300 dark:border-amber-800 bg-amber-50 dark:bg-amber-950/30 text-amber-800 dark:text-amber-300 p-3">{product.status !== 'draft' ? 'Set Status to Draft to save this purchase method. ' : ''}Quote and display-only products must stay in Draft until storefront support is connected.</p>}
  </section>;
}
