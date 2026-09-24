export interface InventoryCsvRow {
  masterSku:string;
  productName:string;
  variantName?:string|null;
  barcode?:string|null;
  totalPhysicalStock?:number|null;
  reservedStock?:number|null;
  availableStock?:number|null;
  syncStatus?:string|null;
}

export function inventoryPage<T>(items:T[],requestedPage:number,pageSize:number){
  const safeSize=[10,25,50,100].includes(pageSize)?pageSize:25;
  const totalItems=items.length;
  const totalPages=Math.max(1,Math.ceil(totalItems/safeSize));
  const currentPage=Math.min(totalPages,Math.max(1,Number.isInteger(requestedPage)?requestedPage:1));
  const offset=(currentPage-1)*safeSize;
  return {
    items:items.slice(offset,offset+safeSize),
    totalItems,totalPages,currentPage,
    startIndex:totalItems===0?0:offset+1,
    endIndex:Math.min(totalItems,offset+safeSize),
  };
}

export function inventoryPageNumbers(current:number,total:number):(number|'...')[]{
  if(total<=7)return Array.from({length:total},(_,index)=>index+1);
  if(current<=4)return [1,2,3,4,5,'...',total];
  if(current>=total-3)return [1,'...',total-4,total-3,total-2,total-1,total];
  return [1,'...',current-1,current,current+1,'...',total];
}

export function toggleInventoryPageSelection(selected:string[],pageSkus:string[],allSelected:boolean):string[]{
  if(allSelected){
    const page=new Set(pageSkus);
    return selected.filter(sku=>!page.has(sku));
  }
  return [...new Set([...selected,...pageSkus])];
}

const csvCell=(value:unknown)=>`"${String(value??'').replaceAll('"','""')}"`;

export function inventoryCsv(items:InventoryCsvRow[]):string{
  const header='Master SKU,Product Name,Variant,Barcode,Physical Stock,Reserved,Available (ATP),Sync Status';
  const rows=items.map(item=>[
    csvCell(item.masterSku),csvCell(item.productName),csvCell(item.variantName),csvCell(item.barcode),
    item.totalPhysicalStock??0,item.reservedStock??0,item.availableStock??0,csvCell(item.syncStatus),
  ].join(','));
  return [header,...rows].join('\n');
}
