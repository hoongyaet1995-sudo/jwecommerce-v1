import {connectedPassport} from './connectedFamilies';
import {isUnifiedPricing} from './unifiedPricing';
import type {PageSection} from '../types';
import type {PageStyle} from './pageStyle';
import {onColor} from './pageStyle';
import matrix from './actionAppearanceMatrix.json';
export type ActionChannel='primary'|'secondary';
export interface ButtonColors {primaryBg?:string;primaryText?:string;primaryHover?:string;secondaryBg?:string;secondaryText?:string;secondaryHover?:string}
export const buttonColorKeys=['primaryBg','primaryText','primaryHover','secondaryBg','secondaryText','secondaryHover'] as const;
export const isButtonColors=(v:unknown):v is ButtonColors=>!!v&&typeof v==='object'&&!Array.isArray(v)&&Object.entries(v).every(([k,c])=>buttonColorKeys.includes(k as any)&&typeof c==='string'&&/^#[\da-f]{6}$/i.test(c));
const presets:Partial<Record<PageSection['type'],keyof PageSection>>={hero_banner:'heroBannerPreset',hero_slider:'sliderPreset',rich_text:'richTextPreset',media_text_split:'layoutPreset',media_gallery:'galleryPreset',feature_cards:'featureCardsPreset',product_listing:'listingPreset',testimonials_slider:'testimonialPreset',shop_navigation:'shopNavigationPreset',comparison_matrix:'comparisonPreset',pricing_table:'pricingPreset',faq_accordion:'faqPreset',contact_form:'contactPreset',store_locator:'contactPreset',cta_strip:'ctaPreset',newsletter_signup:'newsletterPreset',chapter_block:'chapterPreset',policy_content:'policyPreset',logo_cloud:'logoPreset',payment_methods:'paymentLayout'};
type ChannelCapability={button:boolean;link:boolean};
export interface ActionCapability {primary:ChannelCapability;secondary:ChannelCapability;headingOff?:{primary:ChannelCapability;secondary:ChannelCapability}}
const empty=():ActionCapability=>({primary:{button:false,link:false},secondary:{button:false,link:false}});
export function actionCapabilities(s:PageSection):ActionCapability {
 const connected=connectedPassport(s);if(connected)return connected.actions;
 if(isUnifiedPricing(s))return {primary:{button:true,link:false},secondary:{button:true,link:false}};
 const f=presets[s.type],key=s.type+'|'+(f?s[f]??'default':'default');
 let cap=(matrix as Record<string,ActionCapability>)[key]??empty();
 if(s.showSectionHeading===false&&cap.headingOff)cap=cap.headingOff;
 // Reusable EditorialSectionAction can intentionally render a text link.
 const helper=s.type==='policy_content'||s.type==='contact_form'&&s.contactPreset==='enquiry'||s.type==='product_listing'&&s.listingPreset==='industrial-related'||s.type==='media_gallery'&&['industrial-mosaic','album-browser'].includes(s.galleryPreset??'')||s.type==='hero_banner'&&['editorial-gradient','industrial-overlay'].includes(s.heroBannerPreset??'')||s.type==='cta_strip'&&['industrial-outline','image-banner','help-panel','editorial-band'].includes(s.ctaPreset??'')||s.type==='media_text_split'&&['artisan-story','editorial-hero','editorial-story','flush-split','producer-story','story-with-steps'].includes(s.layoutPreset??'');
 if(helper&&s.buttonStyle!==undefined)cap={...cap,primary:{button:s.buttonStyle!=='text-link',link:s.buttonStyle==='text-link'||cap.primary.link}};
 return cap;
}
export const hasButtons=(s:PageSection)=>{const c=actionCapabilities(s);return c.primary.button||c.secondary.button;};
export const hasActions=(s:PageSection)=>{const c=actionCapabilities(s);return c.primary.button||c.primary.link||c.secondary.button||c.secondary.link;};
export const buttonTarget=(channel:ActionChannel,kind?:'button'|'link')=>`[data-cms-button="${channel}"]${kind?`[data-cms-button-kind="${kind}"]`:''}`;
const escapeId=(id:string)=>id.replace(/[^a-zA-Z0-9_-]/g,c=>'\\'+c.codePointAt(0)!.toString(16)+' ');
/** Opt-in writers only. Legacy palette fields remain untouched and retain their old defaults. */
export function actionAppearanceCss(raw:PageSection,resolved:PageSection,page?:PageStyle):string {
 const root='#'+escapeId('section-'+raw.id),rules:string[]=[];
 const rule=(selector:string,value:string)=>rules.push(`${root} ${selector}{${value}}`);
 const radius=raw.buttonRadius??(page?.unifiedButtons?page.buttonRadius??0:undefined);
 const size=raw.buttonSize??(page?.unifiedButtons?page.buttonSize??'normal':undefined);
 const button='[data-cms-button][data-cms-button-kind="button"]';
 if(radius!==undefined)rule(button,`border-radius:${radius}px!important;`);
 if(size)rule(button,`min-height:${{compact:36,normal:44,large:52}[size]}px!important;padding-block:${{compact:8,normal:12,large:16}[size]}px!important;box-sizing:border-box;display:inline-flex;align-items:center;justify-content:center;`);
 for(const channel of ['primary','secondary'] as const){
  const bg=raw.buttonColors?.[`${channel}Bg`],text=raw.buttonColors?.[`${channel}Text`],hover=raw.buttonColors?.[`${channel}Hover`]??(page?.buttonHover?page.buttonHoverColor??page.palette.hover:undefined);
  if(bg)rule(buttonTarget(channel,'button'),`background:${bg}!important;`);
  if(text)rule(buttonTarget(channel),`color:${text}!important;`);
  if(hover){rule(buttonTarget(channel,'button')+':hover',`background:${hover}!important;color:${onColor(hover)}!important;opacity:1!important;`);rule(buttonTarget(channel,'link')+':hover',`color:${hover}!important;`);}
 }
 for(const role of ['eyebrow','title','lead','content','itemTitle','itemContent','secondaryTitle','label','number'] as const){
  const local=raw.typography?.[role],inherited=page?.typography?.[role];
  const owned=local?.colorOverride||(!local?.color&&inherited?.colorOverride);
  const color=owned?(local?.colorOverride?local.color:inherited?.color):undefined;
  if(color){rule(`[data-cms-text="${role}"]`,`color:${color}!important;`);rule(`[data-cms-text="${role}"] :where(h1,h2,h3,h4,p,span,strong,small,em)`,`color:inherit!important;`);}
 }
 return rules.join('\n');
}
