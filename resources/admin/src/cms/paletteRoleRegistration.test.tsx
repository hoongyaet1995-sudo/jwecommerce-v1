import React from 'react';
import {renderToStaticMarkup} from 'react-dom/server';
import {expect,it} from 'vitest';
import {defaultSectionColorCss} from './defaultSectionColors';
import {SECTION_TEMPLATES} from '../components/pages/AddSectionModal';
import {SectionRenderer} from '../components/pages/SectionRenderer';
import {PageRuntimeProvider} from './PageRuntimeContext';
import {sectionPreviewPayload} from './sectionPreviewRuntime';
import {createNewSection} from './sectionStarterDefaults';
import {resolveStyledSection,stylePresets} from './pageStyle';
it('registers the editable Hero statistic caption with the existing item text role',()=>{
 const s=createNewSection(SECTION_TEMPLATES[0].defaultData,0);
 const html=renderToStaticMarkup(<PageRuntimeProvider payload={sectionPreviewPayload(s,false,{palette:stylePresets[0].palette})}><SectionRenderer section={s} isEditingMode/></PageRuntimeProvider>);
 const caption=html.match(/class="mini-stat"[\s\S]*?<\/div>/)?.[0]??'';
 expect(caption).toMatch(/<strong[^>]*data-cms-text="itemContent"/);
 expect(caption).toMatch(/<span[^>]*data-cms-text="itemContent"/);
});
it('uses Dark Text as a consistent missing Contrast Surface fallback for neutral starters only',()=>{
 for(const {palette} of stylePresets){
  const neutral={...createNewSection(SECTION_TEMPLATES[128].defaultData,128),surface:'contrast' as const};
  expect(resolveStyledSection(neutral,{palette}).customColors?.customBgColor).toBe(palette.ink);
  const legacy={...neutral,starterDesign:undefined};
  expect(resolveStyledSection(legacy,{palette}).customColors?.customBgColor).toBe(palette.accent);
  const custom={...neutral,customColors:{customBgColor:'#123456'},colorOverrides:['customBgColor' as const]};
  expect(resolveStyledSection(custom,{palette}).customColors?.customBgColor).toBe('#123456');
  expect(resolveStyledSection(neutral,{palette:{...palette,contrast:'#203040'}}).customColors?.customBgColor).toBe('#203040');
 }
});
it('uses registered Light Text on a safe future palette without preset-name routing',()=>{
 const palette={paper:'#f5fff4',ink:'#183726',muted:'#52745e',accent:'#256848',light:'#f3ffea',secondary:'#d5edce',secondaryInk:'#183726',hover:'#18472f',contrast:'#10251a'};
 const s={...createNewSection(SECTION_TEMPLATES[128].defaultData,128),surface:'contrast' as const};
 const css=defaultSectionColorCss(s,resolveStyledSection(s,{palette}),{palette});
 expect(css).toContain('--cms-auto-ink:#f3ffea;');
 expect(resolveStyledSection(s,{palette}).customColors?.customBgColor).toBe('#10251a');
});
