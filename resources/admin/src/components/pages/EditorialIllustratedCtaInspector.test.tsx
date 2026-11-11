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
import {editorialIllustratedCta} from '../../cms/illustratedCta';
it('offers sparse foreground image and one CTA, hides unrelated controls and retains content across layout switches',async()=>{
 const node=document.createElement('div');document.body.append(node);const root=createRoot(node);let latest:PageSection;
 function Editor(){const [s,set]=useState<PageSection>({...editorialIllustratedCta('qa'),mediaUrl:'/test.webp',imageSlots:[{target:'mediaUrl',mediaAlt:'An authored illustration'}],buttonText:'Book now',buttonLink:'/contact'});latest=s;return <SectionInspector pageId="test" section={s} onChange={set} onMediaUpload={vi.fn()} onClose={vi.fn()} onDelete={vi.fn()}/>;}
 await act(async()=>root.render(<Editor/>));expect(node.querySelector('[data-image-target-inspector]')).not.toBeNull();expect(node.textContent).toContain('The illustration stays fully visible.');expect(node.querySelector<HTMLInputElement>('[placeholder="https://images.unsplash.com/... or upload"]')?.value).toBe('/test.webp');expect(node.querySelector<HTMLInputElement>('[placeholder="e.g. Shop Now"]')?.value).toBe('Book now');expect(node.querySelector('[placeholder="e.g. Learn More"]')).toBeNull();expect(node.querySelector('[aria-label="Choose icon"]')).toBeNull();expect(node.querySelector('[placeholder="e.g. THE DESIGN MANIFESTO"]')).not.toBeNull();expect([...node.querySelectorAll('button')].some(b=>b.textContent?.trim()==='Add Card')).toBe(false);
 const design=[...node.querySelectorAll<HTMLButtonElement>('[role=tab]')].find(b=>b.textContent?.toLowerCase().includes('design'))!;await act(async()=>design.click());await act(async()=>node.querySelector<HTMLButtonElement>('[data-choice-value="default"]')!.click());await act(async()=>node.querySelector<HTMLButtonElement>('[data-choice-value="editorial-illustrated"]')!.click());expect(latest!.mediaUrl).toBe('/test.webp');expect(latest!.imageSlots).toEqual([{target:'mediaUrl',mediaAlt:'An authored illustration'}]);expect(latest!.buttonLink).toBe('/contact');expect(node.querySelector('[data-inspector-image-design]')).toBeNull();await act(async()=>root.unmount());node.remove();
});
