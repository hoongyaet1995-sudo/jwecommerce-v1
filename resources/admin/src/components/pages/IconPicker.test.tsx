// @vitest-environment jsdom
import React,{act} from 'react';
import {createRoot} from 'react-dom/client';
import {renderToStaticMarkup} from 'react-dom/server';
import {it,expect} from 'vitest';
import {IconPicker,SectionIcon} from './IconPicker';
import {PolicySettings} from './PolicySettings';
import type {PageSection} from '../../types';
it('selects a real icon and supports None',()=>{const el=document.createElement('div');const root=createRoot(el);let chosen='';act(()=>root.render(<IconPicker value="Package" onChange={v=>chosen=v}/>));act(()=>el.querySelector('button')!.click());act(()=>(el.querySelector('[aria-label="Select Truck icon"]') as HTMLButtonElement).click());expect(chosen).toBe('Truck');expect(renderToStaticMarkup(<SectionIcon name="Truck"/>)).toContain('lucide-truck');expect(renderToStaticMarkup(<SectionIcon name="None"/>)).toBe('');act(()=>root.unmount());});
it('adds a photo with selected upload target and adjacent uploader',()=>{const el=document.createElement('div');const root=createRoot(el);let section={id:'qa',type:'policy_content',title:'Policy',items:[]} as PageSection;let selected='';const render=()=>root.render(<PolicySettings section={section} mode="content" onChange={s=>section=s} onSelectImage={s=>selected=s} assetControl={target=><button>Upload {target.label}</button>}/>);act(render);act(()=>Array.from(el.querySelectorAll('button')).find(b=>b.textContent==='Add Image')!.click());expect(selected).toBe(`item:${section.items![0].id}:imageUrl`);act(render);expect(el.textContent).toContain('Upload Image 1');act(()=>root.unmount());});
