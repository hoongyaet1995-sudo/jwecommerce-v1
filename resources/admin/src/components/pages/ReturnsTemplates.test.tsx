// @vitest-environment jsdom
import React,{act} from 'react';
import {createRoot} from 'react-dom/client';
import {renderToStaticMarkup} from 'react-dom/server';
import {resolve} from 'node:path';
import {writeFileSync} from 'node:fs';
import {it,expect} from 'vitest';
import {returnsTemplateIds} from '../../cms/returnsTemplates';
import {createPageTemplateSections} from '../../cms/pageTemplates';
import {createAdminPageRuntimePayload,PageRuntimeProvider,readPageRuntimeBootstrap} from '../../cms/PageRuntimeContext';
import {parseDraftImport} from '../../cms/pageImport';
import {imageTargets,updateImageTarget} from '../../cms/imageTargets';
import {SectionRenderer} from './SectionRenderer';
import {PolicyContent} from './PolicyContent';
import {PolicySettings} from './PolicySettings';
const documents=returnsTemplateIds.map(id=>({title:id,slug:id,template:'standard' as const,description:'',heroImage:null,seoTitle:'',seoDescription:'',sections:createPageTemplateSections(id,{},id)}));
it('all three registered templates round trip and render their exact shared sections',()=>{
 for(const document of documents){
  expect(parseDraftImport(JSON.stringify(document),'qa').sections).toEqual(document.sections);
  expect(document.sections).toHaveLength(8);expect(document.sections.every(section=>section.motionPreset==='subtle-lift')).toBe(true);
  const payload=createAdminPageRuntimePayload({...document,heroImage:undefined,id:'qa',status:'draft',createdAt:'',updatedAt:''},{products:[],categories:[],collections:[]},'MYR',[]);
  const rootDocument=window.document.implementation.createHTMLDocument();const script=rootDocument.createElement('script');script.id='cms-page-runtime';script.textContent=JSON.stringify({...payload,context:{...payload.context,mode:'preview'}});rootDocument.body.append(script);expect(readPageRuntimeBootstrap(rootDocument)).not.toBeNull();
  const html=renderToStaticMarkup(<PageRuntimeProvider payload={payload}>{document.sections.map(section=><SectionRenderer key={section.id} section={section}/>)}</PageRuntimeProvider>);
  expect(html).toContain('data-policy-content');expect(html).toContain('#storefront-support-widget');
  for(const section of document.sections){for(const target of imageTargets(section)){expect(target.id==='mediaUrl'||target.url).toBeTruthy();}}
 }
 const chapter=documents[2].sections[3];expect(imageTargets(chapter)).toHaveLength(2);
 expect(updateImageTarget(chapter,imageTargets(chapter)[0].id,{imagePosition:'top-left'}).imageSlots?.[0].imagePosition).toBe('top-left');
 writeFileSync(resolve(process.cwd(),'../../tests/Fixtures/Cms/returns-templates.v1.json'),JSON.stringify(documents,null,2)+'\n');
});
it('tabbed guidelines switch actual content, and layout cards preserve entries',()=>{
 const section=documents[1].sections[4];const element=document.createElement('div');document.body.append(element);const root=createRoot(element);
 act(()=>root.render(<PolicyContent section={section}/>));
 const tabs=element.querySelectorAll('[role=tab]');expect(tabs).toHaveLength(3);
 act(()=>{(tabs[1] as HTMLButtonElement).click()});expect(element.querySelector('[role=tabpanel]')?.textContent).toContain('Exchange requests');expect(element.querySelector('[role=tabpanel]')?.textContent).not.toContain('Request window');
 let changed=section;act(()=>root.render(<PolicySettings section={section} mode="design" onChange={s=>{changed=s}}/>));
 expect(element.querySelectorAll('select')).toHaveLength(0);expect(element.querySelectorAll('button[aria-pressed]')).toHaveLength(9);
 act(()=>{(element.querySelectorAll('button')[4] as HTMLButtonElement).click()});expect(changed.policyPreset).toBe('timeline');expect(changed.items).toEqual(section.items);
 act(()=>root.unmount());element.remove();
});
