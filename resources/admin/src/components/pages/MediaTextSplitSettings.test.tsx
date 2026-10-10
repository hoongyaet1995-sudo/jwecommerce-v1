// @vitest-environment jsdom
import React,{act,useState} from 'react';
import {createRoot,type Root} from 'react-dom/client';
import {afterEach,describe,expect,it,vi} from 'vitest';
import {MediaTextSplitSettings} from './MediaTextSplitSettings';
import type {PageSection} from '../../types';
let root:Root;
afterEach(()=>{if(root)act(()=>root.unmount());document.body.innerHTML='';});
const original:PageSection={id:'global',type:'media_text_split',title:'Saved section',isVisible:true};
function mount(initial=original){
  (globalThis as {IS_REACT_ACT_ENVIRONMENT?:boolean}).IS_REACT_ACT_ENVIRONMENT=true;
  const node=document.createElement('div');document.body.append(node);root=createRoot(node);const onChange=vi.fn();
  function Harness(){const [section,setSection]=useState(initial);return <MediaTextSplitSettings section={section} onChange={patch=>{onChange(patch);setSection(value=>({...value,...patch}));}}/>;}
  act(()=>root.render(<Harness/>));
  const choose=(label:string,value:string)=>{act(()=>(node.querySelector(`[aria-label="${label}"] [data-choice-value="${value}"]`) as HTMLButtonElement).click());};
  return {node,onChange,choose};
}
describe('global media text split settings',()=>{
  it('seeds readable text on a custom white canvas and honors existing text-color intent',()=>{
    const {onChange,choose}=mount({...original,backgroundColor:'custom',customColors:{customBgColor:'#fff'}});
    choose('Layout preset','editorial-story');
    expect(onChange).toHaveBeenLastCalledWith(expect.objectContaining({customColors:expect.objectContaining({titleColor:'#171717',contentColor:'#737373',customBgColor:'#fff'})}));
  });
  it('offers all presets without a page or template dependency and does not migrate old fields on render',()=>{
    const {node,onChange}=mount();
    expect([...node.querySelectorAll('[aria-label="Layout preset"] [data-choice-value]')].map(o=>o.getAttribute('data-choice-value'))).toEqual(['default','editorial-hero','editorial-story','editorial-story-cards','story-with-steps','editorial-cover']);
    expect(onChange).not.toHaveBeenCalled();
  });
  it('initializes absent editable content/media/actions only when the operator selects Story',()=>{
    const {onChange,choose}=mount();choose('Layout preset','editorial-story');
    expect(onChange).toHaveBeenCalledWith(expect.objectContaining({layoutPreset:'editorial-story',subtitle:'',content:'',mediaUrl:'',mediaAlt:'',mediaPosition:'left',mediaAspectRatio:'4:3',buttonText:'',buttonLink:'',buttonStyle:'button'}));
  });
  it('preserves authored data while changing preset, ratio, image position and CTA style',()=>{
    const {node,onChange,choose}=mount({...original,content:'Paragraph one.\n\nParagraph two.',mediaUrl:'/saved.jpg',mediaPosition:'right',mediaAlt:'Saved alt',mediaFocalPoint:'bottom',mediaAspectRatio:'16:9',buttonStyle:'text-link',customColors:{titleColor:'#123456'}});
    choose('Layout preset','editorial-story');choose('CTA style','button');
    choose('Layout preset','editorial-hero');expect(node.querySelector('[aria-label="Image aspect ratio"]')).toBeNull();
    choose('Layout preset','default');expect(onChange).toHaveBeenLastCalledWith({layoutPreset:'default'});
    choose('Layout preset','editorial-story');
    expect(onChange).toHaveBeenLastCalledWith(expect.objectContaining({content:'Paragraph one.\n\nParagraph two.',mediaUrl:'/saved.jpg',mediaPosition:'right',mediaAlt:'Saved alt',mediaFocalPoint:'bottom',mediaAspectRatio:'16:9',buttonStyle:'button',customColors:expect.objectContaining({titleColor:'#123456'})}));
  });
});
