// @vitest-environment jsdom
import React,{act} from 'react';
import {createRoot} from 'react-dom/client';
import {afterEach,beforeEach,describe,expect,it,vi} from 'vitest';
import {MarketplaceAttributeControl} from './MarketplaceAttributeControl';
import type {MarketplaceAttributeField,MarketplaceAttributeValue} from './publishingAttributes/types';

const mounted:ReturnType<typeof createRoot>[]=[];
beforeEach(()=>vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT',true));
afterEach(async()=>{while(mounted.length)await act(async()=>mounted.pop()!.unmount());vi.unstubAllGlobals();});
const field=(patch:Partial<MarketplaceAttributeField>):MarketplaceAttributeField=>({
 key:'shopee:product:1',provider:'shopee',scope:'product',providerName:'battery',providerId:'1',
 label:'Battery Capacity',control:'text',required:true,options:[],allowCustom:false,maxValues:1,
 units:[],supported:true,...patch,
});
const render=(node:React.ReactNode)=>{const host=document.createElement('div');document.body.append(host);const root=createRoot(host);mounted.push(root);act(()=>root.render(node));return host;};
const click=(element:Element)=>act(()=>element.dispatchEvent(new MouseEvent('click',{bubbles:true})));

describe('MarketplaceAttributeControl',()=>{
 it('renders native numeric, textarea, and date semantics with source control classes',()=>{
  const onChange=vi.fn();
  const host=render(<div>
   <MarketplaceAttributeControl field={field({control:'decimal',units:['mAh']})} value={{kind:'number',value:'800'}} unit="mAh" onChange={onChange}/>
   <MarketplaceAttributeControl field={field({key:'lazada:product:description',provider:'lazada',providerId:'description',providerName:'description',label:'Description',control:'textarea'})} value={{kind:'text',value:'Saved'}} onChange={onChange}/>
   <MarketplaceAttributeControl field={field({key:'lazada:product:date',provider:'lazada',providerId:'date',providerName:'date',label:'Manufacture Date',control:'date'})} value={{kind:'date',value:'2026-09-20'}} onChange={onChange}/>
  </div>);
  expect(host.querySelector<HTMLInputElement>('input[aria-label="Battery Capacity"]')?.step).toBe('any');
  expect(host.querySelector('textarea[aria-label="Description"]')).not.toBeNull();
  expect(host.querySelector<HTMLInputElement>('input[aria-label="Manufacture Date"]')?.type).toBe('date');
  expect(host.querySelector<HTMLSelectElement>('select[aria-label="Battery Capacity unit"]')?.value).toBe('mAh');
 });

 it('uses radio cards for binary values and searchable options for larger sets',()=>{
  const onChange=vi.fn();
  const host=render(<div>
   <MarketplaceAttributeControl field={field({key:'shopee:product:2',providerId:'2',label:'Warranty',control:'single',options:[{id:'1',label:'Yes'},{id:'2',label:'No'}]})} value={{kind:'empty'}} onChange={onChange}/>
   <MarketplaceAttributeControl field={field({key:'shopee:product:3',providerId:'3',label:'Bluetooth Version',control:'single',options:[{id:'1',label:'5.1'},{id:'2',label:'5.2'},{id:'3',label:'Bluetooth 5.3'}]})} value={{kind:'empty'}} onChange={onChange}/>
  </div>);
  expect(host.querySelectorAll('input[type="radio"]')).toHaveLength(2);
  click([...host.querySelectorAll('button')].find(button=>button.textContent?.includes('Select Bluetooth Version'))!);
  click([...host.querySelectorAll('button')].find(button=>button.textContent?.trim()==='Bluetooth 5.3')!);
  expect(onChange).toHaveBeenCalledWith({kind:'single',value:{id:'3',label:'Bluetooth 5.3',custom:false}});
 });

 it('adds custom multi values as chips and reports unsupported mandatory fields',()=>{
  const onChange=vi.fn();
  const host=render(<div>
   <MarketplaceAttributeControl field={field({key:'shopee:product:4',providerId:'4',label:'Compatibility',control:'multi',allowCustom:true,maxValues:3,options:[{id:'1',label:'Mobile'}]})} value={{kind:'multi',values:[{id:'1',label:'Mobile',custom:false}]}} onChange={onChange}/>
   <MarketplaceAttributeControl field={field({key:'lazada:product:file',provider:'lazada',providerId:'file',label:'Certificate',supported:false,unsupportedReason:'file attributes cannot be completed yet.'})} value={{kind:'empty'}} error="Certificate cannot be completed yet." onChange={onChange}/>
  </div>);
  expect(host.textContent).toContain('Mobile');
  expect(host.textContent).toContain('Certificate cannot be completed yet.');
  expect(host.querySelector('[data-unsupported="true"]')).not.toBeNull();
 });
});
