import React from 'react';
import {it,expect} from 'vitest';
import {renderToStaticMarkup} from 'react-dom/server';
import {SECTION_TEMPLATES} from '../components/pages/AddSectionModal';
import {sectionContentFields} from './contentCapabilities';
import {connectedPassport,connectedImageTargets,type ConnectedSection} from './connectedFamilies';
import {repeatedItemFields} from './repeatedItemContract';
import {renderedColorFields} from './sectionDesignCapabilities';
import {SectionRenderer} from '../components/pages/SectionRenderer';
import {PageRuntimeProvider} from './PageRuntimeContext';
import {samplePayload} from './testing/starterRuntime';
const card=(n:number)=>({...structuredClone(SECTION_TEMPLATES[n-1].defaultData),id:`repair-${n}`});
const render=(s:any)=>renderToStaticMarkup(<PageRuntimeProvider payload={samplePayload(s,false)}><SectionRenderer section={s} isEditingMode/></PageRuntimeProvider>);
it('hides orphaned tab, age-card, slide and numbered-step controls without deleting values',()=>{
 const tabs=card(2),before=JSON.stringify(tabs);
 for(const key of ['buttonText','buttonLink','mediaUrl'])expect(sectionContentFields(tabs)).not.toContain(key);
 expect(connectedPassport(tabs)!.details.map(d=>d.slot)).not.toContain('tabs-composition-1-field-2');
 expect(connectedImageTargets(card(3) as ConnectedSection)).toHaveLength(1);
 expect(sectionContentFields(card(26))).not.toContain('buttonText');
 expect(sectionContentFields(card(105))).not.toContain('title');
 for(const field of ['buttonText','linkUrl','icon'])expect(repeatedItemFields(card(167))).not.toContain(field);
 expect(JSON.stringify(tabs)).toBe(before);
});
it('uses one canonical image list for both logo marquee copies',()=>{
 const s=card(7);s.items=s.items?.filter(i=>(i as any).layoutGroup==='c0').slice(0,1).map(i=>({...i,imageUrl:'/cms/repair-logo.svg'}));
 const html=render(s);expect(html.match(/src="\/cms\/repair-logo.svg"/g)).toHaveLength(2);expect(html).not.toContain('/assets/integration/');
});
it('retains all three effective spacing choices and legacy unset default on six wide layouts',()=>{
 for(const n of [62,64,65,66,67,68]){const s=card(n);const html=['compact','normal','spacious'].map(paddingY=>render({...s,paddingY}));expect(new Set(html).size,n.toString()).toBe(3);expect(render(s)).toBe(render({...s,paddingY:'spacious'}));}
});
it('keeps palette-owned card surfaces from advertising ineffective custom backgrounds',()=>{
 for(const n of [60,61])expect(renderedColorFields(card(n))).not.toContain('customBgColor');
});
