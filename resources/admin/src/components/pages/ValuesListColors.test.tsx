// @vitest-environment jsdom
import React,{act,useState} from 'react';
import {createRoot} from 'react-dom/client';
import {it,expect} from 'vitest';
import {ValuesListColors} from './ValuesListColors';
import type {PageSection} from '../../types';
it('connects color swatches and Hex fields to the same values without changing unrelated fields',()=>{
  (globalThis as {IS_REACT_ACT_ENVIRONMENT?:boolean}).IS_REACT_ACT_ENVIRONMENT=true;
  const node=document.createElement('div');document.body.append(node);const root=createRoot(node);
  let saved:PageSection={id:'v',type:'values_list',title:'Values',isVisible:true,valuesNumberColor:'#d4d4d4',valuesDividerColor:'#e5e5e5',items:[{id:'one',title:'First'}]};
  function Harness(){const [section,setSection]=useState(saved);return <ValuesListColors section={section} onChange={patch=>{saved={...section,...patch};setSection(saved)}}/>}
  act(()=>root.render(<Harness/>));
  const picker=node.querySelector('[aria-label="Number color picker"]') as HTMLInputElement;
  expect(picker.type).toBe('color');expect(picker.value).toBe('#d4d4d4');
  const setNativeValue=Object.getOwnPropertyDescriptor(HTMLInputElement.prototype,'value')!.set!;
  act(()=>{setNativeValue.call(picker,'#123456');picker.dispatchEvent(new Event('input',{bubbles:true}));picker.dispatchEvent(new Event('change',{bubbles:true}));});
  expect(saved.valuesNumberColor).toBe('#123456');expect((node.querySelector('[aria-label="Number color Hex"]') as HTMLInputElement).value).toBe('#123456');
  expect((node.querySelector('[aria-label="Divider color picker"]') as HTMLInputElement).value).toBe('#e5e5e5');
  act(()=>{const hex=node.querySelector('[aria-label="Number color Hex"]') as HTMLInputElement;setNativeValue.call(hex,'#abcdef');hex.dispatchEvent(new Event('input',{bubbles:true}));});
  expect(saved.valuesNumberColor).toBe('#abcdef');expect(picker.value).toBe('#abcdef');
  expect(saved.items).toEqual([{id:'one',title:'First'}]);
  act(()=>root.render(<ValuesListColors section={{...saved,valuesShowDividers:false}} onChange={()=>{}}/>));
  expect(node.querySelector('[aria-label="Divider color picker"]')).toBeNull();
  act(()=>root.unmount());node.remove();
});
