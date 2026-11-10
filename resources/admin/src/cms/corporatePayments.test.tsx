import React from 'react';
import {describe,it,expect} from 'vitest';
import {renderToStaticMarkup} from 'react-dom/server';
import {corporatePaymentSection} from './corporateLanding';
import {CorporatePayments} from '../components/pages/CorporateSections';
import {imageTargets} from './imageTargets';
import {repeatedItemFields} from './repeatedItemContract';
import {sectionContentFields} from './contentCapabilities';
describe('approved Payment story',()=>{
 it('keeps editable independent logos, site alignment and one CTA',()=>{
 const a=corporatePaymentSection('a','/photo.png',Array(5).fill('/logo.png')),b=corporatePaymentSection('b');
 expect(a.layoutPreset).toBe('corporate-payments');expect(a.containerMode).toBe('page');expect(a.motionPreset).toBe('subtle-lift');
 expect(a.items).toHaveLength(5);expect(repeatedItemFields(a)).toEqual(['title','imageUrl','linkUrl']);expect(imageTargets(a)).toHaveLength(6);expect(sectionContentFields(a)).toContain('buttonLink');
 a.items![0].title='Changed';expect(b.items![0].title).toBe('PayPal');
 const html=renderToStaticMarkup(<CorporatePayments section={a}/>);expect(html).toContain('Explore Payments');expect(html).toContain('/pages/payments');expect(html).toContain('data-scroll="true"');expect(html).not.toContain('corp-workflow');
 });
 it('handles sparse content and stops animation in Builder',()=>{
 const s={...corporatePaymentSection('s'),items:[],mediaUrl:'',buttonText:'',buttonLink:''};expect(imageTargets(s).map(x=>x.id)).toEqual(['mediaUrl']);
 expect(renderToStaticMarkup(<CorporatePayments section={s} editing/>)).toContain('Add a payment photograph');
 expect(renderToStaticMarkup(<CorporatePayments section={corporatePaymentSection('e','',Array(5).fill('/logo.png'))} editing/>)).toContain('data-scroll="false"');
 });
});
