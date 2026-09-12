export function matchesStockFilter(item:{physicalStockStatus?:string;totalPhysicalStock:number|null;availableStock:number|null;safetyStock:number|null},filter:string):boolean {
 const unknown=item.physicalStockStatus==='unknown'||item.totalPhysicalStock==null;
 if(filter==='all')return true;
 if(filter==='awaiting_audit')return unknown;
 if(unknown||item.availableStock==null)return false;
 if(filter==='oos')return item.availableStock===0;
 if(item.safetyStock==null)return false;
 if(filter==='low_stock')return item.availableStock<=item.safetyStock;
 if(filter==='healthy')return item.availableStock>item.safetyStock;
 return false;
}
