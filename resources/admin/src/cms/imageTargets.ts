import {isImageFlow} from './imageFlow';
import {isKnowledgeTriptych} from './knowledgeTriptych';
import {isFocusedSteps} from './focusedSteps';
import {isConnectedRows,connectedRowItems} from './connectedFeatureRows';
import {isImageHighlights} from './imageHighlights';
import {isFixedImageCards,fixedImageCardItems} from './imageCardsThree';
import type {PageSection,SectionImageSlot} from '../types';
export const imageControlKeys=['mediaAlt','imageRatio','imagePosition','imageFit','imageWidth','imageShape','imageOverlay','imageOverlayOpacity','imageForeground','imageForegroundColor','imageForegroundOpacity'] as const;
export const imageSlotFamilies=['product_listing','promotion_highlights','shop_navigation','policy_content','chapter_block','cta_strip','hero_banner','hero_slider','split_hero','media_text_split','featured_deal','media_gallery','before_after','instagram_feed','testimonials_slider','logo_cloud','comparison_matrix'] as const;
export interface ImageTarget {id:string;label:string;url:string;geometryLocked:boolean;itemId?:string;field:'mediaUrl'|'beforeImageUrl'|'afterImageUrl'|'imageUrl'|'authorAvatar'}
function layoutImageTargets(section:PageSection):ImageTarget[] {
 if(section.type==='media_text_split'&&section.layoutPreset==='wide-visual-centered')return [{id:'mediaUrl',field:'mediaUrl',label:'Visual',url:section.mediaUrl??'',geometryLocked:false}];
 if(section.type==='media_text_split'&&['wide-visual-centered','wide-visual-editorial','inset-surface-panel','split-visual-surface'].includes(section.layoutPreset??''))return [{id:'mediaUrl',field:'mediaUrl',label:'Wide visual',url:section.mediaUrl??'',geometryLocked:false}];
 if(isImageFlow(section)||isKnowledgeTriptych(section))return fixedImageCardItems(section).map((item,index)=>({id:`item:${item.id}:imageUrl`,field:'imageUrl' as const,itemId:item.id,label:`Image ${index+1}`,url:item.imageUrl??'',geometryLocked:true}));
 if(isFocusedSteps(section))return (section.items??[]).map((item,index)=>({id:`item:${item.id}:imageUrl`,field:'imageUrl' as const,itemId:item.id,label:`Step image ${index+1}`,url:item.imageUrl??'',geometryLocked:true}));
 if(isConnectedRows(section))return connectedRowItems(section).map((item,index)=>({id:`item:${item.id}:imageUrl`,field:'imageUrl' as const,itemId:item.id,label:`Row image ${index+1}`,url:item.imageUrl??'',geometryLocked:true}));
 if(isImageHighlights(section))return [{id:'mediaUrl',field:'mediaUrl',label:'Main illustration',url:section.mediaUrl??'',geometryLocked:true}];
 if(isFixedImageCards(section))return fixedImageCardItems(section).map((item,index)=>({id:`item:${item.id}:imageUrl`,field:'imageUrl' as const,itemId:item.id,label:`Card image ${index+1}`,url:item.imageUrl??'',geometryLocked:true}));
  if(section.type==='media_text_split'&&section.layoutPreset==='corporate-section-story')return [{id:'mediaUrl',field:'mediaUrl',label:'Section artwork',url:section.mediaUrl??'',geometryLocked:true}];
  if(section.type==='media_text_split'&&section.layoutPreset==='corporate-builder-overview')return [{id:'mediaUrl',field:'mediaUrl',label:'Builder artwork',url:section.mediaUrl??'',geometryLocked:true}];
  if(section.type==='media_text_split'&&section.layoutPreset==='corporate-marketplace')return [{id:'mediaUrl',field:'mediaUrl',label:'Marketplace artwork',url:section.mediaUrl??'',geometryLocked:true}];
  if(section.type==='media_text_split'&&['corporate-payments','corporate-courier'].includes(section.layoutPreset??''))return [{id:'mediaUrl',field:'mediaUrl',label:section.layoutPreset==='corporate-courier'?'Courier image':'Payment image',url:section.mediaUrl??'',geometryLocked:true},...(section.items??[]).map((item,index)=>({id:`item:${item.id}:imageUrl`,field:'imageUrl' as const,itemId:item.id,label:`${section.layoutPreset==='corporate-courier'?'Courier':'Payment'} logo ${index+1}`,url:item.imageUrl??'',geometryLocked:true}))];
  if(section.type==='hero_banner'&&section.heroBannerPreset==='corporate-centered')return [];
  if(section.type==='testimonials_slider'&&section.testimonialPreset==='editorial-quote')return [];
  if(section.type==='chapter_block'&&['principles','process','actions'].includes(section.chapterPreset??'story')||section.type==='cta_strip'&&!['illustrated-panel','editorial-illustrated','image-banner'].includes(section.ctaPreset??'')) return [];
  if(section.type==='comparison_matrix'&&section.comparisonPreset==='device-table')return (section.items??[]).filter(item=>item.subtitle==='device').map((item,index)=>({id:`item:${item.id}:imageUrl`,field:'imageUrl',itemId:item.id,label:`Device ${index+1}`,url:item.imageUrl??'',geometryLocked:false}));
  const main=['cta_strip','hero_banner','split_hero','media_text_split','featured_deal'].includes(section.type)||(section.type==='chapter_block'&&(section.chapterPreset??'story')==='story');
  if(main && !['editorial-story-cards','editorial-journal-rows','artisan-story'].includes(section.layoutPreset??'') && section.mediaType!=='video') return [{id:'mediaUrl',field:'mediaUrl',label:'Image',url:section.mediaUrl??'',geometryLocked:['hero_banner','cta_strip'].includes(section.type)}];
  if(section.type==='before_after') return (['beforeImageUrl','afterImageUrl'] as const).map((field,index)=>({id:field,field,label:index===0?'Before image':'After image',url:section[field]??'',geometryLocked:true}));
  if(!['editorial-story-cards','editorial-journal-rows','artisan-story'].includes(section.layoutPreset??'')&&!['product_listing','promotion_highlights','shop_navigation','policy_content','chapter_block','hero_slider','media_gallery','instagram_feed','testimonials_slider','logo_cloud'].includes(section.type)) return [];
  if(section.type==='product_listing'&&section.listingPreset!=='fashion-lookbook')return [];
  const field=section.type==='testimonials_slider'?'authorAvatar':'imageUrl';
  return (section.items??[]).filter(item=>(section.type!=='hero_slider'||!item.videoUrl)&&(section.type!=='chapter_block'||!['delivery','policy'].includes(section.chapterPreset??'')||item.imageUrl!==undefined)&&(section.type!=='policy_content'||item.imageUrl!==undefined)).map((item,index)=>({id:`item:${item.id}:${field}`,field,itemId:item.id,label:`${section.type==='testimonials_slider'?'Reviewer':section.type==='hero_slider'?'Slide':section.type==='logo_cloud'?'Logo':'Image'} ${index+1}`,url:item[field]??'',geometryLocked:['corporate-connections','corporate-illustrated-steps','corporate-scenarios','photo-marquee','industrial-mosaic','album-browser'].includes(section.galleryPreset??'')||section.type==='promotion_highlights'||['hero_slider','testimonials_slider','logo_cloud'].includes(section.type)||section.type==='shop_navigation'&&['collection-banners','kids-banners','fashion-banners','market-hero','market-offers'].includes(section.shopNavigationPreset??'')}));
}
/** Artwork geometry is employee-owned; logos keep their dedicated height contract. */
export function editableArtworkGeometry(section:PageSection):boolean {return section.type==='media_text_split'&&['corporate-feature','corporate-image-story','corporate-support','corporate-payments','corporate-courier','corporate-marketplace','corporate-builder-overview','corporate-section-story','image-highlights','team-story-overview','wide-visual-editorial','wide-visual-centered','inset-surface-panel','split-visual-surface'].includes(section.layoutPreset??'')
  ||section.type==='media_gallery'&&['product-preview-tabs','corporate-scenarios','corporate-illustrated-steps','corporate-connections','image-cards-three','image-cards-four-mosaic','image-cards-contrast','image-cards-three-staggered','connected-feature-rows','image-flow','editorial-knowledge-triptych','scroll-stack-cards','focused-steps'].includes(section.galleryPreset??'')
  ||section.type==='hero_banner'&&['corporate-split-composite','corporate-centered-composite'].includes(section.heroBannerPreset??'')
  ||section.type==='cta_strip'&&['illustrated-panel','editorial-illustrated'].includes(section.ctaPreset??'');}
export function imageTargets(section:PageSection):ImageTarget[] {
 return layoutImageTargets(section).map(target=>editableArtworkGeometry(section)&&!target.label.toLowerCase().includes('logo')?{...target,geometryLocked:false}:target);
}

export function imageTargetSection(section:PageSection,target:ImageTarget):PageSection {
  const settings=section.imageSlots?.find(slot=>slot.target===target.id);
  return {id:section.id,type:section.type,title:section.title,isVisible:section.isVisible,
    ...(target.id==='mediaUrl'?Object.fromEntries(imageControlKeys.map(key=>[key,section[key]])):{}),
    ...(target.id==='mediaUrl'?{mediaFocalPoint:section.mediaFocalPoint,mediaAspectRatio:section.mediaAspectRatio,layoutPreset:section.layoutPreset}:{}),
    ...settings,mediaUrl:target.url};
}
export function updateImageTarget(section:PageSection,targetId:string,patch:Partial<PageSection>):PageSection {
  const target=imageTargets(section).find(target=>target.id===targetId);if(!target) return section;
  let result={...section};
  if(patch.mediaUrl!==undefined) {
    if(target.itemId){const items=[...(section.items??[])];if(!items.some(i=>i.id===target.itemId))items.push({id:target.itemId,title:''});result.items=items.map(item=>item.id===target.itemId?{...item,[target.field]:patch.mediaUrl}:item);}
    else result={...result,[target.field]:patch.mediaUrl};
  }
  const controls=Object.fromEntries(imageControlKeys.filter(key=>patch[key]!==undefined).map(key=>[key,patch[key]]));
  if(Object.keys(controls).length) {
    // Existing main-slot fields stay canonical; other targets use stable slot identities.
    if(target.id==='mediaUrl' && section.type==='media_text_split' && !section.imageSlots?.some(slot=>slot.target===target.id)) result={...result,...controls};
    else {
      const old=section.imageSlots?.find(slot=>slot.target===target.id);
      const next={...old,target:target.id,...controls} as SectionImageSlot;
      result.imageSlots=[...(section.imageSlots??[]).filter(slot=>slot.target!==target.id),next];
    }
  }
  return pruneImageSlots(result);
}
export function pruneImageSlots(section:PageSection):PageSection {
  if(!section.imageSlots) return section;
  const ids=new Set(imageTargets(section).map(target=>target.id));
  if(isConnectedRows(section))for(const item of section.items??[])if(item.imageUrl!==undefined)ids.add(`item:${item.id}:imageUrl`);
  // Keep dormant, owned assets across layout/video switches; deletion still prunes by item ID.
  if(['hero_banner','media_text_split','chapter_block','cta_strip'].includes(section.type))ids.add('mediaUrl');
  if(['product_listing','media_text_split','chapter_block','hero_slider'].includes(section.type))for(const item of section.items??[])if(item.imageUrl!==undefined)ids.add(`item:${item.id}:imageUrl`);
  return {...section,imageSlots:section.imageSlots.filter(slot=>ids.has(slot.target))};
}

/** Mirrors CmsAiImageService editorial-only slots; editable does not mean generatable. */
export function canGenerateImageTarget(section:PageSection,targetId:string):boolean {
 const target=imageTargets(section).find(target=>target.id===targetId);
 if(!target)return false;
 if(targetId==='mediaUrl')return ['hero_banner','media_text_split','split_hero'].includes(section.type)&&section.mediaType!=='video';
 return ['media_gallery','hero_slider','shop_navigation'].includes(section.type)&&target.field==='imageUrl'&&!!target.itemId;
}
