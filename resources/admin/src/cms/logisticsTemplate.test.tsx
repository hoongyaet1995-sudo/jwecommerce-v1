import {parseDraftImport} from './pageImport';
import React from 'react';
import {it,expect,vi} from 'vitest';
import {renderToStaticMarkup} from 'react-dom/server';
import {createPageTemplateSections,createPageTemplateStyle} from './pageTemplates';
import {repeatedItemFields} from './repeatedItemContract';
import {sectionContentFields} from './contentCapabilities';
import {IndustrialCards,IndustrialFaq} from '../components/pages/IndustrialSections';
vi.mock('../components/pages/motion/CmsMotionWrapper',()=>({MotionItem:({children}:any)=><div>{children}</div>}));
it('creates independent logistics drafts using current active container catalog only',()=>{
 const context={products:[{id:'container',title:'20ft Container',status:'active',categoryId:'c'},{id:'chair',title:'Chair',status:'active',categoryId:'x'},{id:'draft',title:'Container',status:'draft',categoryId:'c'}],categories:[{id:'c',name:'Containers',slug:'containers'}]} as any;
 const a=createPageTemplateSections('logistics-containers',context,'a'),b=createPageTemplateSections('logistics-containers',{},'b');
 expect(a).toHaveLength(8);expect(a[2].productIds).toEqual(['container']);expect(b[2].productIds).toEqual([]);
 expect(a.every(s=>s.motionPreset==='subtle-lift'&&s.sectionBorder==='none')).toBe(true);
 expect(a[1].items?.[0].id).not.toBe(b[1].items?.[0].id);a[1].items![0].title='Changed';expect(b[1].items![0].title).toBe('Container Sales');
 expect(a[4].items?.every(i=>i.description?.includes('PLACEHOLDER'))).toBe(true);expect(createPageTemplateStyle('logistics-containers')?.palette.secondary).toBe('#EF7B32');
});
it('offers sparse industrial controls and renders empty or fourth entries',()=>{
 const sparse={id:'s',type:'feature_cards',title:'Services',isVisible:true,featureCardsPreset:'industrial-cards'} as any;
 expect(repeatedItemFields(sparse)).toEqual(['title','description','icon','buttonText','linkUrl']);expect(sectionContentFields(sparse)).toContain('title');
 expect(renderToStaticMarkup(<IndustrialCards section={sparse}/>)).toContain('Services');
 const sections=createPageTemplateSections('logistics-containers',{},'a');const html=renderToStaticMarkup(<IndustrialCards section={sections[5]}/>);expect(html).toContain('Arrange delivery');expect(html).toContain('04');
 expect(renderToStaticMarkup(<IndustrialFaq section={sections[6]}/>)).toContain('aria-expanded="true"');
});

it('round trips full template import and rejects altered bundled paths',()=>{
 const document={title:'Logistics',slug:'logistics',template:'standard',style:createPageTemplateStyle('logistics-containers'),sections:createPageTemplateSections('logistics-containers',{},'import')};
 expect(parseDraftImport(JSON.stringify(document),'trusted')).toEqual(document);
 for(const url of ['/images/themes/logistics/private.png','/images/themes/logistics/../hero.png','/images/themes/logistics/hero.png?file=secret']){const changed=structuredClone(document);changed.sections[0].mediaUrl=url;expect(()=>parseDraftImport(JSON.stringify(changed),'trusted')).toThrow();}
});
