import React from 'react';
import {renderToStaticMarkup} from 'react-dom/server';
import {expect,it} from 'vitest';
import {StoreWarehouseBindingTab} from './components/marketplace/StoreWarehouseBindingTab';

it('reports saved routes without claiming activation or fabricated platform counts',()=>{
 const html=renderToStaticMarkup(<StoreWarehouseBindingTab onOpenEditBindingModal={()=>{}}
  warehouses={[{id:'9',name:'Local test',code:'QA',address:'Test address',state:'Penang'}] as any}
  bindings={[{id:'website',platform:'webstore',storeName:'JW',boundWarehouseId:'9'},
   {id:'marketplace:3',platform:'lazada',storeName:'Test store',boundWarehouseId:''}] as any}/>);
 expect(html).toContain('50% fulfillment routes mapped');
 expect(html).not.toContain('undefined');
 expect(html).not.toContain('Shopee (2 stores)');
 expect(html).not.toContain('deducted upon order creation');
 expect(html).toContain('Saving a binding does not enable');
});
