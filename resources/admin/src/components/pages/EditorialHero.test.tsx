import React from 'react';
import {renderToStaticMarkup} from 'react-dom/server';
import {describe, expect, it} from 'vitest';
import {SectionRenderer} from './SectionRenderer';
import {PageRuntimeProvider, createAdminPageRuntimePayload} from '../../cms/PageRuntimeContext';
import type {CmsPage, PageSection} from '../../types';

const hero: PageSection = {id:'hero',type:'media_text_split',title:'A closer look\nat JW Studio.',isVisible:true,
  content:'Sample brand story — editable content',mediaUrl:'/sample.jpg',buttonText:'Explore',buttonLink:'/shop',
  layoutPreset:'editorial-hero',mediaAlt:'Illustrative writing desk',mediaFocalPoint:'top',motionPreset:'none'};
function render(sections: PageSection[]) {
  const page: CmsPage = {id:'draft',title:'Sample',slug:'sample',status:'draft',template:'standard',sections,createdAt:'2026-10-08',updatedAt:'2026-10-08'};
  return renderToStaticMarkup(<PageRuntimeProvider payload={createAdminPageRuntimePayload(page,{products:[],categories:[],collections:[]})}>
    {sections.filter(s=>s.isVisible).map(section=><SectionRenderer key={section.id} section={section} />)}
  </PageRuntimeProvider>);
}
describe('Editorial Hero',()=>{
  it('uses the first visible section as H1, exposes authored alt/crop and a real CTA',()=>{
    const html=render([{id:'hidden',type:'rich_text',title:'Hidden',isVisible:false},hero]);
    expect(html).toContain('<h1');
    expect(html).toContain('alt="Illustrative writing desk"');
    expect(html).toContain('object-position:center top');
    expect(html).toContain('href="/shop"');
    expect(html).toContain('whitespace-pre-line');
    expect(html).not.toContain('shadow-2xl');
  });
  it('does not introduce multiple H1 headings',()=>{
    const html=render([hero,{...hero,id:'second'}]);
    expect(html.match(/<h1/g)).toHaveLength(1);
    expect(html).toContain('<h2');
  });
  it('leaves the default legacy layout unchanged',()=>{
    const {layoutPreset,mediaAlt,mediaFocalPoint,...legacy}=hero;
    const html=render([legacy]);
    expect(html).toContain('aspect-4/3 rounded-3xl');
    expect(html).toContain('shadow-2xl');
    expect(html).not.toContain('data-editorial-hero');
  });
  it('does not inject a demo image or a placeholder link when content is absent',()=>{
    const html=render([{...hero,mediaUrl:'',buttonText:''}]);
    expect(html).not.toContain('<img');
    expect(html).not.toContain('<a ');
    expect(html).not.toContain('unsplash');
  });
});
