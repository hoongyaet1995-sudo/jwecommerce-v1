import React from 'react';
import type {FooterMenuL1} from '../../types';
import type {ShellDesign} from '../../cms/shellDesign';
import type {BrandPalette} from '../../cms/pageStyle';
import {semanticPaletteVariables} from '../../cms/semanticPalette';
import {MENU_BADGE_PALETTE,menuBadgeTextColor} from '../../integration/menuBadgePalette';

const stylesheet=new URL('../../../../../public/css/cms-footer-design.css',import.meta.url).href;

/** Preview adapter for the same Footer roles and CSS used by Blade. No settings writes. */
export function StorefrontFooter({value,brand,menus=[],palette}:{value:ShellDesign;brand:string;menus?:FooterMenuL1[];palette?:BrandPalette}){
 const name=value.brandName?.trim()||brand;
 const groups=menus.filter(g=>g.isActive).slice().sort((a,b)=>a.sortOrder-b.sortOrder);
 const compact=value.footerLayout==='compact';
 const legalGroups=compact?groups.filter(group=>group.sectionTitle.trim().toLowerCase()==='legal'):[];
 const navigation=groups.filter(group=>!legalGroups.includes(group));
 const renderLinks=(group:FooterMenuL1)=>group.links.filter(link=>link.isActive).slice().sort((a,b)=>a.sortOrder-b.sortOrder).map(item=>{
  const color=MENU_BADGE_PALETTE.find(c=>c.value===item.badgeColor)?.value;
  return <a key={item.id} href={item.url} target={item.isExternal?'_blank':undefined} rel={item.isExternal?'noopener':undefined} onClick={e=>e.preventDefault()}><span>{item.title}</span>{item.badge&&<em className="cms-footer-badge" style={color?{backgroundColor:color,color:menuBadgeTextColor(color)}:undefined}>{item.badge}</em>}</a>;
 });
 const colors={
  ...semanticPaletteVariables(palette),
  ...(value.footerBackground?{'--cms-shell-contrast':value.footerBackground}:{}),
  ...(value.footerText?{'--cms-shell-oncontrast':value.footerText}:{}),
 };
 return <><link rel="stylesheet" href={stylesheet}/><div className="cms-footer-region" style={colors}><footer data-shell-footer data-shell-style="unified" data-footer-layout={compact?'compact':undefined} data-shell-appearance="light" data-shell-business={value.business} className="jw-shell-footer cms-footer">
  <div className="cms-footer-intro"><section><a href="/" onClick={e=>e.preventDefault()} className="cms-footer-brand">{name}</a>{value.tagline&&<p className="cms-footer-description">{value.tagline}</p>}{compact&&value.contactEmail&&<div className="cms-footer-contact"><a href={`mailto:${value.contactEmail}`} onClick={e=>e.preventDefault()}>{value.contactEmail}</a></div>}</section>{!compact&&value.contactEmail&&<section className="cms-footer-contact"><h2>Contact</h2><a href={`mailto:${value.contactEmail}`} onClick={e=>e.preventDefault()}>{value.contactEmail}</a></section>}</div>
  {navigation.length>0&&<div className="cms-footer-navigation">{navigation.map(group=><nav key={group.id} aria-label={group.sectionTitle}><h2>{group.sectionTitle}</h2>{renderLinks(group)}</nav>)}</div>}
  <div className="cms-footer-wordmark" aria-hidden="true">{name}</div><div data-privacy-entry className="cms-footer-legal">© {new Date().getFullYear()} {name}. All rights reserved.{legalGroups.map(group=><nav key={group.id} aria-label={group.sectionTitle}>{renderLinks(group)}</nav>)}</div>
 </footer></div></>;
}
