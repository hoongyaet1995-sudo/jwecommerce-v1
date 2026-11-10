// @vitest-environment jsdom
import React,{act,useState} from 'react';
import {createRoot} from 'react-dom/client';
import {renderToStaticMarkup} from 'react-dom/server';
import {it,expect,vi} from 'vitest';
import {CorporateHero,CorporateTabs,CorporateFeature} from './CorporateSections';
import {SectionInspector} from './SectionInspector';
import {corporateGrowthSections,corporateLandingSections,corporatePaymentSection,corporateCourierSection,corporateMarketplaceSection} from '../../cms/corporateLanding';
import type {PageSection} from '../../types';
import {websiteBuilderHero} from '../../cms/websiteBuilderHero';
import {websiteBuilderOverview} from '../../cms/websiteBuilderOverview';
import {websiteBuilderStory} from '../../cms/websiteBuilderStory';
import {websiteBuilderScenarios} from '../../cms/websiteBuilderScenarios';
import {websiteBuilderWorkflow} from '../../cms/websiteBuilderWorkflow';
import {websiteBuilderConnections} from '../../cms/websiteBuilderConnections';
import {websiteBuilderStarterFaq,pricingFaqSection} from '../../cms/websiteBuilderStarterFaq';
import {gradientClosing} from '../../cms/gradientClosing';
vi.mock('../../context/AdminContext',()=>({useAdmin:()=>({products:[],categories:[],collections:[],addToast:vi.fn()})}));
vi.mock('../../context/useAdminCurrency',()=>({useAdminCurrency:()=>({currencyCode:'MYR',money:(n:number)=>String(n)})}));
vi.mock('../../integration/adminBootstrap',()=>({readAdminBootstrap:()=>({})}));
vi.mock('../../integration/couponData',()=>({fetchCoupons:async()=>({promotions:[]})}));
(globalThis as {IS_REACT_ACT_ENVIRONMENT?:boolean}).IS_REACT_ACT_ENVIRONMENT=true;
import {pricingBandFaq} from '../../cms/pricingBandFaq';
it('offers bounded sparse FAQ collection, manual description and safe layout/source round trips',async()=>{
 const node=document.createElement('div');document.body.append(node);const root=createRoot(node);let latest:PageSection;
 function Editor(){const [s,set]=useState<PageSection>({...pricingBandFaq('generic'),items:[],planDescription:'A tailored consultation.',planName:'Consultation',planAmount:150,planCurrency:'SGD',planPeriod:'/ session'});latest=s;return <SectionInspector pageId="test" section={s} onChange={set} onMediaUpload={vi.fn()} onClose={vi.fn()} onDelete={vi.fn()}/>;}
 await act(async()=>root.render(<Editor/>));expect(node.querySelector<HTMLTextAreaElement>('[aria-label="Plan description"]')?.value).toBe('A tailored consultation.');expect(node.querySelector('[aria-label="Choose icon"]')).toBeNull();
 const add=()=>[...node.querySelectorAll<HTMLButtonElement>('button')].find(b=>b.textContent?.trim()==='Add Card')!;
 await act(async()=>add().click());await act(async()=>add().click());const id=latest!.items![0].id;
 await act(async()=>node.querySelector<HTMLButtonElement>('[aria-label="Move item 1 down"]')!.click());expect(latest!.items![1].id).toBe(id);
 await act(async()=>node.querySelector<HTMLButtonElement>('[title="Delete item"]')!.click());
 const select=node.querySelector<HTMLSelectElement>('[aria-label="Plan content source"]')!;
 await act(async()=>{select.value='reference';select.dispatchEvent(new Event('change',{bubbles:true}));});expect(node.querySelector('[aria-label="Plan description"]')).toBeNull();expect(node.querySelector('[aria-label="Plan amount"]')).toBeNull();expect(node.querySelector('[aria-label="Pricing page"]')).not.toBeNull();
 await act(async()=>{select.value='manual';select.dispatchEvent(new Event('change',{bubbles:true}));});
 const design=[...node.querySelectorAll<HTMLButtonElement>('[role=tab]')].find(b=>b.textContent?.toLowerCase().includes('design'))!;await act(async()=>design.click());
 await act(async()=>node.querySelector<HTMLButtonElement>('[data-choice-value="corporate-starter-faq"]')!.click());await act(async()=>node.querySelector<HTMLButtonElement>('[data-choice-value="pricing-band-faq"]')!.click());
 expect(latest!.planDescription).toBe('A tailored consultation.');expect(latest!.planAmount).toBe(150);expect(latest!.items![0].id).toBe(id);expect(node.querySelector('[title="Button Background"]')).toBeNull();
 await act(async()=>root.unmount());node.remove();
});
