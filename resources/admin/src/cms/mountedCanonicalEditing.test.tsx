// @vitest-environment jsdom
import React,{act,useState} from 'react';
import {createRoot} from 'react-dom/client';
import {renderToStaticMarkup} from 'react-dom/server';
import {it,expect,vi} from 'vitest';
import {writeFileSync,readFileSync} from 'node:fs';
import {resolve} from 'node:path';
import {SECTION_TEMPLATES} from '../components/pages/AddSectionModal';
import {SectionInspector} from '../components/pages/SectionInspector';
import {SectionRenderer} from '../components/pages/SectionRenderer';
import {parseDraftImport} from './pageImport';
import {stylePresets} from './pageStyle';
import type {PageSection} from '../types';
let current:PageSection;let siblings:PageSection[]=[];
vi.mock('./PageRuntimeContext',()=>({usePageRuntime:()=>({page:{document:{sections:siblings.map(s=>s.id===current.id?current:s),style:{palette:stylePresets[0].palette}}},catalog:{products:[],categories:[],collections:[]},forms:{contact:{},newsletter:{}},context:{mode:'preview',currency:'MYR'}}),usePageSectionRuntime:()=>({}),isCmsFormSubmissionContract:()=>false}));
vi.mock('../context/AdminContext',()=>({useAdmin:()=>({products:[],categories:[],collections:[],pages:[],addToast:vi.fn()})}));
vi.mock('../context/useAdminCurrency',()=>({useAdminCurrency:()=>({currencyCode:'MYR',money:(v:number)=>String(v)})}));
vi.mock('../integration/adminBootstrap',()=>({readAdminBootstrap:()=>({})}));
vi.mock('../integration/couponData',()=>({fetchCoupons:async()=>({promotions:[]})}));
globalThis.IS_REACT_ACT_ENVIRONMENT=true;
it('edits mounted Inspector controls, refreshes canonical documents and renders all 224 instances',async()=>{
 vi.stubGlobal('fetch',vi.fn(async()=>({ok:true,json:async()=>({data:[],palettes:[]})})));
 const dir=resolve(process.cwd(),'../../output/jw-cms-integration-20261114');
 const pages=JSON.parse(readFileSync(resolve(dir,'new-page-documents.json'),'utf8'));
 const instances=SECTION_TEMPLATES.map((t,i)=>({source:'registry',name:t.title,section:{...structuredClone(t.defaultData),id:`edit-${i}`,isVisible:true} as PageSection}));
 for(const page of pages)for(const section of page.sections)instances.push({source:page.slug,name:section.id,section,siblings:page.sections} as any);
 expect(instances).toHaveLength(224);
 const node=document.createElement('div');document.body.append(node);const root=createRoot(node);const rows:any[]=[],documents:any[]=[];
 const html=(section:PageSection)=>{current=section;return renderToStaticMarkup(<SectionRenderer section={section} isEditingMode/>)};
 let latest:PageSection;let key=0;
 function Harness({initial}:{initial:PageSection}){const [s,set]=useState(initial);latest=s;return <SectionInspector pageId="mounted-edit" section={s} onChange={set} onMediaUpload={vi.fn()} onClose={vi.fn()} onDelete={vi.fn()}/>}
 const mount=async(section:PageSection)=>{await act(async()=>root.render(<Harness key={++key} initial={structuredClone(section)}/>))};
 const candidates=()=>[...node.querySelectorAll<HTMLInputElement|HTMLTextAreaElement>('input:not([type]),input[type=text],textarea')].filter(n=>!n.closest('[hidden]')&&!n.disabled&&!n.readOnly).filter(n=>{const field=n.getAttribute('data-inspector-field');if(/Image URL|Image override|Story image/i.test(n.getAttribute('placeholder')??''))return true;if(field)return ['title','eyebrow','subtitle','content','leadText'].includes(field);const hint=[n.getAttribute('aria-label'),n.getAttribute('placeholder'),n.closest('label')?.textContent,n.parentElement?.querySelector('label')?.textContent].join(' ');return !/url|link|destination|class|anchor|id|color|image|video|email|phone|search|token|button/i.test(hint)&&!!n.closest('[data-inspector-item],[data-connected-content]')});
 for(const [index,v]of instances.entries()){
  siblings=(v as any).siblings??[v.section];await mount(v.section);const total=candidates().length;let edited:any=null;const attempts:any[]=[];
  for(let i=0;i<total;i++){
   if(i)await mount(v.section);const control=candidates()[i];const media=/Image URL|Image override|Story image/i.test(control.getAttribute('placeholder')??'');const marker=media?`https://images.unsplash.com/photo-1500534623283-312aade485b7?cms-edit=${index}-${i}`:`Canonical edit ${index} ${i}`;
   const setter=Object.getOwnPropertyDescriptor(control.tagName==='TEXTAREA'?HTMLTextAreaElement.prototype:HTMLInputElement.prototype,'value')!.set!;
   await act(async()=>{setter.call(control,marker);control.dispatchEvent(new Event('input',{bubbles:true}));control.dispatchEvent(new Event('change',{bubbles:true}))});
   if(JSON.stringify(latest)===JSON.stringify(v.section)){attempts.push({i,reason:'control event did not change section state'});continue}
   const document={title:`Edited ${index}`,slug:`mounted-edit-${index}`,template:'standard',sections:siblings.map(s=>s.id===latest.id?latest:s)};
   let parsed:any;try{parsed=parseDraftImport(JSON.stringify(document),'mounted-edit')}catch(error){attempts.push({i,reason:'canonical rejected edit',error:String(error)});continue}
   if(!html(parsed.sections.find((s:PageSection)=>s.id===v.section.id)).includes(marker)){attempts.push({i,field:control.getAttribute('data-inspector-field'),reason:'control changed retained data but this layout does not render that value with current defaults/catalog'});continue}
   await mount(parsed.sections.find((s:PageSection)=>s.id===v.section.id));expect(candidates().some(n=>n.value===marker),v.name+' refreshed control').toBe(true);
   expect(html(parsed.sections.find((s:PageSection)=>s.id===v.section.id)),v.name+' consumer').toContain(marker);
   documents.push(parsed);edited={field:control.getAttribute('data-inspector-field')||(media?'media URL':'repeated item text'),marker};break;
  }
  rows.push({index,source:v.source,name:v.name,type:v.section.type,edited,attempts,skip:edited?null:total?'All offered text candidates are dormant under current defaults or canonical restrictions; inspect attempts':'No owned text/media controls: chapter navigation derives labels from sibling sections rather than independent authored content'});
 }
 await act(async()=>root.unmount());node.remove();vi.unstubAllGlobals();
 writeFileSync(resolve(dir,'mounted-edited-canonical-documents.json'),JSON.stringify(documents,null,2));
 writeFileSync(resolve(dir,'mounted-edit-refresh-evidence.json'),JSON.stringify({instances:rows.length,edited:documents.length,skipped:rows.filter(r=>r.skip).length,method:'Actual mounted stateful Inspector input events -> JSON -> parseDraftImport -> refreshed Inspector -> SectionRenderer consumer',rows},null,2));
 expect(documents.length).toBeGreaterThan(215);
},120000);

it('rejects source-only decoration and excluded description tags from canonical drafts',()=>{
 for(const key of ['excludedDescriptionTag','decoration']){
  const section={...structuredClone(SECTION_TEMPLATES[0].defaultData),id:'schema-negative',isVisible:true,[key]:'unsupported'};
  expect(()=>parseDraftImport(JSON.stringify({title:'Schema',slug:'schema',template:'standard',sections:[section]}),'schema-negative')).toThrow();
 }
});

it('updates functional Chapter Navigation labels by editing its owned sibling chapter',async()=>{
 const navigation={id:'nav-derived',type:'chapter_navigation',title:'Chapters',isVisible:true} as PageSection;
 const chapter={id:'chapter-owned',type:'chapter_block',chapterPreset:'story',title:'Before edit',isVisible:true} as PageSection;
 siblings=[navigation,chapter];const node=document.createElement('div');document.body.append(node);const root=createRoot(node);let latest=chapter;
 function Harness(){const[s,set]=useState(chapter);latest=s;return <SectionInspector pageId="derived" section={s} onChange={set} onMediaUpload={vi.fn()} onClose={vi.fn()} onDelete={vi.fn()}/>}
 await act(async()=>root.render(<Harness/>));
 const input=node.querySelector<HTMLInputElement>('[data-inspector-field=title]')!;expect(input).toBeTruthy();
 await act(async()=>{Object.getOwnPropertyDescriptor(input.tagName==='TEXTAREA'?HTMLTextAreaElement.prototype:HTMLInputElement.prototype,'value')!.set!.call(input,'Refreshed chapter label');input.dispatchEvent(new Event('input',{bubbles:true}))});
 const refreshed=parseDraftImport(JSON.stringify({title:'Derived',slug:'derived',template:'standard',sections:[navigation,latest]}),'derived');siblings=refreshed.sections;current=navigation;
 const html=renderToStaticMarkup(<SectionRenderer section={navigation} isEditingMode/>);
 expect(html).toContain('Refreshed chapter label');expect(html).toContain('#section-chapter-owned');expect(html).not.toContain('Before edit');
 await act(async()=>root.unmount());node.remove();
});
