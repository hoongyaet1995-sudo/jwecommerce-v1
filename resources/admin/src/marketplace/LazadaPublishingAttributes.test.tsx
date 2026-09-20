// @vitest-environment jsdom
import React,{act} from 'react';
import {createRoot} from 'react-dom/client';
import {expect,it,vi} from 'vitest';
import {LazadaPublishingAttributes} from './LazadaPublishingAttributes';
import {lazadaPublishingFields} from '../integration/lazadaPublishingFields';
it('shows missing required fields, hides supplied values, and collapses optional attributes',async()=>{
 vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT',true);
 const fields=lazadaPublishingFields([
  {name:'package_weight',attribute_type:'sku',input_type:'numeric',is_mandatory:1},
  {name:'color_family',label:'Color',attribute_type:'sku',input_type:'singleSelect',is_mandatory:1,options:[{en_name:'Red'},{en_name:'Blue'}]},
  {name:'care',label:'Care instructions',attribute_type:'normal',input_type:'text',is_mandatory:0},
 ],{product_id:1,title:'Product',description:null,revision:'a'.repeat(64),images:[],variants:[{id:2,sku:'JW-A',price_minor:1599,initial_quantity:0,weight_grams:250}]},{});
 const change=vi.fn();const container=document.createElement('div');const root=createRoot(container);
 try{
  await act(async()=>root.render(<LazadaPublishingAttributes fields={fields} onChange={change}/>));
  expect(container.textContent).toContain('Color · JW-A');
  expect(container.textContent).not.toContain('package_weight');
  expect(container.textContent).toContain('Optional Attributes');
  const red=[...container.querySelectorAll('input[type="radio"]')].find(input=>(input.parentElement?.textContent??'').includes('Red')) as HTMLInputElement;
  await act(async()=>red.click());
  expect(change).toHaveBeenCalledWith('sku:JW-A:color_family','Red');
 }finally{await act(async()=>root.unmount());vi.unstubAllGlobals();}
});
