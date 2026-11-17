// @vitest-environment jsdom
import {createHash} from 'node:crypto';
import React,{act} from 'react';
import {createRoot} from 'react-dom/client';
import {renderToStaticMarkup} from 'react-dom/server';
import {it,expect,vi} from 'vitest';
import {writeFileSync,readFileSync,mkdirSync} from 'node:fs';
import {resolve} from 'node:path';
import {SECTION_TEMPLATES} from '../components/pages/AddSectionModal';
import {SectionInspector} from '../components/pages/SectionInspector';
import {SectionRenderer} from '../components/pages/SectionRenderer';
import {layoutControlCapabilities} from './layoutControls';
import {stylePresets} from './pageStyle';
let current:any;let pageSpacing:any;
vi.mock('./PageRuntimeContext',()=>({usePageRuntime:()=>({page:{document:{sections:[current],style:{palette:stylePresets[0].palette,spacing:pageSpacing}}},catalog:{products:[],categories:[],collections:[]},forms:{contact:{},newsletter:{}},context:{mode:'preview',currency:'MYR'}}),usePageSectionRuntime:()=>({}),isCmsFormSubmissionContract:()=>false}));
vi.mock('../context/AdminContext',()=>({useAdmin:()=>({products:[],categories:[],collections:[],pages:[],addToast:vi.fn()})}));
vi.mock('../context/useAdminCurrency',()=>({useAdminCurrency:()=>({currencyCode:'MYR',money:(v:number)=>String(v)})}));
vi.mock('../integration/adminBootstrap',()=>({readAdminBootstrap:()=>({})}));
vi.mock('../integration/couponData',()=>({fetchCoupons:async()=>({promotions:[]})}));
globalThis.IS_REACT_ACT_ENVIRONMENT=true;
it('exports actual renderer states and page inheritance for desktop/mobile measurement',()=>{
 mkdirSync(resolve(process.cwd(),'../../output/spacing-audit-20261117'),{recursive:true});
 const rows:any[]=[];
 const render=(s:any)=>{current=s;return renderToStaticMarkup(<SectionRenderer section={s} isEditingMode/>)};
 for(const [index,t]of SECTION_TEMPLATES.entries()){
 const original:any={...structuredClone(t.defaultData),id:`space-render-${index}`,isVisible:true,motionPreset:'none'};
 const s={...original};delete s.paddingY;
 const states:any={};pageSpacing=undefined;
 for(const value of ['compact','normal','spacious'])states[value]=render({...s,paddingY:value});
 for(const value of ['compact','normal','spacious']){pageSpacing=value;expect(render(s),`${t.title} Follow Page ${value}`).toBe(states[value]);expect(render({...s,paddingY:'compact'}),`${t.title} authored override`).toBe(states.compact);}
 pageSpacing=undefined;expect(render(s)).not.toContain('data-cms-spacing=');expect(render(s)).not.toContain('--cms-section-padding:');rows.push({index,name:t.title,states,native:render(s)});
 }
 writeFileSync(resolve(process.cwd(),'../../output/spacing-audit-20261117/render-states.json'),JSON.stringify(rows));expect(rows).toHaveLength(173);
});
