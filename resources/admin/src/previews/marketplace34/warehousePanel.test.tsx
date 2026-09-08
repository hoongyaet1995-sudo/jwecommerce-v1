// @vitest-environment jsdom
import React from 'react';
import {renderToStaticMarkup} from 'react-dom/server';
import {it,expect} from 'vitest';
import {WarehousePanel} from './WarehousePanel';

it('renders persisted string addresses and leaves unconnected store and SKU metrics unverified',()=>{
 const html=renderToStaticMarkup(<WarehousePanel entityWarehouses={[{id:'1',code:'CYB',name:'Cyberjaya',address:'12 Jalan Test',state:'Selangor',status:'active',isDefaultMaster:false}]} onOpenAddWarehouseModal={()=>{}} onOpenEditWarehouseModal={()=>{}}/>);
 expect(html).toContain('12 Jalan Test');
 expect(html).toContain('Not verified');
 const doc=new DOMParser().parseFromString(html,'text/html');
 expect(doc.querySelector('button[title="Warehouse deletion is not enabled yet"]')?.hasAttribute('disabled')).toBe(true);
});
