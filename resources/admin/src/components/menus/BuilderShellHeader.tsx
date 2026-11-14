import shellStylesheet from '../../../../../public/css/cms-shell-design.css?url';
import {builderNavigationHref} from '../../cms/builderNavigation';
import React,{useEffect,useRef,useState} from 'react';
import {Menu,Search,ShoppingBag,User,ArrowRight} from 'lucide-react';
import type {CmsPage,HeaderMenuL1} from '../../types';
import type {ShellDesign} from '../../cms/shellDesign';
import type {HeaderPresentation} from '../../cms/headerPresentation';
export function BuilderShellHeader({value,brand,logo,announcement,menus,mobile,presentation,currentPage,pages=[]}:{value:ShellDesign;brand:string;logo?:string;announcement?:string;menus:HeaderMenuL1[];mobile:boolean;presentation:HeaderPresentation;currentPage?:CmsPage;pages?:CmsPage[]}){
 brand=value.brandName?.trim()||brand;logo=value.brandLogo||logo;
 const ref=useRef<HTMLElement>(null);const[scrolled,setScrolled]=useState(false),[open,setOpen]=useState(false),[search,setSearch]=useState(false);
 useEffect(()=>{const parent=ref.current?.parentElement?.parentElement;if(!parent)return;const update=()=>setScrolled(parent.scrollTop>16);parent.addEventListener('scroll',update);update();return()=>parent.removeEventListener('scroll',update)},[]);
 const [narrow,setNarrow]=useState(false);
 useEffect(()=>{
  const header=ref.current;if(!header)return;
  const measure=(width:number)=>{if(width>0)setNarrow(width<1024)};
  measure(header.getBoundingClientRect().width);
  if(typeof ResizeObserver==='undefined')return;
  const observer=new ResizeObserver(entries=>{const entry=entries.find(entry=>entry.target===header);if(entry)measure(entry.contentRect.width)});
  observer.observe(header);return()=>observer.disconnect();
 },[]);
 const effectiveMobile=mobile||narrow;
 const paper='var(--cms-shell-paper,#ffffff)',ink='var(--cms-shell-ink,#0a0b0b)';const overlay=!effectiveMobile&&!scrolled&&!open&&!search&&presentation.mode!=='normal';
 const active=menus.filter(m=>m.isActive).sort((a,b)=>a.sortOrder-b.sortOrder);const link=(title:string,url:string,cls?:string)=><a className={cls} href={builderNavigationHref(url,currentPage,pages)} onClick={e=>e.preventDefault()}>{title}</a>;
 return <><link rel="stylesheet" href={shellStylesheet}/><header ref={ref} data-builder-shell data-shell-appearance="light" data-shell-business={value.business} data-mobile={effectiveMobile} data-shell-style="unified" data-header-scrolled={scrolled} className="jw-shell-header" style={{'--shell-paper':paper,'--shell-ink':ink,'--shell-line':'#dedfdd','--shell-hover':'#f4f5f2',background:overlay?(presentation.mode==='translucent'?(presentation.tone==='light'?'rgba(24,24,27,.55)':'rgba(255,255,255,.72)'):'transparent'):paper,color:overlay?(presentation.tone==='light'?'#fff':'#292724'):ink,marginBottom:overlay?-88:0} as React.CSSProperties}>
 {value.showAnnouncement&&announcement&&<div className="jw-shell-announcement">{announcement}</div>}
 <div className="jw-shell-main"><button type="button" className="jw-shell-menu jw-shell-icon" aria-label="Open navigation" aria-expanded={open} onClick={()=>setOpen(!open)}><Menu size={18}/></button><a className="jw-shell-brand" href="/" onClick={e=>e.preventDefault()}>{logo?<img className="jw-shell-brand-logo" src={logo} alt={brand}/>:brand}</a>
 <nav className="jw-shell-desktop" aria-label="Main navigation">{active.map(m=>{const columns=m.columns.filter(c=>c.isActive);const grouped=columns.some(c=>c.items.some(i=>i.isActive));return columns.length?<details className="jw-shell-dropdown" key={m.id}><summary>{m.title}</summary><div className={'jw-shell-submenu '+(grouped?'jw-shell-submenu-grouped':'')}>{columns.map(c=>grouped?<section className="jw-shell-menu-group" key={c.id}>{link(c.title,c.url,'jw-shell-group-title')}{c.items.filter(i=>i.isActive).map(i=><React.Fragment key={i.id}>{link(i.title,i.url)}</React.Fragment>)}</section>:<React.Fragment key={c.id}>{link(c.title,c.url)}</React.Fragment>)}{link(m.title+' →',m.url,'jw-shell-menu-all')}</div></details>:<React.Fragment key={m.id}>{link(m.title,m.url)}</React.Fragment>})}</nav>
 <div className="jw-shell-tools">{value.business==='commerce'&&<><button type="button" className="jw-shell-icon" aria-label="Search products" onClick={()=>setSearch(!search)}><Search size={18}/></button><span className="jw-shell-icon" title="Use Preview to open the live cart drawer"><ShoppingBag size={18}/></span></>}{value.showAccount&&<span className="jw-shell-icon jw-shell-optional"><User size={18}/></span>}{value.showCurrency&&<span className="jw-shell-optional">MYR</span>}{value.ctaLabel&&value.ctaUrl&&<a className="jw-shell-cta" href={builderNavigationHref(value.ctaUrl,currentPage,pages)} onClick={e=>e.preventDefault()}><span>{value.ctaLabel}</span><ArrowRight size={18}/></a>}</div></div>
 {search&&<div className="jw-shell-search"><input placeholder="Search products…" readOnly/><Search size={18}/></div>}
 {open&&effectiveMobile&&<nav className="jw-shell-mobile" aria-label="Mobile navigation">{active.map(m=><details key={m.id}><summary>{m.title}</summary><div className="jw-shell-mobile-group">{link(m.title+' →',m.url)}{m.columns.filter(c=>c.isActive).map(c=>c.items.some(i=>i.isActive)?<details key={c.id}><summary>{c.title}</summary>{c.items.filter(i=>i.isActive).map(i=><React.Fragment key={i.id}>{link(i.title,i.url)}</React.Fragment>)}</details>:<React.Fragment key={c.id}>{link(c.title,c.url)}</React.Fragment>)}</div></details>)}</nav>}
 </header></>;
}
