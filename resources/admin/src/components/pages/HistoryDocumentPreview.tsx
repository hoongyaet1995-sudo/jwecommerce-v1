import React,{useLayoutEffect,useRef,useState} from 'react';
import type {CmsPage,PageDocument} from '../../types';
import {useAdmin} from '../../context/AdminContext';
import {useAdminCurrency} from '../../context/useAdminCurrency';
import {createAdminPageRuntimePayload,PageRuntimeProvider} from '../../cms/PageRuntimeContext';
import {SectionRenderer} from './SectionRenderer';
import {BuilderShellHeader} from '../menus/BuilderShellHeader';
import {ShellDesignPreview} from '../menus/ShellDesignEditor';
import {resolveHeaderPresentation} from '../../cms/headerPresentation';
export function HistoryDocumentPreview({page,document,mobile=false}:{page:CmsPage;document:PageDocument;mobile?:boolean}){
 const outer=useRef<HTMLDivElement>(null),inner=useRef<HTMLDivElement>(null);const [frame,setFrame]=useState({scale:1,height:600});const width=mobile?390:1280;
 useLayoutEffect(()=>{const measure=()=>{if(!outer.current||!inner.current)return;const scale=Math.min(1,outer.current.clientWidth/width);const height=inner.current.scrollHeight*scale;setFrame(old=>old.scale===scale&&Math.abs(old.height-height)<1?old:{scale,height});};const observer=new ResizeObserver(measure);if(outer.current)observer.observe(outer.current);if(inner.current)observer.observe(inner.current);measure();return()=>observer.disconnect();},[width,document]);
 const {products,categories,collections,shopSettings,storefrontShellSettings,headerMenus}=useAdmin();const {currencyCode}=useAdminCurrency();const shell=storefrontShellSettings.shellDesign??document.shellDesign;
 return <div ref={outer} className={'vh-document '+(mobile?'vh-mobile':'')} style={{height:frame.height,position:'relative'}}><div ref={inner} inert style={{pointerEvents:'none',width,transform:`scale(${frame.scale})`,transformOrigin:'top left'}}>{shell&&<BuilderShellHeader value={shell} brand={shopSettings.shopName} logo={shopSettings.shopLogo} menus={headerMenus} mobile={mobile} presentation={resolveHeaderPresentation(document.headerPresentation,storefrontShellSettings.headerPresentation,document.sections)}/>}<PageRuntimeProvider payload={createAdminPageRuntimePayload({...page,...document},{products,categories,collections},currencyCode)}>{document.sections.map(section=><SectionRenderer key={section.id} section={section} viewport={mobile?'mobile':'desktop'} isEditingMode={false}/>)}</PageRuntimeProvider>{shell&&<ShellDesignPreview value={shell} brand={shopSettings.shopName} footer footerOnly/>}</div></div>;
}
