// @vitest-environment jsdom
import React,{act,useState} from 'react';
import {createRoot} from 'react-dom/client';
import {describe,it,expect} from 'vitest';
import {SectionImageSettings} from './SectionImageSettings';
import type {PageSection} from '../../types';
describe('shared Image inspector',()=>{
  it('groups image controls, shows appearance controls without an extra group and preserves unrelated content',()=>{
    (globalThis as {IS_REACT_ACT_ENVIRONMENT?:boolean}).IS_REACT_ACT_ENVIRONMENT=true;
    const node=document.createElement('div');document.body.append(node);const root=createRoot(node);let saved:PageSection={id:'s',type:'media_text_split',title:'Keep title',isVisible:true,layoutPreset:'editorial-hero',mediaFocalPoint:'bottom'};
    function Harness(){const [section,setSection]=useState(saved);return <SectionImageSettings section={section} assetControl={<button>Upload</button>} onChange={patch=>setSection(value=>(saved={...value,...patch}))}/>;}
    act(()=>root.render(<Harness/>));
    expect(node.querySelector('details:not([data-image-choice])')).toBeNull();expect(node.querySelector('[data-image-choice="Image ratio"] summary svg')).not.toBeNull();expect(node.querySelector('[data-image-choice="Image width"] summary')).not.toBeNull();expect(node.querySelectorAll('[data-image-choice="Image position"] button')).toHaveLength(9);
    expect(node.querySelector('[data-image-choice="Image position"] [data-choice-value="bottom-center"]')!.getAttribute('aria-pressed')).toBe('true');
    const ratio=node.querySelector('[data-image-choice="Image ratio"] [data-choice-value="3:4"]') as HTMLButtonElement;
    act(()=>{ratio.click();(node.querySelector('[data-image-choice="Image position"] [data-choice-value="top-right"]') as HTMLButtonElement).click();(node.querySelector('[aria-label="Image foreground"]') as HTMLInputElement).click();});
    expect(saved).toMatchObject({title:'Keep title',imageRatio:'3:4',imagePosition:'top-right',imageForeground:true,mediaFocalPoint:'bottom'});
    expect(node.querySelector('[aria-label="Image foreground opacity"]')).not.toBeNull();
    expect(node.querySelector('[aria-label="Image focal point"]')).toBeNull();act(()=>root.unmount());node.remove();
  });
});
