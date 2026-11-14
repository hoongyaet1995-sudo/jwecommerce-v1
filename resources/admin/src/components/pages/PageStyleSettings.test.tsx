// @vitest-environment jsdom
import React from 'react';
import {it,expect,vi} from 'vitest';
import {renderToStaticMarkup} from 'react-dom/server';
import {SectionStyleSettings} from './SectionStyleSettings';
import {PageStyleSettings} from './PageStyleSettings';
import {presetStyle} from '../../cms/pageStyle';
vi.mock('./usePaletteLibrary',()=>({usePaletteLibrary:()=>({palettes:[],error:'',save:vi.fn()})}));
it('renders independent Inspector without runtime context or customColors',()=>{const html=renderToStaticMarkup(<SectionStyleSettings section={{id:'x',type:'shop_navigation',title:'Rooms',isVisible:true}} onChange={()=>{}} pagePalette={presetStyle('warm').palette}/>);expect(html).toContain('Section surface');expect(html).not.toContain('Quick Theme Presets');expect(html).not.toContain('Everyday beauty.');expect(html).toContain('Follow page')});
it('keeps image hero surface controls hidden and page settings exposes all text roles',()=>{const html=renderToStaticMarkup(<SectionStyleSettings section={{id:'x',type:'hero_slider',title:'Hero',isVisible:true}} onChange={()=>{}}/>);expect(html).not.toContain('Section surface');const page=renderToStaticMarkup(<PageStyleSettings page={{id:'x',title:'QA',slug:'qa',template:'standard',status:'draft',sections:[],createdAt:'',updatedAt:''}} onChange={()=>{}} onClose={()=>{}}/>);for(const label of ['Page colors','Typography','Default buttons','Spacing','Page content width','Search visibility','Introduction','Item descriptions'])expect(page).toContain(label)});
it('exposes existing surface choices only for the split composite Hero',()=>{
 const split=renderToStaticMarkup(<SectionStyleSettings section={{id:'x',type:'hero_banner',heroBannerPreset:'corporate-split-composite',title:'Hero',isVisible:true}} onChange={()=>{}}/>);
 const classic=renderToStaticMarkup(<SectionStyleSettings section={{id:'x',type:'hero_banner',title:'Hero',isVisible:true}} onChange={()=>{}}/>);
 expect(split).toContain('Section surface');expect(classic).not.toContain('Section surface');
});
