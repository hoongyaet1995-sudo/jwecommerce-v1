import {beforeEach, describe, expect, it, vi} from 'vitest';
import {marketplaceRequest} from './marketplaceData';
import {marketplaceOperations, officialShopeeAuthorizationUrl, officialLazadaAuthorizationUrl, requestMarketplaceAuthorization} from './marketplaceOperations';

vi.mock('./marketplaceData', () => ({marketplaceRequest: vi.fn().mockResolvedValue({})}));

describe('marketplace operational contract', () => {
  it('recovers a lost receipt using read requests and rejects a different destination', async () => {
    const key='9285db80-1111-4111-8111-123456789012';
    const task={id:12,storeId:7,productId:9,status:'queued',externalItemId:null,createdAt:'now',updatedAt:'now'};
    vi.mocked(marketplaceRequest).mockResolvedValue(task);
    expect(await marketplaceOperations.recoverPublishingTask('7',key)).toEqual(task);
    expect(marketplaceRequest).toHaveBeenCalledWith(`/stores/7/publishing-request/${key}`);
    expect(vi.mocked(marketplaceRequest).mock.calls.every(call=>call[1]===undefined)).toBe(true);
    vi.mocked(marketplaceRequest).mockResolvedValue({...task,storeId:8});
    await expect(marketplaceOperations.recoverPublishingTask('7',key)).rejects.toThrow('destination');
    await expect(marketplaceOperations.recoverPublishingTask('7','invalid')).rejects.toThrow('identity');
  });
  it('navigates Lazada authorization only after validating the server destination', async () => {
    const assign = vi.fn();
    vi.stubGlobal('window', {location: {assign}});
    try {
      const url = 'https://auth.lazada.com/oauth/authorize?state=test';
      vi.mocked(marketplaceRequest).mockResolvedValueOnce({url});
      await requestMarketplaceAuthorization('lazada', '42');
      expect(marketplaceRequest).toHaveBeenLastCalledWith('/lazada/authorize', 'POST', {reconnectStoreId:42});
      expect(assign).toHaveBeenCalledExactlyOnceWith(url);
      assign.mockClear();
      vi.mocked(marketplaceRequest).mockResolvedValueOnce({url:'https://attacker.test/oauth/authorize'});
      await expect(requestMarketplaceAuthorization('lazada')).rejects.toThrow('Unexpected authorization destination');
      expect(assign).not.toHaveBeenCalled();
    } finally {
      vi.unstubAllGlobals();
    }
  });
  it('routes Lazada reconnect to its provider and rejects lookalike authorization hosts', async () => {
    await marketplaceOperations.authorize('7', 'lazada');
    expect(marketplaceRequest).toHaveBeenLastCalledWith('/lazada/authorize', 'POST', {reconnectStoreId:7});
    expect(officialLazadaAuthorizationUrl('https://auth.lazada.com/oauth/authorize?state=test'))
      .toBe('https://auth.lazada.com/oauth/authorize?state=test');
    for (const url of ['https://auth.lazada.com.attacker.test/oauth/authorize', 'http://auth.lazada.com/oauth/authorize',
      'https://user@auth.lazada.com/oauth/authorize', 'https://auth.lazada.com/other']) {
      expect(()=>officialLazadaAuthorizationUrl(url)).toThrow();
    }
  });
  it('scopes attribute searches to the selected store and encodes keywords', async () => {
    const result={storeId:7,attributeId:123,data:{value_list:[{value_id:42,value_name:'Blue'}],page_info:{cursor:42,has_next:false}}};
    vi.mocked(marketplaceRequest).mockResolvedValueOnce(result);
    expect(await marketplaceOperations.attributeValues('7',123,'Blue&attributeId=999',0)).toEqual(result);
    const url=new URL(vi.mocked(marketplaceRequest).mock.calls.at(-1)![0],'https://example.test');
    expect(url.pathname).toBe('/stores/7/listing-attribute-values');
    expect(url.searchParams.get('attributeId')).toBe('123');
    expect(url.searchParams.get('q')).toBe('Blue&attributeId=999');
    expect(url.searchParams.get('cursor')).toBe('0');
  });
  it('reads persisted publishing states without treating a parent checkpoint as success', async () => {
    const task = {id: 12, storeId: 1, productId: 51, status: 'uncertain', externalItemId: '846055463',
      createdAt: '2026-09-07 12:00:00', updatedAt: '2026-09-07 12:01:00'};
    vi.mocked(marketplaceRequest).mockResolvedValueOnce(task);
    expect(await marketplaceOperations.publishingTask('12')).toEqual(task);
    expect(marketplaceRequest).toHaveBeenLastCalledWith('/publishing-tasks/12');
    for (const invalid of [{...task, status: 'success'}, {...task, status: 'succeeded', externalItemId: null}, {...task, id: 13}]) {
      vi.mocked(marketplaceRequest).mockResolvedValueOnce(invalid);
      await expect(marketplaceOperations.publishingTask('12')).rejects.toThrow('Invalid publishing task response');
    }
  });
  beforeEach(() => vi.mocked(marketplaceRequest).mockReset().mockResolvedValue({}));
  it('does not restore empty or mismatched catalog task responses as running tasks', async () => {
    for(const payload of [null,{}, {id:8,store_id:9,status:'running'}]){
      vi.mocked(marketplaceRequest).mockResolvedValueOnce(payload);
      expect(await marketplaceOperations.latestCatalogRun('3')).toBeNull();
    }
    const run={id:8,store_id:3,status:'queued'};
    vi.mocked(marketplaceRequest).mockResolvedValueOnce(run);
    expect(await marketplaceOperations.latestCatalogRun('3')).toEqual(run);
  });
  it('submits reviewed variant destination only and propagates rejection instead of reporting success', async () => {
    vi.mocked(marketplaceRequest).mockResolvedValueOnce({variantId: 92, productId: 51});
    const result = await marketplaceOperations.importVariant('12', {newProduct: true, sku: 'JW-REVIEWED'});
    expect(marketplaceRequest).toHaveBeenLastCalledWith('/listings/12/import-variant', 'POST', {newProduct: true, sku: 'JW-REVIEWED'});
    expect(result).toEqual({variantId: 92, productId: 51});
    vi.mocked(marketplaceRequest).mockRejectedValueOnce(new Error('SKU conflict'));
    await expect(marketplaceOperations.importVariant('12', {productId: 51, sku: 'TAKEN'})).rejects.toThrow('SKU conflict');
    expect(marketplaceRequest).toHaveBeenLastCalledWith('/listings/12/import-variant', 'POST', {productId: 51, sku: 'TAKEN'});
  });
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
  it('transmits reviewed whole-product SKU overrides to the import endpoint', async()=>{
    await marketplaceOperations.importProduct('12',{skuOverrides:{'12':'JW-NEW','13':'JW-NEXT'}});
    expect(marketplaceRequest).toHaveBeenLastCalledWith('/listings/12/import-product','POST',
      {skuOverrides:{'12':'JW-NEW','13':'JW-NEXT'}});
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
    expect(officialShopeeAuthorizationUrl('https://open.sandbox.test-stable.shopee.com/auth?partner_id=1'))
      .toBe('https://open.sandbox.test-stable.shopee.com/auth?partner_id=1');
    expect(() => officialShopeeAuthorizationUrl('https://open.sandbox.test-stable.shopee.com.attacker.test/auth')).toThrow();
    expect(() => officialShopeeAuthorizationUrl('https://attacker.test/auth')).toThrow();
    expect(() => officialShopeeAuthorizationUrl('javascript:alert(1)')).toThrow();
    expect(officialShopeeAuthorizationUrl('https://open.shopee.com/auth?partner_id=1'))
      .toBe('https://open.shopee.com/auth?partner_id=1');
  });
});

it('updates only the selected store product publishing capability with an audit reason',async()=>{
  vi.mocked(marketplaceRequest).mockResolvedValueOnce({storeId:3,productPublish:true});
  await marketplaceOperations.setProductPublishing('3',true,'Merchant approved production publishing');
  expect(marketplaceRequest).toHaveBeenLastCalledWith('/stores/3/capabilities/product-publishing','PATCH',{
    enabled:true,reason:'Merchant approved production publishing',
  });
});
