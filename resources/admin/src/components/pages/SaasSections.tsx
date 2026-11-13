import {saasAction,saasPriceText} from '../../cms/saasPackages';
import type {StarterPricing} from '../../cms/websiteBuilderStarterFaq';
import {planContentSource} from '../../cms/websiteBuilderStarterFaq';
import {shopNavigationHref} from '../../cms/shopNavigation';
import './CorporateSections.css';
import {EditorialIllustratedCta} from './EditorialIllustratedCta';
import React from 'react';
import {ArrowRight} from 'lucide-react';
import type {PageSection} from '../../types';
import {ScopedImage} from './ScopedImage';
import {SectionIcon} from './IconPicker';
import {typographyStyle} from '../../cms/inspectorCapabilities';
import './SaasSections.css';
function Copy({s,hero=false}:{s:PageSection;hero?:boolean}){const Heading=hero?'h1':'h2';return <><small data-cms-text="eyebrow" style={typographyStyle(s,'eyebrow')}>{s.subtitle}</small><Heading data-cms-text="title" style={typographyStyle(s,'title')}>{s.title}</Heading>{s.content&&<p data-cms-text="content" style={typographyStyle(s,'content')}>{s.content}</p>}</>;}
function Actions({s,editing}:{s:PageSection;editing:boolean}){return <div className="jw-saas-actions">{[[s.buttonText,s.buttonLink],[s.secondaryButtonText,s.secondaryButtonLink]].map(([label,link],i)=>label&&link?<a key={i} data-cms-action={i?'secondary':'primary'} href={link} onClick={e=>saasAction(e,link,editing)} style={{background:i?s.customColors?.secondaryBtnBg:s.customColors?.primaryBtnBg,color:i?s.customColors?.secondaryBtnText:s.customColors?.primaryBtnText,border:i?'1px solid #c7ab7b55':undefined}}>{label}<ArrowRight size={14}/></a>:null)}</div>;}
export function SaasMedia({section:s,editing}:{section:PageSection;editing:boolean}){
 const hero=s.layoutPreset==='saas-hero',wide=s.layoutPreset==='saas-analytics';
 const media=s.mediaUrl?<div className="jw-saas-visual"><ScopedImage section={s} targetId="mediaUrl" src={s.mediaUrl} alt={s.mediaAlt||s.title}/></div>:null;
 return <div className={`jw-saas ${wide?'jw-saas-analytics':`jw-saas-split ${s.mediaPosition==='left'?'media-left':''}`} ${hero?'jw-saas-hero':''}`}><div className="jw-saas-copy"><Copy s={s} hero={hero}/><Actions s={s} editing={editing}/></div>{media}</div>;
}
export function SaasModules({section:s,editing}:{section:PageSection;editing:boolean}){const addons=s.featureCardsPreset==='saas-addons';return <div className={`jw-saas jw-saas-modules ${addons?'jw-saas-addons':''}`} data-saas-tone={addons&&s.textColor==='dark'?'light':undefined} style={{color:s.textColor==='dark'?(s.customColors?.titleColor||'#0d1234'):undefined,'--saas-content':s.customColors?.contentColor||'#626981','--saas-accent':s.customColors?.primaryBtnBg||'#2860ff','--saas-violet':s.customColors?.eyebrowColor||'#6334ed'} as React.CSSProperties}>
 {addons?<div className="jw-saas-addon-label"><span data-cms-text="title" style={typographyStyle(s,'title')}>{s.title}</span></div>:<div className="jw-saas-heading"><Copy s={s}/></div>}
 <div className="jw-saas-module-grid">{s.items?.map(item=><div className="jw-saas-module" data-cms-item={item.id} key={item.id}><SectionIcon name={item.icon}/><div><h3 data-cms-text="itemTitle" data-cms-item={item.id} style={typographyStyle(s,'itemTitle')}>{item.title}</h3><p data-cms-text="itemContent" data-cms-item={item.id} style={typographyStyle(s,'itemContent')}>{item.description}</p>{item.buttonText&&item.linkUrl&&<a data-cms-action="item" data-cms-item={item.id} href={item.linkUrl} onClick={e=>saasAction(e,item.linkUrl,editing)}>{item.buttonText}</a>}</div></div>)}</div>
 {addons&&s.content&&<p data-cms-text="content" style={typographyStyle(s,'content')}>{s.content}</p>}
 </div>;}
export function SaasFaq({section:s,pricing,editing=false}:{section:PageSection;pricing?:StarterPricing;editing?:boolean}){
 const cardInk=s.surface==='contrast'?s.brandPalette?.light:undefined;
 const cardType=(role:Parameters<typeof typographyStyle>[1])=>({...typographyStyle(s,role),...(cardInk?{color:cardInk}:{})});
 if(['pricing-card-faq','pricing-band-faq'].includes(s.faqPreset??''))return <div className={`corp corp-pricing-band-faq ${s.faqPreset==='pricing-card-faq'?'corp-pricing-card-faq':''}`} data-pricing-kind={pricing?.kind} data-pricing-surface={s.surface} style={{'--pricing-card-paper':s.surface==='contrast'?s.brandPalette?.ink:undefined,'--pricing-card-ink':cardInk,'--addon-paper':s.customColors?.customBgColor||'transparent','--addon-main':s.customColors?.primaryBtnBg||'currentColor','--addon-sub':s.customColors?.secondaryBtnBg||'currentColor','--corp-space':s.paddingY==='compact'?'32px':s.paddingY==='spacious'?'96px':'64px'} as React.CSSProperties}>
  <header className="corp-pricing-band-heading"><h2 data-cms-text="title" style={{color:s.customColors?.titleColor,...typographyStyle(s,'title')}}>{s.title}</h2>{s.content&&<p data-cms-text="content" style={{color:s.customColors?.contentColor,...typographyStyle(s,'content')}}>{s.content}</p>}</header>
  <article className="corp-starter-card corp-pricing-band">
   {pricing?.status==='ready'&&pricing.kind==='addon'?<><div className="corp-addon-identity"><SectionIcon name={pricing.icon}/><div>{pricing.label&&<p className="corp-addon-label" data-cms-text="eyebrow" style={cardType('eyebrow')}>{pricing.label}</p>}<h3 style={cardType('itemTitle')}>{pricing.name}</h3>{pricing.description&&<p style={cardType('itemContent')}>{pricing.description}</p>}</div></div><p className="corp-starter-price corp-addon-price">{pricing.priceLabel}</p></>:pricing?.status==='ready'?<><div className="corp-pricing-band-facts"><p className="corp-starter-name">{pricing.name}</p><p className="corp-starter-price">{pricing.priceLabel??saasPriceText({id:'source',title:pricing.name??'',price:pricing.price},pricing.currency??'')}{!pricing.priceLabel&&<small>{pricing.billingLabel}</small>}</p></div>{pricing.description&&<p className="corp-pricing-band-description">{pricing.description}</p>}</>:<p className="corp-pricing-error" role="status">{planContentSource(s)==='reference'?'Pricing source unavailable. Choose a source page, plan and terms in Content.':'Add your plan name and price in Content → Pricing card.'}</p>}
   {s.buttonText&&shopNavigationHref(s.buttonLink??'')&&<a className="corp-primary" data-cms-action="button" href={shopNavigationHref(s.buttonLink??'')} onClick={e=>saasAction(e,s.buttonLink,editing)}>{s.buttonText}<ArrowRight size={16} aria-hidden="true"/></a>}
   {pricing?.status==='ready'&&<p className="corp-starter-terms">{pricing.terms}</p>}
  </article>
  <div className="corp-starter-answers"><h2 data-cms-text={pricing?.kind==='addon'?'title':'eyebrow'} style={{color:s.customColors?.titleColor,...typographyStyle(s,pricing?.kind==='addon'?'title':'eyebrow')}}>{s.subtitle}</h2><div className="corp-pricing-faq-grid">{(s.items??[]).map((item,i)=><details key={item.id} data-cms-item={item.id} open={s.faqInitiallyOpen!==false&&i===0}><summary data-cms-text="itemTitle" data-cms-item={item.id} style={s.faqPreset==='pricing-card-faq'?typographyStyle(s,'itemTitle'):cardType('itemTitle')}>{item.title}<span aria-hidden="true" className="corp-faq-sign"/></summary><p data-cms-text="itemContent" data-cms-item={item.id} style={s.faqPreset==='pricing-card-faq'?typographyStyle(s,'itemContent'):cardType('itemContent')}>{item.description}</p></details>)}</div></div>
 </div>;
 if(s.faqPreset==='corporate-starter-faq')return <div className="corp corp-starter-faq" style={{'--corp-space':s.paddingY==='compact'?'32px':s.paddingY==='spacious'?'96px':'64px'} as React.CSSProperties}>
  <article className="corp-starter-card">
   {pricing?.status==='ready'?<p className="corp-starter-name">{pricing.name}</p>:<p className="corp-pricing-error" role="status">{planContentSource(s)==='reference'?'Pricing source unavailable. Choose a source page, plan and terms in Content.':'Add your plan name and price in Content → Pricing card.'}</p>}
   <h2 data-cms-text="title" style={{color:s.customColors?.titleColor,...typographyStyle(s,'title')}}>{s.title}</h2>
   {pricing?.status==='ready'&&<p className="corp-starter-price">{pricing.priceLabel??saasPriceText({id:'source',title:pricing.name??'',price:pricing.price},pricing.currency??'')}{!pricing.priceLabel&&<small>{pricing.billingLabel}</small>}</p>}
   {s.content&&<p className="corp-starter-intro" data-cms-text="content" style={{color:s.customColors?.contentColor,...typographyStyle(s,'content')}}>{s.content}</p>}
   {s.buttonText&&shopNavigationHref(s.buttonLink??'')&&<a className="corp-primary" data-cms-action="button" href={shopNavigationHref(s.buttonLink??'')} onClick={e=>saasAction(e,s.buttonLink,editing)}>{s.buttonText}<ArrowRight size={16} aria-hidden="true"/></a>}
   {pricing?.status==='ready'&&<p className="corp-starter-terms">{pricing.terms}</p>}
  </article>
  <div className="corp-starter-answers"><h2 data-cms-text="eyebrow" style={{color:s.customColors?.titleColor,...typographyStyle(s,'eyebrow')}}>{s.subtitle}</h2>
   {(s.items??[]).map((item,i)=><details key={item.id} data-cms-item={item.id} open={s.faqInitiallyOpen!==false&&i===0}><summary data-cms-text="itemTitle" data-cms-item={item.id} style={{color:s.customColors?.titleColor,...typographyStyle(s,'itemTitle')}}>{item.title}<span aria-hidden="true" className="corp-faq-sign"/></summary><p data-cms-text="itemContent" data-cms-item={item.id} style={{color:s.customColors?.contentColor,...typographyStyle(s,'itemContent')}}>{item.description}</p></details>)}
  </div>
 </div>;
 return <div className="jw-saas jw-saas-faq"><div><Copy s={s}/></div><div>{s.items?.map((item,i)=><details key={item.id} data-cms-item={item.id} open={s.faqInitiallyOpen && i===0}><summary data-cms-text="itemTitle" data-cms-item={item.id} style={typographyStyle(s,'itemTitle')}>{item.title}</summary><p data-cms-text="itemContent" data-cms-item={item.id} style={typographyStyle(s,'itemContent')}>{item.description}</p></details>)}</div></div>;}
export function SaasClosing({section:s,editing}:{section:PageSection;editing:boolean}){
 if(s.ctaPreset==='editorial-illustrated')return <EditorialIllustratedCta section={s} editing={editing}/>;
 if(['illustrated-panel-right','illustrated-panel'].includes(s.ctaPreset??''))return <div className="corp corp-closing-wrap" data-illustrated-surface={s.surface??'default'} style={{'--illustrated-paper':s.customColors?.customBgColor||'transparent','--illustrated-main':s.brandPalette?.accent||s.customColors?.primaryBtnBg||'currentColor','--illustrated-secondary':s.brandPalette?.secondary||s.customColors?.secondaryBtnBg||'currentColor','--illustrated-ink':s.brandPalette?.ink||s.customColors?.titleColor||'currentColor','--corp-space':s.paddingY==='compact'?'32px':s.paddingY==='spacious'?'96px':'64px'} as React.CSSProperties}><div className={`corp-illustrated-panel ${!s.mediaUrl?'without-image':''} ${s.ctaPreset==='illustrated-panel-right'?'corp-illustrated-right':''}`}>
  {s.mediaUrl&&<div className="corp-illustrated-visual"><ScopedImage section={s} targetId="mediaUrl" src={s.mediaUrl} alt={s.mediaAlt??''}/></div>}
  <div className="corp-illustrated-copy"><h2 data-cms-text="title" style={{color:s.customColors?.titleColor,...typographyStyle(s,'title')}}>{s.title}</h2>
  {s.content&&<p data-cms-text="content" style={{color:s.customColors?.contentColor,...typographyStyle(s,'content')}}>{s.content}</p>}
  {s.buttonText&&shopNavigationHref(s.buttonLink??'')&&<a data-cms-action="button" className="corp-primary" style={{background:s.surface==='contrast'?s.customColors?.primaryBtnBg:'linear-gradient(110deg,var(--illustrated-main),var(--illustrated-secondary))',color:s.customColors?.primaryBtnText}} href={shopNavigationHref(s.buttonLink??'')} onClick={e=>saasAction(e,s.buttonLink,editing)}>{s.buttonText}<ArrowRight size={18} aria-hidden="true"/></a>}</div>
 </div></div>;
 if(s.ctaPreset==='gradient-panel')return <div className="corp corp-closing-wrap" style={{...(s.surface==='contrast'?{'--corp-closing-background':`linear-gradient(110deg, ${s.customColors?.customBgColor||'currentColor'}, ${s.customColors?.secondaryBtnBg||'currentColor'})`}:{}),'--corp-space':s.paddingY==='compact'?'32px':s.paddingY==='spacious'?'96px':'64px'} as React.CSSProperties}><div className="corp-gradient-panel">
  <h2 data-cms-text="title" style={{color:s.customColors?.titleColor,...typographyStyle(s,'title')}}>{s.title}</h2>
  {s.content&&<p data-cms-text="content" style={{color:s.customColors?.contentColor,...typographyStyle(s,'content')}}>{s.content}</p>}
  {s.buttonText&&shopNavigationHref(s.buttonLink??'')&&<a data-cms-action="button" className="corp-closing-cta" href={shopNavigationHref(s.buttonLink??'')} onClick={e=>saasAction(e,s.buttonLink,editing)} style={{background:s.customColors?.primaryBtnBg,color:s.customColors?.primaryBtnText}}>{s.buttonText}<ArrowRight size={16} aria-hidden="true"/></a>}
 </div></div>;
 return <div className="jw-saas jw-saas-closing"><div><Copy s={s}/></div><Actions s={s} editing={editing}/></div>;}
