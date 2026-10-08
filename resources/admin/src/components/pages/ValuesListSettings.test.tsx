// @vitest-environment jsdom
import React,{act,useState} from 'react';
import {createRoot} from 'react-dom/client';
import {it,expect} from 'vitest';
import {ValuesListSettings,ValuesListEditor} from './ValuesListSettings';
import type {PageSection} from '../../types';
it('edits standalone values and styling while preserving stable identity and unedited data',()=>{
  (globalThis as {IS_REACT_ACT_ENVIRONMENT?:boolean}).IS_REACT_ACT_ENVIRONMENT=true;
  const node=document.createElement('div');document.body.append(node);const root=createRoot(node);
  let saved:PageSection={id:'v',type:'values_list',title:'Stored heading',isVisible:true,items:[{id:'one',title:'First',icon:'Sparkles',badge:'Retained legacy'},{id:'two',title:'Second'}]};
  function Harness(){const [section,setSection]=useState(saved);const change=(patch:Partial<PageSection>)=>{saved={...section,...patch};setSection(saved)};return <><ValuesListSettings section={section} onChange={change}/><ValuesListEditor section={section} onChange={change}/></>}
  act(()=>root.render(<Harness/>));
  const click=(label:string)=>act(()=>(node.querySelector(`[aria-label="${label}"]`) as HTMLButtonElement).click());
  click('Move value 1 down');expect(saved.items?.map(item=>item.id)).toEqual(['two','one']);
  act(()=>Array.from(node.querySelectorAll('button')).find(button=>button.textContent==='Add Value')!.click());expect(saved.items).toHaveLength(3);
  click('Delete value 3');expect(saved.items).toHaveLength(2);
  click('Show section heading');expect(saved.valuesShowHeading).toBe(true);
  click('Show dividers');expect(saved.valuesShowDividers).toBe(false);expect(node.querySelector('[aria-label="Divider color"]')).toBeNull();
  act(()=>{const select=node.querySelector('select')!;select.value='spacious';select.dispatchEvent(new Event('change',{bubbles:true}));});
  expect(saved.valuesRowSpacing).toBe('spacious');expect(saved.type).toBe('values_list');expect(saved.title).toBe('Stored heading');
  expect(saved.items?.[1]).toEqual({id:'one',title:'First',icon:'Sparkles',badge:'Retained legacy'});
  act(()=>root.unmount());node.remove();
});
