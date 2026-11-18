import type {PageSection} from '../types';
import type {TextRole} from './inspectorCapabilities';
import type {BrandPalette} from './pageStyle';
export type TextColorSource='auto'|'dark'|'light'|'custom';
export interface TextColorBinding {source:TextColorSource;color?:string}
export type TextColorBindings=Record<string,Partial<Record<TextRole,TextColorBinding>>>;
export const sectionTextTarget='$section';
export const itemTextRoles:readonly TextRole[]=['itemTitle','itemContent','label','number'];
/** Fixed Section fields keep their existing Passport role; repeaters require stable IDs. */
export function requiresTextColorItem(section:PageSection,role:TextRole):boolean{
 if((role==='itemTitle'||role==='itemContent')&&(
  section.type==='product_listing'||section.type==='payment_methods'||
  (section.type==='delivery_table'||section.type==='chapter_block'&&section.chapterPreset==='delivery')&&section.deliverySource!=='manual'
 ))return false;
 if(role==='label'&&section.type==='split_hero')return false;
 if(section.type==='hero_slider'&&['eyebrow','title','content'].includes(role))return true;
 if(role==='number'&&section.type==='chapter_block')return false;
 if(role==='label'&&(
  section.type==='media_gallery'&&section.galleryPreset==='photo-marquee'||
  section.type==='faq_accordion'&&['pricing-card-faq','pricing-band-faq'].includes(section.faqPreset??'')||
  section.type==='comparison_matrix'&&(section.comparisonPreset??'default')==='default'||
  section.type==='hero_banner'&&!section.connectedLayout&&(section.heroBannerPreset??'default')==='default'
 ))return false;
 return itemTextRoles.includes(role);
}
export function getTextColorBinding(section:PageSection,role:TextRole,itemId?:string){return section.textColorBindings?.[itemId??sectionTextTarget]?.[role];}
export function setTextColorBinding(section:PageSection,role:TextRole,itemId:string|undefined,binding:TextColorBinding):PageSection{
 const value=binding.source==='custom'?binding:{source:binding.source};
 return {...section,textColorBindings:{...section.textColorBindings,[itemId??sectionTextTarget]:{...section.textColorBindings?.[itemId??sectionTextTarget],[role]:value}}};
}
export function resetTextColorBinding(section:PageSection,role:TextRole,itemId?:string):PageSection{
 const target=itemId??sectionTextTarget;
 const bindings={...section.textColorBindings};
 if(!bindings[target]?.[role])return section;
 const roles={...bindings[target]};
 delete roles[role];
 if(Object.keys(roles).length)bindings[target]=roles;else delete bindings[target];
 const next={...section};
 if(Object.keys(bindings).length)next.textColorBindings=bindings;else delete next.textColorBindings;
 return next;
}
const escape=(s:string)=>s.replace(/[^a-zA-Z0-9_-]/g,c=>'\\'+c.codePointAt(0)!.toString(16)+' ');
/** Same appearance pipeline as Passport typography. No DOM mutation or palette snapshots. */
export function textColorBindingCss(raw:PageSection,palette:BrandPalette):string{
 if(!raw.textColorBindings)return '';
 const root='#'+escape('section-'+raw.id),rules:string[]=[];
 for(const [target,roles]of Object.entries(raw.textColorBindings))for(const [role,binding]of Object.entries(roles)){
  if(!binding)continue;
  const token=['content','lead','itemContent'].includes(role)?'muted':'ink';
  const color=binding.source==='auto'?`var(--cms-auto-${token})`:binding.source==='dark'?palette.ink:binding.source==='light'?palette.light:binding.color;
  if(!color||binding.source==='custom'&&!/^#[a-f\d]{6}$/i.test(color))continue;
  const roleSelector=`[data-cms-text="${role}"]`;
  const selector=target===sectionTextTarget?`${roleSelector}:not([data-cms-item])`:`:is(${roleSelector}[data-cms-item="${escape(target)}"],[data-cms-item="${escape(target)}"] ${roleSelector}:not([data-cms-item]))`;
  rules.push(`${root} ${selector}{color:${color}!important;}`);
  rules.push(`${root} ${selector} :where(h1,h2,h3,h4,p,span,strong,em,small){color:inherit!important;}`);
 }
 return rules.join('\n');
}

export function isTextColorBindings(value:unknown):value is TextColorBindings{
 const object=(v:unknown):v is Record<string,unknown>=>!!v&&typeof v==='object'&&!Array.isArray(v);
 const roles=['eyebrow','title','lead','content','itemTitle','itemContent','secondaryTitle','label','number'];
 return object(value)&&Object.keys(value).length<=500&&Object.entries(value).every(([id,map])=>(id==='$section'||/^[a-zA-Z0-9_.:-]{1,160}$/.test(id))&&object(map)&&Object.entries(map).every(([role,b])=>roles.includes(role)&&object(b)&&Object.keys(b).every(k=>['source','color'].includes(k))&&['auto','dark','light','custom'].includes(String(b.source))&&(b.source==='custom'?typeof b.color==='string'&&/^#[a-f\d]{6}$/i.test(b.color):!Object.prototype.hasOwnProperty.call(b,'color'))));
}
