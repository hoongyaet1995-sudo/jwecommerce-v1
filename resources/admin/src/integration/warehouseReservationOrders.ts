type Reservation = {owner_type:string;owner_id:number;quantity:number;order:null|{
  id:number;number:string;status:string;channel:string;store_id:number|null;
}};

/** One selected SKU and balance; group reservation lines by their real owner. */
export function warehouseReservationOrders(rows:Reservation[],variantId:string,sku:string,warehouseId:string) {
  const orders=new Map<string,{
    id:string;orderNumber:string;channel:string;channelStoreName:string;channelStatus:string;
    allocatedWarehouse:string;items:{productId:string;sku:string;quantity:number}[];
  }>();
  for(const row of rows) {
    const order=row.order;
    if(!order||order.id!==Number(row.owner_id)||!order.number||!order.status
      ||!['order','marketplace_order'].includes(row.owner_type)
      ||(row.owner_type==='order'?order.channel!=='webstore':!['shopee','lazada'].includes(order.channel))
      ||!Number.isSafeInteger(row.quantity)||row.quantity<1)
      throw new Error('Reservation order identity requires review.');
    const key=`${row.owner_type}:${order.id}`;
    const existing=orders.get(key);
    if(existing) {
      if(existing.orderNumber!==order.number||existing.channelStatus!==order.status||existing.channel!==order.channel)
        throw new Error('Reservation order changed. Reopen the review.');
      existing.items[0].quantity+=row.quantity;
      if(!Number.isSafeInteger(existing.items[0].quantity))throw new Error('Invalid reservation quantity.');
    } else {
      orders.set(key,{id:key,orderNumber:order.number,channel:order.channel,
        channelStoreName:order.store_id===null?'JW':String(order.store_id),channelStatus:order.status,
        allocatedWarehouse:warehouseId,items:[{productId:variantId,sku,quantity:row.quantity}]});
    }
  }
  return [...orders.values()];
}
