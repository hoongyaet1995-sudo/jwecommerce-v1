import {describe,expect,it} from 'vitest';
import {decodeInventoryIntake,encodeInventoryIntake,inventoryIntakeItems} from './inventoryIntake';

describe('inventory intake handoff',()=>{
  it('preserves caller rows and excludes rows without authoritative product identity',()=>{
    const anonymous={id:'unlinked',productName:'Unlinked',totalPhysicalStock:12};
    expect(inventoryIntakeItems([anonymous],[51])).toEqual([]);
    expect(anonymous).toEqual({id:'unlinked',productName:'Unlinked',totalPhysicalStock:12});
    const linked={...anonymous,jwProductId:51};
    expect(inventoryIntakeItems([linked],[51])[0]).toBe(linked);
  });
  it('persists only JW product identity and never marketplace stock',()=>{
    const encoded=encodeInventoryIntake({productIds:[51,51,52]});
    expect(JSON.parse(encoded)).toEqual({productIds:[51,52]});
    expect(encoded).not.toContain('stock');
    expect(decodeInventoryIntake(encoded)).toEqual({productIds:[51,52]});
  });

  it('filters imported SKUs and selects the first uncounted SKU',()=>{
    const rows=[
      {id:'1',jwProductId:51,physicalStockStatus:'verified',totalPhysicalStock:4},
      {id:'2',jwProductId:51,physicalStockStatus:'unknown',totalPhysicalStock:null},
      {id:'3',jwProductId:52,physicalStockStatus:'unknown',totalPhysicalStock:null},
      {id:'4',jwProductId:99,physicalStockStatus:'unknown',totalPhysicalStock:null},
    ];
    expect(inventoryIntakeItems(rows,[51]).map(row=>row.id)).toEqual(['1','2']);
    expect(inventoryIntakeItems(rows,[51]).find(row=>row.totalPhysicalStock===null)?.id).toBe('2');
  });
});
