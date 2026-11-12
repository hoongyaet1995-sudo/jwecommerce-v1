// @vitest-environment jsdom
import React,{act,useState} from 'react';
import {createRoot} from 'react-dom/client';
import {ImageTargetInspector} from '../components/pages/ImageTargetInspector';
import {it,expect} from 'vitest';
import {renderToStaticMarkup} from 'react-dom/server';
import {imageTargets,imageTargetSection,updateImageTarget} from './imageTargets';
import {ScopedImage} from '../components/pages/ScopedImage';
import type {PageSection} from '../types';
const layouts=['corporate-feature','corporate-image-story','corporate-payments','corporate-courier','corporate-marketplace','corporate-builder-overview','corporate-section-story','image-highlights','team-story-overview','wide-visual-editorial','wide-visual-centered','split-visual-surface'];
const galleries=['product-preview-tabs','corporate-scenarios','corporate-illustrated-steps','corporate-connections','image-cards-three','image-cards-four-mosaic','image-cards-three-staggered','connected-feature-rows','image-flow','editorial-knowledge-triptych','scroll-stack-cards','focused-steps'];
const cases:PageSection[]=[...layouts.map(layoutPreset=>({id:layoutPreset,type:'media_text_split' as const,layoutPreset,title:'Artwork',isVisible:true,mediaUrl:'/art.webp'})),...galleries.map(galleryPreset=>({id:galleryPreset,type:'media_gallery' as const,galleryPreset,title:'Cards',isVisible:true,items:[{id:'one',title:'One',imageUrl:'/art.webp'}]})),...['corporate-split-composite','corporate-centered-composite'].map(heroBannerPreset=>({id:heroBannerPreset,type:'hero_banner' as const,heroBannerPreset,title:'Hero',isVisible:true,mediaUrl:'/art.webp'})),...['illustrated-panel','editorial-illustrated'].map(ctaPreset=>({id:ctaPreset,type:'cta_strip' as const,ctaPreset,title:'CTA',isVisible:true,mediaUrl:'/art.webp'}))];
it.each(cases)('$id offers working independent artwork width and shape without altering content',section=>{
 const target=imageTargets(section)[0];expect(target).toBeDefined();expect(target.geometryLocked).toBe(false);
 const edited=updateImageTarget(section,target.id,{imageWidth:'50',imageShape:'circle',imageFit:'cover'});
 const html=renderToStaticMarkup(<ScopedImage section={edited} targetId={target.id} src={target.url}/>);
 expect(html).toContain('width:50%');expect(html).toContain('border-radius:50%');expect(html).toContain('aspect-ratio:1 / 1');expect(edited.title).toBe(section.title);
});

it.each(cases)('$id enables the existing Inspector geometry controls and saves their selected target',section=>{
 globalThis.IS_REACT_ACT_ENVIRONMENT=true;
 const host=document.createElement('div');document.body.append(host);const root=createRoot(host);let saved=section;
 function Editor(){const [value,setValue]=useState(section);return <ImageTargetInspector mode="design" section={value} onChange={s=>{saved=s;setValue(s)}} assetControl={()=><span/>}/>;}
 act(()=>root.render(<Editor/>));
 for(const [label,value] of [['Image width','50'],['Image shape','circle'],['Image ratio','1:1']]){
  const button=host.querySelector<HTMLButtonElement>(`[aria-label="${label}"] [data-choice-value="${value}"]`)!;
  expect(button).not.toBeNull();expect(button.disabled).toBe(false);act(()=>button.click());
 }
 const target=imageTargets(saved)[0];expect(imageTargetSection(saved,target)).toMatchObject({imageWidth:'50',imageShape:'circle',imageRatio:'1:1'});
 act(()=>root.unmount());host.remove();
});
