import {saasSections,saasStyle} from './saasTemplates';
import {logisticsDetailSections} from './logisticsDetailTemplates';
import {logisticsSections} from './logisticsTemplates';
import logisticsTemplate from './logisticsTemplate.json';
import {weddingSections} from './weddingTemplates';
import weddingTemplate from './weddingTemplate.json';
import {jewelrySections} from './jewelryTemplates';
import jewelryTemplate from './jewelryTemplate.json';
import {kidsSections} from './kidsTemplates';
import kidsTemplate from './kidsTemplate.json';
import {techSections} from './techTemplates';
import techTemplate from './techTemplate.json';
import {foodSections} from './foodTemplates';
import foodTemplate from './foodTemplate.json';
import {beautySections} from './beautyTemplates';
import beautyTemplate from './beautyTemplate.json';
import {marketSections} from './marketTemplates';
import marketTemplate from './marketTemplate.json';
import {fashionSections} from './fashionTemplates';
import fashionTemplate from './fashionTemplate.json';
import homeDecoTemplate from './homeDecoTemplate.json';
import type {PageStyle} from './pageStyle';
import {termsSections,termsTemplateIds} from './termsTemplates';
import {privacySections,privacyTemplateIds} from './privacyTemplates';
import {paymentSections,paymentTemplateIds} from './paymentTemplates';
import {contactSections,contactTemplateIds} from './contactTemplates';
import {returnsSections,returnsTemplateIds} from './returnsTemplates';
import {shippingChaptersSections} from './shippingChaptersTemplate';
import {shippingEditorialSections} from './shippingEditorialTemplate';
import type { PageSection, Product, Category, Collection } from '../types';
import type {PromotionOffer} from './promotionHighlights';
import { createSectionColorDefaults } from './sectionColorDefaults';
import aboutEditorialTemplate from './aboutEditorialTemplate.json';
import {aboutChaptersSections} from './aboutChaptersTemplate';
import {aboutStudioSections} from './aboutStudioTemplate';

export interface PageTemplateContext {
  products?: Product[];
  categories?: Category[];
  collections?: Collection[];
  promotionOffers?: PromotionOffer[];
  shopName?: string;
  tagline?: string;
  currency?: string;
  shippingRates?: Array<{
    id: string; name: string; zoneRegion: string; customStates?: string[];
    isActive: boolean; rateType: string; flatRateCost?: number; estimatedDays?: string;
  }>;
}

export function createPageTemplateStyle(id:string):PageStyle|undefined {return id==='saas-connected'?structuredClone(saasStyle): ['logistics-solution-detail','logistics-media-gallery'].includes(id)?structuredClone(logisticsTemplate.style) as PageStyle:id==='logistics-containers'?structuredClone(logisticsTemplate.style) as PageStyle:id==='wedding-photography'?structuredClone(weddingTemplate.style) as PageStyle:id==='jewelry-gifts'?structuredClone(jewelryTemplate.style) as PageStyle:id==='baby-kids'?structuredClone(kidsTemplate.style) as PageStyle:id==='tech-electronics'?structuredClone(techTemplate.style) as PageStyle:id==='food-beverage'?structuredClone(foodTemplate.style) as PageStyle:id==='beauty-skincare'?structuredClone(beautyTemplate.style) as PageStyle:id==='everyday-market'?structuredClone(marketTemplate.style) as PageStyle:id==='fashion-design'?structuredClone(fashionTemplate.style) as PageStyle:id==='home-deco'?structuredClone(homeDecoTemplate.style) as PageStyle:undefined;}

// These are editable draft snapshots. Checkout remains the authority for live rates.
// Photographs illustrate the starter layout; they do not depict the merchant's team.
export function createPageTemplateSections(templateId: string, context: PageTemplateContext, prefix: string): PageSection[] {
  if(templateId==='saas-connected')return saasSections(prefix);
  if(['logistics-solution-detail','logistics-media-gallery'].includes(templateId))return logisticsDetailSections(templateId,context,prefix);
  if(templateId==='logistics-containers')return logisticsSections(context,prefix);
  if(templateId==='wedding-photography')return weddingSections(prefix);
  if(templateId==='jewelry-gifts')return jewelrySections(context,prefix);
  if(templateId==='baby-kids')return kidsSections(context,prefix);
  if(templateId==='tech-electronics')return techSections(context,prefix);
  if(templateId==='food-beverage')return foodSections(context,prefix);
  if(templateId==='beauty-skincare')return beautySections(context,prefix);
  if(templateId==='everyday-market')return marketSections(context,prefix);
  if(templateId==='fashion-design')return fashionSections(context,prefix);
  if(templateId==='home-deco')return (structuredClone(homeDecoTemplate.sections) as PageSection[]).map((section,index)=>{
    const ids=new Map<string,string>();section.id=`${prefix}-home-${index}`;
    section.items=section.items?.map((item,i)=>{const id=`${section.id}-item-${i}`;ids.set(item.id,id);return {...item,id};});
    section.productTabs=section.productTabs?.map((tab,i)=>({...tab,id:`${section.id}-tab-${i}`}));
    section.imageSlots=section.imageSlots?.map(slot=>{for(const [old,id] of ids)if(slot.target.startsWith(`item:${old}:`))return {...slot,target:slot.target.replace(`item:${old}:`,`item:${id}:`)};return slot;});
    // A reusable starter must never carry another database's catalog identities.
    const products=(context.products??[]).filter(product=>product.status==='active');
    const categories=(context.categories??[]).filter(category=>category.status==='active');
    const group=(pattern:RegExp)=>categories.find(category=>pattern.test(category.slug+' '+category.name));
    if(section.type==='product_listing') {
      section.sourceType='manual';section.sourceId='';section.productIds=products.slice(0,4).map(product=>product.id);
      section.productTabs=section.productTabs?.map((tab,i)=>{
        const category=i===1?group(/lighting/i):i===2?group(/decor|storage/i):undefined;
        return {...tab,sourceType:category?'category':products.length?'manual':'all',sourceId:category?.id??'',productIds:category?[]:(i===3?[...products].reverse():products).slice(0,4).map(product=>product.id)};
      });
    }
    if(section.type==='shop_navigation')section.items=section.items?.map((item,i)=>{
      const category=section.shopNavigationPreset==='collection-banners'?undefined:group([/living|tables/i,/dining|tables/i,/lighting/i,/decor|storage/i,/bedroom/i][i]??/$^/);
      return {...item,sourceType:category?'category':'custom',sourceId:category?.id??'',linkUrl:category?item.linkUrl:'/shop'};
    });
    if(section.type==='promotion_highlights')section.items=section.items?.map((item,i)=>({...item,sourceId:context.promotionOffers?.filter(offer=>offer.status==='active')[i]?.id??''}));
    return section;
  });
  if((termsTemplateIds as readonly string[]).includes(templateId))return termsSections(templateId,prefix);
  if((privacyTemplateIds as readonly string[]).includes(templateId))return privacySections(templateId,prefix);
  if((paymentTemplateIds as readonly string[]).includes(templateId))return paymentSections(templateId,prefix);
  if((contactTemplateIds as readonly string[]).includes(templateId))return contactSections(templateId,prefix);
  if((returnsTemplateIds as readonly string[]).includes(templateId))return returnsSections(templateId,prefix);
  if(templateId==='shipping-chapters')return shippingChaptersSections(prefix);
  if(templateId==='shipping-editorial')return shippingEditorialSections(prefix);
  const brand = context.shopName?.trim() || 'Your store';
  if (templateId === 'about-chapters') return aboutChaptersSections(brand,prefix);
  if (templateId === 'about-studio') return aboutStudioSections(brand,prefix);
  const section = (key: string, data: Omit<PageSection, 'id' | 'isVisible'> & {isVisible?: boolean}): PageSection => ({
    textAlignment: 'left', backgroundColor: 'bg-neutral-950', textColor: 'light',
    paddingY: 'normal', isVisible: true, customColors: createSectionColorDefaults(),
    ...data, id: `${prefix}-${key}`,
    ...(data.items ? {items: data.items.map((item, i) => ({...item, id: `${prefix}-${key}-item-${i}`}))} : {}),
  });
  const light = {backgroundColor:'bg-neutral-50', textColor:'dark', customColors:{
    ...createSectionColorDefaults(), titleColor:'#171717',contentColor:'#525252',eyebrowColor:'#4f46e5',
  }} as const;
  if (templateId === 'about-editorial') {
    const snapshot = JSON.parse(JSON.stringify(aboutEditorialTemplate)) as PageSection[];
    return snapshot.map((data, index) => {
      const result = section(`editorial-${index}`, {
      ...data,
      title: index === 0 ? `A closer look\nat ${brand}.` : data.title,
      sectionBorder: 'none', containerMode: 'page', motionPreset: 'subtle-lift',
      });
      if (result.type === 'values_list') delete result.textAlignment;
      return result;
    });
  }
  if (templateId === 'about') return [
    section('intro', {type:'media_text_split',title:`A closer look at ${brand}`,subtitle:'ABOUT US',
      content:context.tagline?.trim() || 'To edit: introduce your brand, the collection you offer and the people you create it for.',
      mediaUrl:'https://images.unsplash.com/photo-1441986300917-64674bd600d8?w=1400&auto=format&fit=crop&q=85',
      mediaPosition:'right',buttonText:'Explore the collection',buttonLink:'/shop',paddingY:'spacious',...light}),
    section('story', {type:'media_text_split',title:'Every brand has a story. This is yours.',subtitle:'OUR STORY',
      content:'To edit: tell customers why you started, what inspires your collection and where you want to take your brand. Add only milestones and details that are true for your business.',
      mediaUrl:'https://images.unsplash.com/photo-1455390582262-044cdead277a?w=1200&auto=format&fit=crop&q=85',mediaPosition:'left'}),
    section('values', {type:'feature_cards',title:'The details that matter',subtitle:'OUR VALUES',
      content:'To edit: make these values your own and support each one with a real example.',columnsCount:3,
      items:[{id:'',icon:'Heart',title:'Purpose',description:'To edit: explain the idea that guides your brand and the needs you aim to meet.'},
        {id:'',icon:'Sparkles',title:'Thoughtful choices',description:'To edit: describe how you select your collection, materials or products.'},
        {id:'',icon:'Mail',title:'A human connection',description:'To edit: share how your team helps customers before and after an order.'}]}),
    section('explore', {type:'cta_strip',title:'Find your next favourite',subtitle:brand,
      content:'Take a look around and discover what is in store.',buttonText:'Browse the collection',buttonLink:'/shop',...light}),
  ];
  if (templateId !== 'shipping') return [];
  const activeRates = context.shippingRates?.filter(rate => rate.isActive) || [];
  const regions: Record<string,string> = {all:'All configured delivery areas',peninsular:'Peninsular Malaysia',east_malaysia:'East Malaysia',sabah:'Sabah',sarawak:'Sarawak',international:'International',custom:'Custom delivery area'};
  const rateItems = activeRates.map(rate => {
    const region = rate.zoneRegion === 'custom' ? rate.customStates?.join(', ') || regions.custom : regions[rate.zoneRegion] || rate.zoneRegion;
    const fee = rate.rateType === 'flat' && typeof rate.flatRateCost === 'number'
      ? `Configured flat rate: ${context.currency || 'Currency to edit'} ${rate.flatRateCost.toFixed(2)}.`
      : 'Charges depend on the applicable rate rules; review the final amount at checkout.';
    return {id:'',icon:'Truck',title:rate.name,description:`${region}. ${fee}${rate.estimatedDays ? ` Configured estimate: ${rate.estimatedDays}.` : ''} Availability and final charges are confirmed at checkout.`};
  });
  return [
    section('intro', {type:'media_text_split',title:'From our store to your door',subtitle:'SHIPPING & DELIVERY',
      content:'Plan your delivery, review the charges and follow your order. Everything you need for the next step is here.',
      mediaUrl:'https://images.unsplash.com/photo-1607082348824-0a96f2a4b9da?w=1400&auto=format&fit=crop&q=85',mediaPosition:'right',
      buttonText:'Track your order',buttonLink:'/order-tracking',paddingY:'spacious',...light}),
    section('delivery', {type:'feature_cards',title:'Where we deliver & what it costs',subtitle:'DELIVERY OPTIONS',
      content:'A snapshot of the saved store shipping settings. Check your address and final charge at checkout.',columnsCount:3,
      items:rateItems.length ? rateItems : [{id:'',icon:'Globe',title:'Delivery areas',description:'To edit: confirm the countries, regions and postcodes your store serves.'},
        {id:'',icon:'Truck',title:'Delivery charges',description:'To edit: add your configured fees and any applicable conditions. Confirm the final charge at checkout.'},
        {id:'',icon:'Clock',title:'Delivery estimates',description:'To edit: add confirmed processing and delivery estimates, including exceptions.'}]}),
    section('tracking', {type:'cta_strip',title:'Follow your order',subtitle:'ORDER TRACKING',
      content:'Open Order Tracking and complete the required verification to view the information available for your order.',
      buttonText:'Open Order Tracking',buttonLink:'/order-tracking',...light}),
    section('faq', {type:'faq_accordion',title:'Delivery questions, answered',subtitle:'GOOD TO KNOW',
      items:[{id:'',title:'Can you deliver to my address?',description:activeRates.length ? 'Review the configured delivery options above. Enter your delivery address at checkout to confirm availability and the final charge.' : 'To edit: confirm your delivery coverage and exclusions before publishing.'},
        {id:'',title:'How much will shipping cost?',description:'Review the delivery charge shown at checkout before confirming your order. The final amount depends on the address and applicable shipping rules.'},
        {id:'',title:'When will my order arrive?',description:'To edit: add your confirmed processing times, delivery estimates and any holiday or remote-area exceptions. Do not publish an unconfirmed delivery promise.'},
        {id:'',title:'How can I track my delivery?',description:'Use the Order Tracking entry above and complete verification. Keep your order confirmation for reference.'},
        {id:'',title:'What if I need to change my address or report a delay?',description:'To edit: add your verified support channel and explain the process for address changes and delayed deliveries.'}]}),
  ];
}
