import {PolicyContent} from './PolicyContent';
import {MediaWidthRegion} from './MediaWidthRegion';
import {ShippingChapterBody} from './ShippingChapterBody';
import {typographyStyle} from '../../cms/inspectorCapabilities';
import React,{useEffect,useState} from 'react';
import type {PageSection} from '../../types';
import {usePageRuntime} from '../../cms/PageRuntimeContext';
import {ScopedImage,imageSlotFrameStyle} from './ScopedImage';
import {MotionItem,MotionMedia} from './motion/CmsMotionWrapper';
import {EditorialSectionAction} from './EditorialSectionAction';
const paragraphs=(value:string|undefined)=>(value??'').split(/\r?\n[ \t]*\r?\n+/);
export function ChapterNavigation({section,isEditingMode}:{section:PageSection;isEditingMode:boolean}) {
 const runtime=usePageRuntime();
 const chapters=section.items?.length?section.items.map(item=>({id:item.linkUrl?.replace(/^#section-/, '')??item.id,title:item.title,subtitle:''})):runtime.page.document.sections.filter(item=>item.type==='chapter_block'&&item.isVisible!==false);
 const signature=chapters.map(item=>item.id).join('|');
 const [active,setActive]=useState(chapters[0]?.id);
 useEffect(()=>{
  if(isEditingMode)return;
  let frame=0;
  const update=()=>{const current=[...chapters].reverse().find(item=>(document.getElementById(`section-${item.id}`)?.getBoundingClientRect().top??Infinity)<=165);setActive(current?.id??chapters[0]?.id);};
  const scroll=()=>{cancelAnimationFrame(frame);frame=requestAnimationFrame(update);};
  update();window.addEventListener('scroll',scroll,{passive:true});
  return()=>{window.removeEventListener('scroll',scroll);cancelAnimationFrame(frame);};
 },[signature,isEditingMode]);
 return <nav aria-label={section.title||'Page chapters'} data-chapter-navigation className="flex flex-wrap gap-x-6 @min-[640px]:gap-x-16 border-b border-neutral-300">
  {chapters.map((chapter,index)=><a data-cms-text="eyebrow" key={chapter.id} href={`#section-${chapter.id}`} aria-current={active===chapter.id?'location':undefined} onClick={event=>{if(isEditingMode)event.preventDefault();else setActive(chapter.id);}} style={{...({color:active===chapter.id?(section.customColors?.eyebrowColor??'#4f46e5'):section.customColors?.contentColor,borderColor:active===chapter.id?(section.customColors?.eyebrowColor??'#4f46e5'):'transparent'}),...typographyStyle(section,'eyebrow')}} className="inline-flex gap-4 py-5 border-b-2 -mb-px text-sm font-semibold">
   <span>{String(index+1).padStart(2,'0')}</span><span>{chapter.subtitle||chapter.title}</span>
  </a>)}
 </nav>;
}
export function ChapterBlock({section,isEditingMode=false}:{section:PageSection;isEditingMode?:boolean}) {
 const runtime=usePageRuntime();
 const index=runtime.page.document.sections.filter(item=>item.type==='chapter_block'&&item.isVisible!==false).findIndex(item=>item.id===section.id);
 const preset=section.chapterPreset??'story';
 const copy=<MotionItem><h2 data-cms-text="title" style={{...({color:section.customColors?.titleColor}),...typographyStyle(section,'title')}} className={`${preset==='story'?'text-4xl @min-[640px]:text-5xl':'text-3xl @min-[1024px]:text-4xl'} font-extrabold leading-[1.08] tracking-tight whitespace-pre-line break-words`}>{section.title}</h2><div data-cms-text="content" style={{...({color:section.customColors?.contentColor}),...typographyStyle(section,'content')}} className={`mt-6 space-y-5 leading-relaxed ${preset==='story'?'text-base @min-[640px]:text-xl':'text-base'}`}>{paragraphs(section.content).map((text,i)=><p key={i} className="whitespace-pre-line break-words">{text}</p>)}</div></MotionItem>;
 return <div data-chapter-block={preset} className={`@container w-full grid grid-cols-1 @min-[1024px]:grid-cols-[180px_minmax(0,1fr)] gap-6 @min-[1024px]:gap-12 ${section.paddingY==='compact'?'py-8':section.paddingY==='spacious'?'py-20':'py-12 @min-[1024px]:py-16'}`}>
  <aside className="@min-[1024px]:border-r border-neutral-200 pr-6"><p data-cms-text="eyebrow" style={{...({color:section.customColors?.eyebrowColor??'#4f46e5'}),...typographyStyle(section,'eyebrow')}} className="text-lg font-bold">{String(Math.max(index,0)+1).padStart(2,'0')}</p><p data-cms-text="content" style={{...({color:section.customColors?.contentColor}),...typographyStyle(section,'content')}} className="mt-3 text-xs uppercase tracking-widest">{section.subtitle}</p></aside>
  <div className="min-w-0 @container">
   {preset==='policy'&&<PolicyContent section={section} isEditingMode={isEditingMode} embedded/>}
   {['process','delivery','actions'].includes(preset)&&<ShippingChapterBody section={section} isEditingMode={isEditingMode}/>}
   {preset==='story'&&<>{copy}<MediaWidthRegion section={section}><MotionMedia className="mt-8 aspect-[3/1] overflow-hidden" style={imageSlotFrameStyle(section,'mediaUrl')}>{section.mediaUrl&&<ScopedImage section={section} targetId="mediaUrl" src={section.mediaUrl} alt={section.mediaAlt??''} className="w-full h-full object-cover"/>}</MotionMedia></MediaWidthRegion></>}
   {preset==='perspective'&&section.mediaContainerMode!=='full-bleed'&&<div className="grid grid-cols-1 @min-[640px]:grid-cols-[minmax(0,1.2fr)_minmax(0,.85fr)_minmax(0,1.5fr)] gap-6 items-start">{(section.items??[]).filter(item=>item.imageUrl!==undefined).slice(0,2).map((item,i)=><MotionMedia key={item.id} className={`${i===0?'aspect-[3/4]':'aspect-[1/2]'} overflow-hidden`} style={imageSlotFrameStyle(section,`item:${item.id}:imageUrl`)}>{item.imageUrl&&<ScopedImage section={section} targetId={`item:${item.id}:imageUrl`} src={item.imageUrl} alt={item.title} className="w-full h-full object-cover"/>}</MotionMedia>)}{copy}</div>}
   {preset==='perspective'&&section.mediaContainerMode==='full-bleed'&&<>{copy}<MediaWidthRegion section={section}><div className="grid grid-cols-2 gap-6 mt-8">{(section.items??[]).filter(item=>item.imageUrl!==undefined).slice(0,2).map(item=><div key={item.id} className="aspect-[4/3] overflow-hidden">{item.imageUrl&&<ScopedImage section={section} targetId={`item:${item.id}:imageUrl`} src={item.imageUrl} alt={item.title} className="w-full h-full object-cover"/>}</div>)}</div></MediaWidthRegion></>}
   {preset==='principles'&&(section.items??[]).filter(item=>item.imageUrl===undefined).map((item,i)=><MotionItem key={item.id}><div className={`grid grid-cols-1 @min-[640px]:grid-cols-[2fr_3fr] gap-3 @min-[640px]:gap-8 py-6 ${i?'border-t border-neutral-200':''}`}><h3 data-cms-text="itemTitle" data-cms-item={item.id} style={{...({color:section.customColors?.titleColor}),...typographyStyle(section,'itemTitle')}} className="text-xl font-bold break-words">{item.title}</h3><p data-cms-text="itemContent" data-cms-item={item.id} style={{...({color:section.customColors?.contentColor}),...typographyStyle(section,'itemContent')}} className="text-base leading-relaxed whitespace-pre-line break-words">{item.description}</p></div></MotionItem>)}
  </div>
 </div>;
}
export function EditorialCover({section}:{section:PageSection}) {
 return <div data-editorial-cover><div className={`${section.paddingY==='compact'?'py-8':section.paddingY==='spacious'?'py-20':'py-12 @min-[1024px]:py-16'} ${section.containerMode==='full-bleed'?'max-w-[var(--cms-page-width)] mx-auto px-6 @min-[640px]:px-10 @min-[1024px]:px-16':''}`}>
  {section.subtitle&&<p data-cms-text="eyebrow" style={{...({color:section.customColors?.eyebrowColor}),...typographyStyle(section,'eyebrow')}} className="text-xs uppercase font-bold tracking-widest mb-5">{section.subtitle}</p>}
  <h1 data-cms-text="title" style={{...({color:section.customColors?.titleColor}),...typographyStyle(section,'title')}} className="text-5xl @min-[640px]:text-7xl @min-[1024px]:text-8xl font-extrabold leading-[1.04] tracking-tight whitespace-pre-line break-words">{section.title}</h1>
  {section.leadText&&<p data-cms-text="lead" style={{...({color:section.customColors?.contentColor}),...typographyStyle(section,'lead')}} className="mt-3 text-xl @min-[640px]:text-3xl @min-[1024px]:text-4xl font-medium">{section.leadText}</p>}
  {section.content&&<p data-cms-text="content" style={{...({color:section.customColors?.contentColor}),...typographyStyle(section,'content')}} className="mt-3 text-sm opacity-70 whitespace-pre-line">{section.content}</p>}
 </div><MediaWidthRegion section={{...section,mediaContainerMode:section.mediaContainerMode??(section.containerMode==='full-bleed'?'full-bleed':'page')}}><MotionMedia className="aspect-[8/3] overflow-hidden" style={imageSlotFrameStyle(section,'mediaUrl')}>{section.mediaUrl&&<ScopedImage section={section} targetId="mediaUrl" src={section.mediaUrl} alt={section.mediaAlt??''} className="w-full h-full object-cover"/>}</MotionMedia></MediaWidthRegion></div>;
}
export function EditorialCtaBand({section,isEditingMode}:{section:PageSection;isEditingMode:boolean}) {
 return <div data-editorial-cta-band className={`${section.paddingY==='compact'?'py-8':section.paddingY==='spacious'?'py-20':'py-12 @min-[1024px]:py-16'} grid grid-cols-1 @min-[640px]:grid-cols-[minmax(0,1fr)_auto] gap-6 items-center`}><div><h2 data-cms-text="title" style={{...({color:section.customColors?.titleColor}),...typographyStyle(section,'title')}} className="text-3xl @min-[1024px]:text-5xl font-bold leading-tight whitespace-pre-line break-words">{section.title}</h2>{section.content&&<p data-cms-text="content" style={{...({color:section.customColors?.contentColor}),...typographyStyle(section,'content')}} className="mt-3 text-base @min-[1024px]:text-lg leading-relaxed">{section.content}</p>}</div><div><EditorialSectionAction section={section} isEditingMode={isEditingMode}/></div></div>;
}
