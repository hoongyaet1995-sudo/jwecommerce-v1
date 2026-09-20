// @vitest-environment jsdom
import React,{act} from 'react';
import {createRoot} from 'react-dom/client';
import {afterEach,expect,it,vi} from 'vitest';
import {PublishingSizeChart} from './PublishingSizeChart';

globalThis.IS_REACT_ACT_ENVIRONMENT=true;

let host:HTMLDivElement|undefined;
afterEach(()=>{host?.remove();host=undefined;});

it('uses ZIP image-card styling and returns the selected owned JW image',async()=>{
 host=document.createElement('div');document.body.append(host);
 const root=createRoot(host);const change=vi.fn();
 await act(async()=>root.render(<PublishingSizeChart platform="lazada" required source={{product_id:1,title:'Dress',description:null,revision:'a'.repeat(64),variants:[{id:1,sku:'DRESS',price_minor:100,initial_quantity:0}],images:[{id:7,path:'/storage/chart.png'},{id:8,path:'/storage/front.jpg'}]}} selectedImageId={null} onChange={change}/>));
 expect(host.textContent).toContain('Size Chart');
 expect(host.textContent).toContain('Required by this marketplace category');
 const buttons=[...host.querySelectorAll<HTMLButtonElement>('button[data-size-chart-image]')];
 expect(buttons).toHaveLength(2);
 await act(async()=>buttons[0].click());
 expect(change).toHaveBeenCalledWith(7);
 await act(async()=>root.unmount());
});
