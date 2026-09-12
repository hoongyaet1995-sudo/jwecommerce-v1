import React,{useEffect,useState,useRef} from 'react';
import {initialCountApi} from './initialCountApi';
import {InventoryAdjustmentModal} from './components/marketplace/InventoryAdjustmentModal';
import {countReviewApi} from './countReviewApi';

export function PhysicalCountModal(props: any) {
  // A fresh component owns each SKU's asynchronous work.
  if (!props.isOpen || !props.item) return null;
  return <LoadedCountModal key={props.item.id} {...props}/>;
}

function LoadedCountModal(props: any) {
  const [rows,setRows]=useState<any[]>([]);
  const [message,setMessage]=useState('Loading physical inventory balances…');
  const [loaded,setLoaded]=useState(false);
  const initialApis=useRef(new Map<string,ReturnType<typeof initialCountApi>>());
  useEffect(()=>{
    const abort=new AbortController();
    async function load() {
      const all:any[]=[];
      let total:number|undefined;
      for(let page=1;page<=100;page++) {
        const response=await fetch(`/admin/api/inventory/balances?variantId=${encodeURIComponent(props.item.id)}&page=${page}`,
          {credentials:'same-origin',signal:abort.signal,headers:{Accept:'application/json'}});
        if(!response.ok)throw new Error('Physical balances could not be loaded. Close and try again.');
        const result=await response.json();
        if(!Array.isArray(result.data)||result.current_page!==page||!Number.isSafeInteger(result.total)
          || !Number.isSafeInteger(result.last_page) || result.last_page<page || result.last_page>100
          || (total!==undefined && total!==result.total)) throw new Error('Inventory balances changed. Reload before reviewing.');
        total=result.total;
        all.push(...result.data);
        if(page===result.last_page)break;
      }
      if(all.length!==total || new Set(all.map(row=>row.balance_id)).size!==all.length
        || all.some(row=>row.variant_id!==Number(props.item.id))) throw new Error('Inventory balances were incomplete.');
      const shared=all.filter(row=>row.pool_kind==='shared');
      const warehouses=all.filter(row=>row.pool_kind==='warehouse');
      if(shared.length>1 || (shared.length && warehouses.length) || shared.length+warehouses.length!==all.length)
        throw new Error('Inventory mode requires reconciliation before selecting a physical balance.');
      if(props.inventoryMode==='independent_warehouses'){
        for(const warehouse of props.warehouses??[]){
          if(warehouse.status!=='active'||all.some(row=>String(row.warehouse_id)===String(warehouse.id)))continue;
          all.push({balance_id:null,variant_id:Number(props.item.id),pool_kind:'warehouse',warehouse_id:Number(warehouse.id),warehouse_code:warehouse.code,warehouse_name:warehouse.name,
            physical_stock:null,reserved_stock:null,safety_stock:null,atp:null});
        }
      }else if(props.inventoryMode==='shared_pool'&&all.length===0){
        all.push({balance_id:null,variant_id:Number(props.item.id),pool_kind:'shared',physical_stock:null,reserved_stock:null,safety_stock:null,atp:null});
      }
      if(abort.signal.aborted)return;
      setRows(all);setLoaded(true);
      setMessage(all.length ? '' : 'No physical balance is configured for this SKU. Configure the inventory pool and warehouse first.');
    }
    load().catch(error=>{if(!abort.signal.aborted)setMessage(error.message);});
    return()=>abort.abort();
  },[props.item.id,props.inventoryMode,props.warehouses]);
  const shared=rows.find(row=>row.pool_kind==='shared');
  const csrf=document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
  const root=document.getElementById('root');
  const staff={id:root?.dataset.employeeId ?? '',fullName:root?.dataset.employeeName ?? '',username:root?.dataset.employeeName ?? '',position:'Admin'};
  const item={...props.item,physicalStockStatus:shared?.physical_stock!=null?'verified':'unknown',
    totalPhysicalStock:shared?.physical_stock ?? null,reservedStock:shared?.basic_wms ? shared.basic_wms.owned_reserved : shared?.reserved_stock ?? null,
    safetyStock:shared?.safety_stock ?? null,availableStock:shared?.basic_wms ? shared.basic_wms.available : shared?.atp ?? null,
    warehouseBalances:rows.filter(row=>row.pool_kind==='warehouse').map(row=>({
      warehouseId:String(row.warehouse_id),warehouseCode:row.warehouse_code,warehouseName:row.warehouse_name,
      binLocation:row.bin_location,physicalStock:row.physical_stock,reservedStock:row.basic_wms ? row.basic_wms.owned_reserved : row.reserved_stock,
      safetyStock:row.safety_stock,availableAtp:row.basic_wms ? row.basic_wms.available : row.atp,physicalStockStatus:row.physical_stock===null?'unknown':'verified',
    }))};
  return <InventoryAdjustmentModal {...props} key={loaded?'loaded':'loading'} item={item}
    basicAvailability={rows.length>0 && rows.every(row=>Boolean(row.basic_wms))}
    staffUsers={staff.id?[staff]:[]} currentUserName={staff.fullName} availabilityMessage={message}
    countApiForWarehouse={(warehouseId)=>{
      const row=shared ?? rows.find(row=>String(row.warehouse_id)===warehouseId);
      if(row&&row.balance_id===null){
        const key=row.pool_kind==='shared'?'shared':String(row.warehouse_id);
        if(!initialApis.current.has(key))initialApis.current.set(key,initialCountApi(Number(props.item.id),row.pool_kind,row.warehouse_id??null,csrf));
        return initialApis.current.get(key);
      }
      return row ? countReviewApi(row.balance_id,csrf) : undefined;
    }}/>;
}
