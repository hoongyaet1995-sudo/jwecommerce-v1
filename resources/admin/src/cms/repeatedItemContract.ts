import type {PageSection,SectionItem} from '../types';
/** Inspector capabilities describe consumed renderer fields, never accidental data presence. */
const fields:Partial<Record<PageSection['type'],readonly string[]>>={
 promotion_highlights:['title','description','imageUrl','sourceId','buttonText','linkUrl'],
 shop_navigation:['title','imageUrl','linkUrl','sourceType','sourceId'],
 hero_slider:['title','subtitle','badge','description','imageUrl','videoUrl','linkUrl','buttonText','secondaryButtonText','secondaryButtonLink'],
 announcement_bar:['title'], timeline_story:['title','description','date'],
 stats_counter:['title','description','value'], pricing_table:['title','description','price','features','badge','linkUrl','isHighlighted'],
 media_gallery:['title','description','imageUrl'], instagram_feed:['title','imageUrl','handle'],
 feature_cards:['title','description','icon','badge'],icon_list:['title','description','icon'],
 comparison_matrix:['title','description','badge'],faq_accordion:['title','description'],
 store_locator:['title','description','subtitle','badge','linkUrl'],
 testimonials_slider:['title','description','author','authorRole','authorAvatar','rating','badge'],
 logo_cloud:['title','description','imageUrl','linkUrl'], values_list:['title','description'],
 policy_content:['title','description','icon','linkUrl','buttonText','imageUrl'],
 delivery_table:['title','deliveryEstimate','deliveryFee'],chapter_navigation:['title','linkUrl'],
};
export function repeatedItemFields(section:PageSection):readonly string[]{
 if(section.type==='shop_navigation'&&section.shopNavigationPreset==='collection-banners')return ['title','description','buttonText','imageUrl','linkUrl','sourceType','sourceId'];
 if(section.type==='hero_slider'&&section.sliderPreset==='editorial-overlay')return ['title','subtitle','description','imageUrl','videoUrl','linkUrl','buttonText','secondaryButtonText','secondaryButtonLink','caption'];
 if(section.type==='store_locator'&&section.contactPreset==='location')return ['title','description','icon'];
 if(section.type==='feature_cards'&&section.featureCardsPreset==='service-highlights')return ['title','description','icon'];
 if(section.type==='feature_cards'&&section.featureCardsPreset==='editorial-columns')return ['title','description'];
 if(section.type==='media_gallery'&&section.galleryPreset==='editorial-montage')return ['title','imageUrl'];
 if(section.type==='media_text_split'&&section.layoutPreset==='editorial-story-cards')return ['title','description','imageUrl','buttonText','linkUrl'];
 if(section.type==='media_text_split')return !section.layoutPreset||['default','story-with-steps'].includes(section.layoutPreset)?['title','description']:[];
 if(section.type==='chapter_block'){
  if(section.chapterPreset==='policy')return repeatedItemFields({...section,type:'policy_content'});
  if(section.chapterPreset==='process')return ['title','description','icon'];
  if(section.chapterPreset==='principles')return ['title','description'];
  if(section.chapterPreset==='delivery')return ['title','deliveryEstimate','deliveryFee','imageUrl'];
  if(!section.chapterPreset||['story','actions'].includes(section.chapterPreset))return [];
  if(section.chapterPreset==='perspective')return ['title','imageUrl'];
  return ['title','description','imageUrl'];
 }
 if(section.type==='policy_content')return [...(fields.policy_content??[]),...(section.policyPreset==='tabs'?['category']:[])];
 return fields[section.type]??[];
}
export function itemSupports(section:PageSection,key:string){return repeatedItemFields(section).includes(key);}
export function supportedNewItem(section:PageSection,item:SectionItem):SectionItem{
 return Object.fromEntries(Object.entries(item).filter(([key])=>key==='id'||itemSupports(section,key))) as unknown as SectionItem;
}
