// @vitest-environment jsdom
import React,{act,useState} from 'react';
import {createRoot} from 'react-dom/client';
import {it,expect} from 'vitest';
import {ImageTargetInspector} from './ImageTargetInspector';
import type {PageSection} from '../../types';
it('switches one Image tool panel between stable targets and preserves independent settings',()=>{
  (globalThis as {IS_REACT_ACT_ENVIRONMENT?:boolean}).IS_REACT_ACT_ENVIRONMENT=true;
  const node=document.createElement('div');document.body.append(node);const root=createRoot(node);
  let saved:PageSection={id:'g',type:'media_gallery',title:'Gallery',isVisible:true,items:[{id:'one',title:'One',imageUrl:''},{id:'two',title:'Two',imageUrl:''}]};
  function Harness(){const [section,setSection]=useState(saved);const [selected,setSelected]=useState<string>();return <ImageTargetInspector section={section} selected={selected} onSelect={setSelected} onChange={value=>{saved=value;setSection(value)}} assetControl={target=><span>{target.id}</span>}/>;}
  act(()=>root.render(<Harness/>));
  const change=(value:string)=>act(()=>{(node.querySelector(`[aria-label="Image ratio"] [data-choice-value="${value}"]`) as HTMLButtonElement).click();});
  change('1:1');act(()=>(node.querySelector('[aria-label="Edit Image 2"]') as HTMLButtonElement).click());change('3:4');
  act(()=>(node.querySelector('[aria-label="Edit Image 1"]') as HTMLButtonElement).click());
  expect(node.querySelector('[aria-label="Image ratio"] [data-choice-value="1:1"]')?.getAttribute('aria-pressed')).toBe('true');
  expect(node.querySelectorAll('[data-section-image-settings]')).toHaveLength(1);
  expect(saved.imageSlots).toEqual([{target:'item:one:imageUrl',imageRatio:'1:1'},{target:'item:two:imageUrl',imageRatio:'3:4'}]);
  act(()=>root.unmount());node.remove();
});
