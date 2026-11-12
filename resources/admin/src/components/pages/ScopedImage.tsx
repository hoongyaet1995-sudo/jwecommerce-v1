import React,{useContext} from 'react';
import type {PageSection} from '../../types';
import {imageTargets,imageTargetSection,editableArtworkGeometry} from '../../cms/imageTargets';
import {imagePositions,imageRatios} from './SectionImage';
import './ScopedImage.css';
import {ImageSelectionContext} from './ImageSelectionContext';
export function imageSlotFrameStyle(section:PageSection,targetId:string):React.CSSProperties|undefined {
  const target=imageTargets(section).find(item=>item.id===targetId);if(!target || target.geometryLocked) return;
  const settings=imageTargetSection(section,target);
  return {aspectRatio:settings.imageShape==='circle'?'1 / 1':settings.imageRatio && settings.imageRatio!=='default'?imageRatios[settings.imageRatio]??'auto':undefined,
    borderRadius:settings.imageShape==='rectangle'?'0':settings.imageShape==='rounded'?'24px':settings.imageShape==='circle'?'50%':undefined};
}
export function ScopedImage({section,targetId,...props}:React.ImgHTMLAttributes<HTMLImageElement>&{section:PageSection;targetId:string}) {
  const context=useContext(ImageSelectionContext);
  const target=imageTargets(section).find(item=>item.id===targetId);
  if(!target) return <img {...props}/>;
  const settings=imageTargetSection(section,target);
  const geometry=!target.geometryLocked && (settings.imageRatio && settings.imageRatio!=='default' || settings.imageShape==='circle');
  const artworkGeometry=editableArtworkGeometry(section)&&!target.geometryLocked&&((settings.imageWidth!==undefined&&settings.imageWidth!=='100')||(settings.imageRatio!==undefined&&settings.imageRatio!=='default')||(settings.imageShape!==undefined&&settings.imageShape!=='default'));
  const active=context.editing && context.selected===targetId;
  const style:React.CSSProperties={...props.style,...(settings.imageFit?{objectFit:settings.imageFit}:{}),...(settings.imagePosition?{objectPosition:imagePositions[settings.imagePosition]}:{}),
    ...(active?{outline:'3px solid #7965ff',outlineOffset:'-3px'}:{})};
  const onClick=context.editing?(event:React.MouseEvent)=>{event.preventDefault();event.stopPropagation();context.select?.(targetId);}:undefined;
  const attributes={'data-image-slot':targetId,onClick};
  if(!settings.imageOverlay && !settings.imageForeground && !geometry && (!settings.imageWidth||settings.imageWidth==='100') && !settings.imageShape) return <img {...props} {...attributes} alt={settings.mediaAlt??props.alt} style={style}/>;
  const frameStyle:React.CSSProperties={position:props.className?.includes('absolute')?'absolute':'relative',...(props.className?.includes('absolute')?{inset:0}:{}),width:target.geometryLocked?undefined:`${settings.imageWidth??'100'}%`,height:target.geometryLocked?undefined:geometry||artworkGeometry?'auto':'100%',marginInline:'auto',overflow:'hidden',
    ...(geometry?{aspectRatio:settings.imageShape==='circle'?'1 / 1':settings.imageRatio && settings.imageRatio!=='default'?imageRatios[settings.imageRatio]:undefined}:{}),
    borderRadius:!target.geometryLocked?(settings.imageShape==='rounded'?'24px':settings.imageShape==='circle'?'50%':'0'):undefined};
  return <div {...attributes} data-image-frame data-artwork-geometry={artworkGeometry?'custom':undefined} className={target.geometryLocked?props.className:undefined} style={frameStyle}><img {...props} alt={settings.mediaAlt??props.alt} style={{...style,position:'static',width:'100%',height:settings.imageRatio==='auto'&&!target.geometryLocked||artworkGeometry&&!geometry?'auto':'100%',...(artworkGeometry||settings.imageRatio==='auto'&&!target.geometryLocked?{aspectRatio:'auto'}:{})}}/>
    {settings.imageOverlay && <div aria-hidden="true" data-image-overlay className="absolute inset-0 pointer-events-none bg-black" style={{opacity:(settings.imageOverlayOpacity??30)/100}}/>}
    {settings.imageForeground && <div aria-hidden="true" data-image-foreground className="absolute inset-0 pointer-events-none" style={{backgroundColor:settings.imageForegroundColor??'#4f46e5',opacity:(settings.imageForegroundOpacity??20)/100}}/>}
  </div>;
}
