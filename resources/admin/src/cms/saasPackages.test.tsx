import {writeFileSync} from 'node:fs';
import {parseDraftImport} from './pageImport';
import React from 'react';
import {describe,it,expect} from 'vitest';
import {renderToStaticMarkup} from 'react-dom/server';
import {saasPriceText,validSaasPricingSection} from './saasPackages';
import {SaasPackages} from '../components/pages/SaasPackages';
import {createPageTemplateSections} from './pageTemplates';
import {repeatedItemFields} from './repeatedItemContract';
import type {PageSection} from '../types';

const section:PageSection={id:'plans',type:'pricing_table',title:'Plans',isVisible:true,pricingPreset:'saas-packages',items:[{id:'a',title:'Starter',price:299,billingLabel:'/ month',buttonText:'Explore Starter',linkUrl:'/contact'},{id:'b',title:'Custom',pricingMode:'contact',pricingLabel:"Let's talk",price:399}]};
describe('SaaS packages',()=>{
 it('roundtrips the complete template contract',()=>{const doc={title:'SaaS local acceptance',slug:'saas-local-acceptance',template:'standard',sections:createPageTemplateSections('saas-connected',{},'acceptance')};expect(parseDraftImport(JSON.stringify(doc),'qa').sections).toEqual(doc.sections);writeFileSync('../../output/jw-corporate-migration/saas-document.json',JSON.stringify(doc));});
 it('formats major units and contact labels without silently inventing zero',()=>{
  expect(saasPriceText(section.items![0],'MYR')).toContain('299');
  expect(saasPriceText(section.items![1],'MYR')).toBe("Let's talk");
  expect(saasPriceText({id:'x',title:'Incomplete'},'MYR')).toBeNull();
 });
 it('rejects wrong owners, malformed currencies and negative amounts',()=>{
  expect(validSaasPricingSection(section)).toBe(true);
  expect(validSaasPricingSection({...section,type:'feature_cards'})).toBe(false);
  expect(validSaasPricingSection({...section,pricingCurrency:'NOT-A-CURRENCY'})).toBe(false);
  expect(validSaasPricingSection({...section,items:[{id:'x',title:'Bad',price:-1}]})).toBe(false);
 });
 it('renders editable actions and contact cards without legacy billing copy',()=>{
  const html=renderToStaticMarkup(<SaasPackages section={section} currency="MYR" editing={false}/>);
  expect(html).toContain('Explore Starter');expect(html).toContain('/ month');
  expect(html).toContain('Let&#x27;s talk');expect(html).not.toContain('one-time');
  expect(html).not.toContain('399');
 });
 it('creates independent template data and exposes controls on sparse cards',()=>{
  const a=createPageTemplateSections('saas-connected',{},'a'),b=createPageTemplateSections('saas-connected',{},'b');
  const pa=a.find(s=>s.type==='pricing_table')!,pb=b.find(s=>s.type==='pricing_table')!;
  expect(pa.items).toHaveLength(4);pa.items![0].title='Changed';expect(pb.items![0].title).toBe('Starter');
  expect(pa.id).not.toBe(pb.id);expect(repeatedItemFields({...section,items:[]})).toContain('pricingMode');
 });
});
