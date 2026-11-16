import type {PageSection} from '../types';
import {normalizeSampleSection,demoPalette} from './sectionDefaultDesign';
import measurements from './sectionDefaultMedia.json';
import {imageTargets,updateImageTarget} from './imageTargets';
import {hasFullHeroBackdrop,hasCollectionPhotoBackdrop} from './photoBackdrop';

export const unifiedStarterPalette=demoPalette;
export const neutralMediaRoot='/images/cms/defaults/neutral/';
/** Trusted, bundled files only. Neither data URLs nor sample routes enter saved documents. */
export function neutralMediaUrl(kind='photo',ratio=1.6,safeSide='center'){
 const height=Math.max(1,Math.round(1600/ratio));
 return `${neutralMediaRoot}${kind}-${height}-${safeSide}.svg`;
}
/** Creation boundary only: existing document reads, layout switches and saves never call this. */
export function applyNewSectionDefaults(source:PageSection,cardIndex?:number):PageSection{
 let section=normalizeSampleSection(source);
 section.starterDesign='neutral-v1';
 // Creation defaults only: reuse the existing full-photo overlay, never text backing boxes.
 if(hasFullHeroBackdrop(section))section.overlayOpacity=section.type==='hero_slider'?100:65;
 if(typeof section.formSuccessMessage==='string')section.formSuccessMessage='Thank you. Your message has been received.';
 // Page palette remains authoritative. The JW demo palette is the fallback when no page palette is set.
 delete section.brandPalette;
 const replace=(v:unknown):unknown=>Array.isArray(v)?v.map(replace):v&&typeof v==='object'?Object.fromEntries(Object.entries(v).map(([k,x])=>[k,typeof x==='string'&&x.startsWith('/section-defaults-sample/media/')?neutralMediaUrl(x.endsWith('logo.svg')?'logo':'photo'):replace(x)])):v;
 section=replace(section) as PageSection;
 const slots=cardIndex===undefined?[]:measurements[cardIndex]??[];
 for(const [i,target] of imageTargets(section).entries()){
  const measured=slots[i]??slots[0];
  const kind=target.field==='authorAvatar'?'avatar':section.type==='logo_cloud'?'logo':measured?.kind??'photo';
  section=updateImageTarget(section,target.id,{mediaUrl:neutralMediaUrl(kind,measured?.ratio??1.6,measured?.safeSide??'center')});
  if(section.type==='cta_strip'&&section.ctaPreset==='image-banner'||hasCollectionPhotoBackdrop(section))section=updateImageTarget(section,target.id,{imageOverlay:true,imageOverlayOpacity:70});
 }
 // Starters expose actions for editing without carrying unrelated sample-page anchors.
 const clearDemoLinks=(v:unknown):unknown=>Array.isArray(v)?v.map(clearDemoLinks):v&&typeof v==='object'?Object.fromEntries(Object.entries(v).map(([k,x])=>[k,x==='#sample'?'':clearDemoLinks(x)])):v;
 return clearDemoLinks(section) as PageSection;
}
/** Rekey owned identities and slot/plan references together; source/catalog IDs remain untouched. */
export function createNewSection(data:Omit<PageSection,'id'>,cardIndex?:number):PageSection{
 const prefix=`sec-${crypto.randomUUID()}`;
 const normalized=applyNewSectionDefaults({...structuredClone(data),id:prefix},cardIndex);
 const ids=new Map<string,string>();let counter=0;
 const collect=(v:unknown)=>{if(Array.isArray(v))v.forEach(collect);else if(v&&typeof v==='object')for(const [k,x]of Object.entries(v)){if(k==='id'&&typeof x==='string'&&x!==prefix)ids.set(x,`${prefix}-entry-${++counter}`);else collect(x)}};
 collect(normalized);
 const rewrite=(v:unknown):unknown=>Array.isArray(v)?v.map(rewrite):v&&typeof v==='object'?Object.fromEntries(Object.entries(v).map(([k,x])=>[ids.get(k)??k,rewrite(x)])):typeof v==='string'?(ids.get(v)??(v.startsWith('item:')?v.replace(/^item:([^:]+):/,(_,id)=>`item:${ids.get(id)??id}:`):v)):v;
 return rewrite(normalized) as PageSection;
}
