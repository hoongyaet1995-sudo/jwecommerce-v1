import {isTeamStoryOverview} from '../../cms/teamStoryOverview';
import {isImageHighlights,highlightItems} from '../../cms/imageHighlights';
import React,{useState} from 'react';
import type {PageSection} from '../../types';
import {ScopedImage} from './ScopedImage';
import {SectionIcon} from './IconPicker';
import {typographyStyle} from '../../cms/inspectorCapabilities';
import {saasAction} from '../../cms/saasPackages';
import {CorporateWorkflow} from './CorporateWorkflow';
import './CorporateSections.css';
import {PartnerLogos} from './PartnerLogos';
function brand(s:PageSection):React.CSSProperties{return {'--corp-blue':s.customColors?.primaryBtnBg||'#1463ff','--corp-violet':s.customColors?.secondaryBtnBg||'#8524ff','--corp-primary-ink':s.customColors?.primaryBtnText||'#ffffff','--corp-secondary-ink':s.customColors?.secondaryBtnText||'#3424bd','--corp-space':s.paddingY==='compact'?'32px':s.paddingY==='normal'?'64px':'96px'} as React.CSSProperties;}

function Action({s,editing,secondary=false}:{s:PageSection;editing:boolean;secondary?:boolean}){
 const label=secondary?s.secondaryButtonText:s.buttonText,href=secondary?s.secondaryButtonLink:s.buttonLink;
 if(!label||!href)return null;
 return <a data-cms-action={secondary?'secondary':'button'} className={secondary?'corp-secondary':'corp-primary'} href={href} onClick={e=>saasAction(e,href,editing)}>{label}<span aria-hidden="true"> →</span></a>;
}
function Copy({s,hero=false}:{s:PageSection;hero?:boolean}){
 const H=hero?'h1':'h2';return <>{s.subtitle&&<p className="corp-eyebrow" data-cms-text="eyebrow" style={{color:s.customColors?.eyebrowColor,...typographyStyle(s,'eyebrow')}}>{s.subtitle}</p>}<H data-cms-text="title" style={{color:s.customColors?.titleColor,...typographyStyle(s,'title')}}>{s.title}</H>{s.content&&<p className="corp-body" data-cms-text="content" style={{color:s.customColors?.contentColor,...typographyStyle(s,'content')}}>{s.content}</p>}</>;
}
export function CorporateHero({section:s,editing=false}:{section:PageSection;editing?:boolean}){
 if(s.heroBannerPreset==='corporate-centered-composite')return <div className="corp corp-hero-centered-composite" style={{...brand(s),'--hero-paper':s.customColors?.customBgColor||'transparent','--hero-accent':s.customColors?.primaryBtnBg||'currentColor'} as React.CSSProperties} data-corporate="centered-composite-hero">
  <div className="corp-centered-copy"><Copy s={s} hero/><div className="corp-actions"><Action s={s} editing={editing}/><Action s={s} editing={editing} secondary/></div></div>
  <div className="corp-centered-art">{s.mediaUrl?<><ScopedImage section={s} targetId="mediaUrl" src={s.mediaUrl} alt={s.mediaAlt||s.title} fetchPriority="high"/><div className="corp-art-activity" aria-hidden="true"><i/><i/><i/></div></>:editing?<p>Add hero artwork in Content → Images.</p>:null}</div>
 </div>;
 if(s.heroBannerPreset==='corporate-split-composite')return <div className="corp corp-hero-split" style={brand(s)} data-corporate="hero-split">
  <div className="corp-hero-copy"><Copy s={s} hero/><div className="corp-actions"><Action s={s} editing={editing}/><Action s={s} editing={editing} secondary/></div></div>
  <div className="corp-hero-art">{s.mediaUrl?<ScopedImage section={s} targetId="mediaUrl" src={s.mediaUrl} alt={s.mediaAlt||s.title} fetchPriority="high"/>:editing?<p>Add hero artwork in Content → Images.</p>:null}</div>
 </div>;
 return <div className="corp corp-hero" style={brand(s)} data-corporate="hero"><Copy s={s} hero/><div className="corp-actions"><Action s={s} editing={editing}/><Action s={s} editing={editing} secondary/></div></div>;
}
export function CorporateFeature({section:s,editing=false}:{section:PageSection;editing?:boolean}){
 if(s.layoutPreset==='corporate-section-story')return <div className="corp corp-feature corp-media-right corp-picture-story corp-section-story" style={brand(s)} data-corporate="section-story">
  <div className="corp-copy"><Copy s={{...s,subtitle:undefined}}/><ul className="corp-benefits">{s.items?.map(item=><li key={item.id} data-cms-item={item.id}><SectionIcon name={item.icon}/><strong data-cms-text="itemTitle" data-cms-item={item.id} style={{color:s.customColors?.titleColor,...typographyStyle(s,'itemTitle')}}>{item.title}</strong></li>)}</ul></div>
  <div className="corp-photo">{s.mediaUrl?<ScopedImage section={s} targetId="mediaUrl" src={s.mediaUrl} alt={s.mediaAlt||s.title}/>:editing?<p>Add section artwork in Content → Images.</p>:null}</div>
 </div>;
 const support=s.layoutPreset==='corporate-support';
 const benefits=s.items?.filter(i=>!['workflow','caption'].includes(i.category??'')&&(!support||i.category!=='channel'))??[];
 return <div className={`corp corp-feature ${s.mediaPosition==='right'?'corp-media-right':''} ${support?'corp-support':''} ${s.layoutPreset==='corporate-image-story'?'corp-picture-story':''}`} style={brand(s)} data-corporate={support?'support':'feature'}>
  <div className="corp-photo">
   {s.mediaUrl?<ScopedImage section={s} targetId="mediaUrl" src={s.mediaUrl} alt={s.mediaAlt||s.title}/>:editing?<p>Add a business photograph in Content → Images.</p>:null}
   {s.layoutPreset!=='corporate-image-story'&&<CorporateWorkflow section={s}/>}
  </div>
  <div className="corp-copy"><Copy s={s}/><ul className="corp-benefits">{benefits.map(item=><li key={item.id} data-cms-item={item.id}><SectionIcon name={item.icon}/><div><strong data-cms-text="itemTitle" data-cms-item={item.id} style={{color:s.customColors?.titleColor,...typographyStyle(s,'itemTitle')}}>{item.title}</strong>{item.description&&<p data-cms-text="itemContent" data-cms-item={item.id} style={{color:s.customColors?.contentColor,...typographyStyle(s,'itemContent')}}>{item.description}</p>}</div></li>)}</ul><div className="corp-actions"><Action s={s} editing={editing}/><Action s={s} editing={editing} secondary/></div></div>
 </div>;
}
/** One composite owns the illustrative UI; real labels remain independently editable. */
export function CorporateBuilderOverview({section:s,editing=false}:{section:PageSection;editing?:boolean}){
 const team=isTeamStoryOverview(s);
 const labels=<ul className="corp-builder-labels">{(isImageHighlights(s)?highlightItems(s):s.items)?.map(item=><li key={item.id} data-cms-item={item.id}><SectionIcon name={item.icon}/><span data-cms-text="itemTitle" data-cms-item={item.id} style={{color:s.customColors?.titleColor,...typographyStyle(s,'itemTitle')}}>{item.title}</span>{isImageHighlights(s)&&item.description&&<p data-cms-text="itemContent" data-cms-item={item.id} style={{color:s.customColors?.contentColor,...typographyStyle(s,'itemContent')}}>{item.description}</p>}</li>)}</ul>;
 if(team)return <div className="corp corp-builder-overview corp-team-story-overview" style={{...brand(s),backgroundColor:s.customColors?.customBgColor}} data-corporate="team-story-overview">
  <div className="corp-builder-visual">{s.mediaUrl?<ScopedImage section={s} targetId="mediaUrl" src={s.mediaUrl} alt={s.mediaAlt||s.title}/>:editing?<p>Add builder artwork in Content → Images.</p>:null}</div>
  <div className="corp-team-story-copy"><header className="corp-builder-heading"><Copy s={s}/></header>{labels}</div>
 </div>;
 return <div className={`corp corp-builder-overview ${team?'corp-team-story-overview':isImageHighlights(s)?'corp-image-highlights':''}`} style={{...brand(s),...(team?{backgroundColor:s.customColors?.customBgColor}: {})}} data-corporate={team?'team-story-overview':'builder-overview'}>
  <header className="corp-builder-heading">{team&&s.subtitle&&<p className="corp-eyebrow" data-cms-text="eyebrow" style={{color:s.customColors?.eyebrowColor,...typographyStyle(s,'eyebrow')}}>{s.subtitle}</p>}<h2 data-cms-text="title" style={{color:s.customColors?.titleColor,...typographyStyle(s,'title')}}>{s.title}</h2>{s.content&&<p className="corp-body" data-cms-text="content" style={{color:s.customColors?.contentColor,...typographyStyle(s,'content')}}>{s.content}</p>}</header>
  {team&&labels}
  <div className="corp-builder-visual">{s.mediaUrl?<ScopedImage section={s} targetId="mediaUrl" src={s.mediaUrl} alt={s.mediaAlt||s.title}/>:editing?<p>Add builder artwork in Content → Images.</p>:null}</div>
  {!team&&labels}
 </div>;
}
export function CorporateTabs({section:s,editing=false}:{section:PageSection;editing?:boolean}){
 const items=s.items??[],[selected,setSelected]=useState<string>();
 const current=items.find(i=>i.id===selected)??items[0];
 const choose=(index:number)=>{const item=items[index];if(item){setSelected(item.id);document.getElementById(`${s.id}-tab-${item.id}`)?.focus();}};
 return <div className="corp corp-tabs" style={brand(s)} data-corporate="tabs">{s.showSectionHeading!==false&&<div className="corp-tabs-heading"><Copy s={s}/></div>}<div className="corp-tablist" role="tablist" aria-label={s.title||'Product previews'}>{items.map((item,index)=><button type="button" role="tab" key={item.id} id={`${s.id}-tab-${item.id}`} aria-selected={current?.id===item.id} aria-controls={`${s.id}-panel`} tabIndex={current?.id===item.id?0:-1} onClick={()=>setSelected(item.id)} onKeyDown={e=>{const n=e.key==='ArrowRight'?(index+1)%items.length:e.key==='ArrowLeft'?(index+items.length-1)%items.length:e.key==='Home'?0:e.key==='End'?items.length-1:null;if(n!==null){e.preventDefault();choose(n);}}}><SectionIcon name={item.icon}/><span>{item.title}</span></button>)}</div>{current?<div className="corp-tabpanel" role="tabpanel" tabIndex={0} id={`${s.id}-panel`} aria-labelledby={`${s.id}-tab-${current.id}`} data-cms-item={current.id}><div className="corp-tab-image">{current.imageUrl?<ScopedImage section={s} targetId={`item:${current.id}:imageUrl`} src={current.imageUrl} alt={s.imageSlots?.find(slot=>slot.target===`item:${current.id}:imageUrl`)?.mediaAlt||current.title}/>:editing?<p>Add an image for this tab.</p>:null}</div><div className="corp-tab-caption"><div><h3 data-cms-text="itemTitle" data-cms-item={current.id} style={{color:s.customColors?.titleColor,...typographyStyle(s,'itemTitle')}}>{current.subtitle||current.title}</h3>{current.description&&<p data-cms-text="itemContent" data-cms-item={current.id} style={{color:s.customColors?.contentColor,...typographyStyle(s,'itemContent')}}>{current.description}</p>}</div>{current.buttonText&&current.linkUrl&&<a className="corp-textlink" data-cms-action="item" data-cms-item={current.id} href={current.linkUrl} onClick={e=>saasAction(e,current.linkUrl!,editing)}>{current.buttonText} →</a>}</div></div>:editing?<p className="corp-empty">Add your first tab in Content → Item cards.</p>:null}</div>;
}

/** Corporate narrative using the shared logo marquee; no payment-operation state. */
export function CorporatePayments({section:s,editing=false}:{section:PageSection;editing?:boolean}){
 return <div className="corp corp-payments" style={brand(s)} data-corporate="payments"><div className="corp-payment-copy"><Copy s={s}/></div><div className="corp-payment-art">{s.mediaUrl?<ScopedImage section={s} targetId="mediaUrl" src={s.mediaUrl} alt={s.mediaAlt||s.title}/>:editing?<p>Add a payment photograph in Content → Images.</p>:null}</div><PartnerLogos section={{...s,type:'logo_cloud',title:'',subtitle:'',content:'',paddingY:'compact',logoPreset:'partners',logoHeight:38,logoGap:64,marqueeSpeed:32,marqueePauseOnHover:true}} editing={editing}/><div className="corp-actions"><Action s={s} editing={editing}/></div></div>;
}

/** Approved shipping composition reuses the shared, editable logo marquee. */
export function CorporateCourier({section:s,editing=false}:{section:PageSection;editing?:boolean}){
 return <div className="corp corp-courier" style={brand(s)} data-corporate="courier"><div className="corp-courier-copy"><Copy s={s}/></div><div className="corp-courier-art">{s.mediaUrl?<ScopedImage section={s} targetId="mediaUrl" src={s.mediaUrl} alt={s.mediaAlt||s.title}/>:editing?<p>Add a courier photograph in Content → Images.</p>:null}</div><div className="corp-courier-footer"><PartnerLogos section={{...s,type:'logo_cloud',title:'',subtitle:'',content:'',paddingY:'compact',logoPreset:'partners',logoHeight:38,logoGap:48,marqueeSpeed:32,marqueePauseOnHover:true}} editing={editing}/><div className="corp-actions"><Action s={s} editing={editing}/></div></div></div>;
}

/** Marketplace artwork owns the embedded platform network; outside copy stays editable. */
export function CorporateMarketplace({section:s,editing=false}:{section:PageSection;editing?:boolean}){
 return <div className="corp corp-marketplace" style={brand(s)} data-corporate="marketplace"><div className="corp-marketplace-heading"><div><Copy s={{...s,content:''}}/></div>{s.content&&<p className="corp-body" data-cms-text="content" style={{color:s.customColors?.contentColor,...typographyStyle(s,'content')}}>{s.content}</p>}</div><div className="corp-marketplace-art">{s.mediaUrl?<ScopedImage section={s} targetId="mediaUrl" src={s.mediaUrl} alt={s.mediaAlt||s.title}/>:editing?<p>Add marketplace artwork in Content → Images.</p>:null}</div><div className="corp-marketplace-footer"><ul className="corp-benefits">{(s.items??[]).map(item=><li key={item.id} data-cms-item={item.id}><SectionIcon name={item.icon}/><div><strong data-cms-text="itemTitle" data-cms-item={item.id} style={{color:s.customColors?.titleColor,...typographyStyle(s,'itemTitle')}}>{item.title}</strong>{item.description&&<p data-cms-text="itemContent" data-cms-item={item.id} style={{color:s.customColors?.contentColor,...typographyStyle(s,'itemContent')}}>{item.description}</p>}</div></li>)}</ul><div className="corp-actions"><Action s={s} editing={editing}/></div></div></div>;
}
