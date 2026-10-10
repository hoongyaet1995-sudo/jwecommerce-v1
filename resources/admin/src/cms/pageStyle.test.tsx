// @vitest-environment jsdom
import React from 'react';
import {describe,it,expect} from 'vitest';
import {renderToStaticMarkup} from 'react-dom/server';
import {generatePalette,colorContrast,presetStyle,resolveStyledSection,isPageStyle} from './pageStyle';
import {parseDraftImport} from './pageImport';
import {PageRuntimeProvider,createAdminPageRuntimePayload,readPageRuntimeBootstrap} from './PageRuntimeContext';
import {SectionRenderer} from '../components/pages/SectionRenderer';
import {SECTION_TEMPLATES} from '../components/pages/AddSectionModal';
import {writeFileSync} from 'node:fs';
import {resolve} from 'node:path';
describe('shared page style contract',()=>{
 it('generates readable palettes for extreme brands',()=>{for(const primary of ['#ffffff','#000000','#ffff00','#0000ff','#ff0000','#00ff00']){const p=generatePalette(primary);expect(colorContrast(p.paper,p.muted)).toBeGreaterThanOrEqual(4.5);expect(colorContrast(p.accent,p.light)).toBeGreaterThanOrEqual(4.5)}});
 it('keeps legacy sections untouched and preserves explicit typography',()=>{const section={id:'qa',type:'rich_text' as const,title:'Heading',isVisible:true,typography:{title:{fontFamily:'inter' as const,fontSize:29}}};expect(resolveStyledSection(section)).toBe(section);const result=resolveStyledSection(section,presetStyle('warm'));expect(result.typography?.title?.fontFamily).toBe('inter');expect(result.typography?.content?.fontFamily).toBe('sans');expect(result.typography?.title?.fontSize).toBe(29)});
 it('supports surface snapshots and canonical roundtrip for every section type',()=>{const style=presetStyle('warm');const sections=SECTION_TEMPLATES.map((t,i)=>({...t.defaultData,id:'style-'+i,isVisible:true,surface:'contrast' as const,brandPalette:generatePalette('#254e5e')}));const doc={title:'Style QA',slug:'style-qa',template:'standard' as const,style,sections};expect(parseDraftImport(JSON.stringify(doc),'style-qa').style).toEqual(style);const page={...doc,id:'qa',status:'draft' as const,createdAt:'',updatedAt:''};const payload=createAdminPageRuntimePayload(page,{products:[],categories:[],collections:[]},'MYR',[]);const dom=document.implementation.createHTMLDocument();const script=dom.createElement('script');script.id='cms-page-runtime';script.textContent=JSON.stringify({...payload,context:{...payload.context,mode:'preview'}});dom.body.append(script);expect(readPageRuntimeBootstrap(dom)).not.toBeNull();for(const s of sections){const html=renderToStaticMarkup(<PageRuntimeProvider payload={payload}><SectionRenderer section={s} isEditingMode/></PageRuntimeProvider>);expect(html).toContain('data-cms-shared-style');}writeFileSync(resolve(process.cwd(),'../../output/cms-page-style/all-sections.json'),JSON.stringify(doc,null,2));});
 it('rejects unknown fonts and malformed palette state',()=>{expect(isPageStyle({...presetStyle('warm'),typography:{title:{fontFamily:'javascript:bad'}}})).toBe(false);expect(isPageStyle({...presetStyle('warm'),palette:{}})).toBe(false)});
});
