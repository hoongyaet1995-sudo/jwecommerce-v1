import type {PageRuntimeProduct} from './PageRuntimeContext';
export const isOnlinePurchase = (product: PageRuntimeProduct): boolean => !product.purchaseMode || product.purchaseMode === 'buy';
export function purchasePriceLabel(product: PageRuntimeProduct, amount: number, money: (value:number)=>string): string {
  if (product.purchaseMode === 'display' || product.purchaseMode === 'quote' && product.priceDisplay === 'hide') return '';
  return (product.purchaseMode === 'quote' && product.priceDisplay === 'from' ? 'From ' : '') + money(amount);
}
