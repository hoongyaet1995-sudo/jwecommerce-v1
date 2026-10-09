import approvedPresets from './paymentTemplatePresets.json';
import {parseDraftImport} from './pageImport';
import React from 'react';
import {describe,it,expect} from 'vitest';
import {renderToStaticMarkup} from 'react-dom/server';
import {paymentSections,paymentTemplateIds} from './paymentTemplates';
import {createAdminPageRuntimePayload,PageRuntimeProvider,readPageRuntimeBootstrap} from './PageRuntimeContext';
import {SectionRenderer} from '../components/pages/SectionRenderer';
import {PolicyContent} from '../components/pages/PolicyContent';
import {isPaymentFacts} from './paymentInformation';
describe('Payment template contracts',()=>{
 it.each(paymentTemplateIds)('round trips %s through the shared runtime and renders actual facts',id=>{
  const sections=paymentSections(id,'payment');
  const payload=createAdminPageRuntimePayload({id:'p',title:'Payment',slug:'payment',description:'',status:'draft',template:'standard',createdAt:'',updatedAt:'',sections},{products:[],categories:[],collections:[]},'MYR');
  payload.context.mode='preview';
  parseDraftImport(JSON.stringify(payload.page.document),'p');
  const index=sections.findIndex(s=>s.type==='payment_methods');
  payload.runtime.sections[index]={payments:{methods:[{id:'paypal',title:'PayPal',description:'Pay through the provider.',category:'card',status:'available'}],bankTransfer:false}};
  expect(readPageRuntimeBootstrap({getElementById:()=>({textContent:JSON.stringify(payload)})} as unknown as Document)).not.toBeNull();
  const html=renderToStaticMarkup(<PageRuntimeProvider payload={payload}>{sections.map(s=><SectionRenderer key={s.id} section={s}/>)}</PageRuntimeProvider>);
  expect(html).toContain('PayPal');expect(html).toContain('data-payment-methods');
  expect(sections.every(s=>s.motionPreset==='subtle-lift')).toBe(true);
  expect(sections[index].items).toBeUndefined();
  expect(paymentSections(id,'another')[0].id).not.toBe(sections[0].id);
 });
 it('preserves approved spacing and creates independent instances',()=>{for(const id of paymentTemplateIds){const sections=paymentSections(id,'new');const saved=approvedPresets[id];expect(sections.map(s=>s.paddingY)).toEqual(saved.map(s=>s.paddingY));expect(sections.map(s=>s.typography)).toEqual(saved.map(s=>s.typography));expect(sections.map(s=>s.customColors)).toEqual(saved.map(s=>s.customColors));sections[0].paddingY='spacious';expect(paymentSections(id,'other')[0].paddingY).toBe(saved[0].paddingY);}expect(paymentSections('payment-editorial','new')[0].buttonLink).toBe('#section-new-methods');});
 it('uses process flow for Essential and renders every added step',()=>{const section=paymentSections('payment-essential','p').find(s=>s.policyPreset==='process-flow')!;expect(section).toBeDefined(); const html=renderToStaticMarkup(<PolicyContent section={{...section,items:[...section.items!,{id:'four',title:'Fourth step',description:'Still included'}]}}/>);expect(html).toContain('Fourth step');expect(html).toContain('>04</span>');expect((html.match(/<li /g)||[])).toHaveLength(4);expect(html).toContain('@min-[640px]:grid-cols-3');});
 it('rejects raw credentials in runtime facts',()=>{expect(isPaymentFacts({methods:[],bankTransfer:false,credentials:{secret:'x'}})).toBe(false)});
});
