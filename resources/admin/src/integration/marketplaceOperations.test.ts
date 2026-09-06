import {beforeEach, describe, expect, it, vi} from 'vitest';
import {marketplaceRequest} from './marketplaceData';
import {marketplaceOperations, officialShopeeAuthorizationUrl} from './marketplaceOperations';

vi.mock('./marketplaceData', () => ({marketplaceRequest: vi.fn().mockResolvedValue({})}));

describe('marketplace operational contract', () => {
  beforeEach(() => vi.mocked(marketplaceRequest).mockReset().mockResolvedValue({}));
  it('loads persisted order detail without inventing mapping or missing price information', async () => {
    const detail = {data: {id: 8, external_order_id: 'TEST-ORDER', lines: [{external_sku: 'JWSTORE1PS5-500GB',
      quantity: 1, unit_price: null, mapping_status: 'unmapped', jw_variant_id: null, jw_sku: null}]}};
    vi.mocked(marketplaceRequest).mockResolvedValueOnce(detail);
    const result = await marketplaceOperations.orderDetail('8');
    expect(marketplaceRequest).toHaveBeenLastCalledWith('/orders/8');
    expect(result.data.lines[0].mapping_status).toBe('unmapped');
    expect(result.data.lines[0].unit_price).toBeNull();
    expect(result.data.lines[0].jw_variant_id).toBeNull();
  });
  it('requests recipient data explicitly only for the selected order detail', async () => {
    await marketplaceOperations.orderDetail('8', {includeRecipient: true});
    expect(marketplaceRequest).toHaveBeenLastCalledWith('/orders/8?includeRecipient=1');
    await marketplaceOperations.orderDetail('8', {includeRecipient: false});
    expect(marketplaceRequest).toHaveBeenLastCalledWith('/orders/8');
  });
  it('loads grouped products without flattening variants or inventing unknown values', async () => {
    const page = {total: 1, current_page: 1, last_page: 1, per_page: 15, data: [{store_id: 7, external_product_id: '100',
      variants: [{id: 11, observed_data: {price: null, stock: null, productImage: null}}]}]};
    vi.mocked(marketplaceRequest).mockResolvedValueOnce(page);
    const result = await marketplaceOperations.catalogProducts({storeId: '7', q: 'blue&status=mapped', page: 1});
    expect(result).toEqual(page);
    const path = vi.mocked(marketplaceRequest).mock.calls.at(-1)?.[0] ?? '';
    const url = new URL(path, 'https://example.test');
    expect(url.pathname).toBe('/catalog/products');
    expect(url.searchParams.get('q')).toBe('blue&status=mapped');
    expect(url.searchParams.has('status')).toBe(false);
  });
  it('imports a saved listing without transmitting client product or inventory data', async () => {
    await marketplaceOperations.importProduct('12');
    expect(marketplaceRequest).toHaveBeenLastCalledWith('/listings/12/import-product', 'POST');
  });
  it('retries an order run through the protected task endpoint', async () => {
    await marketplaceOperations.retryOrders('9');
    expect(marketplaceRequest).toHaveBeenLastCalledWith('/order-runs/9/retry', 'POST');
  });
  it('encodes catalog search without allowing it to inject store filters', async () => {
    await marketplaceOperations.catalog({q: 'blue&storeId=99', storeId: '1', page: 2});
    const path = vi.mocked(marketplaceRequest).mock.calls.at(-1)?.[0] ?? '';
    const query = new URL(path, 'https://example.test').searchParams;
    expect(query.get('q')).toBe('blue&storeId=99');
    expect(query.get('storeId')).toBe('1');
    expect(query.get('page')).toBe('2');
  });
  it('starts authorization with only an optional reconnect identity', async () => {
    await marketplaceOperations.authorize('7');
    expect(marketplaceRequest).toHaveBeenLastCalledWith('/shopee/authorize', 'POST', {reconnectStoreId: 7});
  });
  it('refuses non-official authorization redirects', () => {
    expect(officialShopeeAuthorizationUrl('https://openplatform.sandbox.test-stable.shopee.sg/api/v2/shop/auth_partner?sign=test'))
      .toBe('https://openplatform.sandbox.test-stable.shopee.sg/api/v2/shop/auth_partner?sign=test');
    expect(() => officialShopeeAuthorizationUrl('https://openplatform.sandbox.test-stable.shopee.sg.attacker.test/api/v2/shop/auth_partner')).toThrow();
    expect(() => officialShopeeAuthorizationUrl('https://attacker.test/api/v2/shop/auth_partner')).toThrow();
    expect(() => officialShopeeAuthorizationUrl('javascript:alert(1)')).toThrow();
    expect(officialShopeeAuthorizationUrl('https://partner.shopeemobile.com/api/v2/shop/auth_partner?sign=test'))
      .toBe('https://partner.shopeemobile.com/api/v2/shop/auth_partner?sign=test');
  });
});
