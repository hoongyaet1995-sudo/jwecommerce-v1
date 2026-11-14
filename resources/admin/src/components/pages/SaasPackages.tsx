import {SectionEyebrow} from './SectionEyebrow';
import {saasAction} from '../../cms/saasPackages';
import React from 'react';
import {Check} from 'lucide-react';
import type {PageSection} from '../../types';
import {saasPriceText} from '../../cms/saasPackages';
import {typographyStyle} from '../../cms/inspectorCapabilities';
import './SaasSections.css';
export function SaasPackages({section:s,currency,editing}:{section:PageSection;currency:string;editing:boolean}) {
 return <div className="jw-saas jw-saas-plans" data-saas-packages data-saas-tone={s.textColor==='dark'?'light':undefined} style={{color:s.textColor==='dark'?(s.customColors?.titleColor||'#0d1234'):undefined,'--saas-content':s.customColors?.contentColor||'#626981','--saas-accent':s.customColors?.primaryBtnBg||'#2860ff','--saas-violet':s.customColors?.eyebrowColor||'#6334ed'} as React.CSSProperties}>
  <div className="jw-saas-heading"><div><SectionEyebrow section={s}/><h2 data-cms-text="title" style={typographyStyle(s,'title')}>{s.title}</h2></div><p data-cms-text="content" style={typographyStyle(s,'content')}>{s.content}</p></div>
  <div className="jw-saas-plan-grid">{s.items?.map(item=><article key={item.id} data-cms-item={item.id} className={`jw-saas-plan ${item.isHighlighted?'is-highlighted':''}`}>
   {item.isHighlighted&&item.highlightLabel&&<span className="jw-saas-ribbon">{item.highlightLabel}</span>}
   <h3 data-cms-text="itemTitle" data-cms-item={item.id} style={typographyStyle(s,'itemTitle')}>{item.title}</h3>
   {item.badge&&<small>{item.badge}</small>}
   <div className={`jw-saas-price ${item.pricingMode==='contact'?'is-contact':''}`}>{saasPriceText(item,s.pricingCurrency||currency)}{item.pricingMode!=='contact'&&item.price!==undefined&&<small>{item.billingLabel}</small>}</div>
   <p data-cms-text="itemContent" data-cms-item={item.id} style={typographyStyle(s,'itemContent')}>{item.description}</p>
   <ul>{item.features?.filter(f=>f.trim()).map((feature,i)=><li key={i}><Check size={14} aria-hidden="true"/>{feature}</li>)}</ul>
   {item.buttonText&&item.linkUrl&&<a data-cms-button={item.isHighlighted?'primary':'secondary'} data-cms-button-kind="button" data-cms-action="item" data-cms-item={item.id} href={item.linkUrl} onClick={e=>saasAction(e,item.linkUrl,editing)} style={{background:item.isHighlighted?(s.textColor==='dark'?`linear-gradient(110deg,${s.customColors?.primaryBtnBg||'#2860ff'},${s.customColors?.eyebrowColor||'#6334ed'})`:s.customColors?.primaryBtnBg):s.customColors?.secondaryBtnBg,color:item.isHighlighted?s.customColors?.primaryBtnText:s.customColors?.secondaryBtnText}}>{item.buttonText}</a>}
  </article>)}</div>
 </div>;
}
