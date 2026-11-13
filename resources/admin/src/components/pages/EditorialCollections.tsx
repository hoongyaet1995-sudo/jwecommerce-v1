import {isFocusedSteps} from '../../cms/focusedSteps';
import {FocusedSteps} from './FocusedSteps';
import {isScrollStackCards} from '../../cms/scrollStackCards';
import {ScrollStackCards} from './ScrollStackCards';
import {isKnowledgeTriptych} from '../../cms/knowledgeTriptych';
import {KnowledgeTriptych} from './KnowledgeTriptych';
import {isConnectedRows,connectedRowItems} from '../../cms/connectedFeatureRows';
import {isFixedImageCards,fixedImageCardItems} from '../../cms/imageCardsThree';
import {isImageFlow} from '../../cms/imageFlow';
import {ImageFlow} from './ImageFlow';
import {MediaWidthRegion} from './MediaWidthRegion';
import {SectionIcon} from './IconPicker';
import './CorporateSections.css';
import {typographyStyle} from '../../cms/inspectorCapabilities';
import React from 'react';
import {ArrowRight} from 'lucide-react';
import {shopNavigationHref} from '../../cms/shopNavigation';
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
export function EditorialGallery({section,authoredSection,editing=false}:{section:PageSection;authoredSection?:PageSection;editing?:boolean}) {
 if(isFocusedSteps(section))return <FocusedSteps section={section} editing={editing}/>;
 if(isScrollStackCards(section))return <ScrollStackCards section={section} editing={editing}/>;
 if(isKnowledgeTriptych(section))return <KnowledgeTriptych section={section} editing={editing}/>;
 if(isImageFlow(section))return <ImageFlow section={section} editing={editing}/>;
 if(isConnectedRows(section)||isFixedImageCards(section)||section.galleryPreset==='corporate-connections')return <div data-card-surface={section.surface} data-editorial-gallery={section.galleryPreset} className={`corp corp-connections ${isConnectedRows(section)?'corp-connected-feature-rows':section.galleryPreset==='image-cards-four-mosaic'?'corp-image-cards-four-mosaic':['staggered-focus-cards','image-cards-contrast','image-cards-three-staggered'].includes(section.galleryPreset??'')?'corp-image-cards-three corp-image-cards-staggered':isFixedImageCards(section)?'corp-image-cards-three':''}`} style={{'--card-paper':section.brandPalette?.paper,'--card-ink':section.brandPalette?.ink,'--card-secondary':section.brandPalette?.secondary,'--corp-space':section.paddingY==='compact'?'32px':section.paddingY==='spacious'?'96px':'64px'} as React.CSSProperties}>
  <Heading section={{...section,subtitle:undefined}}/>
  <div className="corp-connections-grid">{(isConnectedRows(section)?connectedRowItems(section):isFixedImageCards(section)?fixedImageCardItems(section):section.items??[]).map(item=><article key={item.id} data-cms-item={item.id} className="corp-connection-card">
   <MotionMedia className="corp-connection-art">{item.imageUrl?<ScopedImage section={section} targetId={`item:${item.id}:imageUrl`} src={item.imageUrl} alt={item.title} className="w-full h-full object-contain"/>:editing?<p>Add feature illustration</p>:null}</MotionMedia>
   <div className="corp-connection-copy"><h3 data-cms-text="itemTitle" data-cms-item={item.id} style={{color:section.customColors?.titleColor,...typographyStyle(section,'itemTitle'),...(['staggered-focus-cards','image-cards-contrast'].includes(section.galleryPreset??'')?{color:authoredSection?.typography?.itemTitle?.color??'var(--card-copy-ink)'}:{})}}>{item.title}</h3>
    {item.description&&<p data-cms-text="itemContent" data-cms-item={item.id} style={{color:section.customColors?.contentColor,...typographyStyle(section,'itemContent'),...(['staggered-focus-cards','image-cards-contrast'].includes(section.galleryPreset??'')?{color:authoredSection?.typography?.itemContent?.color??'var(--card-copy-ink)'}:{})}}>{item.description}</p>}
    {!isFixedImageCards(section)&&item.buttonText&&shopNavigationHref(item.linkUrl??'')&&<a data-cms-action="button" data-cms-item={item.id} className="corp-primary corp-connection-button" href={shopNavigationHref(item.linkUrl??'')} onClick={e=>editing&&e.preventDefault()}>{item.buttonText}<ArrowRight size={17} aria-hidden="true"/></a>}
   </div>
  </article>)}</div>
 </div>;
 if(section.galleryPreset==='corporate-scenarios')return <div data-editorial-gallery="corporate-scenarios" className="corp corp-scenarios" style={{'--corp-space':section.paddingY==='compact'?'32px':section.paddingY==='spacious'?'96px':'64px'} as React.CSSProperties}>
  <Heading section={{...section,subtitle:undefined}}/>
  <div className="corp-scenario-grid">{section.items?.map(item=><article key={item.id} data-cms-item={item.id} className="corp-scenario-card">
   <MotionMedia className="corp-scenario-image">{item.imageUrl?<ScopedImage section={section} targetId={`item:${item.id}:imageUrl`} src={item.imageUrl} alt={item.title} className="w-full h-full object-contain"/>:editing?<p>Add scenario image</p>:null}</MotionMedia>
   <MotionItem><div className="corp-scenario-label"><SectionIcon name={item.icon}/><h3 data-cms-text="itemTitle" data-cms-item={item.id} style={{color:section.customColors?.titleColor,...typographyStyle(section,'itemTitle')}}>{item.title}</h3></div></MotionItem>
  </article>)}</div>
 </div>;
 const triptych=section.galleryPreset==='editorial-triptych';
 const portfolio=section.galleryPreset==='portfolio-cards';
 const montage=section.galleryPreset==='editorial-montage';
 return <div data-editorial-gallery={section.galleryPreset} className={`@container w-full ${spacing(section)}`}>
  <Heading section={section} hero={montage}/>
  {portfolio&&section.buttonText&&shopNavigationHref(section.buttonLink??'')&&<a data-cms-action="button" className="block mb-5 text-right text-xs" href={shopNavigationHref(section.buttonLink??'')} onClick={e=>editing&&e.preventDefault()}>{section.buttonText} →</a>}
  <MediaWidthRegion section={section}><div className={`grid grid-cols-1 gap-7 items-start ${montage&&(section.columnsCount??3)===3?'@min-[640px]:grid-cols-[minmax(0,1fr)_minmax(0,.82fr)_minmax(0,1fr)]':columns(section.columnsCount??3)}`}>
   {(section.items??[]).map((item,index)=>{
    const target=`item:${item.id}:imageUrl`;
    return <div key={item.id} className={`min-w-0 ${montage&&index%3===1?'@min-[640px]:pt-8':montage&&index%3===2?'@min-[640px]:pt-10':''}`}>
     <MotionMedia className={`overflow-hidden ${montage?(index%3===2?'aspect-[3/4]':index%3===1?'aspect-[6/11]':'aspect-[2/3]'):triptych?'aspect-[4/3]':portfolio?'aspect-[16/9]':'aspect-[4/3]'}`} style={imageSlotFrameStyle(section,target)}>
      {item.imageUrl&&(portfolio&&shopNavigationHref(item.linkUrl??'')?<a href={shopNavigationHref(item.linkUrl??'')} className="block w-full h-full" onClick={event=>editing&&event.preventDefault()}><ScopedImage section={section} targetId={target} src={item.imageUrl} alt={item.title} className="w-full h-full object-cover"/></a>:<ScopedImage section={section} targetId={target} src={item.imageUrl} alt={item.title} className="w-full h-full object-cover"/>)}
     </MotionMedia>
     {!montage&&!triptych&&<MotionItem><div className="mt-4"><h3 data-cms-text="itemTitle" data-cms-item={item.id} style={{...({color:section.customColors?.titleColor}),...typographyStyle(section,'itemTitle')}} className="text-base font-semibold leading-snug break-words">{item.title}</h3>{item.description&&<p data-cms-text="itemContent" data-cms-item={item.id} style={{...({color:section.customColors?.contentColor}),...typographyStyle(section,'itemContent')}} className="mt-1 text-sm leading-relaxed whitespace-pre-line break-words">{item.description}</p>}{portfolio&&item.buttonText&&shopNavigationHref(item.linkUrl??'')&&<a data-cms-action="button" data-cms-item={item.id} className="inline-flex items-center gap-2 mt-3 text-xs" href={shopNavigationHref(item.linkUrl??'')} onClick={e=>editing&&e.preventDefault()}>{item.buttonText}<ArrowRight size={13}/></a>}</div></MotionItem>}
    </div>;
   })}
  </div></MediaWidthRegion>
 </div>;
}
export function EditorialColumns({section}:{section:PageSection}) {
 const count=section.columnsCount??3;
 const numbered=section.featureCardsPreset==='numbered-process';
 return <div data-editorial-columns className={`@container w-full ${spacing(section)}`}>
  <Heading section={section}/>
  <div className={`grid grid-cols-1 ${columns(count)} gap-8 @min-[1024px]:gap-20`}>
   {(section.items??[]).map((item,index)=><MotionItem key={item.id}><div className={`min-w-0 ${numbered?'border-t border-stone-300 pt-4':''}`}>{numbered&&<span className="block mb-3 font-serif text-xl">{String(index+1).padStart(2,'0')}</span>}<h3 data-cms-text="itemTitle" data-cms-item={item.id} style={{...({color:section.customColors?.titleColor}),...typographyStyle(section,'itemTitle')}} className="text-2xl @min-[1024px]:text-3xl font-semibold leading-tight tracking-tight whitespace-pre-line break-words">{item.title}</h3><div data-cms-text="itemContent" data-cms-item={item.id} style={{...({color:section.customColors?.contentColor}),...typographyStyle(section,'itemContent')}} className={`mt-5 ${count===2?'text-base @min-[1024px]:text-lg':'text-base'} leading-relaxed space-y-4`}>{(item.description??'').split(/\r?\n[ \t]*\r?\n+/).map((p,index)=><p key={index} className="whitespace-pre-line break-words">{p}</p>)}</div></div></MotionItem>)}
  </div>
 </div>;
}
