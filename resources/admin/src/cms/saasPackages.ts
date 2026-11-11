import type {PageSection,SectionItem} from '../types';
export const saasItemFields=['pricingMode','pricingLabel','billingLabel','highlightLabel'] as const;
export function validSaasPricingSection(value:unknown):boolean {
 if(!value||typeof value!=='object')return false;
 const s=value as PageSection;
 if(('pricingPreset'in s||'pricingCurrency'in s)&&s.type!=='pricing_table')return false;
 if(s.pricingCurrency!==undefined&&!/^[A-Z]{3}$/.test(s.pricingCurrency))return false;
 if(s.pricingPreset!==undefined&&!['default','saas-packages','plan-comparison'].includes(s.pricingPreset))return false;
 if(s.items!==undefined&&!Array.isArray(s.items))return false;
 return (s.items??[]).every(item=>{
  if(!item||typeof item!=='object')return false;
  if(saasItemFields.some(k=>k in item)&&s.type!=='pricing_table')return false;
  if(item.pricingMode!==undefined&&!['amount','contact'].includes(item.pricingMode))return false;
  if(saasItemFields.some(k=>k in item&&typeof item[k]!=='string'))return false;
  return s.pricingPreset!=='saas-packages'||item.price===undefined||Number.isFinite(item.price)&&item.price>=0;
 });
}
export function saasPriceText(item:SectionItem,currency:string):string|null {
 if(item.pricingMode==='contact')return item.pricingLabel?.trim()||null;
 if(item.price===undefined||!Number.isFinite(item.price)||item.price<0)return null;
 try{return new Intl.NumberFormat('en-MY',{style:'currency',currency,maximumFractionDigits:2,minimumFractionDigits:0}).format(item.price);}catch{return `${currency} ${item.price}`;}
}

export function saasAction(event:{preventDefault:()=>void},link:string|undefined,editing:boolean){if(editing){event.preventDefault();return;}if(link==='#storefront-support-widget'){const launcher=document.getElementById('sf-support-launcher');if(launcher){event.preventDefault();launcher.click();}}}
