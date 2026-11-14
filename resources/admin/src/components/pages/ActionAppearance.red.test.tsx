// @vitest-environment jsdom
import React from 'react';
import {renderToStaticMarkup} from 'react-dom/server';
import {it,expect,vi} from 'vitest';
import {SectionStyleSettings} from './SectionStyleSettings';
import {SectionButtonSettings} from './SectionButtonSettings';
vi.mock('../../cms/useCmsBrand',()=>({useCmsBrand:()=>undefined}));
it('does not offer number/label color again as a decorative Accent',()=>{
 const s:any={id:'s',type:'shop_navigation',title:'Market',isVisible:true,shopNavigationPreset:'market-hero'};
 expect(renderToStaticMarkup(<SectionStyleSettings section={s} onChange={()=>{}}/>)).not.toContain('Accent color picker');
});
it('does not offer button geometry for a navigation grid with only text/card links',()=>{
 const s:any={id:'s',type:'shop_navigation',title:'Shop',isVisible:true,buttonText:'Browse',buttonLink:'/shop'};
 expect(renderToStaticMarkup(<SectionButtonSettings section={s} onChange={()=>{}}/>)).not.toContain('Section button corners');
});
