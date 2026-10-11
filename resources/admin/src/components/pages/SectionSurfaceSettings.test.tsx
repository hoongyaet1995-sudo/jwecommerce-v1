// @vitest-environment jsdom
import React, {act} from 'react';
import {createRoot} from 'react-dom/client';
import {it,expect,vi} from 'vitest';
import {SectionStyleSettings} from './SectionStyleSettings';
import {presetStyle} from '../../cms/pageStyle';
import type {PageSection} from '../../types';
Object.assign(globalThis,{IS_REACT_ACT_ENVIRONMENT:true});
vi.mock('./usePaletteLibrary',()=>({usePaletteLibrary:()=>({palettes:[],error:''})}));
it('keeps authored colors through mounted Default Light Contrast controls and JSON refresh',()=>{
 const node=document.createElement('div');document.body.append(node);const root=createRoot(node);
 let section:PageSection={id:'legacy',type:'media_text_split',title:'Story',isVisible:true,customColors:{secondaryBtnBg:'#f7f3ed'},typography:{title:{color:'#292724'}}};
 const render=()=>root.render(<SectionStyleSettings section={section} pagePalette={presetStyle('clay').palette} onChange={next=>{section=JSON.parse(JSON.stringify(next));render()}}/>);
 act(render);
 for(const mode of ['Contrast','Light','Default']){const button=Array.from(node.querySelectorAll('button')).find(b=>b.textContent?.endsWith(mode))!;expect(button).toBeTruthy();act(()=>button.click());expect(section.customColors?.secondaryBtnBg).toBe('#f7f3ed');expect(section.typography?.title?.color).toBe('#292724');expect(section.surface).toBe(mode==='Default'?undefined:mode.toLowerCase());}
 expect(node.querySelector('button[aria-pressed="true"]')?.textContent).toContain('Default');act(()=>root.unmount());node.remove();
});
