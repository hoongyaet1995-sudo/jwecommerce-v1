import React from 'react';
import {renderToStaticMarkup} from 'react-dom/server';
import {expect,it} from 'vitest';
import {SECTION_TEMPLATES} from '../components/pages/AddSectionModal';
import {createNewSection} from './sectionStarterDefaults';
import {resolveStyledSection,stylePresets,type PageStyle} from './pageStyle';
import {sectionPreviewPayload} from './sectionPreviewRuntime';
import {PageRuntimeProvider} from './PageRuntimeContext';
import {SectionRenderer} from '../components/pages/SectionRenderer';
it('new neutral starters follow the palette even when the page preserves layout, without changing legacy sections',()=>{
 const card=SECTION_TEMPLATES.findIndex(t=>t.type==='values_list');const section=createNewSection(SECTION_TEMPLATES[card].defaultData,card);
 const style:PageStyle={palette:stylePresets[2].palette,preserveLayout:true};
 expect(resolveStyledSection(section,style).customColors?.customBgColor).toBe(style.palette.paper);
 const legacy={...section,starterDesign:undefined};const before=resolveStyledSection(legacy,{...style,palette:stylePresets[1].palette});
 expect(resolveStyledSection(legacy,style).customColors).toEqual(before.customColors);
});
it('keeps neutral Contrast hero panels on the selected surface instead of a fixed pale panel',()=>{
 const section={...createNewSection(SECTION_TEMPLATES[0].defaultData,0),surface:'contrast' as const};
 const style={palette:{...stylePresets[1].palette,contrast:'#18232b'}};
 const html=renderToStaticMarkup(<PageRuntimeProvider payload={sectionPreviewPayload(section,false,style)}><SectionRenderer section={section} isEditingMode/></PageRuntimeProvider>);
 expect(html).toContain('--soft:#18232b');
});
it('all 173 starters retain Light/Contrast and current palette through preview and serialized saved rendering',()=>{
 const style:PageStyle={palette:{...stylePresets[1].palette,contrast:'#18232b'},colorSource:'custom',preserveLayout:true};
 const baseline=JSON.stringify(SECTION_TEMPLATES);
 for(const [i,t] of SECTION_TEMPLATES.entries())for(const surface of ['light','contrast'] as const){
  const section={...createNewSection(t.defaultData,i),surface};const runtime=sectionPreviewPayload(section,false,style);
  expect(runtime.page.document.style).toEqual(style);expect(section.brandPalette).toBeUndefined();
  const html=renderToStaticMarkup(<PageRuntimeProvider payload={runtime}><SectionRenderer section={section} isEditingMode/></PageRuntimeProvider>);
  expect(html).toContain(`data-cms-surface="${surface}"`);
  const refreshed=JSON.parse(JSON.stringify(runtime));
  const saved=renderToStaticMarkup(<PageRuntimeProvider payload={refreshed}><SectionRenderer section={refreshed.page.document.sections[0]} isEditingMode/></PageRuntimeProvider>);
  expect(saved).toBe(html);
 }
 expect(JSON.stringify(SECTION_TEMPLATES)).toBe(baseline);
},120000);
