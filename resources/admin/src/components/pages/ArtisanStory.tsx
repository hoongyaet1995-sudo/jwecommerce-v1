import {SectionEyebrow} from './SectionEyebrow';
import React from 'react';
import type {PageSection} from '../../types';
import {ScopedImage,imageSlotFrameStyle} from './ScopedImage';
import {MotionItem} from './motion/CmsMotionWrapper';
import {typographyStyle} from '../../cms/inspectorCapabilities';
import {EditorialSectionAction} from './EditorialSectionAction';

/** Image entries remain independently editable and later entries wrap below the opening pair. */
export function ArtisanStory({section,editing=false}:{section:PageSection;editing?:boolean}){
 const photos=section.items??[];
 const photo=(item:NonNullable<PageSection['items']>[number])=><MotionItem key={item.id}><figure data-cms-item={item.id} className="aspect-square overflow-hidden bg-black/5" style={imageSlotFrameStyle(section,`item:${item.id}:imageUrl`)}>{item.imageUrl&&<ScopedImage section={section} targetId={`item:${item.id}:imageUrl`} src={item.imageUrl} alt={item.title} className="w-full h-full object-cover"/>}</figure></MotionItem>;
 return <div data-artisan-story className={`@container ${section.paddingY==='spacious'?'py-20':section.paddingY==='compact'?'py-8':'py-12'}`}><div className="grid grid-cols-1 @min-[800px]:grid-cols-3 gap-6 items-center">{photos[0]&&photo(photos[0])}<MotionItem><div className="py-5 px-4" style={{textAlign:section.textAlignment??'center'}}>{<SectionEyebrow section={section}/>}<h2 data-cms-text="title" className="font-serif text-3xl whitespace-pre-line" style={typographyStyle(section,'title')}>{section.title}</h2>{section.content&&<p data-cms-text="content" className="mt-4 text-sm leading-relaxed whitespace-pre-line" style={typographyStyle(section,'content')}>{section.content}</p>}<div className="mt-5"><EditorialSectionAction section={section} isEditingMode={editing}/></div></div></MotionItem>{photos[1]&&photo(photos[1])}</div>{photos.length>2&&<div className="grid grid-cols-2 @min-[800px]:grid-cols-3 gap-6 mt-6">{photos.slice(2).map(photo)}</div>}</div>;
}

