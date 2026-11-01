import {saasAction} from '../../cms/saasPackages';
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
export function SaasModules({section:s,editing}:{section:PageSection;editing:boolean}){const addons=s.featureCardsPreset==='saas-addons';return <div className={`jw-saas jw-saas-modules ${addons?'jw-saas-addons':''}`}>
 {addons?<div className="jw-saas-addon-label"><span data-cms-text="title" style={typographyStyle(s,'title')}>{s.title}</span></div>:<div className="jw-saas-heading"><Copy s={s}/></div>}
 <div className="jw-saas-module-grid">{s.items?.map(item=><div className="jw-saas-module" data-cms-item={item.id} key={item.id}><SectionIcon name={item.icon}/><div><h3 data-cms-text="itemTitle" data-cms-item={item.id} style={typographyStyle(s,'itemTitle')}>{item.title}</h3><p data-cms-text="itemContent" data-cms-item={item.id} style={typographyStyle(s,'itemContent')}>{item.description}</p>{item.buttonText&&item.linkUrl&&<a data-cms-action="item" data-cms-item={item.id} href={item.linkUrl} onClick={e=>saasAction(e,item.linkUrl,editing)}>{item.buttonText}</a>}</div></div>)}</div>
 {addons&&s.content&&<p data-cms-text="content" style={typographyStyle(s,'content')}>{s.content}</p>}
 </div>;}
export function SaasFaq({section:s}:{section:PageSection}){return <div className="jw-saas jw-saas-faq"><div><Copy s={s}/></div><div>{s.items?.map((item,i)=><details key={item.id} data-cms-item={item.id} open={s.faqInitiallyOpen && i===0}><summary data-cms-text="itemTitle" data-cms-item={item.id} style={typographyStyle(s,'itemTitle')}>{item.title}</summary><p data-cms-text="itemContent" data-cms-item={item.id} style={typographyStyle(s,'itemContent')}>{item.description}</p></details>)}</div></div>;}
export function SaasClosing({section:s,editing}:{section:PageSection;editing:boolean}){return <div className="jw-saas jw-saas-closing"><div><Copy s={s}/></div><Actions s={s} editing={editing}/></div>;}
