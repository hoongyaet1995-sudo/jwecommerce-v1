// @vitest-environment jsdom
import React,{act} from 'react';
import {createRoot,type Root} from 'react-dom/client';
import {afterEach,describe,expect,it,vi} from 'vitest';
import {EditorialHeroSettings} from './EditorialHeroSettings';
import type {PageSection} from '../../types';
let root:Root;
afterEach(()=>{if(root)act(()=>root.unmount());document.body.innerHTML='';});
const section:PageSection={id:'old',type:'media_text_split',title:'Saved story',isVisible:true,mediaAlt:'Saved alt',mediaFocalPoint:'bottom'};
function mount(value:PageSection){
  (globalThis as {IS_REACT_ACT_ENVIRONMENT?:boolean}).IS_REACT_ACT_ENVIRONMENT=true;
  const node=document.createElement('div');document.body.append(node);root=createRoot(node);const onChange=vi.fn();
  act(()=>root.render(<EditorialHeroSettings section={value} onChange={onChange}/>));return {node,onChange};
}
describe('Editorial Hero settings',()=>{
  it('seeds readable missing colors for a preserved dark canvas',()=>{
    const {node,onChange}=mount({...section,backgroundColor:'bg-neutral-900'});
    const select=node.querySelector('[data-choice-value="editorial-hero"]') as HTMLButtonElement;
    act(()=>{select.click();});
    expect(onChange.mock.calls[0][0]).toMatchObject({backgroundColor:'bg-neutral-900',customColors:{titleColor:'#ffffff',contentColor:'#d4d4d8'}});
  });
  it('preserves authored colors and background when a merchant switches layouts',()=>{
    const {node,onChange}=mount({...section,backgroundColor:'bg-neutral-900',textColor:'light',customColors:{titleColor:'#d4b46a',contentColor:'#aaaaaa',primaryBtnBg:'#335577'}});
    const select=node.querySelector('[data-choice-value="editorial-hero"]') as HTMLButtonElement;
    act(()=>{select.click();});
    expect(onChange.mock.calls[0][0]).toMatchObject({backgroundColor:'bg-neutral-900',textColor:'light',customColors:{titleColor:'#d4b46a',contentColor:'#aaaaaa',primaryBtnBg:'#335577'}});
  });
  it('lets an old section opt in without overwriting its existing image description or crop',()=>{
    const {node,onChange}=mount(section);
    const select=node.querySelector('[data-choice-value="editorial-hero"]') as HTMLButtonElement;
    expect(node.querySelector('[data-choice-value="default"]')!.getAttribute('aria-pressed')).toBe('true');
    act(()=>{select.click();});
    expect(onChange).toHaveBeenCalledWith(expect.objectContaining({layoutPreset:'editorial-hero',mediaAlt:'Saved alt',mediaFocalPoint:'bottom',backgroundColor:'bg-white'}));
    expect(onChange.mock.calls[0][0]).not.toHaveProperty('title');
  });
  it('keeps desktop layout separate from the shared Image crop controls',()=>{
    const {node,onChange}=mount({...section,layoutPreset:'editorial-hero'});
    expect(node.querySelector('[aria-label="Image focal point"]')).toBeNull();
    const position=node.querySelector('[aria-label="Desktop image position"] [data-choice-value="left"]') as HTMLButtonElement;
    act(()=>{position.click();});
    expect(onChange).toHaveBeenLastCalledWith({mediaPosition:'left'});
  });
  it('hides editorial controls for other section types and for the classic layout',()=>{
    const {node}=mount({...section,type:'rich_text'});expect(node.textContent).toBe('');
    act(()=>root.render(<EditorialHeroSettings section={section} onChange={()=>{}}/>));
    expect(node.querySelector('[aria-label="Image focal point"]')).toBeNull();
  });
});
