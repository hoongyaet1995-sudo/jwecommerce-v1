// @vitest-environment jsdom
import {afterEach, expect, it, vi} from 'vitest';
import {saveCatalogEntity} from './catalogData';
import type {LaravelAdminBootstrap, Product} from '../types';

afterEach(()=>vi.restoreAllMocks());

it('preserves the reviewed inventory version and propagates the field conflict to the existing error notice',async()=>{
  const fetch=vi.spyOn(window,'fetch').mockResolvedValue(new Response(JSON.stringify({errors:{'variants.0.inventoryQuantity':['Inventory changed. Reload the product before saving.']}}),{status:422}));
  const product={id:'51',variants:[{id:'92',inventoryQuantity:10,stockVersion:3}]} as Product;
  const bootstrap={catalogUrl:'/admin/api/catalog',csrfToken:'test-only'} as LaravelAdminBootstrap;
  await expect(saveCatalogEntity(bootstrap,'products',product)).rejects.toThrow('Inventory changed. Reload the product before saving.');
  expect(fetch).toHaveBeenCalledTimes(1);
  expect(fetch.mock.calls[0][0]).toBe('/admin/api/catalog/products/51');
  expect(JSON.parse(fetch.mock.calls[0][1]!.body as string).variants[0].stockVersion).toBe(3);
});
