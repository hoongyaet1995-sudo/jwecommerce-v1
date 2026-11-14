import {shopNavigationHref} from './shopNavigation';
import type {PageSection,SectionItem} from '../types';
export type PricingCell={type:'text'|'available'|'unavailable';value?:string};
export type PricingRow={id:string;title:string;source?:'cells'|'price'|'renewal';showInCards?:boolean;cells:Record<string,PricingCell>};
export type PricingGroup={id:string;title:string;rows:PricingRow[]};
export type PricingMatrixConfig={enabled:true;view:'cards'|'matrix';billing?:'monthly'|'yearly';sourceSectionId?:string;groups:PricingGroup[]};
export type PricingPlan=SectionItem & {annualPrice?:number;planSurface?:'paper'|'contrast';annualNote?:string;monthlyNote?:string;includesLabel?:string};
export type PricingSection=PageSection & {pricingMatrix?:PricingMatrixConfig};
export const isUnifiedPricing=(s:PageSection)=>s.type==='pricing_table'&&(s as PricingSection).pricingMatrix?.enabled===true;
export function resolveUnifiedPricing(section:PricingSection,sections:PageSection[]=[]){const config=section.pricingMatrix;if(!config)return undefined;const source=config.sourceSectionId?sections.find(s=>s.id===config.sourceSectionId&&s.id!==section.id&&isUnifiedPricing(s)&&!(s as PricingSection).pricingMatrix?.sourceSectionId) as PricingSection|undefined:section;return {sourceId:source?.id??section.id,billing:source?.pricingMatrix?.billing??'monthly',status:source?'ready':'missing-source',plans:(source?.items??[]) as PricingPlan[],groups:source?.pricingMatrix?.groups??[],currency:(source?.pricingCurrency??section.pricingCurrency)==='MYR'?'RM':source?.pricingCurrency??section.pricingCurrency??''} as const;}
export function pricingAmount(plan:PricingPlan,billing:'monthly'|'yearly',currency:string){if(billing==='yearly')return plan.annualPrice===undefined?'On request':`${currency}${plan.annualPrice}`;if(plan.pricingMode==='contact')return plan.pricingLabel||'On request';return plan.price===undefined?'On request':`${currency}${plan.price}`;}
export function pricingCellText(row:PricingRow,plan:PricingPlan,billing:'monthly'|'yearly',currency:string){if(row.source==='price'||row.source==='renewal')return pricingAmount(plan,billing,currency);const cell=row.cells[plan.id];return cell?.value||(cell?.type==='available'?'Included':cell?.type==='unavailable'?'Not included':'—');}
export function movePricingEntry<T extends {id:string}>(items:T[],id:string,offset:number){const next=[...items],from=next.findIndex(x=>x.id===id),to=from+offset;if(from>=0&&to>=0&&to<next.length)[next[from],next[to]]=[next[to],next[from]];return next;}
/** Matrix cells track canonical item IDs. New plans get empty cells; deleted plans lose orphan cells. */
export function reconcilePricingGroups(groups:PricingGroup[],plans:SectionItem[]):PricingGroup[]{
 return groups.map(group=>({...group,rows:group.rows.map(row=>({...row,cells:Object.fromEntries(plans.map(plan=>[plan.id,row.cells[plan.id]??{type:'text',value:''}]))}))}));
}
export function validatePricingMatrix(config:PricingMatrixConfig,plans:SectionItem[]){const errors:string[]=[],seen=new Set<string>();if(plans.length>6)errors.push('At most 6 plans');if(config.groups.length>30)errors.push('At most 30 groups');for(const group of config.groups){if(seen.has(group.id))errors.push('Duplicate group ID');seen.add(group.id);if(group.rows.length>200)errors.push('At most 200 rows per group');for(const row of group.rows){if(seen.has(row.id))errors.push('Duplicate row ID');seen.add(row.id);if(Object.keys(row.cells).some(id=>!plans.some(p=>p.id===id)))errors.push('Orphan plan cell');}}return errors;}
export const unifiedPricingPassport={itemFields:['title','description','price','features','isHighlighted','pricingMode','pricingLabel','billingLabel','highlightLabel','buttonText','linkUrl','planSurface','annualPrice','annualNote','monthlyNote','includesLabel'],textRoles:['title','content','itemTitle','itemContent'],imageTargets:[],actions:['item']} as const;

/** Semantic checks complement schema shape validation; old layouts are untouched. */
export function validateUnifiedPricingSection(section:PricingSection,sections:PageSection[]=[]):string[]{
 if(!isUnifiedPricing(section))return [];
 const config=section.pricingMatrix!,plans=section.items??[],errors=validatePricingMatrix(config,plans),ids=new Set<string>();
 for(const plan of plans){if(!plan.id||ids.has(plan.id))errors.push('Duplicate or empty plan ID');ids.add(plan.id);if(plan.annualPrice!==undefined&&(!Number.isFinite(plan.annualPrice)||plan.annualPrice<0))errors.push('Annual price must be a non-negative number');}
 for(const group of config.groups){if(!group.id)errors.push('Empty group ID');for(const row of group.rows){if(!row.id)errors.push('Empty row ID');if(!config.sourceSectionId&&plans.some(p=>!row.cells[p.id]))errors.push('Missing plan cell');}}
 if(config.sourceSectionId){if(resolveUnifiedPricing(section,sections)?.status!=='ready')errors.push('Pricing source unavailable');}
 return errors;
}

export function pricingActionHref(value:string):string{if(/[\u0000-\u0020\\]/.test(value))return '';if(/^mailto:[^@?]+@[^@?]+(?:\?.*)?$/i.test(value)||/^tel:\+?[0-9().-]+$/i.test(value))return value;return shopNavigationHref(value);}
