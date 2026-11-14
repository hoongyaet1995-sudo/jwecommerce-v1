import type {PageSection} from '../types';

/** Copy authored data independently and remap image targets with their owning items. */
export function duplicateSection(section:PageSection):PageSection {
 const copy=structuredClone(section);
 const itemIds=new Map<string,string>();
 copy.id=`sec-${crypto.randomUUID()}`;
 copy.productTabs=copy.productTabs?.map(tab=>({...tab,id:`tab-${crypto.randomUUID()}`}));
 copy.title=`${section.title} (Copy)`;
 copy.items=copy.items?.map(item=>{const id=`item-${crypto.randomUUID()}`;itemIds.set(item.id,id);return {...item,id};});
 if(copy.pricingMatrix&&!copy.pricingMatrix.sourceSectionId)copy.pricingMatrix={...copy.pricingMatrix,groups:copy.pricingMatrix.groups.map(group=>({...group,id:`group-${crypto.randomUUID()}`,rows:group.rows.map(row=>({...row,id:`row-${crypto.randomUUID()}`,cells:Object.fromEntries(Object.entries(row.cells).map(([id,cell])=>[itemIds.get(id)??id,{...cell}]))}))}))};
 if(copy.imageSlots)copy.imageSlots=copy.imageSlots.map(slot=>{
  for(const [oldId,newId] of itemIds)if(slot.target.startsWith(`item:${oldId}:`))return {...slot,target:`item:${newId}:${slot.target.slice(`item:${oldId}:`.length)}`};
  return slot;
 });
 return copy;
}
