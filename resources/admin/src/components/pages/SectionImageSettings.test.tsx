// @vitest-environment jsdom
import React,{act,useState} from 'react';
import {createRoot} from 'react-dom/client';
import {describe,it,expect} from 'vitest';
import {SectionImageSettings} from './SectionImageSettings';
import type {PageSection} from '../../types';
describe('shared Image inspector',()=>{
  it('groups image controls, keeps advanced settings collapsed and preserves unrelated content',()=>{
    (globalThis as {IS_REACT_ACT_ENVIRONMENT?:boolean}).IS_REACT_ACT_ENVIRONMENT=true;
    const node=document.createElement('div');document.body.append(node);const root=createRoot(node);let saved:PageSection={id:'s',type:'media_text_split',title:'Keep title',isVisible:true,layoutPreset:'editorial-hero',mediaFocalPoint:'bottom'};
    function Harness(){const [section,setSection]=useState(saved);return <SectionImageSettings section={section} assetControl={<button>Upload</button>} onChange={patch=>setSection(value=>(saved={...value,...patch}))}/>;}
    act(()=>root.render(<Harness/>));
    expect(node.querySelector('details')!.open).toBe(false);expect(node.querySelectorAll('[aria-label^="Image position "]')).toHaveLength(9);
    expect(node.querySelector('[aria-label="Image position bottom-center"]')!.getAttribute('aria-pressed')).toBe('true');
    const ratio=node.querySelector('[aria-label="Image ratio"]') as HTMLSelectElement;
    act(()=>{ratio.value='3:4';ratio.dispatchEvent(new Event('change',{bubbles:true}));(node.querySelector('[aria-label="Image position top-right"]') as HTMLButtonElement).click();(node.querySelector('[aria-label="Image foreground"]') as HTMLInputElement).click();});
    expect(saved).toMatchObject({title:'Keep title',imageRatio:'3:4',imagePosition:'top-right',imageForeground:true,mediaFocalPoint:'bottom'});
    expect(node.querySelector('[aria-label="Image foreground opacity"]')).not.toBeNull();
    expect(node.querySelector('[aria-label="Image focal point"]')).toBeNull();act(()=>root.unmount());node.remove();
  });
});
