import {afterEach,expect,it,vi} from 'vitest';
import {marketplaceOperations} from './marketplaceOperations';
import {checkMarketplaceMappingConflict} from './marketplaceMappingConflict';
afterEach(()=>vi.restoreAllMocks());
it('requires an explicit eligible candidate, not an empty result',async()=>{
 const api=vi.spyOn(marketplaceOperations,'mappingContext').mockResolvedValue({data:[]});
 await expect(checkMarketplaceMappingConflict('7','3')).rejects.toThrow('could not be verified');
 api.mockResolvedValue({data:[{jw_variant_id:3,same_store_conflict:null}]});
 await expect(checkMarketplaceMappingConflict('7','3')).resolves.toBeNull();
 expect(api).toHaveBeenLastCalledWith('7',[3]);
});
