// @vitest-environment jsdom
import React,{act,useState} from 'react';
import {createRoot} from 'react-dom/client';
import {it,expect} from 'vitest';
import type {PageSection} from '../../types';
import {connectedPageSections} from '../../cms/connectedPageTemplates';
import {ConnectedFamilyContent} from './ConnectedFamilyContent';
import {ConnectedFamilySection} from './ConnectedFamilySection';
import {renderToStaticMarkup} from 'react-dom/server';
import {connectedPageIds} from '../../cms/connectedPageTemplates';
globalThis.IS_REACT_ACT_ENVIRONMENT=true;
it('edits, adds, reorders and deletes actual canonical entries from an empty group',async()=>{
 const host=document.createElement('div');document.body.append(host);const root=createRoot(host);let current:PageSection;
 const source=connectedPageSections('jw-connected-commerce',{},'editor')[1];
 function Editor(){const [section,set]=useState<PageSection>({...source,items:[]});current=section;return <><ConnectedFamilyContent section={section} onChange={set}/><ConnectedFamilySection section={section}/></>;}
 await act(async()=>root.render(<Editor/>));
 const add=()=>[...host.querySelectorAll<HTMLButtonElement>('button')].find(b=>b.textContent==='Add item')!;
 await act(async()=>add().click());expect(current!.items).toHaveLength(1);
 let input=host.querySelector<HTMLInputElement>('details details input[aria-label="title"]')!;expect(input).not.toBeNull();
 await act(async()=>{Object.getOwnPropertyDescriptor(HTMLInputElement.prototype,'value')!.set!.call(input,'First new entry');input.dispatchEvent(new Event('input',{bubbles:true}));});
 expect(current!.items![0].title).toBe('First new entry');expect(host.querySelector('.cms-connected-native')?.textContent).toContain('First new entry');
 await act(async()=>add().click());const second=current!.items![1].id;
 const down=[...host.querySelectorAll<HTMLButtonElement>('button')].find(b=>b.textContent==='Move down')!;await act(async()=>down.click());expect(current!.items![0].id).toBe(second);
 await act(async()=>host.querySelector<HTMLButtonElement>('[aria-label="Delete item"]')!.click());expect(current!.items).toHaveLength(1);expect(current!.items![0].title).toBe('First new entry');
 await act(async()=>root.unmount());host.remove();
});
it('changes the native preview tab and orbit through React state while retaining canonical entries',async()=>{
 const host=document.createElement('div');document.body.append(host);const root=createRoot(host);const sections=connectedPageSections('jw-connected-home',{},'interactive');
 await act(async()=>root.render(<ConnectedFamilySection section={sections[1]}/>));const buttons=[...host.querySelectorAll<HTMLButtonElement>('.capability-tabs button')];expect(buttons).toHaveLength(4);await act(async()=>buttons[1].click());expect(buttons[1].getAttribute('aria-selected')).toBe('true');expect(host.querySelector('.capability-preview p')?.textContent).toContain('Prepare channel-ready drafts');
 await act(async()=>root.render(<ConnectedFamilySection section={sections[5]}/>));const dots=[...host.querySelectorAll<HTMLButtonElement>('.step-dots button')];expect(dots).toHaveLength(4);await act(async()=>dots[2].click());expect(host.querySelector('.process-step.is-active h3')?.textContent).toBe('Connect');
 await act(async()=>root.unmount());host.remove();
});
it('resolves native local SVG/ARIA references and canonical page anchors without duplicate IDs',()=>{
 for(const pageId of connectedPageIds){const sections=connectedPageSections(pageId,{},pageId),doc=new DOMParser().parseFromString(sections.map(section=>`<section id="section-${section.id}">${renderToStaticMarkup(<ConnectedFamilySection section={section}/>)}</section>`).join(''),'text/html');const ids=[...doc.querySelectorAll('[id]')].map(node=>node.id);expect(new Set(ids).size).toBe(ids.length);for(const element of doc.querySelectorAll('*')){for(const attr of [...element.attributes]){const refs=[...attr.value.matchAll(/url\(#([^)]*)\)/g)].map(match=>match[1]);if(['aria-labelledby','aria-controls'].includes(attr.name))refs.push(...attr.value.split(' '));if(['href','xlink:href'].includes(attr.name)&&attr.value.startsWith('#')&&attr.value!=='#storefront-support-widget')refs.push(attr.value.slice(1));for(const ref of refs)expect(doc.getElementById(ref),`${pageId}: ${attr.name}=${attr.value}`).not.toBeNull();}}}
});
it('numbers every orbit and sticky card by current order, including appended entries',()=>{
 const sections=connectedPageSections('jw-connected-home',{},'number'),orbit=sections[5],entry=orbit.items!.find(item=>item.layoutGroup==='c0')!;orbit.items!.push({...entry,id:'fifth',title:'Fifth step'});orbit.items=orbit.items!.reverse();const doc=new DOMParser().parseFromString(renderToStaticMarkup(<ConnectedFamilySection section={orbit}/>),'text/html');expect([...doc.querySelectorAll('.process-step small')].map(node=>node.textContent)).toEqual(['01','02','03','04','05']);expect(doc.querySelector('.process-wheel>span')?.textContent).toBe('5 connected steps');expect(doc.querySelector('.process-photo strong')?.textContent).toBe('5');const stack=sections[3];stack.items=stack.items!.reverse();const stackDoc=new DOMParser().parseFromString(renderToStaticMarkup(<ConnectedFamilySection section={stack}/>),'text/html');expect([...stackDoc.querySelectorAll<HTMLElement>('.solution-card')].map(node=>node.style.getPropertyValue('--stack-index'))).toEqual(['0','1','2','3']);
});
it('keeps edited native title emphasis nonempty and renders canonical range emphasis after a long append',()=>{
 const section=connectedPageSections('jw-connected-commerce',{},'emphasis')[0];section.title+=' with a much longer customer authored heading and useful detail';let doc=new DOMParser().parseFromString(renderToStaticMarkup(<ConnectedFamilySection section={section}/>),'text/html');expect(doc.querySelector('h1')?.textContent).toBe(section.title);for(const mark of doc.querySelectorAll('h1 em,h1 mark'))expect(mark.textContent?.trim()).not.toBe('');section.titleEmphasis=[{start:0,end:8}];doc=new DOMParser().parseFromString(renderToStaticMarkup(<ConnectedFamilySection section={section}/>),'text/html');expect(doc.querySelector('[data-cms-title-emphasis]')?.textContent).toBe(section.title.slice(0,8));expect(doc.querySelector('h1')?.textContent).toBe(section.title);
});
