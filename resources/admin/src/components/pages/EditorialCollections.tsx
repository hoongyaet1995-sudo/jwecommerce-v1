import {typographyStyle} from '../../cms/inspectorCapabilities';
import React from 'react';
import type {PageSection} from '../../types';
import {ScopedImage,imageSlotFrameStyle} from './ScopedImage';
import {MotionItem,MotionMedia} from './motion/CmsMotionWrapper';

const spacing=(section:PageSection)=>section.paddingY==='compact'?'py-8':section.paddingY==='spacious'?'py-20':'py-12 @min-[1024px]:py-16';
const columns=(count:number)=>count===2?'@min-[640px]:grid-cols-2':count===4?'@min-[640px]:grid-cols-2 @min-[1024px]:grid-cols-4':'@min-[640px]:grid-cols-3';
function Heading({section,hero=false}:{section:PageSection;hero?:boolean}) {
 const Tag=hero?'h1':'h2';
 return section.showSectionHeading===false?null:<div className={`mb-8 ${section.textAlignment==='left'?'text-left':section.textAlignment==='right'?'text-right':'text-center'}`}>
  {section.subtitle&&<MotionItem><p data-cms-text="eyebrow" style={{...({color:section.customColors?.eyebrowColor}),...typographyStyle(section,'eyebrow')}} className="text-xs font-bold tracking-widest uppercase mb-3">{section.subtitle}</p></MotionItem>}
  <MotionItem><Tag data-cms-text="title" style={{...({color:section.customColors?.titleColor}),...typographyStyle(section,'title')}} className={`${hero?'text-4xl @min-[640px]:text-5xl @min-[1024px]:text-6xl':'text-3xl @min-[640px]:text-4xl'} font-bold leading-[1.1] tracking-tight whitespace-pre-line break-words`}>{section.title}</Tag></MotionItem>
  {section.content&&<MotionItem><div data-cms-text="content" style={{...({color:section.customColors?.contentColor}),...typographyStyle(section,'content')}} className={`mt-4 text-base @min-[640px]:text-lg leading-relaxed ${section.textAlignment==='left'?'':'mx-auto'} max-w-2xl space-y-2`}>{section.content.split(/\r?\n[ \t]*\r?\n+/).map((p,i)=><p key={i} className="whitespace-pre-line break-words">{p}</p>)}</div></MotionItem>}
 </div>;
}
export function EditorialGallery({section}:{section:PageSection}) {
 const montage=section.galleryPreset==='editorial-montage';
 return <div data-editorial-gallery={section.galleryPreset} className={`@container w-full ${spacing(section)}`}>
  <Heading section={section} hero={montage}/>
  <div className={`grid grid-cols-1 gap-7 items-start ${montage&&(section.columnsCount??3)===3?'@min-[640px]:grid-cols-[minmax(0,1fr)_minmax(0,.82fr)_minmax(0,1fr)]':columns(section.columnsCount??3)}`}>
   {(section.items??[]).map((item,index)=>{
    const target=`item:${item.id}:imageUrl`;
    return <div key={item.id} className={`min-w-0 ${montage&&index%3===1?'@min-[640px]:pt-8':montage&&index%3===2?'@min-[640px]:pt-10':''}`}>
     <MotionMedia className={`overflow-hidden ${montage?(index%3===2?'aspect-[3/4]':index%3===1?'aspect-[6/11]':'aspect-[2/3]'):'aspect-[4/3]'}`} style={imageSlotFrameStyle(section,target)}>
      {item.imageUrl&&<ScopedImage section={section} targetId={target} src={item.imageUrl} alt={item.title} className="w-full h-full object-cover"/>}
     </MotionMedia>
     {!montage&&<MotionItem><div className="mt-4"><h3 data-cms-text="itemTitle" data-cms-item={item.id} style={{...({color:section.customColors?.titleColor}),...typographyStyle(section,'itemTitle')}} className="text-base font-semibold leading-snug break-words">{item.title}</h3>{item.description&&<p data-cms-text="itemContent" data-cms-item={item.id} style={{...({color:section.customColors?.contentColor}),...typographyStyle(section,'itemContent')}} className="mt-1 text-sm leading-relaxed whitespace-pre-line break-words">{item.description}</p>}</div></MotionItem>}
    </div>;
   })}
  </div>
 </div>;
}
export function EditorialColumns({section}:{section:PageSection}) {
 const count=section.columnsCount??3;
 return <div data-editorial-columns className={`@container w-full ${spacing(section)}`}>
  <Heading section={section}/>
  <div className={`grid grid-cols-1 ${columns(count)} gap-8 @min-[1024px]:gap-20`}>
   {(section.items??[]).map(item=><MotionItem key={item.id}><div className="min-w-0"><h3 data-cms-text="itemTitle" data-cms-item={item.id} style={{...({color:section.customColors?.titleColor}),...typographyStyle(section,'itemTitle')}} className="text-2xl @min-[1024px]:text-3xl font-semibold leading-tight tracking-tight whitespace-pre-line break-words">{item.title}</h3><div data-cms-text="itemContent" data-cms-item={item.id} style={{...({color:section.customColors?.contentColor}),...typographyStyle(section,'itemContent')}} className={`mt-5 ${count===2?'text-base @min-[1024px]:text-lg':'text-base'} leading-relaxed space-y-4`}>{(item.description??'').split(/\r?\n[ \t]*\r?\n+/).map((p,index)=><p key={index} className="whitespace-pre-line break-words">{p}</p>)}</div></div></MotionItem>)}
  </div>
 </div>;
}
