import {isConnectedFamily} from '../../cms/connectedFamilies';
import {isConnectedRows} from '../../cms/connectedFeatureRows';
import {isImageHighlights} from '../../cms/imageHighlights';
import {isFixedImageCards} from '../../cms/imageCardsThree';
import React,{useLayoutEffect,useRef,useState} from 'react';
import type {PageSection} from '../../types';
export function supportsMediaWidth(section:PageSection) {if(isConnectedFamily(section))return false;if(isConnectedRows(section)||isFixedImageCards(section)||isImageHighlights(section))return false;if(['team-story-overview','contained-wide-centered','wide-visual-centered','inset-surface-panel','split-visual-surface','contained-wide-editorial','wide-visual-editorial','corporate-section-story','corporate-builder-overview','corporate-feature','corporate-support','corporate-image-story','corporate-payments','corporate-courier','corporate-marketplace'].includes(section.layoutPreset??'')||['corporate-connections','product-preview-tabs','corporate-scenarios','corporate-illustrated-steps'].includes(section.galleryPreset??''))return false;return section.type==='policy_content'&&!!section.items?.some(item=>item.imageUrl!==undefined)||section.type==='media_text_split'&&!['editorial-story-cards','editorial-journal-rows','flush-split','producer-story','tech-feature','kids-discovery','artisan-story'].includes(section.layoutPreset??'default')||section.type==='media_gallery'&&section.galleryPreset!=='routine-steps'||section.type==='chapter_block'&&(['story','perspective','delivery'].includes(section.chapterPreset??'story')||section.chapterPreset==='policy'&&!!section.items?.some(item=>item.imageUrl!==undefined));}
/** Measures the section canvas, never the browser viewport, so Builder and public agree. */
export function MediaWidthRegion({section,children,className}:{section:PageSection;children:React.ReactNode;className?:string}) {
 const ref=useRef<HTMLDivElement>(null);const [geometry,setGeometry]=useState<{width:number;offset:number}>();
 const full=section.mediaContainerMode==='full-bleed';
 useLayoutEffect(()=>{
  if(!full){setGeometry(undefined);return;}
  const node=ref.current;const canvas=node?.closest('section');if(!node||!canvas)return;
  const measure=()=>{const c=canvas.getBoundingClientRect();const parent=node.parentElement!.getBoundingClientRect();const offset=c.left-parent.left;setGeometry(previous=>previous&&Math.abs(previous.width-c.width)<.5&&Math.abs(previous.offset-offset)<.5?previous:{width:c.width,offset});};
  const observer=new ResizeObserver(measure);observer.observe(canvas);observer.observe(node.parentElement!);measure();return()=>observer.disconnect();
 },[full]);
 return <div ref={ref} className={className} data-media-width={full?'full-bleed':'page'} style={full&&geometry?{width:geometry.width,marginLeft:geometry.offset,overflow:'clip'}:!full&&section.containerMode==='full-bleed'?{maxWidth:'var(--cms-page-width)',marginInline:'auto',paddingInline:'var(--cms-page-gutter, 24px)',boxSizing:'border-box'}:undefined}>{children}</div>;
}
