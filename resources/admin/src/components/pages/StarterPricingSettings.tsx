import React from 'react';
import type {CmsPage,PageSection} from '../../types';
import {resolveStarterPricing,planContentSource} from '../../cms/websiteBuilderStarterFaq';
export function StarterPricingSettings({section:s,pages=[],onChange}:{section:PageSection;pages?:CmsPage[];onChange:(patch:Partial<PageSection>)=>void}){
 if(s.type!=='faq_accordion'||!['corporate-starter-faq','pricing-band-faq'].includes(s.faqPreset??''))return null;
 const page=pages.find(p=>p.id===s.pricingSourcePageId),plans=page?.sections.filter(x=>(x.type==='pricing_table'&&x.pricingPreset==='saas-packages'||x.type==='feature_cards'&&x.featureCardsPreset==='saas-addons'))??[],plan=plans.find(x=>x.id===s.pricingSourceSectionId),fact=resolveStarterPricing(s,page);
 const cls='w-full p-2 rounded border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-sm';
 const mode=planContentSource(s);
 return <div className="space-y-3 p-3 my-3 border border-indigo-200 dark:border-indigo-800 rounded-lg"><strong className="text-sm">Pricing card</strong>
 <label className="block text-xs">Content source<select aria-label="Plan content source" className={cls} value={mode} onChange={e=>onChange({planContentSource:e.target.value as 'manual'|'reference'})}><option value="manual">Enter manually</option><option value="reference">Reference an existing plan</option></select></label>
 {mode==='manual'?<>
 <label className="block text-xs">Plan name<input aria-label="Plan name" className={cls} value={s.planName??''} onChange={e=>onChange({planName:e.target.value})}/></label>
 <label className="block text-xs">Price display<select aria-label="Plan price display" className={cls} value={s.planPricingMode??'amount'} onChange={e=>onChange({planPricingMode:e.target.value as 'amount'|'contact'})}><option value="amount">Amount</option><option value="contact">Contact for pricing</option></select></label>
 {s.planPricingMode==='contact'?<label className="block text-xs">Price label<input aria-label="Plan price label" className={cls} value={s.planPriceLabel??''} onChange={e=>onChange({planPriceLabel:e.target.value})}/></label>:<>
 <label className="block text-xs">Amount<input aria-label="Plan amount" type="number" min="0" step="0.01" className={cls} value={s.planAmount??''} onChange={e=>onChange({planAmount:e.target.value===''?undefined:Number(e.target.value)})}/></label>
 <label className="block text-xs">Currency<input aria-label="Plan currency" maxLength={3} placeholder="MYR, SGD, USD…" className={cls} value={s.planCurrency??''} onChange={e=>onChange({planCurrency:e.target.value.toUpperCase()})}/></label>
 <label className="block text-xs">Billing period (optional)<input aria-label="Plan billing period" placeholder="/ month, / session, one-time…" className={cls} value={s.planPeriod??''} onChange={e=>onChange({planPeriod:e.target.value})}/></label>
 </>}
 {s.faqPreset==='pricing-band-faq'&&<label className="block text-xs">Plan description<textarea aria-label="Plan description" className={cls} rows={3} value={s.planDescription??''} onChange={e=>onChange({planDescription:e.target.value})}/></label>}
 <label className="block text-xs">Terms (optional)<textarea aria-label="Plan terms" className={cls} rows={3} value={s.planTerms??''} onChange={e=>onChange({planTerms:e.target.value})}/></label>
 </>:<>
 <label className="block text-xs">Pricing page<select aria-label="Pricing page" className={cls} value={s.pricingSourcePageId??''} onChange={e=>onChange({pricingSourcePageId:e.target.value,pricingSourceSectionId:'',pricingSourceItemId:'',pricingSourceTermsSectionId:''})}><option value="">Choose page</option>{pages.filter(p=>p.sections.some(x=>(x.type==='pricing_table'&&x.pricingPreset==='saas-packages'||x.type==='feature_cards'&&x.featureCardsPreset==='saas-addons'))).map(p=><option key={p.id} value={p.id}>{p.title}</option>)}</select></label>
 <label className="block text-xs">Pricing section<select aria-label="Pricing section" className={cls} value={s.pricingSourceSectionId??''} onChange={e=>onChange({pricingSourceSectionId:e.target.value,pricingSourceItemId:''})}><option value="">Choose pricing section</option>{plans.map(p=><option key={p.id} value={p.id}>{p.title}</option>)}</select></label>
 <label className="block text-xs">Referenced plan<select aria-label="Referenced plan" className={cls} value={s.pricingSourceItemId??''} onChange={e=>onChange({pricingSourceItemId:e.target.value})}><option value="">Choose plan</option>{plan?.items?.map(i=><option key={i.id} value={i.id}>{i.title}</option>)}</select></label>
 <label className="block text-xs">Terms section<select aria-label="Terms section" className={cls} value={s.pricingSourceTermsSectionId??''} onChange={e=>onChange({pricingSourceTermsSectionId:e.target.value})}><option value="">Choose terms section</option>{page?.sections.filter(x=>x.content).map(x=><option key={x.id} value={x.id}>{x.title}</option>)}</select></label>
 <p className="text-xs text-neutral-500">{fact.status==='ready'?`${fact.name} · ${fact.priceLabel??`${fact.currency} ${fact.price} ${fact.billingLabel}`}`:'Pricing source unavailable. Select the source above.'}{s.faqPreset==='pricing-band-faq'&&fact.status==='ready'&&fact.description&&<span className="block mt-2">{fact.description}</span>}</p><p className="text-xs text-neutral-500">Plan name, amount, billing period and terms follow this page. Edit them at the source.</p>
 </>}
 </div>;
}
