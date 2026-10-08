import React from 'react';
import {renderToStaticMarkup} from 'react-dom/server';
import {describe,expect,it} from 'vitest';
import {SectionRenderer} from './SectionRenderer';
import {PageRuntimeProvider,createAdminPageRuntimePayload} from '../../cms/PageRuntimeContext';
import type {CmsPage,PageSection} from '../../types';

const story:PageSection={id:'story',type:'media_text_split',title:'More than\nproducts.',isVisible:true,motionPreset:'none',
  layoutPreset:'editorial-story',mediaPosition:'left',mediaUrl:'/sample.jpg',mediaAlt:'Illustrative clothing rail',mediaFocalPoint:'right',mediaAspectRatio:'4:3',
  content:'First paragraph.\n\nSecond paragraph with <script>alert(1)</script>.',buttonText:'View collection',buttonLink:'/shop',buttonStyle:'text-link'};
function render(section:PageSection,pageTitle='Reusable section QA'){
  const page:CmsPage={id:'qa',title:pageTitle,slug:'global-section-qa',status:'draft',template:'standard',sections:[section],createdAt:'2026-10-08',updatedAt:'2026-10-08'};
  return renderToStaticMarkup(<PageRuntimeProvider payload={createAdminPageRuntimePayload(page,{products:[],categories:[],collections:[]})}><SectionRenderer section={section}/></PageRuntimeProvider>);
}
describe('global Editorial Story',()=>{
  it('renders escaped independent paragraphs and an H2 on an unrelated page',()=>{
    const html=render(story,'Shipping guide QA');
    expect(html).toContain('data-editorial-story');
    expect(html).toContain('<h2');expect(html).not.toContain('<h1');
    expect(html.match(/<p[ >]/g)).toHaveLength(2);
    expect(html).toContain('&lt;script&gt;');expect(html).not.toContain('<script>');
    expect(html).toContain('alt="Illustrative clothing rail"');expect(html).toContain('object-position:right center');
    expect(html).not.toContain('shadow-2xl');expect(html).not.toContain('rounded-3xl');
  });
  it('provides a text link or button without depending on the page title',()=>{
    const link=render(story);
    expect(link).toContain('href="/shop"');expect(link).toContain('data-editorial-action="text-link"');
    const button=render({...story,buttonStyle:'button'});
    expect(button).toContain('data-editorial-action="button"');
  });
  it('does not substitute a demo asset, blank paragraph or placeholder target',()=>{
    const html=render({...story,content:' \n\n ',mediaUrl:'',buttonLink:''});
    expect(html).not.toContain('<img');expect(html).not.toContain('<p');expect(html).not.toContain('<a ');expect(html).not.toContain('unsplash');
  });
  it('keeps the old Classic layout when no preset is selected',()=>{
    const {layoutPreset,buttonStyle,...classic}=story;
    const html=render(classic);
    expect(html).toContain('aspect-4/3 rounded-3xl');expect(html).not.toContain('data-editorial-story');
  });
  it('shares the optional CTA style with Editorial Hero',()=>{
    const html=render({...story,layoutPreset:'editorial-hero'});
    expect(html).toContain('data-editorial-action="text-link"');
  });
});
