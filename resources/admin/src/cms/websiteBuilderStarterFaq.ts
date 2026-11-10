import type {PageDocument,PageSection} from '../types';
export interface StarterPricing {status:'ready'|'unavailable';name?:string;price?:number;priceLabel?:string;currency?:string;billingLabel?:string;terms?:string}
export function planContentSource(s:PageSection):'manual'|'reference' {return s.planContentSource??(s.pricingSourcePageId?'reference':'manual');}
export function resolveStarterPricing(s:PageSection,document?:Pick<PageDocument,'sections'>):StarterPricing {
 if(planContentSource(s)==='manual'){
  if(!s.planName?.trim())return {status:'unavailable'};
  if(s.planPricingMode==='contact')return s.planPriceLabel?.trim()?{status:'ready',name:s.planName,priceLabel:s.planPriceLabel,terms:s.planTerms??''}:{status:'unavailable'};
  if(typeof s.planAmount!=='number'||!Number.isFinite(s.planAmount)||s.planAmount<0||!/^[A-Z]{3}$/.test(s.planCurrency??''))return {status:'unavailable'};
  return {status:'ready',name:s.planName,price:s.planAmount,currency:s.planCurrency,billingLabel:s.planPeriod??'',terms:s.planTerms??''};
 }
 const plan=document?.sections.find(x=>x.id===s.pricingSourceSectionId&&x.type==='pricing_table'&&x.pricingPreset==='saas-packages'&&x.isVisible);
 const item=plan?.items?.find(x=>x.id===s.pricingSourceItemId);
 const terms=document?.sections.find(x=>x.id===s.pricingSourceTermsSectionId&&x.isVisible);
 if(!item?.title||item.pricingMode==='contact'||typeof item.price!=='number'||!Number.isFinite(item.price)||item.price<0||!item.billingLabel||!plan?.pricingCurrency||!terms?.content)return {status:'unavailable'};
 return {status:'ready',name:item.title,price:item.price,currency:plan.pricingCurrency,billingLabel:item.billingLabel,terms:terms.content};
}
export function isStarterPricing(value:unknown):value is StarterPricing {
 if(!value||typeof value!=='object')return false;const p=value as StarterPricing;
 return p.status==='unavailable'||p.status==='ready'&&typeof p.name==='string'&&typeof p.terms==='string'&&(typeof p.priceLabel==='string'&&p.priceLabel.trim().length>0||typeof p.price==='number'&&Number.isFinite(p.price)&&p.price>=0&&typeof p.currency==='string'&&/^[A-Z]{3}$/.test(p.currency)&&typeof p.billingLabel==='string');
}
/** Generic library starter; JW-specific copy/references belong to the page factory above. */
export function pricingFaqSection(prefix:string):PageSection {
 const base=websiteBuilderStarterFaq(prefix);
 const {pricingSourcePageId,pricingSourceSectionId,pricingSourceItemId,pricingSourceTermsSectionId,...section}=base;
 return {...section,planContentSource:'manual',planName:'Your plan',planPricingMode:'amount',planCurrency:'MYR',planPeriod:'',planTerms:'',title:'A plan for your next step.',content:'Describe what this plan includes.',subtitle:'Frequently asked questions.',buttonText:'Learn more',buttonLink:'',items:[{id:prefix+'-question-1',title:'What is included?',description:'Describe the services or benefits included in this plan.'},{id:prefix+'-question-2',title:'How do I get started?',description:'Explain the next step for your customers.'}]};
}
export function websiteBuilderStarterFaq(prefix:string):PageSection {
 return {id:prefix+'-starter-faq',type:'faq_accordion',faqPreset:'corporate-starter-faq',faqInitiallyOpen:true,isVisible:true,containerMode:'page',motionPreset:'subtle-lift',sectionBorder:'none',paddingY:'normal',backgroundColor:'custom',textColor:'dark',textAlignment:'left',
 customColors:{customBgColor:'#ffffff',titleColor:'#0d1234',contentColor:'#626981',eyebrowColor:'#7426ff'},
 title:'A place to start.\nSpace to grow.',subtitle:'A few helpful answers.',content:'Editable website, product catalogue and support workspace.',buttonText:'Compare plans',buttonLink:'https://www.jwaistudio.online/#section-sec-a25d72bd-6236-44af-84b8-b15e4ff5965e-plans',
 pricingSourcePageId:'59',pricingSourceSectionId:'corporate-review-pricing',pricingSourceItemId:'corporate-review-pricing-item-0',pricingSourceTermsSectionId:'corporate-review-addons',
 items:[['content','Can I update content myself?','Edit supported text, images, links and section content in the visual builder.'],['mobile','Can I preview the mobile layout?','Desktop, tablet and mobile previews help you review your page before publication.'],['catalogue','Can I show products and services?','Yes. Purchase and enquiry options follow your catalogue setup.'],['ai','Is AI generation included in Starter?','AI features and usage allowances are confirmed with your selected plan. Contact us to discuss your setup.']].map(([key,title,description])=>({id:prefix+'-faq-'+key,title,description}))};
}
