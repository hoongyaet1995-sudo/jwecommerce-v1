// @vitest-environment jsdom
import React,{act,useState} from 'react';
import {createRoot} from 'react-dom/client';
import {expect,it,vi} from 'vitest';
import {MarketplaceAttributeSections} from './MarketplaceAttributeSections';
import type {MarketplaceAttributeField,MarketplaceAttributeValue} from './publishingAttributes/types';

const fields:MarketplaceAttributeField[]=Array.from({length:8},(_,index)=>({
 key:`lazada:product:optional_${index+1}`,provider:'lazada',scope:'product',providerName:`optional_${index+1}`,
 providerId:`optional_${index+1}`,label:`Optional ${index+1}`,control:'text',required:false,options:[],
 allowCustom:false,maxValues:1,units:[],supported:true,
}));

it('updates the optional filled count and preserves values while collapsed',async()=>{
 vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT',true);
 const initial=new Map<string,MarketplaceAttributeValue>(fields.slice(0,4).map(field=>[field.key,{kind:'text',value:field.label}]));
 function Probe(){
  const [values,setValues]=useState(initial);
  return <MarketplaceAttributeSections fields={fields} values={values} errors={{}} onChange={(key,value)=>setValues(old=>new Map(old).set(key,value))}/>;
 }
 const host=document.createElement('div');document.body.append(host);const root=createRoot(host);
 try{
  await act(async()=>root.render(<Probe/>));
  expect(host.textContent).toContain('4 of 8 filled');
  const input=host.querySelector<HTMLInputElement>('input[aria-label="Optional 5"]')!;
  await act(async()=>{const setter=Object.getOwnPropertyDescriptor(HTMLInputElement.prototype,'value')!.set!;setter.call(input,'new');input.dispatchEvent(new Event('input',{bubbles:true}));});
  expect(host.textContent).toContain('5 of 8 filled');
  const collapse=[...host.querySelectorAll('button')].find(button=>button.textContent?.includes('Collapse'))!;
  await act(async()=>collapse.dispatchEvent(new MouseEvent('click',{bubbles:true})));
  expect(host.querySelector('input[aria-label="Optional 5"]')).toBeNull();
  const expand=[...host.querySelectorAll('button')].find(button=>button.textContent?.includes('Expand Form'))!;
  await act(async()=>expand.dispatchEvent(new MouseEvent('click',{bubbles:true})));
  expect(host.querySelector<HTMLInputElement>('input[aria-label="Optional 5"]')?.value).toBe('new');
 }finally{await act(async()=>root.unmount());host.remove();vi.unstubAllGlobals();}
});
