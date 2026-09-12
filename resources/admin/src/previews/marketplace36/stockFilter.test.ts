import {expect,it} from 'vitest';
import {matchesStockFilter} from './stockFilter';
it('unknown availability is neither out of stock nor healthy',()=>{
 const row={physicalStockStatus:'verified',totalPhysicalStock:20,availableStock:null,safetyStock:null};
 for(const filter of ['healthy','oos','low_stock'])expect(matchesStockFilter(row,filter)).toBe(false);
 expect(matchesStockFilter({...row,availableStock:0,safetyStock:2},'oos')).toBe(true);
 expect(matchesStockFilter({...row,availableStock:10,safetyStock:2},'healthy')).toBe(true);
 expect(matchesStockFilter({...row,totalPhysicalStock:null},'awaiting_audit')).toBe(true);
});
