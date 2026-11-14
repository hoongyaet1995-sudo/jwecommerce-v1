export const INVENTORY_INTAKE_STORAGE_KEY='jw.inventory-management.intake';

export type InventoryIntake={productIds:number[]};

export function encodeInventoryIntake(value:InventoryIntake):string{
  return JSON.stringify({productIds:Array.from(new Set(value.productIds.filter(Number.isSafeInteger)))});
}

export function decodeInventoryIntake(raw:string|null):InventoryIntake|null{
  if(!raw)return null;
  try{
    const value=JSON.parse(raw);
    if(!Array.isArray(value?.productIds))return null;
    const productIds:number[]=Array.from(new Set<number>(value.productIds.filter((id:unknown):id is number=>Number.isSafeInteger(id))));
    return productIds.length?{productIds}:null;
  }catch{return null;}
}

export function inventoryIntakeItems<T extends object>(items:T[],productIds:number[]):T[]{
  const ids=new Set(productIds.map(String));
  return items.filter(item=>'jwProductId' in item&&item.jwProductId!==undefined&&ids.has(String(item.jwProductId)));
}
