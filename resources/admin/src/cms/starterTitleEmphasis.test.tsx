// @vitest-environment jsdom
import React from 'react';
import {renderToStaticMarkup} from 'react-dom/server';
import {expect,it,vi} from 'vitest';
import {readFileSync} from 'node:fs';
import {resolve} from 'node:path';
import {SECTION_TEMPLATES} from '../components/pages/AddSectionModal';
import {SectionRenderer} from '../components/pages/SectionRenderer';
import type {PageSection} from '../types';

vi.mock('./actionAppearance',()=>({actionAppearanceCss:()=>''}));
let section:PageSection,index=0;
const facts=JSON.parse(readFileSync(resolve(process.cwd(),'../../docs/planning/section-passport-2026-11-13/color-buttons/runtime-facts.json'),'utf8'));
vi.mock('./PageRuntimeContext',()=>({
 usePageRuntime:()=>({...facts,forms:{contact:{},newsletter:{}},page:{document:{sections:[section]}}}),
 usePageSectionRuntime:()=>facts.runtime.sections[String(index)]??{},
 isCmsFormSubmissionContract:()=>false,
}));

it('renders starter headings without forced inline emphasis',()=>{
 console.info('Starter sections rendered:',SECTION_TEMPLATES.length);
 const affected:string[]=[];
 for(const [i,template] of SECTION_TEMPLATES.entries()){
  index=i;
  section={...structuredClone(template.defaultData),id:`emphasis-audit-${i}`,isVisible:true};
  const dom=new DOMParser().parseFromString(renderToStaticMarkup(<SectionRenderer section={section} isEditingMode/>),'text/html');
  if(section.titleEmphasis?.length||dom.querySelector('[data-cms-text="title"] em,[data-cms-text="title"] mark,h1 em,h1 mark,h2 em,h2 mark,[data-cms-title-emphasis]'))affected.push(template.title);
 }
 expect(affected).toEqual([]);
});
