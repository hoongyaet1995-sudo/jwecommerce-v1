import {expect,it} from 'vitest';
import {warehouseReservationOrders} from './warehouseReservationOrders';

it('groups multiple lines without colliding website and marketplace owner IDs',()=>{
 const website={owner_type:'order',owner_id:1,quantity:2,order:{id:1,number:'JW-1',status:'pending',channel:'webstore',store_id:null}};
 const marketplace={owner_type:'marketplace_order',owner_id:1,quantity:3,order:{id:1,number:'LZ-1',status:'packed',channel:'lazada',store_id:3}};
 const result=warehouseReservationOrders([website,{...website,quantity:1},marketplace],'102','SKU','4');
 expect(result).toHaveLength(2);
 expect(result[0]).toMatchObject({id:'order:1',items:[{quantity:3}],allocatedWarehouse:'4'});
 expect(result[1]).toMatchObject({id:'marketplace_order:1',items:[{quantity:3}]});
 expect(()=>warehouseReservationOrders([{...website,order:null}],'102','SKU','4')).toThrow('identity');
 expect(()=>warehouseReservationOrders([website,{...website,order:{...website.order,status:'cancelled'}}],'102','SKU','4')).toThrow('changed');
});
