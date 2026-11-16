import type {PageSection} from '../types';
import {connectedFamilyMetadata,connectedPassport} from './connectedFamilies';
import {connectedPageIds,connectedPageSections} from './connectedPageTemplates';
import {pricingSampleSections} from './unifiedPricingSample';

export const sharedLayoutLabels:Record<string,string>={
 'hero-centered':'Centered Hero','hero-split':'Split Hero','cards-grid':'Card Grid','cards-bento':'Bento Cards','cards-compact':'Compact Cards',
 'story-rows':'Alternating Story Rows','story-split':'Split Story','process-cards':'Process Cards','process-orbit':'Orbit Process',
 'metrics-bento':'Bento Metrics','metrics-row':'Metrics Row','comparison-narrative':'Narrative Comparison','preview-tabs':'Preview Tabs',
 'sticky-stack':'Sticky Stack','logo-marquee':'Logo Marquee','faq-list':'FAQ List','closing-cta':'Closing CTA',
 'pricing-cards':'Plan Cards','pricing-matrix':'Feature Matrix',
};
const familyNames:Record<string,string>={hero:'Hero',cards:'Cards',story:'Story',process:'Process',metrics:'Metrics',tabs:'Preview Tabs',stack:'Sticky Stack',comparison:'Comparison',logos:'Logos',faq:'FAQ',cta:'Closing CTA',pricing:'Pricing'};
export const sharedSectionFamilies=[...new Set(Object.values(connectedFamilyMetadata).map(x=>x.family))].map(id=>{
 const metadata=Object.values(connectedFamilyMetadata).filter(x=>x.family===id);
 return {id,title:`Connected ${familyNames[id]}`,type:metadata[0].type as PageSection['type'],layouts:[...new Set(metadata.map(x=>x.layout))].map(id=>({id,label:sharedLayoutLabels[id]}))};
}).concat([{id:'pricing',title:'Connected Pricing',type:'pricing_table',layouts:['pricing-cards','pricing-matrix'].map(id=>({id,label:sharedLayoutLabels[id]}))}]);

/** Every insert owns fresh section/item/image/feature IDs. Never modify existing page documents. */
export function createSharedSection(familyId:string,layoutId:string):PageSection{
 const family=sharedSectionFamilies.find(x=>x.id===familyId);
 if(!family?.layouts.some(x=>x.id===layoutId))throw new Error('Unsupported shared layout');
 const prefix=`shared-${crypto.randomUUID()}`;
 if(familyId==='pricing'){
  const source=pricingSampleSections(prefix)[0];
  return {...source,pricingPreset:layoutId==='pricing-matrix'?'plan-comparison':'saas-packages',pricingMatrix:{...source.pricingMatrix!,view:layoutId==='pricing-matrix'?'matrix':'cards'},containerMode:'page',sectionBorder:'none',motionPreset:'subtle-lift',isVisible:true};
 }
 for(const pageId of connectedPageIds){
  const source=connectedPageSections(pageId,{},prefix).find(s=>{const p=connectedPassport(s);return p?.family===familyId&&p.layout===layoutId});
  if(source){
   // Standalone inserts must not keep anchors to other sections in the factory page.
   const href=(value:string|undefined)=>value?.startsWith('#section-')?'#storefront-support-widget':value;
   return {...source,containerMode:'page',sectionBorder:'none',motionPreset:'subtle-lift',isVisible:true,buttonLink:href(source.buttonLink),secondaryButtonLink:href(source.secondaryButtonLink),items:source.items?.map(i=>({...i,linkUrl:href(i.linkUrl)}))};
  }
 }
 throw new Error('Shared layout starter unavailable');
}
