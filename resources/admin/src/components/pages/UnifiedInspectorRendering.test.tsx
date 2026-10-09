import React from 'react';
import {renderToStaticMarkup} from 'react-dom/server';
import {describe,it,expect,vi} from 'vitest';
import {SECTION_TEMPLATES} from './AddSectionModal';
import {SectionRenderer} from './SectionRenderer';
import {textRoles} from '../../cms/inspectorCapabilities';
import type {PageSection} from '../../types';
let section:PageSection;
vi.mock('../../cms/PageRuntimeContext',()=>({usePageRuntime:()=>({page:{document:{sections:[section]}},catalog:{products:[],categories:[],collections:[]},forms:{contact:{},newsletter:{}},context:{mode:'admin',currency:'MYR'}}),usePageSectionRuntime:()=>section.type==='payment_methods'?{payments:{methods:[{id:'paypal',title:'PayPal',description:'Payment at checkout',category:'card',status:'available'}],bankTransfer:false}}:(section.type==='delivery_table'||section.type==='chapter_block'&&section.chapterPreset==='delivery')?{delivery:{rows:[{id:'rate',title:'Region',deliveryEstimate:'Configured estimate',deliveryFee:'Calculated at checkout'}]}}:{},isCmsFormSubmissionContract:()=>false}));
describe('global Inspector rendering',()=>{
 it('renders every registered preset and reports offered text targets',()=>{
  for(const template of SECTION_TEMPLATES){
   section={...template.defaultData,id:'sample',isVisible:true};
   for(const field of ['subtitle','content'] as const)if(field in section)section[field]='Visible text';
   const html=renderToStaticMarkup(<SectionRenderer section={section}/>);
   expect(html).toContain('data-page-container');
   const rendered=[...new Set([...html.matchAll(/data-cms-text="([^"]+)"/g)].map(match=>match[1]))];
   const missing=textRoles(section).filter(role=>!rendered.includes(role));
   expect(missing,template.title+' offers only rendered text roles').toEqual([]);
  }
 });
 it('uses the same full-width outer container for every registered preset',()=>{
  for(const template of SECTION_TEMPLATES){
   section={...template.defaultData,id:'sample',isVisible:true,containerMode:'full-bleed'};
   const html=renderToStaticMarkup(<SectionRenderer section={section}/>);
   expect(html,template.title).toContain('data-container-mode="full-bleed"');
   expect(html,template.title).toContain('max-width:none');
  }
 });
 it('renders shared item typography and leaves legacy markup styles unchanged without overrides',()=>{
  section={id:'values',type:'values_list',title:'Values',subtitle:'Our values',isVisible:true,items:[{id:'one',title:'One',description:'Description'},{id:'two',title:'Two',description:'Body'}]};
  const legacy=renderToStaticMarkup(<SectionRenderer section={section}/>);
  expect(legacy).not.toContain('Georgia');
  section={...section,typography:{itemTitle:{fontFamily:'serif',color:'#6847d4',fontSize:32}}};
  const styled=renderToStaticMarkup(<SectionRenderer section={section}/>);
  expect(styled.match(/font-family:Georgia, serif/g)).toHaveLength(2);
  expect(styled).toContain('data-cms-item="two"');
 });
});
