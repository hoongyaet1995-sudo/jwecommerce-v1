import React from 'react';
import {it,expect} from 'vitest';
import {renderToStaticMarkup} from 'react-dom/server';
import {websiteBuilderStarterFaq,pricingFaqSection,resolveStarterPricing} from './websiteBuilderStarterFaq';
import {SaasFaq} from '../components/pages/SaasSections';
import {sectionContentFields} from './contentCapabilities';
import {repeatedItemFields} from './repeatedItemContract';
it('creates an industry-neutral manually editable starter with no JW source or destination',()=>{
 const s=pricingFaqSection('generic');expect(s.planContentSource).toBe('manual');expect(s.pricingSourcePageId).toBeUndefined();expect(s.buttonLink).toBe('');
 Object.assign(s,{planName:'Wellness package',planAmount:150,planCurrency:'SGD',planPeriod:'/ session',planTerms:'By appointment.'});
 expect(resolveStarterPricing(s)).toMatchObject({status:'ready',name:'Wellness package',price:150,currency:'SGD',billingLabel:'/ session',terms:'By appointment.'});
 expect(resolveStarterPricing({...s,planAmount:0}).price).toBe(0);
 expect(resolveStarterPricing({...s,planPricingMode:'contact',planPriceLabel:'Request an estimate'})).toMatchObject({status:'ready',priceLabel:'Request an estimate'});
 expect(resolveStarterPricing({...s,planAmount:undefined}).status).toBe('unavailable');
});
it('reuses FAQ with editable copy/actions and stable references, without copied price',()=>{
 const s=websiteBuilderStarterFaq('f01');expect(s.type).toBe('faq_accordion');expect(s.faqPreset).toBe('corporate-starter-faq');expect(s.containerMode).toBe('page');expect(s.items).toHaveLength(4);
 expect(s).not.toHaveProperty('price');expect(s.items!.every(i=>i.price===undefined)).toBe(true);
 expect(sectionContentFields(s)).toEqual(['eyebrow','title','subtitle','content','buttonText','buttonLink']);expect(repeatedItemFields(s)).toEqual(['title','description']);
});
const document={slug:'corporate',sections:[{id:'plans',type:'pricing_table',pricingPreset:'saas-packages',isVisible:true,pricingCurrency:'MYR',items:[{id:'starter',title:'Starter',price:299,billingLabel:'/ month'}]},{id:'terms',type:'feature_cards',isVisible:true,content:'Agreed terms.'}]} as any;
it('resolves current source values and fails closed for missing sections/items',()=>{
 const s={...websiteBuilderStarterFaq('s'),pricingSourceSectionId:'plans',pricingSourceItemId:'starter',pricingSourceTermsSectionId:'terms'};
 expect(resolveStarterPricing(s,document)).toMatchObject({status:'ready',name:'Starter',price:299,billingLabel:'/ month',terms:'Agreed terms.'});
 const changed=structuredClone(document);changed.sections[0].items[0].price=321;expect(resolveStarterPricing(s,changed).price).toBe(321);
 expect(resolveStarterPricing({...s,pricingSourceItemId:'missing'},document).status).toBe('unavailable');
});
it('renders sourced pricing and native editable FAQ with first answer open',()=>{
 const s=websiteBuilderStarterFaq('s');const pricing={status:'ready' as const,name:'Starter',price:299,currency:'MYR',billingLabel:'/ month',terms:'Agreed terms.'};
 const html=renderToStaticMarkup(<SaasFaq section={s} pricing={pricing} editing/>);
 expect(html).toContain('corp-starter-faq');expect(html).toContain('299');expect(html).toContain('/ month');expect(html).toContain('Agreed terms.');expect(html.match(/<details/g)).toHaveLength(4);expect(html.match(/ open=""/g)).toHaveLength(1);expect(html).toContain('Compare plans');
 const missing=renderToStaticMarkup(<SaasFaq section={s} editing/>);expect(missing).toContain('Pricing source unavailable');expect(missing).not.toContain('299');
});

it('references authoritative add-on label verbatim without copying or parsing its price',()=>{
 const source={sections:[{id:'addons',type:'feature_cards',featureCardsPreset:'saas-addons',isVisible:true,title:'Optional add-ons',content:'Confirm limits first.',items:[{id:'addon',title:'Content studio',description:'+RM200 / month\nCampaign tools and conversion reporting.',icon:'Calendar'}]}]} as any;
 const ref={...pricingFaqSection('addon'),planContentSource:'reference',pricingSourceSectionId:'addons',pricingSourceItemId:'addon',pricingSourceTermsSectionId:'addons'} as any;
 expect(resolveStarterPricing(ref,source)).toMatchObject({status:'ready',kind:'addon',name:'Content studio',priceLabel:'+RM200 / month',description:'Campaign tools and conversion reporting.',terms:'Confirm limits first.'});
 source.sections[0].items[0].description='+RM250 / month\nUpdated description.';
 expect(resolveStarterPricing(ref,source).priceLabel).toBe('+RM250 / month');
 source.sections[0].isVisible=false;expect(resolveStarterPricing(ref,source).status).toBe('unavailable');
});

it('uses Page Settings title size for the add-on FAQ heading rather than eyebrow size',()=>{
 const s={...pricingFaqSection('a'),faqPreset:'pricing-band-faq',subtitle:'Helpful answers',typography:{title:{fontSize:40},eyebrow:{fontSize:12}}} as any;
 const html=renderToStaticMarkup(<SaasFaq section={s} pricing={{status:'ready',kind:'addon',name:'Studio',priceLabel:'+RM200 / month',terms:'Limits apply',label:'Optional add-ons'}}/>);
 expect(html).toMatch(/data-cms-text="title"[^>]*font-size:[^>]*>Helpful answers/);
 expect(html).toContain('data-pricing-kind="addon"');
});
