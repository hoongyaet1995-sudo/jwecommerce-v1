import {expect,it} from 'vitest';
import {readableDefaultColor,defaultSectionColorCss} from './defaultSectionColors';
import {colorContrast,resolveStyledSection,stylePresets} from './pageStyle';
import {actionAppearanceCss} from './actionAppearance';
import type {PageSection} from '../types';
import {createNewSection} from './sectionStarterDefaults';
import {SECTION_TEMPLATES} from '../components/pages/AddSectionModal';
import {parseDraftImport} from './pageImport';
const starter:PageSection={id:'readability',type:'hero_banner',title:'Keep',isVisible:true,starterDesign:'neutral-v1',surface:'contrast',connectedLayout:'hero-split'} as PageSection;
it('keeps safe palette colors and derives readable automatic colors for both surfaces',()=>{
 for(const p of stylePresets)for(const bg of [p.palette.paper,p.palette.ink,p.palette.secondary,p.palette.accent])for(const fg of [p.palette.muted,p.palette.ink,p.palette.accent,'#ffffff'])expect(colorContrast(readableDefaultColor(fg,bg),bg)).toBeGreaterThanOrEqual(4.5);
 expect(readableDefaultColor('#18232b','#ffffff')).toBe('#18232b');
});
it('gives neutral CTA channels their own readable normal and hover colors',()=>{
 const page={palette:stylePresets[1].palette};const s=resolveStyledSection(starter,page);
 const css=actionAppearanceCss(starter,s,page);
 expect(css).toContain('[data-cms-button="primary"][data-cms-button-kind="button"]{background:#ffffff!important;color:#18232b!important;');
 expect(css).toContain(':hover{background:#183b49!important;color:#ffffff!important;opacity:1!important;}');
});
it('does not rewrite unmarked legacy styles, source data or authored colors',()=>{
 const s={...starter,typography:{content:{color:'#123456',colorOverride:true}},buttonColors:{primaryText:'#abcdef'},colorOverrides:['titleColor' as const],customColors:{titleColor:'#654321'}};
 const before=JSON.stringify(s);const page={palette:stylePresets[1].palette};
 expect(defaultSectionColorCss({...s,starterDesign:undefined},resolveStyledSection(s,page),page)).toBe('');
 const css=actionAppearanceCss(s,resolveStyledSection(s,page),page);
 expect(css).toContain('[data-cms-text="content"]{color:#123456!important;}');
 expect(css).toContain('[data-cms-button="primary"]{color:#abcdef!important;}');
 expect(css).toContain(':hover{background:#183b49!important;color:#abcdef!important;opacity:1!important;}');
 expect(css).toContain('[data-cms-text="title"]{color:#654321!important;}');
 expect(JSON.stringify(s)).toBe(before);
});
it('keeps every default rule inside its own section, including comma-separated contexts',()=>{
 const css=defaultSectionColorCss(starter,resolveStyledSection(starter,{palette:stylePresets[2].palette}),{palette:stylePresets[2].palette});
 for(const rule of css.split('\n'))expect(rule.startsWith('#section-readability')).toBe(true);
 expect(css).toContain(':where(.process-story){--cms-auto-ink:#ffffff;');
 expect(css).not.toMatch(/:where\([^{}]*\[data-cms-text[^{}]*\)\{background:/);
 expect(css).not.toContain(':where([data-cms-hero-safe] > div){background:');
});
it('uses the existing whole-image overlay for new billboard defaults, without changing its source',()=>{
 const source=SECTION_TEMPLATES[103].defaultData,before=JSON.stringify(source);
 expect(createNewSection(source,103).overlayOpacity).toBe(65);
 expect(createNewSection(SECTION_TEMPLATES[104].defaultData,104).overlayOpacity).toBe(100);
 expect(JSON.stringify(source)).toBe(before);
});
it('keeps every new image overlay within the existing canonical save/import controls',()=>{
 for(const [i,t]of SECTION_TEMPLATES.entries()){
  const section=createNewSection(t.defaultData,i);
  const document={title:'Readability',slug:'readability-'+i,description:'',template:'landing',sections:[section]};
  expect(parseDraftImport(JSON.stringify(document),'readability').sections[0]).toEqual(section);
 }
});
