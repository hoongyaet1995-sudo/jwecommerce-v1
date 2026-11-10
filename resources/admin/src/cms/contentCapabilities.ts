import type {PageSection} from '../types';

// Consumer-backed Content capabilities. Optional saved keys are values, not permissions.
const copy=['title','subtitle','content'];
const primary=['buttonText','buttonLink'];
const actions=[...primary,'secondaryButtonText','secondaryButtonLink'];
const media=['mediaUrl','mediaAlt'];
const fields:Record<PageSection['type'],readonly string[]>={
 hero_banner:[...copy,...media,...actions], split_hero:[...copy,...media,...actions,'cardBadge','cardTitle','cardSubtitle','cardPrice','cardExtra'],
 hero_slider:[...copy,...actions], announcement_bar:[], rich_text:[...copy,'quote','authorName','authorRole'],
 media_text_split:[...copy,...media,...primary], timeline_story:copy, stats_counter:[],
 product_grid:[...copy,'productIds'], product_listing:[...copy,'sourceType','sourceId','productIds','productLimit'],
 collection_tabs:[...copy,'tabs','tabCategories'], featured_deal:[...copy,...media,...primary,'dealBadge','discountPercent','dealEndDate'],
 pricing_table:copy, media_gallery:copy, video_embed:[...copy,'videoEmbedUrl'], before_after:[...copy,'beforeImageUrl','afterImageUrl','beforeLabel','afterLabel'],
 instagram_feed:copy, feature_cards:copy, icon_list:[], comparison_matrix:[...copy,'comparisonDimensionName','comparisonOurBrandName','comparisonCompetitorName'],
 faq_accordion:copy, contact_form:[...copy,'formEmail','contactPhone','contactAddress','contactButtonText','formSuccessMessage','subjectPlaceholder','categoryPlaceholder','messagePlaceholder','inquiryCategories'],
 store_locator:copy, testimonials_slider:copy, logo_cloud:['title','subtitle'], cta_strip:[...copy,...actions],
 newsletter_signup:[...copy,'buttonText','formSuccessMessage','newsletterCouponId'], values_list:copy,
 chapter_navigation:[], chapter_block:copy, delivery_table:[...copy,'deliverySource','deliveryNote','deliveryDestinationLabel','deliveryEstimateLabel','deliveryFeeLabel'],
 policy_content:[...copy,'policyNote'], payment_methods:[...copy,'paymentNote'], shop_navigation:[...copy,...primary], promotion_highlights:copy,
};
export const contentFieldKeys=new Set(Object.values(fields).flat().concat(['leadText','mapEmbedUrl','contactAddressIcon','buttonText','buttonLink']));
export function sectionContentFields(section:PageSection):readonly string[]{
 let result=[...fields[section.type]];
 if(section.type==='faq_accordion'&&section.faqPreset==='corporate-starter-faq')return ['title','subtitle','content','buttonText','buttonLink'];
 if(section.type==='media_gallery'&&['corporate-connections','corporate-scenarios','corporate-illustrated-steps'].includes(section.galleryPreset??''))return ['title','content'];
 if(section.type==='media_text_split'&&['corporate-builder-overview','corporate-section-story'].includes(section.layoutPreset??''))return ['title','content','mediaUrl','mediaAlt'];
 if(section.type==='media_text_split'&&['corporate-payments','corporate-courier','corporate-marketplace'].includes(section.layoutPreset??''))return [...copy,...media,...primary];
 if(section.type==='hero_banner'&&section.heroBannerPreset==='corporate-centered')return [...copy,...actions];
 if(section.type==='media_text_split'&&['corporate-feature','corporate-support','corporate-image-story','corporate-payments'].includes(section.layoutPreset??''))return [...copy,...media,...actions];
 if(section.type==='media_gallery'&&['portfolio-cards','industrial-mosaic','album-browser'].includes(section.galleryPreset??''))result.push(...primary);
 if(section.type==='comparison_matrix'&&section.comparisonPreset==='device-table')return [...copy,'comparisonDimensionName','buttonText','buttonLink'];
 if(section.type==='media_text_split'){
  const preset=section.layoutPreset??'default';
  if(['editorial-story-cards','editorial-journal-rows','artisan-story'].includes(preset))result=[...copy,...primary];
  if(preset==='editorial-cover')result=[...copy,...media,'leadText'];
  if(preset==='kids-discovery')result=[...copy,...media,'leadText'];
  if(preset==='tech-feature')result=[...copy,...media,...actions];
  if(preset==='editorial-hero')result=[...copy,...media,'leadText',...actions];
 }
 if(section.type==='feature_cards'&&section.featureCardsPreset==='service-highlights')result=section.showSectionHeading===true?[...copy]:[];
 if(section.type==='rich_text'&&['manifesto','industrial-split'].includes(section.richTextPreset??''))result=[...copy];
 if(section.type==='hero_slider'&&['editorial-overlay','fashion-editorial'].includes(section.sliderPreset??''))result=['title']; // Slide copy/actions belong to entries.
 if(section.type==='product_listing'&&['industrial-related','editorial-grid','editorial-tabs','fashion-lookbook','beauty-grid','pantry-grid','pantry-tabs','tech-grid','tech-tabs','kids-grid','kids-tabs'].includes(section.listingPreset??''))result.push(...primary);
 if(section.type==='shop_navigation'&&['collection-banners','kids-banners','fashion-banners'].includes(section.shopNavigationPreset??''))result=result.filter(field=>!primary.includes(field));
 if(section.type==='shop_navigation'&&['market-hero','market-offers'].includes(section.shopNavigationPreset??'')&&section.showSectionHeading===false)result=result.filter(field=>!primary.includes(field));
 if(section.type==='cta_strip'){
  if(section.ctaPreset==='industrial-outline')result=['title','content',...actions];
  if(section.ctaPreset==='image-banner')result.push(...media);
  if(section.ctaPreset==='gradient-panel')result=['title','content',...primary];
  if(section.ctaPreset==='editorial-band')result=['title','content',...primary];
  if(section.ctaPreset==='help-panel')result=[...copy,...primary];
 }
 if(section.type==='contact_form'&&section.contactPreset==='enquiry')result=[...copy,...primary,'formEmail','contactPhone','contactButtonText','subjectPlaceholder','categoryPlaceholder','messagePlaceholder','inquiryCategories'];
 if(section.type==='store_locator'&&section.contactPreset==='location')result.push('contactAddress','mapEmbedUrl','contactAddressIcon');
 if(section.type==='chapter_block'){
  const preset=section.chapterPreset??'story';
  if(preset==='story')result.push(...media);
  if(preset==='actions')result.push(...actions);
  if(preset==='delivery')result.push('deliverySource','deliveryNote','deliveryDestinationLabel','deliveryEstimateLabel','deliveryFeeLabel');
  if(preset==='policy')result.push('policyNote');
  if(preset==='principles')result=['subtitle'];
 }
 if(section.showSectionHeading===false || (section.type==='values_list'||section.featureCardsPreset==='values-list')&&!section.valuesShowHeading)result=result.filter(key=>!copy.includes(key));
 if(section.type==='media_gallery'&&section.galleryPreset==='photo-marquee'&&section.showSectionHeading===false)result.push('subtitle');
 return [...new Set(result)];
}
