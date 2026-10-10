import {promotionStatus} from '../integration/promotionStatus';
import type {PageSection} from '../types';
import {createSectionColorDefaults} from './sectionColorDefaults';

/** Public merchandising projection. Checkout remains the authority for redemption. */
export interface PromotionOffer {
 id:string;name:string;description:string;code:string;benefit:string;
 minimumSpend:string|null;endsAt:string|null;
 status:'active'|'scheduled'|'expired'|'inactive'|'exhausted';
}
export interface HighlightCoupon {
 id:string;name:string;description?:string|null;code:string;
 discountType:'percentage'|'fixed_amount'|'free_shipping';value:number;
 minimumSpend:number|null;usageLimit:number|null;usageCount:number;
 isActive:boolean;startsAt:string|null;endsAt:string|null;
}
export function couponHighlight(c:HighlightCoupon,now=Date.now()):PromotionOffer {
 const status=promotionStatus(c,now);
 return {id:c.id,name:c.name||c.code,description:c.description??'',code:c.code,
  benefit:c.discountType==='percentage'?`${c.value}% off`:c.discountType==='fixed_amount'?`RM ${c.value.toFixed(2)} off`:'Free shipping',
  minimumSpend:c.minimumSpend===null?null:`RM ${c.minimumSpend.toFixed(2)}`,endsAt:c.endsAt,status};
}
export function isPromotionOffers(value:unknown):value is PromotionOffer[] {
 return Array.isArray(value)&&value.length<=106&&value.every(v=>v&&typeof v==='object'&&!Array.isArray(v)&&Object.keys(v).every(k=>['id','name','description','code','benefit','minimumSpend','endsAt','status'].includes(k))&&['id','name','description','code','benefit'].every(k=>typeof v[k]==='string')&&(v.minimumSpend===null||typeof v.minimumSpend==='string')&&(v.endsAt===null||typeof v.endsAt==='string'&&Number.isFinite(Date.parse(v.endsAt)))&&['active','scheduled','expired','inactive','exhausted'].includes(v.status));
}
export function resolvePromotionCards(section:PageSection,offers:PromotionOffer[]) {
 const eligible=offers.filter(o=>o.status==='active');const seen=new Set<string>();
 return (section.items??[]).slice(0,6).flatMap((item,index)=>{
  const offer=section.promotionSource==='manual'?eligible.find(o=>o.id===item.sourceId):eligible[index];
  if(!offer||seen.has(offer.id))return [];seen.add(offer.id);return [{item,offer}];
 });
}
export function promotionHighlightDefaults():Omit<PageSection,'id'> {
 return {type:'promotion_highlights',title:'A little more,\nfor less.',subtitle:'',content:'Considered pieces. Brighter days.',isVisible:true,
  promotionSource:'automatic',promotionLayout:'side',showOfferBadges:true,containerMode:'full-bleed',sectionBorder:'none',motionPreset:'subtle-lift',paddingY:'compact',backgroundColor:'custom',textColor:'light',textAlignment:'left',
  customColors:{...createSectionColorDefaults(),customBgColor:'#595e43',titleColor:'#f7f6ef',contentColor:'#f7f6ef',primaryBtnBg:'#595e43',primaryBtnText:'#ffffff',secondaryBtnText:'#292b24'},
  items:[{id:'offer-lighting',title:'',description:'',sourceId:'',imageUrl:'/storage/cms/media/28ab0cb3-b015-4596-bd2e-9f0028bf94e9.png',buttonText:'Explore offer',linkUrl:'/shop'},{id:'offer-decor',title:'',description:'',sourceId:'',imageUrl:'/storage/cms/media/524de4a8-d5ae-4fd8-8502-f05e9d6ecfbc.png',buttonText:'Explore offer',linkUrl:'/shop'}]};
}
