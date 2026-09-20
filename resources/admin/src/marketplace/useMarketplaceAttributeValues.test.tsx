// @vitest-environment jsdom
import React,{act} from 'react';
import {createRoot} from 'react-dom/client';
import {afterEach,expect,it,vi} from 'vitest';
import {useMarketplaceAttributeValues} from './useMarketplaceAttributeValues';
import type {MarketplaceAttributeField,MarketplaceAttributeValue} from './publishingAttributes/types';

globalThis.IS_REACT_ACT_ENVIRONMENT=true;
const roots:ReturnType<typeof createRoot>[]=[];
afterEach(async()=>{while(roots.length)await act(async()=>roots.pop()!.unmount());});
const field=(key:string,patch:Partial<MarketplaceAttributeField>={}):MarketplaceAttributeField=>({
 key,provider:'lazada',scope:'product',providerName:key,providerId:key,label:key,control:'text',required:true,
 options:[],allowCustom:false,maxValues:1,units:[],supported:true,...patch,
});

it('retains only descriptor-compatible values across schema identities and isolates SKU fields',async()=>{
 const seen:{values:ReadonlyMap<string,MarketplaceAttributeValue>;update:(key:string,value:MarketplaceAttributeValue)=>void}[]=[];
 const Harness=({schemaKey,fields}:{schemaKey:string;fields:MarketplaceAttributeField[]})=>{const state=useMarketplaceAttributeValues(schemaKey,fields);seen.push(state);return null;};
 const host=document.createElement('div');document.body.append(host);const root=createRoot(host);roots.push(root);
 const a=[field('lazada:product:material',{providerName:'material'}),field('lazada:variant:A:color',{scope:'variant',sku:'A',providerName:'color'})];
 await act(async()=>root.render(<Harness schemaKey="store:cat-a:rev-1" fields={a}/>));
 await act(async()=>seen.at(-1)!.update(a[0].key,{kind:'text',value:'Gold'}));
 await act(async()=>seen.at(-1)!.update(a[1].key,{kind:'text',value:'Blue'}));

 await act(async()=>root.render(<Harness schemaKey="store:cat-b:rev-2" fields={[]}/>));
 expect(seen.at(-1)!.values.size).toBe(0);
 const b=[field('lazada:product:material',{providerName:'material'}),field('lazada:variant:B:color',{scope:'variant',sku:'B',providerName:'color'}),field('lazada:product:voltage',{providerName:'voltage',control:'decimal'})];
 await act(async()=>root.render(<Harness schemaKey="store:cat-b:rev-2" fields={b}/>));
 expect(seen.at(-1)!.values.get(b[0].key)).toEqual({kind:'text',value:'Gold'});
 expect(seen.at(-1)!.values.has('lazada:variant:A:color')).toBe(false);
 expect(seen.at(-1)!.values.has(b[1].key)).toBe(false);

 const changed=[field('lazada:product:material',{providerName:'material',control:'single',options:[{id:'1',label:'Gold'}]})];
 await act(async()=>root.render(<Harness schemaKey="other-store:cat-b:rev-2" fields={changed}/>));
 expect(seen.at(-1)!.values.has(changed[0].key)).toBe(false);
});

it('does not restore a selected option when the new option list no longer permits it',async()=>{
 const seen:ReturnType<typeof useMarketplaceAttributeValues>[]=[];
 const Harness=({schemaKey,fields}:{schemaKey:string;fields:MarketplaceAttributeField[]})=>{const state=useMarketplaceAttributeValues(schemaKey,fields);seen.push(state);return null;};
 const host=document.createElement('div');document.body.append(host);const root=createRoot(host);roots.push(root);
 const old=[field('shopee:product:1',{provider:'shopee',providerId:'1',providerName:'Material',control:'single',options:[{id:'1',label:'Gold'}]})];
 await act(async()=>root.render(<Harness schemaKey="old" fields={old}/>));
 await act(async()=>seen.at(-1)!.update(old[0].key,{kind:'single',value:{id:'1',label:'Gold',custom:false}}));
 const next=[field('shopee:product:1',{provider:'shopee',providerId:'1',providerName:'Material',control:'single',options:[{id:'2',label:'Silver'}]})];
 await act(async()=>root.render(<Harness schemaKey="new" fields={next}/>));
 expect(seen.at(-1)!.values.has(next[0].key)).toBe(false);
});
