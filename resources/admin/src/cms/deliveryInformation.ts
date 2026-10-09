export interface DeliveryRate {id:string;name:string;zoneRegion:string;customStates?:string[];isActive:boolean;rateType:string;estimatedDays?:string;flatRateCost?:number}
export interface DeliveryRow {id:string;title:string;deliveryEstimate:string;deliveryFee:string}
export function deliveryRows(rates:DeliveryRate[]):DeliveryRow[] {
 const regions:Record<string,string>={all:'Configured delivery areas',peninsular:'Peninsular Malaysia',east_malaysia:'Sabah & Sarawak',sabah:'Sabah',sarawak:'Sarawak',international:'International',custom:'Custom delivery area'};
 return rates.filter(rate=>rate.isActive).map(rate=>({id:rate.id,title:`${rate.zoneRegion==='custom'?rate.customStates?.join(', ')||regions.custom:regions[rate.zoneRegion]||rate.zoneRegion} — ${rate.name}`,deliveryEstimate:rate.estimatedDays?.trim()||'Confirm at checkout',deliveryFee:'Calculated at checkout'}));
}
