// @vitest-environment jsdom
import React from 'react';
import {renderToStaticMarkup} from 'react-dom/server';
import {it,expect} from 'vitest';
import {writeFileSync} from 'node:fs';
import {resolve} from 'node:path';
import {contactTemplateIds} from '../../cms/contactTemplates';
import {createPageTemplateSections} from '../../cms/pageTemplates';
import {parseDraftImport} from '../../cms/pageImport';
import {createAdminPageRuntimePayload,PageRuntimeProvider,readPageRuntimeBootstrap} from '../../cms/PageRuntimeContext';
import {SectionRenderer} from './SectionRenderer';
import {safeMapEmbed,EnquiryForm} from './ContactSections';
it('three Contact templates import, bootstrap and render',()=>{const docs=contactTemplateIds.map(id=>({title:id,slug:id,template:'standard' as const,sections:createPageTemplateSections(id,{},id)}));for(const doc of docs){expect(parseDraftImport(JSON.stringify(doc),'qa').sections).toEqual(doc.sections);expect(doc.sections).toHaveLength(7);const payload=createAdminPageRuntimePayload({...doc,id:'qa',status:'draft',createdAt:'',updatedAt:''},{products:[],categories:[],collections:[]},'MYR',[]);const rootDoc=document.implementation.createHTMLDocument();const script=rootDoc.createElement('script');script.id='cms-page-runtime';script.textContent=JSON.stringify({...payload,context:{...payload.context,mode:'preview'}});rootDoc.body.append(script);expect(readPageRuntimeBootstrap(rootDoc)).not.toBeNull();const html=renderToStaticMarkup(<PageRuntimeProvider payload={payload}>{doc.sections.map(s=><SectionRenderer key={s.id} section={s}/>)}</PageRuntimeProvider>);expect(html).toContain('CONTACT');if(doc.slug==='contact-studio')expect(html).toContain('#section-contact-studio-contacts');}writeFileSync(resolve(process.cwd(),'../../tests/Fixtures/Cms/contact-templates.v1.json'),JSON.stringify(docs,null,2)+'\n');});
it('enquiry submit disabled and only HTTPS Google map embeds permitted',()=>{const html=renderToStaticMarkup(<EnquiryForm section={createPageTemplateSections('contact-guided',{},'qa')[2]}/>);expect(html).toContain('disabled=""');expect(html).not.toContain('Preview only');expect(safeMapEmbed('https://www.google.com/maps/embed?pb=test')).toBeTruthy();for(const url of ['javascript:alert(1)','https://example.com/maps/embed','https://www.google.com.evil.test/maps/embed','http://www.google.com/maps/embed','https://www.google.com/search'])expect(safeMapEmbed(url)).toBeUndefined();});
it('accepts Google Maps Copy HTML without executing its markup',()=>{const url='https://www.google.com/maps/embed?pb=!1m18!2sStar%20Avenue';expect(safeMapEmbed(`<iframe src="${url}" width="600" style="border:0" allowfullscreen></iframe>`)).toBe(url);expect(safeMapEmbed(`<iframe src='${url}&amp;language=en'></iframe>`)).toBe(url+'&language=en');expect(safeMapEmbed('<iframe src="https://example.com/maps/embed"></iframe>')).toBeUndefined();});
