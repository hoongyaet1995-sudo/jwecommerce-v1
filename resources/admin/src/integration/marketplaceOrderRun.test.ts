import {beforeEach,expect,it,vi} from 'vitest';
vi.mock('./marketplaceData',()=>({marketplaceRequest:vi.fn()}));
import {marketplaceRequest} from './marketplaceData';
import {marketplaceOperations} from './marketplaceOperations';

beforeEach(()=>vi.mocked(marketplaceRequest).mockReset());

it('treats Laravel empty latest-run serialization as no order recovery run',async()=>{
 vi.mocked(marketplaceRequest).mockResolvedValue({});
 await expect(marketplaceOperations.latestOrderRun('7')).resolves.toBeNull();
});

it('accepts only a valid latest run belonging to the requested store',async()=>{
 const run={id:5,store_id:7,status:'running',pages_completed:2,orders_processed:8,error_code:null,finished_at:null};
 vi.mocked(marketplaceRequest).mockResolvedValue(run);
 await expect(marketplaceOperations.latestOrderRun('7')).resolves.toEqual(run);
});
