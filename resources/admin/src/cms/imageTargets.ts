import type {PageSection,SectionImageSlot} from '../types';
export const imageControlKeys=['mediaAlt','imageRatio','imagePosition','imageFit','imageWidth','imageShape','imageOverlay','imageOverlayOpacity','imageForeground','imageForegroundColor','imageForegroundOpacity'] as const;
export const imageSlotFamilies=['promotion_highlights','shop_navigation','policy_content','chapter_block','cta_strip','hero_banner','hero_slider','split_hero','media_text_split','featured_deal','media_gallery','before_after','instagram_feed','testimonials_slider','logo_cloud'] as const;
export interface ImageTarget {id:string;label:string;url:string;geometryLocked:boolean;itemId?:string;field:'mediaUrl'|'beforeImageUrl'|'afterImageUrl'|'imageUrl'|'authorAvatar'}
export function imageTargets(section:PageSection):ImageTarget[] {
  if(section.type==='chapter_block'&&['principles','process','actions'].includes(section.chapterPreset??'story')||section.type==='cta_strip'&&section.ctaPreset!=='image-banner') return [];
  const main=['cta_strip','hero_banner','split_hero','media_text_split','featured_deal'].includes(section.type)||(section.type==='chapter_block'&&(section.chapterPreset??'story')==='story');
  if(main && section.layoutPreset!=='editorial-story-cards' && section.mediaType!=='video') return [{id:'mediaUrl',field:'mediaUrl',label:'Image',url:section.mediaUrl??'',geometryLocked:['hero_banner','cta_strip'].includes(section.type)}];
  if(section.type==='before_after') return (['beforeImageUrl','afterImageUrl'] as const).map((field,index)=>({id:field,field,label:index===0?'Before image':'After image',url:section[field]??'',geometryLocked:true}));
  if(section.layoutPreset!=='editorial-story-cards'&&!['promotion_highlights','shop_navigation','policy_content','chapter_block','hero_slider','media_gallery','instagram_feed','testimonials_slider','logo_cloud'].includes(section.type)) return [];
  const field=section.type==='testimonials_slider'?'authorAvatar':'imageUrl';
  return (section.items??[]).filter(item=>(section.type!=='hero_slider'||!item.videoUrl)&&(section.type!=='chapter_block'||!['delivery','policy'].includes(section.chapterPreset??'')||item.imageUrl!==undefined)&&(section.type!=='policy_content'||item.imageUrl!==undefined)).map((item,index)=>({id:`item:${item.id}:${field}`,field,itemId:item.id,label:`${section.type==='testimonials_slider'?'Reviewer':section.type==='hero_slider'?'Slide':section.type==='logo_cloud'?'Logo':'Image'} ${index+1}`,url:item[field]??'',geometryLocked:section.type==='promotion_highlights'||['hero_slider','testimonials_slider','logo_cloud'].includes(section.type)||section.type==='shop_navigation'&&section.shopNavigationPreset==='collection-banners'}));
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
    if(target.itemId) result.items=(section.items??[]).map(item=>item.id===target.itemId?{...item,[target.field]:patch.mediaUrl}:item);
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
  // Keep dormant, owned assets across layout/video switches; deletion still prunes by item ID.
  if(['media_text_split','chapter_block','cta_strip'].includes(section.type))ids.add('mediaUrl');
  if(['media_text_split','chapter_block','hero_slider'].includes(section.type))for(const item of section.items??[])if(item.imageUrl!==undefined)ids.add(`item:${item.id}:imageUrl`);
  return {...section,imageSlots:section.imageSlots.filter(slot=>ids.has(slot.target))};
}
