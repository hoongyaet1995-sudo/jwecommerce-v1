import {it,expect} from 'vitest';
import {SECTION_TEMPLATES} from '../components/pages/AddSectionModal';
import {createNewSection,unifiedStarterPalette} from './sectionStarterDefaults';
import {imageTargets} from './imageTargets';
import {sharedSectionFamilies,createSharedSection} from './sharedSectionLibrary';
import {applyNewSectionDefaults} from './sectionStarterDefaults';
import {typographyStyle} from './inspectorCapabilities';

it('creates all 173 new starters without modifying source factories or authored documents',()=>{
 const before=JSON.stringify(SECTION_TEMPLATES);
 for(const [index,t] of SECTION_TEMPLATES.entries()){
  const a=createNewSection(t.defaultData,index),b=createNewSection(t.defaultData,index);
  expect(a.starterDesign).toBe('neutral-v1');expect(a.title).toBe('Lorem ipsum dolor sit amet.');
  expect(a.id).not.toBe(b.id);expect(a.items?.some(i=>b.items?.some(j=>j.id===i.id))).not.toBe(true);
  expect(a.brandPalette).toBeUndefined();expect(a.containerMode).toBe('page');
  expect(a.typography?.title?.fontSize).toBe(48);expect(a.typography?.title?.fontWeight).toBe(700);
  for(const k of ['type','connectedLayout','connectedDiagram','galleryPreset','layoutPreset','heroBannerPreset','columnsCount','mediaPosition','motionPreset'] as const)expect(a[k]).toEqual(t.defaultData[k]);
  for(const target of imageTargets(a))if(target.url)expect(target.url).toMatch(/^\/images\/cms\/defaults\/neutral\/[a-z0-9-]+\.svg$/);
  expect(JSON.stringify(a)).not.toContain('/section-defaults-sample/');
 }
 expect(JSON.stringify(SECTION_TEMPLATES)).toBe(before);
});
it('normalizes every shared insert layout and retains the device discriminator and bounded labels',()=>{
 for(const f of sharedSectionFamilies)for(const l of f.layouts){const raw=createSharedSection(f.id,l.id),old=JSON.stringify(raw),s=applyNewSectionDefaults(raw);expect(s.starterDesign).toBe('neutral-v1');expect(JSON.stringify(raw)).toBe(old);}
 const device=createNewSection(SECTION_TEMPLATES[31].defaultData,31);expect(device.items?.filter(i=>i.subtitle==='device')).toHaveLength(3);
 const hero=createNewSection(SECTION_TEMPLATES[0].defaultData,0);expect(hero.items?.filter(i=>(i as any).layoutSlot?.match(/label-[0-3]$/)).map(i=>i.description)).toEqual(['LI','LI','LI','LI']);
});
it('uses approved inherited fonts only for new starters, while explicit employee font edits win',()=>{
 const source={...SECTION_TEMPLATES[0].defaultData,id:'legacy'},s=createNewSection(source,0);
 expect(String(typographyStyle(source,'title').fontFamily??'')).not.toContain('Stack Sans');
 expect(typographyStyle(s,'title').fontFamily).toContain('Stack Sans Headline');
 expect(typographyStyle(s,'content').fontFamily).toContain('Switzer');
 expect(typographyStyle({...s,typography:{...s.typography,title:{fontFamily:'serif'}}},'title').fontFamily).toBe('Georgia, serif');
});
