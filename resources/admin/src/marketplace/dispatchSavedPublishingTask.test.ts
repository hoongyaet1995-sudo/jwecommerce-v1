import {expect,it,vi,beforeEach} from 'vitest';
import {marketplaceRequest} from '../integration/marketplaceData';
import {marketplaceOperations} from '../integration/marketplaceOperations';
import {dispatchSavedPublishingTask} from './dispatchSavedPublishingTask';
vi.mock('../integration/marketplaceData',()=>({marketplaceRequest:vi.fn()}));
vi.mock('../integration/marketplaceOperations',()=>({marketplaceOperations:{publishingTask:vi.fn()}}));
beforeEach(()=>vi.clearAllMocks());
it('executes a saved queued task once for concurrent clicks',async()=>{
 vi.mocked(marketplaceOperations.publishingTask).mockResolvedValue({status:'queued'} as never);
 await Promise.all([dispatchSavedPublishingTask('1'),dispatchSavedPublishingTask('1')]);
 expect(marketplaceRequest).toHaveBeenCalledExactlyOnceWith('/publishing-tasks/1/dispatch','POST');
});
it.each(['processing','succeeded','rejected','uncertain'])('never dispatches %s tasks',async(status)=>{
 vi.mocked(marketplaceOperations.publishingTask).mockResolvedValue({status} as never);
 await dispatchSavedPublishingTask('1');expect(marketplaceRequest).not.toHaveBeenCalled();
});
it('does not retry an ambiguous transport failure',async()=>{
 vi.mocked(marketplaceOperations.publishingTask).mockResolvedValue({status:'queued'} as never);
 vi.mocked(marketplaceRequest).mockRejectedValueOnce(new Error('offline'));
 await expect(dispatchSavedPublishingTask('1')).rejects.toThrow('offline');
 expect(marketplaceRequest).toHaveBeenCalledTimes(1);
});
