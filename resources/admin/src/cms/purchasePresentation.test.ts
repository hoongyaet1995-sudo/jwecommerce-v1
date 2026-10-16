import {describe,it,expect} from 'vitest';
import {purchasePriceLabel,isOnlinePurchase} from './purchasePresentation';
import type {PageRuntimeProduct} from './PageRuntimeContext';
const product={id:'1',title:'Item',slug:'item',brand:'',summary:'',images:[],tags:[],basePrice:79,baseCompareAtPrice:null} as PageRuntimeProduct;
const money=(n:number)=>`RM${n.toFixed(2)}`;
describe('Shared CMS purchase presentation',()=>{
 it('preserves legacy buy labels and enables buying',()=>{expect(purchasePriceLabel(product,79,money)).toBe('RM79.00');expect(isOnlinePurchase(product)).toBe(true);});
 it('formats quote exact and from prices and blocks buying',()=>{const quote={...product,purchaseMode:'quote' as const,priceDisplay:'from' as const};expect(purchasePriceLabel(quote,79,money)).toBe('From RM79.00');expect(isOnlinePurchase(quote)).toBe(false);expect(purchasePriceLabel({...quote,priceDisplay:'exact'},79,money)).toBe('RM79.00');});
 it('never formats a hidden or display-only price',()=>{const forbidden=()=>{throw new Error('Hidden amount was formatted');};expect(purchasePriceLabel({...product,purchaseMode:'quote',priceDisplay:'hide'},79,forbidden)).toBe('');expect(purchasePriceLabel({...product,purchaseMode:'display'},79,forbidden)).toBe('');});
});
