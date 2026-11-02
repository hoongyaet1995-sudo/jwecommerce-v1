import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it, vi } from 'vitest';
vi.mock('../../context/AdminContext', () => ({useAdmin:()=>({products:[],categories:[],collections:[],taxClasses:[],reviews:[],saveProduct:vi.fn()})}));
vi.mock('../../context/useAdminCurrency',()=>({useAdminCurrency:()=>({money:(v:number)=>String(v),currencyCode:'MYR'})}));
import { CategoryAdmin } from './CategoryAdmin';
import { CollectionAdmin } from './CollectionAdmin';
import { ProductAdmin } from './ProductAdmin';
import { ProductExtraInfoEditor } from './ProductExtraInfoEditor';
describe('Product page editor',()=>{
 it('renders category and collection lists without a browser location during SSR',()=>{
 expect(renderToStaticMarkup(<CategoryAdmin/>)).toContain('Category');
 expect(renderToStaticMarkup(<CollectionAdmin/>)).toContain('Create Collection');
 });
 it('renders the product list without a browser location during SSR',()=>{expect(renderToStaticMarkup(<ProductAdmin/>)).toContain('Add Product')});
 it('places FAQ and Story exactly once in the shared PDP navigation',()=>{
 const html=renderToStaticMarkup(<ProductExtraInfoEditor formData={{id:'1',extraInfo:{},pdpContent:{}} as any} allProducts={[]} onChange={()=>{}}/>);
 expect((html.match(/Product FAQ/g)||[]).length).toBe(1);
 expect((html.match(/Product Story/g)||[]).length).toBe(1);
 expect(html).toContain('Signature Highlights');
 });
});
