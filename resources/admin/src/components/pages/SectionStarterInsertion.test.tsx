// @vitest-environment jsdom
import React,{act} from 'react';
import {createRoot} from 'react-dom/client';
import {renderToStaticMarkup} from 'react-dom/server';
import {it,expect,vi} from 'vitest';
import {writeFileSync,mkdirSync} from 'node:fs';
import {resolve} from 'node:path';
import {AddSectionModal,SECTION_TEMPLATES} from './AddSectionModal';
import {SectionRenderer} from './SectionRenderer';
import {PageRuntimeProvider} from '../../cms/PageRuntimeContext';
import {samplePayload} from '../../cms/testing/starterRuntime';
import {imageTargets,updateImageTarget} from '../../cms/imageTargets';
import {resolveStyledSection} from '../../cms/pageStyle';
import {unifiedStarterPalette} from '../../cms/sectionStarterDefaults';
import {parseDraftImport} from '../../cms/pageImport';
import type {PageSection} from '../../types';
globalThis.IS_REACT_ACT_ENVIRONMENT=true;
it('inserts all 173 cards through the real picker and exports editable canonical save fixtures',async()=>{
 const dir=resolve('../../output/section-defaults-integration-20261116');mkdirSync(dir,{recursive:true});
 const node=document.createElement('div');document.body.append(node);const root=createRoot(node);const docs:any[]=[],edited:any[]=[],report:any[]=[];
 const baseline=JSON.stringify(SECTION_TEMPLATES);
 const render=(s:PageSection)=>renderToStaticMarkup(<PageRuntimeProvider payload={samplePayload(s,false)}><SectionRenderer section={s} isEditingMode/></PageRuntimeProvider>);
 for(const [i,t]of SECTION_TEMPLATES.entries()){
  const onAdd=vi.fn();await act(async()=>root.render(<AddSectionModal key={i} isOpen onClose={()=>{}} onAdd={onAdd} categories={[]} collections={[]}/>));
  const choices=[...node.querySelectorAll<HTMLButtonElement>('button')].filter(b=>b.querySelector('h3'));
  await act(async()=>choices[i].click());
  if(t.sharedFamily)await act(async()=>[...node.querySelectorAll<HTMLButtonElement>('button')].find(b=>b.textContent==='Insert selected layout')!.click());
  expect(onAdd).toHaveBeenCalledTimes(1);const section=onAdd.mock.calls[0][0] as PageSection;
  expect(section.starterDesign).toBe('neutral-v1');expect(section.title).toBe('Lorem ipsum dolor sit amet.');
  const plain=render(section);expect(plain).toContain('data-cms-starter-design="neutral-v1"');
  expect(resolveStyledSection(section).brandPalette??unifiedStarterPalette).toEqual(unifiedStarterPalette);
  const doc={title:t.title,slug:`new-default-${i+1}`,description:'Local new defaults acceptance',template:'landing',sections:[section]};expect(parseDraftImport(JSON.stringify(doc),'local-qa').sections[0].starterDesign).toBe('neutral-v1');docs.push(doc);
  let changed={...structuredClone(section),title:`Edited section ${i+1}`,typography:{...section.typography,title:{...section.typography?.title,fontSize:52,color:'#254e5e',colorOverride:true}},...(typeof section.buttonText==='string'?{buttonText:'Edited action'}:{})};
  const target=imageTargets(changed)[0];if(target)changed=updateImageTarget(changed,target.id,{mediaUrl:'https://images.unsplash.com/photo-1497366216548-37526070297c',mediaAlt:'Employee replacement'}) as typeof changed;
  edited.push({...doc,sections:[changed]});report.push({card:i+1,name:t.title,inserted:true,rendered:true,images:imageTargets(section).length,editedHtmlChanged:render(changed)!==plain});
 }
 expect(JSON.stringify(SECTION_TEMPLATES)).toBe(baseline);
 writeFileSync(resolve(dir,'documents-173.json'),JSON.stringify(docs,null,2));writeFileSync(resolve(dir,'documents-edited-173.json'),JSON.stringify(edited,null,2));writeFileSync(resolve(dir,'insertion-render-173.json'),JSON.stringify(report,null,2));
 await act(async()=>root.unmount());node.remove();expect(docs).toHaveLength(173);
},180000);
