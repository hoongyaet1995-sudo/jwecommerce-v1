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
it('hides inactive palette and alignment controls without deleting retained layout values',async()=>{
 const node=document.createElement('div');document.body.append(node);const root=createRoot(node);let latest:PageSection;
 function Editor(){const [s,set]=useState<PageSection>({...websiteBuilderOverview('qa'),textAlignment:'center',customColors:{customBgColor:'#ffffff',titleColor:'#111111',contentColor:'#333333',eyebrowColor:'#123456',primaryBtnBg:'#abcdef',primaryBtnText:'#ffffff',secondaryBtnBg:'#aaaaaa',secondaryBtnText:'#111111'}});latest=s;return <SectionInspector pageId="test" section={s} onChange={set} onMediaUpload={vi.fn()} onClose={vi.fn()} onDelete={vi.fn()}/>;}
 await act(async()=>root.render(<Editor/>));const design=[...node.querySelectorAll<HTMLButtonElement>('[role=tab]')].find(b=>b.textContent?.toLowerCase().includes('design'))!;await act(async()=>design.click());
 expect(node.querySelector('[title="Button Background"]')).toBeNull();expect(node.querySelector('[placeholder="#818cf8"]')).toBeNull();expect(node.querySelector('[placeholder="#ffffff"]')).not.toBeNull();
 await act(async()=>node.querySelector<HTMLButtonElement>('[data-choice-value="default"]')!.click());expect(node.querySelector('[title="Button Background"]')).not.toBeNull();
 await act(async()=>node.querySelector<HTMLButtonElement>('[data-choice-value="corporate-builder-overview"]')!.click());expect(node.querySelector('[title="Button Background"]')).toBeNull();expect(latest!.customColors?.primaryBtnBg).toBe('#abcdef');expect(latest!.customColors?.eyebrowColor).toBe('#123456');
 await act(async()=>root.unmount());node.remove();
});
it('offers one editable action and text for a generic gradient CTA and preserves data across layouts',async()=>{
 const node=document.createElement('div');document.body.append(node);const root=createRoot(node);let latest:PageSection;
 function Editor(){const [s,set]=useState<PageSection>({...gradientClosing('generic'),title:'Book your consultation',content:'A tailored service.',buttonText:'Book now',buttonLink:'/pages/contact'});latest=s;return <SectionInspector pageId="test" section={s} onChange={set} onMediaUpload={vi.fn()} onClose={vi.fn()} onDelete={vi.fn()}/>;}
 await act(async()=>root.render(<Editor/>));expect(node.querySelector<HTMLInputElement>('[placeholder="e.g. Shop Now"]')?.value).toBe('Book now');expect(node.querySelector('[placeholder="e.g. Learn More"]')).toBeNull();expect(node.querySelector('[aria-label="Choose icon"]')).toBeNull();expect(node.querySelector('[placeholder="e.g. THE DESIGN MANIFESTO"]')).toBeNull();
 const design=[...node.querySelectorAll<HTMLButtonElement>('[role=tab]')].find(b=>b.textContent?.toLowerCase().includes('design'))!;await act(async()=>design.click());
 await act(async()=>node.querySelector<HTMLButtonElement>('[data-choice-value="default"]')!.click());await act(async()=>node.querySelector<HTMLButtonElement>('[data-choice-value="gradient-panel"]')!.click());
 expect(latest!.buttonText).toBe('Book now');expect(latest!.buttonLink).toBe('/pages/contact');expect(latest!.title).toBe('Book your consultation');expect(latest!.imageSlots).toBeUndefined();
 await act(async()=>root.unmount());node.remove();
});
it('offers industry-neutral manual price editors and preserves manual data through source mode switches',async()=>{
 const node=document.createElement('div');document.body.append(node);const root=createRoot(node);let latest:PageSection;
 function Editor(){const [s,set]=useState<PageSection>({...pricingFaqSection('generic'),planName:'Consultation',planAmount:150,planCurrency:'SGD',planPeriod:'/ session',planTerms:'Appointment required.'});latest=s;return <SectionInspector pageId="test" section={s} onChange={set} onMediaUpload={vi.fn()} onClose={vi.fn()} onDelete={vi.fn()}/>;}
 await act(async()=>root.render(<Editor/>));expect(node.querySelector<HTMLInputElement>('[aria-label="Plan amount"]')?.value).toBe('150');expect(node.querySelector('[aria-label="Pricing page"]')).toBeNull();
 const select=node.querySelector<HTMLSelectElement>('[aria-label="Plan content source"]')!;
 await act(async()=>{select.value='reference';select.dispatchEvent(new Event('change',{bubbles:true}));});expect(node.querySelector('[aria-label="Pricing page"]')).not.toBeNull();expect(node.querySelector('[aria-label="Plan amount"]')).toBeNull();
 await act(async()=>{select.value='manual';select.dispatchEvent(new Event('change',{bubbles:true}));});expect(latest!.planAmount).toBe(150);expect(latest!.planCurrency).toBe('SGD');expect(latest!.planTerms).toBe('Appointment required.');
 const display=node.querySelector<HTMLSelectElement>('[aria-label="Plan price display"]')!;await act(async()=>{display.value='contact';display.dispatchEvent(new Event('change',{bubbles:true}));});expect(node.querySelector('[aria-label="Plan price label"]')).not.toBeNull();expect(latest!.planAmount).toBe(150);
 await act(async()=>root.unmount());node.remove();
});
it('keeps sourced price uneditable here and offers sparse FAQ entries through preset switches',async()=>{
 const node=document.createElement('div');document.body.append(node);const root=createRoot(node);let latest:PageSection;
 function Editor(){const [s,set]=useState<PageSection>({...websiteBuilderStarterFaq('faq'),items:[]});latest=s;return <SectionInspector pageId="test" section={s} onChange={set} onMediaUpload={vi.fn()} onClose={vi.fn()} onDelete={vi.fn()}/>;}
 await act(async()=>root.render(<Editor/>));expect(node.querySelector('[aria-label="Pricing page"]')).not.toBeNull();expect(node.querySelector<HTMLInputElement>('[placeholder="e.g. Shop Now"]')?.value).toBe('Compare plans');
 expect(node.querySelector('[aria-label="Referenced plan"]')).not.toBeNull();expect(node.querySelector('[aria-label="Choose icon"]')).toBeNull();
 const add=()=>[...node.querySelectorAll<HTMLButtonElement>('button')].find(b=>b.textContent?.trim()==='Add Card')!;
 await act(async()=>add().click());await act(async()=>add().click());const id=latest!.items![0].id;
 await act(async()=>node.querySelector<HTMLButtonElement>('[aria-label="Move item 1 down"]')!.click());expect(latest!.items![1].id).toBe(id);
 await act(async()=>node.querySelector<HTMLButtonElement>('[title="Delete item"]')!.click());
 const design=[...node.querySelectorAll<HTMLButtonElement>('[role=tab]')].find(b=>b.textContent?.toLowerCase().includes('design'))!;await act(async()=>design.click());
 await act(async()=>node.querySelector<HTMLButtonElement>('[data-choice-value="minimal"]')!.click());
 await act(async()=>node.querySelector<HTMLButtonElement>('[data-choice-value="corporate-starter-faq"]')!.click());
 expect(latest!.items![0].id).toBe(id);expect(latest!.pricingSourcePageId).toBe('59');
 await act(async()=>root.unmount());node.remove();
});
it('offers sparse connected feature copy and CTA editors and retains entries through preset switches',async()=>{
 const node=document.createElement('div');document.body.append(node);const root=createRoot(node);let latest:PageSection;
 function Editor(){const [s,set]=useState<PageSection>({...websiteBuilderConnections('connections'),items:[]});latest=s;return <SectionInspector pageId="test" section={s} onChange={set} onMediaUpload={vi.fn()} onClose={vi.fn()} onDelete={vi.fn()}/>;}
 await act(async()=>root.render(<Editor/>));const add=()=>[...node.querySelectorAll<HTMLButtonElement>('button')].find(b=>b.textContent?.trim()==='Add Card')!;
 expect(add()).toBeDefined();await act(async()=>add().click());await act(async()=>add().click());
 expect(latest!.items).toHaveLength(2);expect(latest!.items![0]).toMatchObject({title:'New feature',description:'Introduce this feature.',imageUrl:'',buttonText:'Explore feature',linkUrl:''});
 expect(node.querySelector('[aria-label="Choose icon"]')).toBeNull();expect([...node.querySelectorAll('label')].some(l=>l.textContent?.trim()==='Button label'&&l.querySelector('input')?.value==='Explore feature')).toBe(true);
 const id=latest!.items![0].id;await act(async()=>node.querySelector<HTMLButtonElement>('[aria-label="Move item 1 down"]')!.click());expect(latest!.items![1].id).toBe(id);
 await act(async()=>node.querySelector<HTMLButtonElement>('[title="Delete item"]')!.click());
 const design=[...node.querySelectorAll<HTMLButtonElement>('[role=tab]')].find(b=>b.textContent?.toLowerCase().includes('design'))!;await act(async()=>design.click());
 await act(async()=>node.querySelector<HTMLButtonElement>('[data-choice-value="portfolio-cards"]')!.click());
 await act(async()=>node.querySelector<HTMLButtonElement>('[data-choice-value="corporate-connections"]')!.click());
 expect(latest!.items![0].id).toBe(id);expect(latest!.items![0].buttonText).toBe('Explore feature');
 await act(async()=>root.unmount());node.remove();
});
it('edits sparse illustrated steps without manual numbers and retains image/copy through layout switches',async()=>{
 const node=document.createElement('div');document.body.append(node);const root=createRoot(node);let latest:PageSection;
 function Editor(){const [s,set]=useState<PageSection>({...websiteBuilderWorkflow('workflow'),items:[]});latest=s;return <SectionInspector pageId="test" section={s} onChange={set} onMediaUpload={vi.fn()} onClose={vi.fn()} onDelete={vi.fn()}/>;}
 await act(async()=>root.render(<Editor/>));const add=()=>[...node.querySelectorAll<HTMLButtonElement>('button')].find(b=>b.textContent?.trim()==='Add step')!;
 expect(add()).toBeDefined();await act(async()=>add().click());await act(async()=>add().click());
 expect(latest!.items).toHaveLength(2);expect(latest!.items![0]).toMatchObject({title:'New step',description:'Add your step description.',imageUrl:''});
 expect(node.querySelector('[aria-label="Choose icon"]')).toBeNull();expect(node.querySelector('[placeholder="e.g. Shop Now"]')).toBeNull();
 const id=latest!.items![0].id;await act(async()=>node.querySelector<HTMLButtonElement>('[aria-label="Move item 1 down"]')!.click());expect(latest!.items![1].id).toBe(id);
 await act(async()=>node.querySelector<HTMLButtonElement>('[title="Delete item"]')!.click());
 const design=[...node.querySelectorAll<HTMLButtonElement>('[role=tab]')].find(b=>b.textContent?.toLowerCase().includes('design'))!;await act(async()=>design.click());
 await act(async()=>node.querySelector<HTMLButtonElement>('[data-choice-value="routine-steps"]')!.click());
 await act(async()=>node.querySelector<HTMLButtonElement>('[data-choice-value="corporate-illustrated-steps"]')!.click());
 expect(latest!.items![0].id).toBe(id);expect(latest!.items![0].description).toBe('Add your step description.');
 await act(async()=>root.unmount());node.remove();
});
it('edits sparse scenario images/icons/names and keeps them through Gallery layout switches',async()=>{
 const node=document.createElement('div');document.body.append(node);const root=createRoot(node);let latest:PageSection;
 function Editor(){const [s,set]=useState<PageSection>({...websiteBuilderScenarios('scenario'),items:[]});latest=s;return <SectionInspector pageId="test" section={s} onChange={set} onMediaUpload={vi.fn()} onClose={vi.fn()} onDelete={vi.fn()}/>;}
 await act(async()=>root.render(<Editor/>));
 const add=()=>[...node.querySelectorAll<HTMLButtonElement>('button')].find(b=>b.textContent?.trim()==='Add scenario')!;
 expect(add()).toBeDefined();await act(async()=>add().click());await act(async()=>add().click());
 expect(latest!.items).toHaveLength(2);expect(latest!.items![0]).toMatchObject({title:'New scenario',icon:'Monitor',imageUrl:''});
 expect(node.querySelectorAll('[aria-label="Choose icon"]')).toHaveLength(2);expect(node.querySelector('[placeholder="e.g. Shop Now"]')).toBeNull();
 const id=latest!.items![0].id;await act(async()=>node.querySelector<HTMLButtonElement>('[aria-label="Move item 1 down"]')!.click());
 expect(latest!.items![1].id).toBe(id);await act(async()=>node.querySelector<HTMLButtonElement>('[title="Delete item"]')!.click());
 const design=[...node.querySelectorAll<HTMLButtonElement>('[role=tab]')].find(b=>b.textContent?.toLowerCase().includes('design'))!;await act(async()=>design.click());
 await act(async()=>node.querySelector<HTMLButtonElement>('[data-choice-value="editorial-cards"]')!.click());
 await act(async()=>node.querySelector<HTMLButtonElement>('[data-choice-value="corporate-scenarios"]')!.click());
 expect(latest!.items![0].id).toBe(id);expect(latest!.items![0].icon).toBe('Monitor');
 await act(async()=>root.unmount());node.remove();
});
it.each([{name:'overview',factory:websiteBuilderOverview,preset:'corporate-builder-overview'},{name:'section story',factory:websiteBuilderStory,preset:'corporate-section-story'}])('edits sparse $name labels with add/reorder/delete and preserves them through A/B/A',async({factory,preset})=>{
 const node=document.createElement('div');document.body.append(node);const root=createRoot(node);let latest:PageSection;
 function Editor(){const [s,set]=useState<PageSection>({...factory('overview','/storage/builder.webp'),items:[]});latest=s;return <SectionInspector pageId="test" section={s} onChange={set} onMediaUpload={vi.fn()} onClose={vi.fn()} onDelete={vi.fn()}/>;}
 await act(async()=>root.render(<Editor/>));
 if(preset==='corporate-section-story')expect(node.querySelector('[data-inspector-field="title"]')?.tagName).toBe('TEXTAREA');
 const add=()=>[...node.querySelectorAll<HTMLButtonElement>('button')].find(b=>b.textContent?.trim()==='Add label')!;
 expect(add()).toBeDefined();await act(async()=>add().click());await act(async()=>add().click());
 expect(latest!.items).toHaveLength(2);expect(latest!.items![0].title).toBe('New label');
 expect(node.querySelectorAll('[aria-label="Choose icon"]')).toHaveLength(2);
 expect(node.querySelector('[placeholder="e.g. Shop Now"]')).toBeNull();expect(node.querySelector('[aria-label="Desktop image position"]')).toBeNull();
 const firstId=latest!.items![0].id;await act(async()=>node.querySelector<HTMLButtonElement>('[aria-label="Move item 1 down"]')!.click());expect(latest!.items![1].id).toBe(firstId);
 await act(async()=>node.querySelector<HTMLButtonElement>('[title="Delete item"]')!.click());expect(latest!.items).toHaveLength(1);
 const design=[...node.querySelectorAll<HTMLButtonElement>('[role=tab]')].find(b=>b.textContent?.toLowerCase().includes('design'))!;
 await act(async()=>design.click());
 await act(async()=>node.querySelector<HTMLButtonElement>('[data-choice-value="default"]')!.click());
 await act(async()=>node.querySelector<HTMLButtonElement>(`[data-choice-value="${preset}"]`)!.click());
 expect(latest!.items![0].id).toBe(firstId);expect(latest!.mediaUrl).toBe('/storage/builder.webp');
 await act(async()=>root.unmount());node.remove();
});
it('switches composite Hero A/B/A in a mounted Inspector without losing image, copy or CTA targets',async()=>{
 const node=document.createElement('div');document.body.append(node);const root=createRoot(node);let latest:PageSection;
 function Editor(){const [s,set]=useState<PageSection>(websiteBuilderHero('mounted','/storage/hero.png'));latest=s;return <SectionInspector pageId="test" section={s} onChange={set} onMediaUpload={vi.fn()} onClose={vi.fn()} onDelete={vi.fn()}/>;}
 await act(async()=>root.render(<Editor/>));
 expect(node.querySelector('[data-inspector-field="title"]')?.tagName).toBe('TEXTAREA');
 const design=[...node.querySelectorAll<HTMLButtonElement>('[role=tab]')].find(b=>b.textContent?.toLowerCase().includes('design'))!;
 await act(async()=>design.click());
 await act(async()=>node.querySelector<HTMLButtonElement>('[data-choice-value="corporate-centered"]')!.click());
 expect(latest!.heroBannerPreset).toBe('corporate-centered');
 await act(async()=>node.querySelector<HTMLButtonElement>('[data-choice-value="corporate-split-composite"]')!.click());
 expect(latest!.heroBannerPreset).toBe('corporate-split-composite');
 expect(latest!.mediaUrl).toBe('/storage/hero.png');expect(latest!.secondaryButtonLink).toBe('#section-mounted-builder');
 expect(latest!.title).toContain('Beautifully');
 await act(async()=>root.unmount());node.remove();
});
it('renders semantic copy, independent images and editable workflow labels',()=>{
 const sections=corporateLandingSections('test','/images/cms/corporate/business-team.png');
 expect(renderToStaticMarkup(<CorporateHero section={sections[0]}/>)).toContain('<h1');
 const html=renderToStaticMarkup(<CorporateFeature section={sections[2]}/>);
 expect(html).toContain('business-team.png');expect(html).toContain('Enquiry');expect(html).not.toContain('hotspot');
});
it('renders editable channel network and marketing workflow with correct CTA ownership',()=>{
 const [support,marketing]=corporateGrowthSections('growth',{support:'/support.png',marketing:'/marketing.png'});
 support.layoutPreset='corporate-support';support.items=[...(support.items??[]),...['Facebook','Instagram','WhatsApp','TikTok'].map((title,i)=>({id:'channel-'+i,title,category:'channel'})),{id:'ai',title:'AI assistance',category:'workflow'},{id:'team',title:'Your team',category:'workflow'}];
 marketing.layoutPreset='corporate-feature';marketing.items=[...(marketing.items??[]),{id:'review',title:'Team review',category:'workflow'}];
 const supportHtml=renderToStaticMarkup(<CorporateFeature section={support}/>),marketingHtml=renderToStaticMarkup(<CorporateFeature section={marketing}/>);
 expect(supportHtml).toContain('data-corporate-workflow="network"');
 for(const word of ['Facebook','Instagram','WhatsApp','TikTok','AI assistance','Your team'])expect(supportHtml).toContain(word);
 expect(supportHtml).toContain('/pages/website-ai-chat');expect(supportHtml).toContain('/support.png');
 expect(marketingHtml).toContain('data-corporate-workflow="linear"');expect(marketingHtml).toContain('Team review');
 expect(marketingHtml.match(/<a /g)).toHaveLength(1);expect(marketingHtml).toContain('/pages/marketing-studio');
});
it('keeps sparse support editable across default/support and item role changes',async()=>{
 const node=document.createElement('div');document.body.append(node);const root=createRoot(node);let latest:PageSection;
 function Editor(){const [s,set]=useState<PageSection>({...corporateGrowthSections('sparse')[0],layoutPreset:'corporate-support',items:[],mediaUrl:''});latest=s;return <SectionInspector pageId="test" section={s} onChange={set} onMediaUpload={vi.fn()} onClose={vi.fn()} onDelete={vi.fn()}/>;}
 await act(async()=>root.render(<Editor/>));
 const add=[...node.querySelectorAll<HTMLButtonElement>('button')].find(b=>b.textContent?.trim()==='Add Card')!;
 expect(add).toBeDefined();await act(async()=>add.click());expect(latest!.items).toHaveLength(1);
 const role=node.querySelector<HTMLSelectElement>('[aria-label="Entry role"]')!;
 await act(async()=>{role.value='caption';role.dispatchEvent(new Event('change',{bubbles:true}));});
 expect(latest!.items![0].category).toBe('caption');expect(node.querySelectorAll('[aria-label="Choose icon"]')).toHaveLength(0);
 await act(async()=>{role.value='workflow';role.dispatchEvent(new Event('change',{bubbles:true}));});
 expect(latest!.items![0].category).toBe('workflow');expect(node.querySelectorAll('[aria-label="Choose icon"]')).toHaveLength(1);
 const design=[...node.querySelectorAll<HTMLButtonElement>('[role=tab]')].find(b=>b.textContent?.toLowerCase().includes('design'))!;
 await act(async()=>design.click());
 await act(async()=>node.querySelector<HTMLButtonElement>('[data-choice-value="default"]')!.click());
 await act(async()=>node.querySelector<HTMLButtonElement>('[data-choice-value="corporate-support"]')!.click());
 expect(latest!.items).toHaveLength(1);expect(node.querySelector('[data-inspector-item]')).not.toBeNull();
 await act(async()=>root.unmount());node.remove();
});
it('switches tab image, copy and destination together and falls back after deletion',async()=>{
 const node=document.createElement('div');document.body.append(node);const root=createRoot(node);
 const s=corporateLandingSections('tabs')[1];s.items![1].imageUrl='/second.png';
 await act(async()=>root.render(<CorporateTabs section={s}/>));
 await act(async()=>node.querySelectorAll<HTMLButtonElement>('[role=tab]')[1].click());
 expect(node.querySelector('img')?.getAttribute('src')).toBe('/second.png');
 expect(node.querySelector('a')?.getAttribute('href')).toBe('/pages/ecommerce');
 await act(async()=>root.render(<CorporateTabs section={{...s,items:s.items!.filter((_,i)=>i!==1)}}/>));
 expect(node.querySelector('[aria-selected=true]')?.textContent).toContain('Website Builder');
 await act(async()=>root.unmount());node.remove();
});
it.each([1,2])('offers Add with empty items and refreshes controls across presets: %s',async index=>{
 const node=document.createElement('div');document.body.append(node);const root=createRoot(node);let latest:PageSection;
 function Editor(){const [s,set]=useState<PageSection>({...corporateLandingSections('edit')[index],items:[]});latest=s;return <SectionInspector pageId="test" section={s} onChange={set} onMediaUpload={vi.fn()} onClose={vi.fn()} onDelete={vi.fn()}/>;}
 await act(async()=>root.render(<Editor/>));
 const add=()=>[...node.querySelectorAll<HTMLButtonElement>('button')].find(b=>b.textContent?.trim()==='Add Card')!;
 expect(add()).toBeDefined();await act(async()=>add().click());expect(latest!.items).toHaveLength(1);
 expect(node.querySelector('[data-inspector-item]')).not.toBeNull();
 const button=[...node.querySelectorAll<HTMLButtonElement>('[role=tab]')].find(b=>b.textContent?.toLowerCase().includes('design'));
 expect(button).toBeDefined();await act(async()=>button!.click());
 const active=index===1?'product-preview-tabs':'corporate-feature';
 const switchTo=async(value:string)=>{const b=node.querySelector<HTMLButtonElement>(`[data-choice-value="${value}"]`);expect(b).not.toBeNull();await act(async()=>b!.click());};
 await switchTo('default');await switchTo(active);expect(latest!.items).toHaveLength(1);
 await act(async()=>root.unmount());node.remove();
});

it.each([['payment',corporatePaymentSection,'corporate-payments'],['courier',corporateCourierSection,'corporate-courier']] as const)('edits %s logos across preset switches with sparse content',async(label,factory,preset)=>{
 const node=document.createElement('div');document.body.append(node);const root=createRoot(node);let latest:PageSection;
 function Editor(){const [s,set]=useState<PageSection>({...factory(label),items:[]});latest=s;return <SectionInspector pageId="test" section={s} onChange={set} onMediaUpload={vi.fn()} onClose={vi.fn()} onDelete={vi.fn()}/>;}
 await act(async()=>root.render(<Editor/>));
 const add=()=>[...node.querySelectorAll<HTMLButtonElement>('button')].find(b=>b.textContent?.trim()==='Add Card')!;
 await act(async()=>add().click());expect(latest!.items).toHaveLength(1);expect(latest!.items![0].imageUrl).toBe('');
 expect(node.querySelector('[aria-label="Entry role"]')).toBeNull();expect(node.querySelector('[placeholder="e.g. Learn More"]')).toBeNull();
 const design=[...node.querySelectorAll<HTMLButtonElement>('[role=tab]')].find(b=>b.textContent?.toLowerCase().includes('design'))!;
 await act(async()=>design.click());
 for(const selectedPreset of ['default',preset]){const b=node.querySelector<HTMLButtonElement>(`[data-choice-value="${selectedPreset}"]`)!;expect(b).not.toBeNull();await act(async()=>b.click());}
 expect(latest!.items).toHaveLength(1);expect(latest!.layoutPreset).toBe(preset);
 await act(async()=>root.unmount());node.remove();
});

it('offers marketplace benefit editing across sparse layout switches',async()=>{
 const node=document.createElement('div');document.body.append(node);const root=createRoot(node);let latest:PageSection;
 function Editor(){const [s,set]=useState<PageSection>({...corporateMarketplaceSection('market'),items:[]});latest=s;return <SectionInspector pageId="test" section={s} onChange={set} onMediaUpload={vi.fn()} onClose={vi.fn()} onDelete={vi.fn()}/>;}
 await act(async()=>root.render(<Editor/>));const add=[...node.querySelectorAll<HTMLButtonElement>('button')].find(b=>b.textContent?.trim()==='Add Card')!;expect(add).toBeDefined();await act(async()=>add.click());expect(latest!.items).toHaveLength(1);expect(latest!.items![0].title).toBe('New benefit');expect(node.querySelector('[aria-label="Choose icon"]')).not.toBeNull();expect(node.querySelector('[aria-label="Entry role"]')).toBeNull();expect(node.querySelector('[placeholder="e.g. Learn More"]')).toBeNull();
 const design=[...node.querySelectorAll<HTMLButtonElement>('[role=tab]')].find(b=>b.textContent?.toLowerCase().includes('design'))!;await act(async()=>design.click());
 for(const preset of ['default','corporate-marketplace']){const b=node.querySelector<HTMLButtonElement>(`[data-choice-value="${preset}"]`)!;expect(b).not.toBeNull();await act(async()=>b.click());}
 expect(latest!.items).toHaveLength(1);expect(latest!.layoutPreset).toBe('corporate-marketplace');await act(async()=>root.unmount());node.remove();
});
