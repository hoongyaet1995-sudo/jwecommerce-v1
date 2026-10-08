import React,{useContext} from 'react';
import {imageTargets,imageTargetSection} from '../../cms/imageTargets';
import {ImageSelectionContext} from './ImageSelectionContext';
import type {PageSection} from '../../types';
import {mediaFocalPositions} from '../../cms/mediaTextSplitPresets';
export const imagePositions={
  'top-left':'left top','top-center':'center top','top-right':'right top',
  'center-left':'left center',center:'center center','center-right':'right center',
  'bottom-left':'left bottom','bottom-center':'center bottom','bottom-right':'right bottom',
} as const;
export const imageRatios={auto:undefined,'16:9':'16 / 9','3:2':'3 / 2','4:3':'4 / 3','1:1':'1 / 1','3:4':'3 / 4'};
export function hasCustomImageGeometry(section:PageSection) {
  const target=imageTargets(section).find(target=>target.id==='mediaUrl');
  if(target) section=imageTargetSection(section,target);
  return (section.imageRatio!==undefined && section.imageRatio!=='default') || section.imageShape==='circle';
}
export function SectionImage({section,className='h-full'}:{section:PageSection;className?:string}) {
  const selection=useContext(ImageSelectionContext);
  const target=imageTargets(section).find(target=>target.id==='mediaUrl');
  if(target) section=imageTargetSection(section,target);
  if(!section.mediaUrl) return null;
  const custom=hasCustomImageGeometry(section);
  const ratio=section.imageShape==='circle'?'1 / 1':section.imageRatio && section.imageRatio!=='default'?imageRatios[section.imageRatio]:undefined;
  const original=section.imageRatio==='auto' && section.imageShape!=='circle';
  const shape=section.imageShape==='rounded'?'rounded-3xl':section.imageShape==='circle'?'rounded-full':'';
  return <div data-section-image data-image-slot="mediaUrl" onClick={selection.editing?e=>{e.preventDefault();e.stopPropagation();selection.select?.('mediaUrl');}:undefined} className={`relative overflow-hidden mx-auto ${shape} ${custom?'':className}`} style={{width:`${section.imageWidth??'100'}%`,aspectRatio:ratio}}>
    <img src={section.mediaUrl} alt={section.mediaAlt??''} referrerPolicy="no-referrer" className={`w-full ${original||className===''?'h-auto':'h-full'}`}
      style={{objectFit:section.imageFit??'cover',objectPosition:section.imagePosition?imagePositions[section.imagePosition]:mediaFocalPositions[section.mediaFocalPoint??'center']}} />
    {section.imageOverlay && <div aria-hidden="true" data-image-overlay className="absolute inset-0 pointer-events-none bg-black" style={{opacity:(section.imageOverlayOpacity??30)/100}} />}
    {section.imageForeground && <div aria-hidden="true" data-image-foreground className="absolute inset-0 pointer-events-none" style={{backgroundColor:section.imageForegroundColor??'#4f46e5',opacity:(section.imageForegroundOpacity??20)/100}} />}
  </div>;
}
