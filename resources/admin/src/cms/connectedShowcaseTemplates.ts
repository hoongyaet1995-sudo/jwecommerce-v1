import type {PageSection} from '../types';
import type {PageStyle} from './pageStyle';
import type {PageTemplateContext} from './pageTemplates';
import snapshots from './connectedShowcaseTemplates.json';
export const connectedShowcaseIds=['connected-business','business-showcase','commerce-catalogue','customer-conversations','content-campaigns'] as const;
type ShowcaseId=typeof connectedShowcaseIds[number];
export const isConnectedShowcase=(id:string):id is ShowcaseId=>connectedShowcaseIds.includes(id as ShowcaseId);
const names=['Connected Business','Business Showcase','Commerce & Catalogue','Customer Conversations','Content & Campaigns'];
const descriptions=['A connected platform homepage with visual modules, integrations and editable plan examples.','An editorial business and services showcase with a visual workflow, related services and FAQ.','A commerce feature story with catalogue options, order journey, integrations and FAQ.','A customer support story with channel flow, team handover, scroll cards, focused steps and FAQ.','A visual content and campaign service story with editable artwork, recurring creation, channel choices and FAQ.'];
export const connectedShowcaseCards=connectedShowcaseIds.map((id,i)=>({id,name:names[i],category:i===2?'Commerce & Shop':i===3?'Customer Help':'Brand & Story',description:descriptions[i]+' Replace sample copy, artwork and plan details before publishing.',sections:snapshots[id].sections.map(s=>s.type),ready:true,thumbnail:(snapshots[id].sections as PageSection[]).find(s=>s.mediaUrl)?.mediaUrl||(snapshots[id].sections as PageSection[]).flatMap(s=>s.items??[]).find(i=>i.imageUrl)?.imageUrl}));
export function connectedShowcaseStyle(id:ShowcaseId):PageStyle{return structuredClone(snapshots[id].style) as PageStyle;}
/** Fresh page-owned values only: no references to another merchant's documents or prices. */
export function connectedShowcaseSections(id:ShowcaseId,context:PageTemplateContext,prefix:string):PageSection[]{
 const sections=structuredClone(snapshots[id].sections) as PageSection[];
 const sectionIds=new Map(sections.map((s,i)=>[s.id,`${prefix}-section-${i+1}`]));
 const closing=sections.findLast(s=>s.type==='cta_strip');
 const closingId=closing?.id;
 const contact=closingId?`#section-${sectionIds.get(closingId)}`:'#storefront-support-widget';
 const brand=context.shopName?.trim()||'Your business';
 const copy=(value:string)=>value.replace(/JW Studio/g,brand);
 const destination=(value:string|undefined,final=false):string|undefined=>{
  if(!value)return value;
  const anchor=value.match(/#section-([A-Za-z0-9_-]+)$/)?.[1];
  if(anchor&&sectionIds.has(anchor))return '#section-'+sectionIds.get(anchor);
  if(value==='#storefront-support-widget')return value;
  if(value.startsWith('/pages/')||value.startsWith('/admin/')||value.startsWith('#section-')||/jwaistudio\.online/i.test(value))return final?'#storefront-support-widget':contact;
  return value;
 };
 return sections.map(s=>{
  const original=s.id;const ownItems=new Map((s.items??[]).map((item,i)=>[item.id,`${sectionIds.get(original)}-item-${i+1}`]));s.id=sectionIds.get(original)!;s.containerMode='page';
  for(const field of ['title','subtitle','content','leadText'] as const)if(typeof s[field]==='string')s[field]=copy(s[field]!);
  s.buttonLink=destination(s.buttonLink,original===closingId);s.secondaryButtonLink=destination(s.secondaryButtonLink);
  if(s.type==='cta_strip'&&s.buttonText)s.buttonText='Get in touch';
  if(s.type==='hero_banner'&&s.buttonText)s.buttonText='Get in touch';
  if(s.type==='pricing_table'){if(s.buttonText){s.buttonText='Get in touch';s.buttonLink=contact;}s.pricingCurrency=context.currency||'MYR';}
  s.items=s.items?.map(item=>({...item,id:ownItems.get(item.id)!,title:copy(item.title),...(item.description!==undefined?{description:copy(item.description)}:{}),...(item.linkUrl!==undefined?{linkUrl:destination(item.linkUrl)}:{}),...(s.type==='pricing_table'?{buttonText:'Get in touch',linkUrl:contact}:{})}));
  s.imageSlots=s.imageSlots?.map(slot=>{for(const [old,next] of ownItems)if(slot.target.startsWith(`item:${old}:`))return {...slot,target:slot.target.replace(`item:${old}:`,`item:${next}:`)};return slot;});
  s.productTabs=s.productTabs?.map((tab,i)=>({...tab,id:`${s.id}-tab-${i+1}`}));
  return s;
 });
}
