import React,{useEffect,useState} from 'react';
import type {PageSection} from '../../types';
import {typographyStyle} from '../../cms/inspectorCapabilities';
import {EditorialSectionAction} from './EditorialSectionAction';
import {ScopedImage,imageSlotFrameStyle} from './ScopedImage';
import {MediaWidthRegion} from './MediaWidthRegion';

/** Long-form policy layouts share the existing policy item contract. */
export function PolicyDocument({section,isEditingMode=false,embedded=false}:{section:PageSection;isEditingMode?:boolean;embedded?:boolean}){
 const directory=section.policyPreset==='directory';
 const entries=(section.items??[]).filter(item=>item.imageUrl===undefined);
 const photos=(section.items??[]).filter(item=>item.imageUrl!==undefined);
 const [active,setActive]=useState(entries[0]?.id);
 const [expanded,setExpanded]=useState(false);
 const target=(id:string)=>`policy-${section.id}-${id}`;
 useEffect(()=>{
  if(!directory||isEditingMode||typeof IntersectionObserver==='undefined')return;
  const observer=new IntersectionObserver(records=>{const visible=records.find(record=>record.isIntersecting);if(visible)setActive(visible.target.getAttribute('data-inspector-item')??undefined);},{rootMargin:'-10% 0px -65% 0px'});
  entries.forEach(item=>{const node=document.getElementById(target(item.id));if(node)observer.observe(node);});
  return()=>observer.disconnect();
 },[directory,isEditingMode,section.id,entries.map(item=>item.id).join('|')]);
 const style=(role:'eyebrow'|'title'|'content'|'itemTitle'|'itemContent')=>({color:role==='eyebrow'?section.customColors?.eyebrowColor:role==='title'||role==='itemTitle'?section.customColors?.titleColor:section.customColors?.contentColor,...typographyStyle(section,role)});
 const contents=<nav aria-label={directory?'Policy directory':'On this page'} className={directory?'flex flex-col gap-1':'flex flex-wrap gap-2 mb-6'}>{entries.map((item,index)=><a key={item.id} href={`#${target(item.id)}`} aria-current={active===item.id?'location':undefined} className={directory?`py-3 px-3 text-sm border-l-2 ${active===item.id?'border-indigo-500 bg-indigo-500/10 text-indigo-600':'border-transparent text-neutral-500'}`:'px-3 py-2 text-xs border border-neutral-200 rounded text-neutral-500'} onClick={event=>{if(isEditingMode)event.preventDefault();else{setActive(item.id);setExpanded(false);}}}><span className="mr-3 text-xs opacity-60">{String(index+1).padStart(2,'0')}</span>{item.title}</a>)}</nav>;
 return <div data-policy-content={section.policyPreset} className={`@container min-w-0 ${embedded?'':section.paddingY==='compact'?'py-8':section.paddingY==='spacious'?'py-20':'py-12 @min-[1024px]:py-16'}`}>
  <div className={directory?'grid grid-cols-1 @min-[900px]:grid-cols-[235px_minmax(0,1fr)] gap-8 @min-[900px]:gap-16':''}>
   {directory?<aside style={{backgroundColor:section.customColors?.customBgColor}} className={`self-start sticky top-4 z-10 ${section.backgroundColor??'bg-white'} @min-[900px]:border-r border-neutral-200 @min-[900px]:pr-6`}>
    <button type="button" aria-expanded={expanded} aria-controls={`${section.id}-directory`} onClick={()=>setExpanded(!expanded)} className="@min-[900px]:hidden w-full text-left p-3 border border-neutral-200 rounded text-sm font-semibold">On this page <span aria-hidden="true" className="float-right">{expanded?'−':'+'}</span></button>
    <div id={`${section.id}-directory`} className={`${expanded?'block':'hidden'} @min-[900px]:block`}><p className="hidden @min-[900px]:block text-xs uppercase tracking-widest text-neutral-500 mb-3">On this page</p>{contents}</div>
   </aside>:null}
   <div className={`min-w-0 ${directory?'':'max-w-[800px] mx-auto'}`}>
    {section.showSectionHeading!==false&&<header className={embedded?'mb-8':'mb-8 rounded-lg bg-neutral-500/5 p-6'}>
   {!embedded&&section.subtitle&&<p data-cms-text="eyebrow" style={style('eyebrow')} className="text-xs font-bold uppercase tracking-widest mb-4">{section.subtitle}</p>}
   <h2 data-cms-text="title" style={style('title')} className="text-3xl font-extrabold tracking-tight whitespace-pre-line">{section.title}</h2>
   {section.content&&<div data-cms-text="content" style={style('content')} className="mt-4 text-base leading-relaxed whitespace-pre-line">{section.content}</div>}
  </header>}
    {!directory&&!embedded&&contents}
    {entries.map((item,index)=><article key={item.id} id={target(item.id)} data-inspector-item={item.id} className="scroll-mt-32 py-7 border-b border-neutral-200 first:pt-0">
     <p className="text-sm text-neutral-400 mb-3">{String(index+1).padStart(2,'0')}</p>
     <h3 data-cms-text="itemTitle" data-cms-item={item.id} style={style('itemTitle')} className="text-2xl font-bold tracking-tight break-words">{item.title}</h3>
     <div data-cms-text="itemContent" data-cms-item={item.id} style={style('itemContent')} className="mt-4 text-base leading-relaxed space-y-4 break-words">{(item.description??'').split(/\n\s*\n/).map((paragraph,i)=><p key={i} className="whitespace-pre-line">{paragraph}</p>)}</div>
     {item.linkUrl&&<div className="mt-4"><EditorialSectionAction section={{...section,buttonText:item.buttonText||'Learn more',buttonLink:item.linkUrl,buttonStyle:'text-link'}} isEditingMode={isEditingMode}/></div>}
    </article>)}
    {section.policyNote&&<p className="mt-5 text-xs text-neutral-500 whitespace-pre-line">{section.policyNote}</p>}
    {photos.length>0&&<MediaWidthRegion section={section}><div className="grid grid-cols-1 @min-[640px]:grid-cols-2 gap-5 mt-8">{photos.map(item=><div key={item.id} className="aspect-[4/3] overflow-hidden" style={imageSlotFrameStyle(section,`item:${item.id}:imageUrl`)}>{item.imageUrl&&<ScopedImage section={section} targetId={`item:${item.id}:imageUrl`} src={item.imageUrl} alt={item.title} className="w-full h-full object-cover"/>}</div>)}</div></MediaWidthRegion>}
    {section.buttonText&&<div className="mt-6"><EditorialSectionAction section={section} isEditingMode={isEditingMode}/></div>}
   </div>
  </div>
 </div>;
}
