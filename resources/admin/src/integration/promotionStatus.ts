export function promotionStatus(p:{isActive:boolean;startsAt:string|null;endsAt:string|null;usageLimit:number|null;usageCount:number},now=Date.now()) {
 return !p.isActive?'inactive':p.startsAt&&Date.parse(p.startsAt)>now?'scheduled':p.endsAt&&Date.parse(p.endsAt)<now?'expired':p.usageLimit!==null&&p.usageCount>=p.usageLimit?'exhausted':'active';
}
