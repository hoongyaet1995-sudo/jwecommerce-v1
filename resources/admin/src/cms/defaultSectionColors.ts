import type {PageSection} from '../types';
import {colorContrast,onColor,type PageStyle} from './pageStyle';
import {demoPalette} from './sectionDefaultDesign';
import {hasFullHeroBackdrop} from './photoBackdrop';

/** Renderer-only automatic ink. Never writes a corrected value into a document. */
export function readableDefaultColor(foreground:string,background:string,minimum=4.5):string {
 if(!/^#[\da-f]{6}$/i.test(foreground)||!/^#[\da-f]{6}$/i.test(background))return foreground;
 if(colorContrast(foreground,background)>=minimum)return foreground;
 const target=onColor(background),rgb=(s:string)=>s.slice(1).match(/../g)!.map(c=>parseInt(c,16)),a=rgb(foreground),b=rgb(target);
 for(let step=1;step<=100;step++){
  const candidate='#'+a.map((v,i)=>Math.round(v+(b[i]-v)*step/100).toString(16).padStart(2,'0')).join('');
  if(colorContrast(candidate,background)>=minimum)return candidate;
 }
 return target;
}
const idSelector=(id:string)=>'#'+('section-'+id).replace(/[^a-zA-Z0-9_-]/g,c=>'\\'+c.codePointAt(0)!.toString(16)+' ');
/** Existing Passport roles, scoped to the approved unified starters only.
 * Local panel variables separate foreground ink from paint/background tokens.
 * Authored role and action overrides are applied afterwards by actionAppearanceCss.
 */
export function defaultSectionColorCss(raw:PageSection,resolved:PageSection,page?:PageStyle):string {
 if(raw.starterDesign!=='neutral-v1')return '';
 const p=resolved.brandPalette??raw.brandPalette??page?.palette??demoPalette;
 const bg=resolved.customColors?.customBgColor??(raw.surface==='contrast'?(p.contrast??p.ink):p.paper);
 const root=idSelector(raw.id),rules:string[]=[];
 const add=(selector:string,declarations:string)=>rules.push(`${root}${selector?' :where('+selector+')':''}{${declarations}}`);
 const context=(background:string,ink=p.ink,muted=p.muted)=>`--cms-auto-ink:${colorContrast(ink,background)<7?onColor(background):ink};--cms-auto-muted:${readableDefaultColor(muted,background,onColor(background)==='#ffffff'?7:5.5)};--cms-auto-accent:${readableDefaultColor(p.accent,background)};color:var(--cms-auto-ink);`;
 const dark=p.contrast??p.ink;
 add('',context(bg));
 for(const role of ['title','itemTitle','secondaryTitle','number','label'])add(`[data-cms-text="${role}"]`,'color:var(--cms-auto-ink)!important;');
 for(const role of ['content','lead','itemContent'])add(`[data-cms-text="${role}"]`,'color:var(--cms-auto-muted)!important;');
 // Legacy opacity utilities must not dim an already muted semantic color a second time.
 add('[data-cms-text], [data-cms-text] :where(p,span,strong,em,small,footer)','opacity:1!important;');
 add('[data-cms-text] :where(p,span,strong,em,small,footer)','color:inherit!important;');
 add('[data-cms-title-emphasis], .subpage-hero-copy h1 em','color:var(--cms-auto-accent)!important;');
 // The existing eyebrow capsule owns a real background independently of the section.
 add('.cms-eyebrow',`background:${p.secondary};color:${readableDefaultColor(p.secondaryInk,p.secondary)}!important;`);
 add('.cms-eyebrow[data-eyebrow-background="dark"]',`background:#151515;color:#ffffff!important;`);
 add('.cms-eyebrow[data-eyebrow-background="light"]','background:#ffffff;color:#151515!important;');
 add('.hero-subcopy, .subpage-hero-copy > p, .about-story-copy p, .live-check-list li','color:var(--cms-auto-muted)!important;');

 // Shared native panels retain their geometry; only their default paint/ink changes.
 add('.feature-tile',`background:${p.secondary};${context(p.secondary)}`);
 add('.feature-tile-lead, .feature-tile-dark, .dark-panel, .performance-card, .outcome-wide, .process-wheel',`background:${dark};${context(dark)}`);
 add('.feature-tile-pink',`background:${p.accent};${context(p.accent)}`);
 add('.hero-shell',context(raw.surface==='contrast'?bg:'#f1f1ef'));
 add('.outcome-yellow',context('#fde589'));
 add('.outcome-grey, .about-story-copy .eyebrow',context('#f0f0f0'));
 add('.outcome-card > span',context('#000000'));
 add('.outcome-grey > span, .outcome-wide a',context('#ffffff'));
 add('.comparison-panel, .compare-grid article, .compare-quote, .final-panel',context('#090a09'));
 add('.compare-grid h3',context('#1d1e1d'));
 add('.compare-webspert h3, .process-step',context(p.secondary));
 add('.process-step[data-step="1"], .process-step[data-step="3"]',context(p.accent));
 add('.jw-saas-addon-label','opacity:1!important;');
 add('.solution-overlay',`background:${dark};${context(dark)}`);
 add('.solution-overlay a',context('#ffffff'));
 add('.tag-row span',context('#202321'));
 add('.eyebrow',`background:${p.secondary};${context(p.secondary)}`);
 add('.service-process-panel, .service-process-grid article',context('#090a09'));
 add('.service-highlight-grid article',context(raw.surface==='contrast'?bg:'#f1f1ef'));
 add('.service-highlight-grid article:nth-child(2)',context(p.accent));
 add('.service-highlight-grid article:nth-child(4)',context('#111111'));
 add('.service-feature-grid article',context('#ffffff'));
 add('.service-feature-grid article:nth-child(2), .service-feature-grid article:nth-child(6)',context(p.secondary));
 add('.service-feature-grid article:nth-child(4)',context(p.accent));
 add('.service-feature-grid article:nth-child(5)',context('#111111'));
 add('.process-story',context(dark));
 add('.process-story','--cms-auto-ink:#ffffff;--cms-auto-muted:#ffffff;');
 if(!raw.colorOverrides?.includes('customBgColor'))add('.process-story',`background-color:${dark}!important;background-blend-mode:multiply;`);
 add('.faq-grid details',`background:${p.secondary};${context(p.secondary)}`);
 add('.faq-grid details[open]',`background:${p.secondary};`);
 add('.jw-unified-pricing .plan-card',context(p.paper));
 add('.jw-unified-pricing .plan-card-dark',context(dark));
 add('.jw-unified-pricing .plan-table-wrap, .jw-unified-pricing .mobile-plan-comparison',context('#ffffff'));
 add('.jw-unified-pricing .plan-table td.is-unavailable',`color:${readableDefaultColor(p.muted,'#ffffff')}!important;`);
 add('[data-editorial-story-cards] article',context(resolved.customColors?.secondaryBtnBg??p.secondary));
 add('[data-industrial-faq] article:has([aria-expanded="true"])',context(resolved.customColors?.secondaryBtnBg??p.secondary));
 add('[data-minimal-faq] .bg-neutral-50, [data-device-comparison] tr:nth-child(even)',context('#fafafa'));
 add('[data-kids-discovery] .relative.w-\\[62\\%\\]',`background:${p.secondary};${context(p.secondary)}`);
 add('.corp-connections:not(.corp-connected-feature-rows):not(.corp-image-cards-staggered) .corp-connection-copy',context(p.paper));
 add('.corp-starter-card, .corp-starter-answers details',context('#f3edff'));
 add('[data-market-navigation] article > .relative.z-10',context('#f7f7f7'));
 add('[data-market-navigation] article > [data-cms-text="label"], .plan-comparison-badge',`--cms-auto-ink:${onColor(resolved.customColors?.primaryBtnBg??p.accent)};`);
 // Existing gradient panels keep their shape; automatic colors use one readable tonal range.
 if(!raw.colorOverrides?.includes('customBgColor')){
  add('.corp-gradient-panel',`background-image:linear-gradient(110deg,${p.accent},${p.hover});${context(p.accent)}`);
  if(raw.surface==='contrast'){
   add('.corp-payments',`background-image:radial-gradient(ellipse at 0 0,color-mix(in srgb,${p.secondary} 8%,${bg}),transparent 45%),radial-gradient(ellipse at 100% 25%,color-mix(in srgb,${p.secondary} 8%,${bg}),transparent 40%);`);
   add('.corp-courier',`background-image:radial-gradient(ellipse at 100% 0,color-mix(in srgb,${p.secondary} 8%,${bg}),transparent 48%);`);
   add('.corp-marketplace',`background-image:radial-gradient(ellipse at 70% 35%,color-mix(in srgb,${p.secondary} 8%,${bg}),transparent 65%);`);
  }
 }
 if(raw.type==='featured_deal')add('[class~="bg-gradient-to-r"]',context('#151515'));
 if(raw.type==='pricing_table'&&!raw.pricingMatrix)add('[class~="bg-gradient-to-b"]',`background-image:linear-gradient(180deg,${dark},color-mix(in srgb,${p.accent} 24%,${dark}));${context(dark)}`);
 if(raw.type==='product_listing'&&raw.listingPreset?.startsWith('kids-'))add('[data-cms-source] article',`background:${p.secondary};${context(p.secondary)}`);
 if(raw.surface==='contrast'){
  add('.corp-pricing-band-faq .corp-starter-answers details',`background:${p.ink}!important;${context(p.ink)}`);
  add('[data-editorial-gallery="image-cards-contrast"] .corp-connection-copy',`background:${p.ink}!important;${context(p.ink)}`);
  add('[data-editorial-gallery="image-cards-contrast"] article:nth-child(2) .corp-connection-copy',`background:${p.paper}!important;${context(p.paper)}`);
  add('[data-editorial-gallery="staggered-focus-cards"] article:nth-child(2) .corp-connection-copy',`background:${p.ink};${context(p.ink)}`);
 }
 add('.corp-workflow, .corp-workflow-caption, .corp-support-overlay',`background:${p.secondary};${context(p.secondary)}`);
 add('.corp-network-steps > div',`background:${p.paper};${context(p.paper)}`);
 // Announcement is painted with the primary button background, not the section surface.
 if(raw.type==='announcement_bar')add('[data-cms-text="itemTitle"]',`color:${readableDefaultColor(p.light,resolved.customColors?.primaryBtnBg??p.accent)}!important;`);
 // Photo text uses its existing composition/overlay. Never paint heading/paragraph wrappers.
 add('[data-editorial-overlay] [data-cms-hero-safe] > div',context('#f5f5f4'));
 add('[data-image-banner], [data-collection-banners] article',`--cms-auto-ink:#ffffff;--cms-auto-muted:#ffffff;--cms-auto-accent:${readableDefaultColor(p.accent,'#151515')};`);
 if(hasFullHeroBackdrop(raw)){
  add('[data-cms-hero-safe]',`--cms-auto-ink:#ffffff;--cms-auto-muted:#ffffff;--cms-auto-accent:${readableDefaultColor(p.accent,'#151515')};`);
  add('[data-cms-hero-safe] [data-cms-text="title"], [data-cms-hero-safe] [data-cms-text="content"]','text-shadow:0 1px 3px rgba(0,0,0,.6);');
 }
 add('[data-editorial-overlay] > .flex, [data-editorial-overlay] > .absolute.right-0',context('#f5f5f4'));
 add('[data-editorial-overlay] [data-cms-text="content"][style*="text-shadow"]',context('#f5f5f4'));
 add('[data-product-lookbook] article > .relative, [data-promotion-highlights] [data-cms-item] > .p-5',context('#f7f6f0'));
 if(raw.type==='media_gallery'&&!raw.connectedLayout)add('[class~="bg-gradient-to-t"]','--cms-auto-ink:#ffffff;--cms-auto-muted:#ffffff;');
 // Keep the original full-image gradient readable across mobile text, without text backgrounds.
 if(!raw.colorOverrides?.includes('customBgColor'))add('[data-editorial-gradient-hero] > .absolute.pointer-events-none',`background:linear-gradient(90deg,${bg} 0%,${bg}F2 65%,${bg}CC 100%)!important;`);
 // Source-only cards sometimes use a translucent pale panel inside a Contrast section.
 add('[data-cms-button][data-cms-button-kind="link"]','color:var(--cms-auto-ink)!important;opacity:1!important;');
 return rules.join('\n');
}
