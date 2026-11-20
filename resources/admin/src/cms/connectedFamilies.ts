import type {PageSection,SectionItem} from '../types';
import source from './connectedFamilyMetadata.json';
import illustrationNames from './illustrationLabelNames.json';
export const connectedLayouts=['hero-centered','hero-split','cards-grid','cards-bento','cards-compact','story-rows','story-split','process-cards','process-orbit','metrics-bento','metrics-row','comparison-narrative','preview-tabs','sticky-stack','logo-marquee','faq-list','closing-cta'] as const;
export type ConnectedItem=SectionItem&{layoutGroup?:string;layoutVariant?:number;layoutSlot?:string};
export type ConnectedSection=PageSection&{connectedLayout?:typeof connectedLayouts[number];connectedDiagram?:keyof typeof source;connectedVisualContext?:'default'|'commerce'|'operations'|'workflow'|'fulfilment'|'website';connectedLayoutMemory?:Partial<Record<typeof connectedLayouts[number],keyof typeof source>>;items?:ConnectedItem[]};
type IllustrationSlot={slot:string;label:string;field:string;role:string};
export type ConnectedPassport={family:string;layout:string;type:string;groups:Array<{id:string;label:string;kind:string;fields:string[];min:number;max:number}>;details:IllustrationSlot[];textRoles:string[];sectionFields:string[];actions:{primary:{button:boolean;link:boolean};secondary:{button:boolean;link:boolean}}};
const names:Record<string,Record<string,string>>=illustrationNames;
export const connectedFamilyMetadata:Record<string,ConnectedPassport>=Object.fromEntries(
 Object.entries(source).map(([id,meta])=>[id,{
  ...meta,
  details:meta.details.map((slot,index)=>({...slot,label:names[id]?.[slot.slot]??`Illustration field ${index+1}`}))
 }])
);
export const connectedDiagramIds=Object.keys(source);
export function isConnectedFamily(section:PageSection):section is ConnectedSection{const s=section as ConnectedSection;return !!s.connectedDiagram&&!!connectedFamilyMetadata[s.connectedDiagram]&&connectedFamilyMetadata[s.connectedDiagram].type===s.type&&connectedFamilyMetadata[s.connectedDiagram].layout===s.connectedLayout;}
export function connectedPassport(section:PageSection):ConnectedPassport|undefined{
 if(!isConnectedFamily(section))return undefined;
 const meta=connectedFamilyMetadata[section.connectedDiagram!];
 if(section.connectedDiagram==='tabs-composition-1')return {...meta,sectionFields:meta.sectionFields.filter(k=>!['buttonText','buttonLink','mediaUrl','mediaAlt'].includes(k)),actions:{...meta.actions,primary:{button:false,link:false}},details:meta.details.filter(d=>d.slot!=='tabs-composition-1-field-2'&&!Array.from({length:8},(_,i)=>`tabs-composition-1-label-${i+3}`).includes(d.slot))};
 return meta;
}
/** Per-entry capabilities do not delete hidden values when an authored variant changes. */
export function connectedEntryFields(section:PageSection,item:ConnectedItem):readonly string[]{
 const fields=connectedPassport(section)?.groups.find(g=>g.id===item.layoutGroup)?.fields??[];
 if((section as ConnectedSection).connectedDiagram==='metrics-composition-1'&&item.layoutGroup==='c0'){
  const variant=item.layoutVariant??0;
  return fields.filter(k=>variant===1?['title','value'].includes(k):variant===0?['title','value','description'].includes(k):true);
 }
 return fields;
}
export function connectedLayoutChoices(section:PageSection){const family=connectedPassport(section)?.family;if(!family)return [];return [...new Set(Object.values(connectedFamilyMetadata).filter(m=>m.family===family).map(m=>m.layout))];}
/** A layout choice changes presentation only. Hidden slot and item values remain page owned. */
export function connectedLayoutPatch(section:ConnectedSection,layout:string):ConnectedSection{const old=connectedPassport(section);if(!old||layout===section.connectedLayout)return section;const memory={...section.connectedLayoutMemory,[section.connectedLayout!]:section.connectedDiagram!};const remembered=memory[layout as keyof typeof memory],candidate=remembered?connectedFamilyMetadata[remembered]:undefined;const next=candidate?.family===old.family&&candidate.layout===layout?[remembered,candidate] as const:Object.entries(connectedFamilyMetadata).find(([,meta])=>meta.family===old.family&&meta.layout===layout);return next?{...section,connectedLayout:layout as ConnectedSection['connectedLayout'],connectedDiagram:next[0] as ConnectedSection['connectedDiagram'],connectedLayoutMemory:memory}:section;}
export function connectedImageTargets(section:ConnectedSection):Array<{id:string;field:'mediaUrl'|'imageUrl';itemId?:string}>{const metadata=connectedPassport(section);if(!metadata)return [];return [...(metadata.sectionFields.includes('mediaUrl')?[{id:'mediaUrl',field:'mediaUrl' as const}]:[]),...(section.items??[]).filter(item=>connectedEntryFields(section,item).includes('imageUrl')||metadata.details.some(d=>d.slot===item.layoutSlot&&d.field==='imageUrl')).map(item=>({id:`item:${item.id}:imageUrl`,field:'imageUrl' as const,itemId:item.id}))];}
export function connectedItemFields(section:PageSection){const meta=connectedPassport(section);return meta?[...new Set(meta.groups.flatMap(g=>g.fields))]:[];}
