import React from 'react';
import {renderToStaticMarkup} from 'react-dom/server';
import {it,expect} from 'vitest';
import {corporateGrowthSections} from './corporateLanding';
import {repeatedItemFields} from './repeatedItemContract';
import {imageTargets} from './imageTargets';
import {CorporateFeature} from '../components/pages/CorporateSections';
it('uses one replaceable composite image and only editable external benefits',()=>{
 const sections=corporateGrowthSections('composite',{support:'/support-composite.png',marketing:'/marketing-composite.png'});
 for(const section of sections){
  expect(section.layoutPreset).toBe('corporate-image-story');
  expect(section.items).toHaveLength(3);
  expect(repeatedItemFields(section)).toEqual(['title','description','icon']);
  expect(imageTargets(section).map(t=>t.id)).toEqual(['mediaUrl']);
  const html=renderToStaticMarkup(<CorporateFeature section={section}/>);
  expect(html).not.toContain('data-corporate-workflow');expect(html).not.toContain('corp-workflow-caption');
  expect(html).toContain(section.mediaUrl);
 }
 const marketing=renderToStaticMarkup(<CorporateFeature section={sections[1]}/>);
 expect(marketing).not.toContain('Create with AI. Publish with control.');expect(marketing.match(/<a /g)).toHaveLength(1);
});
