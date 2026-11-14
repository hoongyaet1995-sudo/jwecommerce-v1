import type {PageSection} from '../types';
import type {PageStyle} from './pageStyle';
import type {PageTemplateContext} from './pageTemplates';
import type {ConnectedSection} from './connectedFamilies';
import snapshots from './connectedFamilySnapshots.json';
import {pricingSampleSections} from './unifiedPricingSample';
export const connectedPageIds=Object.keys(snapshots).map(id=>`jw-connected-${id}`);
export const isConnectedPage=(id:string)=>connectedPageIds.includes(id);
export const connectedPageCards=Object.entries(snapshots).map(([id,page])=>({id:`jw-connected-${id}`,name:`JW ${page.name}`,category:'Brand & Story',description:'Editable connected business starter. Review business copy and scope before publishing.',ready:true,sections:page.sections.map(s=>'type' in s?s.type:'pricing_table'),thumbnail:page.sections.find(s=>'mediaUrl' in s)?.mediaUrl}));
export function connectedPageStyle():PageStyle{return {palette:{paper:'#ffffff',ink:'#0a0b0b',muted:'#686d6b',accent:'#ffa1ed',light:'#111111',secondary:'#94ff55',secondaryInk:'#111111',hover:'#0d1b18',contrast:'#0d1b18'},colorSource:'custom',preserveLayout:true,motionPace:'smooth'};}
/** Every call makes fresh page, item, image-slot and pricing IDs. No merchant draft is reused. */
export function connectedPageSections(id:string,context:PageTemplateContext&{connectedPageDestinations?:Record<string,string>},prefix:string):PageSection[]{
 const key=id.replace(/^jw-connected-/,'') as keyof typeof snapshots;const page=snapshots[key];if(!page)return [];
 const sections=structuredClone(page.sections) as Array<ConnectedSection&{pricingSource?:string}>;
 const pricing=pricingSampleSections(`${prefix}-pricing`);let pricingIndex=0;
 const ids=new Map(sections.map((s,i)=>[s.id,`${prefix}-section-${i+1}`]));
 const closing=[...sections].reverse().find(s=>s.type==='cta_strip');const contact=closing?`#section-${ids.get(closing.id)}`:'#storefront-support-widget';
 const anchorOwner=(anchor:string)=>{const sourceId=(page.anchors as Record<string,string>)[anchor];if(!sourceId)return undefined;const pricingSource=sections.find(s=>s.pricingSource===sourceId);return pricingSource?pricing[sourceId.endsWith('-3')?0:1]?.id:ids.get(sourceId);};
 const destination=(href:string|undefined):string|undefined=>{if(!href)return href;if(/^mailto:sales@jwaistudio\.online/i.test(href))return '#storefront-support-widget';if(href.startsWith('#')){const owner=anchorOwner(href.slice(1));return owner?`#section-${owner}`:href;}if(href.startsWith('/jw-builder-sample/preview/')){const rest=href.split('/preview/')[1],slug=rest.split('/')[0];if(!slug){const anchor=rest.split('#')[1],owner=anchor&&anchorOwner(anchor);if(owner)return `#section-${owner}`;}return context.connectedPageDestinations?.[`jw-connected-${slug||'home'}`]||'#storefront-support-widget';}return href;};
 const copy=(value:string)=>value.replace(/JW Studio/g,context.shopName?.trim()||'Your business');
 return sections.map((s,i)=>{
  if(s.pricingSource){const p=pricing[pricingIndex++];const sourceId=s.pricingSource;p.title=sourceId.endsWith('-3')?'Room to start. Freedom to grow.':'Compare plans and project scope.';p.eyebrow=sourceId.endsWith('-3')?'Monthly plans':'Scope overview';p.content=sourceId.endsWith('-3')?'Review monthly plans or request an annual project scope.':'Bring your website, commerce, support and marketing work together, one practical workflow at a time.';return {...p,containerMode:'page',motionPreset:'subtle-lift',sectionBorder:'none'};}
  const old=s.id;s.id=ids.get(old)!;const itemIds=new Map((s.items??[]).map((item,n)=>[item.id,`${s.id}-item-${n+1}`]));s.items=s.items?.map((item,n)=>({...item,id:itemIds.get(item.id)!,title:copy(item.title??''),...(item.description!==undefined?{description:copy(item.description)}:{}),...(item.linkUrl!==undefined?{linkUrl:destination(item.linkUrl)}:{})}));s.imageSlots=s.imageSlots?.map(slot=>{const match=slot.target.match(/^item:([^:]+):imageUrl$/);return match&&itemIds.has(match[1])?{...slot,target:`item:${itemIds.get(match[1])}:imageUrl`}:slot;});
  for(const field of ['title','content','eyebrow','subtitle','leadText'] as const)if(typeof s[field]==='string')s[field]=copy(s[field]!);
  s.buttonLink=destination(s.buttonLink)||((s.buttonText)?contact:undefined);s.secondaryButtonLink=destination(s.secondaryButtonLink);
  return s;
 });
}
