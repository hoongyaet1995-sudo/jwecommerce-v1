import React from 'react';
import {renderToStaticMarkup} from 'react-dom/server';
import {describe,it,expect} from 'vitest';
import {termsSections,termsTemplateIds} from './termsTemplates';
import {createPageTemplateSections} from './pageTemplates';
import {parseDraftImport} from './pageImport';
import {createAdminPageRuntimePayload,PageRuntimeProvider,readPageRuntimeBootstrap} from './PageRuntimeContext';
import {SectionRenderer} from '../components/pages/SectionRenderer';
import {policyLayouts} from '../components/pages/PolicySettings';
describe('Approved Terms templates',()=>{
 it.each(termsTemplateIds)('registers and round trips %s',id=>{
  const sections=createPageTemplateSections(id,{},'terms');
  expect(sections).toEqual(termsSections(id,'terms'));
  const page={id:'terms',title:'Terms',slug:'terms',description:'',status:'draft' as const,template:'standard' as const,createdAt:'',updatedAt:'',sections};
  const payload=createAdminPageRuntimePayload(page,{products:[],categories:[],collections:[]},'MYR');payload.context.mode='preview';
  parseDraftImport(JSON.stringify(payload.page.document),'terms');
  expect(readPageRuntimeBootstrap({getElementById:()=>({textContent:JSON.stringify(payload)})} as unknown as Document)).not.toBeNull();
  const html=renderToStaticMarkup(<PageRuntimeProvider payload={payload}>{sections.map(s=><SectionRenderer section={s} key={s.id}/>)}</PageRuntimeProvider>);
  expect(html).toContain('Products &amp; pricing');expect(html).toContain('Sample terms');
  expect(html).not.toContain('mailto:hello@example.com');
  expect(sections.every(s=>s.motionPreset==='subtle-lift'&&s.containerMode==='page')).toBe(true);
  expect(sections.flatMap(s=>s.items??[]).filter(i=>i.description)).toHaveLength(7);
  if(id==='terms-guided'){expect(html).toContain('Policy directory');expect(html).toContain('aria-expanded="false"');}
  if(id==='terms-chapters'){expect(html).toContain('#section-terms-order');expect(sections.filter(s=>s.type==='chapter_block')).toHaveLength(3);}
 });
 it('offers document and directory cards and preserves independent data',()=>{
  expect(policyLayouts.map(([id])=>id)).toContain('directory');expect(policyLayouts.map(([id])=>id)).toContain('document');
  const one=termsSections('terms-guided','one');one[1].items![0].description='Changed';
  expect(termsSections('terms-guided','two')[1].items![0].description).not.toBe('Changed');
 });
});
