import {expect, it} from 'vitest';
import {listingSources} from '../listingSources';

it('shows active sources with eligible counts rather than admin draft counts', () => {
  const result = listingSources([
    {id:'1',status:'active',productCount:99},{id:'2',status:'draft',productCount:99},
  ] as any, [{id:'3',status:'active',productCount:99}] as any, [
    {status:'active',categoryId:'1',collectionIds:['3']},
    {status:'draft',categoryId:'1',collectionIds:['3']},
  ] as any);
  expect(result.categories).toEqual([{id:'1',status:'active',productCount:1}]);
  expect(result.collections[0].productCount).toBe(1);
});
