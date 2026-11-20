// @vitest-environment jsdom
import React, {act} from 'react';
import {createRoot} from 'react-dom/client';
import {it,expect,vi} from 'vitest';
import {SectionStyleSettings} from './SectionStyleSettings';
import {presetStyle} from '../../cms/pageStyle';
import type {PageSection} from '../../types';
Object.assign(globalThis,{IS_REACT_ACT_ENVIRONMENT:true});
vi.mock('./usePaletteLibrary',()=>({usePaletteLibrary:()=>({palettes:[],error:''})}));
it('keeps authored colors through mounted Follow Page, Light, and Contrast controls and JSON refresh',()=>{
 const node=document.createElement('div');document.body.append(node);const root=createRoot(node);
 let section:PageSection={id:'legacy',type:'media_text_split',title:'Story',isVisible:true,customColors:{secondaryBtnBg:'#f7f3ed',customBgColor:'linear-gradient(45deg, #000000 0%, #ffffff 100%)'},colorOverrides:['customBgColor'],typography:{title:{color:'#292724'}}};
 const render=()=>root.render(<SectionStyleSettings section={section} pagePalette={presetStyle('clay').palette} onChange={next=>{section=JSON.parse(JSON.stringify(next));render()}}/>);
 act(render);
 expect(node.querySelector('summary[aria-label="Mode: Existing layout surface"]')).toBeTruthy();
 for(const mode of ['Contrast','Light','Follow Page']){const button=Array.from(node.querySelectorAll('button')).find(b=>b.textContent?.endsWith(mode))!;expect(button).toBeTruthy();act(()=>button.click());expect(section.customColors?.secondaryBtnBg).toBe('#f7f3ed');expect(section.customColors?.customBgColor).toBe('linear-gradient(45deg, #000000 0%, #ffffff 100%)');expect(section.colorOverrides).not.toContain('customBgColor');expect(section.typography?.title?.color).toBe('#292724');expect(section.surface).toBe(mode==='Follow Page'?'page':mode.toLowerCase());}
 expect(node.querySelector('button[aria-pressed="true"]')?.textContent).toContain('Follow Page');act(()=>root.unmount());node.remove();
});
