import {describe,expect,it} from 'vitest';
import {
  inventoryCsv,
  inventoryPage,
  inventoryPageNumbers,
  toggleInventoryPageSelection,
} from './inventoryTablePagination';

const items=Array.from({length:61},(_,index)=>({
  masterSku:`SKU-${index+1}`,
  productName:index===0?'Quoted "Product"':'Product',
  variantName:index===0?'Blue, Large':'Default',
  barcode:index===0?'001':undefined,
  totalPhysicalStock:index,
  reservedStock:1,
  availableStock:Math.max(0,index-1),
  syncStatus:index===0?'synced':'queued',
}));

describe('inventory table pagination',()=>{
  it('paginates only the supplied real inventory rows',()=>{
    const page=inventoryPage(items,2,25);
    expect(page.items.map(item=>item.masterSku)).toEqual(Array.from({length:25},(_,index)=>`SKU-${index+26}`));
    expect(page).toMatchObject({totalItems:61,totalPages:3,startIndex:26,endIndex:50,currentPage:2});
  });

  it('clamps a stale page after filtering reduces the result count',()=>{
    const page=inventoryPage(items.slice(0,3),9,25);
    expect(page.currentPage).toBe(1);
    expect(page.items).toHaveLength(3);
  });

  it('renders compact page numbers for a large real catalog',()=>{
    expect(inventoryPageNumbers(4,217)).toEqual([1,2,3,4,5,'...',217]);
    expect(inventoryPageNumbers(108,217)).toEqual([1,'...',107,108,109,'...',217]);
  });

  it('selects and clears only the current page while preserving other pages',()=>{
    expect(toggleInventoryPageSelection(['SKU-1'],['SKU-26','SKU-27'],false)).toEqual(['SKU-1','SKU-26','SKU-27']);
    expect(toggleInventoryPageSelection(['SKU-1','SKU-26','SKU-27'],['SKU-26','SKU-27'],true)).toEqual(['SKU-1']);
  });

  it('exports selected real rows as escaped CSV',()=>{
    const csv=inventoryCsv(items.slice(0,1));
    expect(csv).toContain('Master SKU,Product Name,Variant,Barcode,Physical Stock,Reserved,Available (ATP),Sync Status');
    expect(csv).toContain('"SKU-1","Quoted ""Product""","Blue, Large","001",0,1,0,"synced"');
  });
});
