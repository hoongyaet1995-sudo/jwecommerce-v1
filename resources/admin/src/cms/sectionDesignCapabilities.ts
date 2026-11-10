import {isIllustratedCta,illustratedCtaPassport} from './illustratedCta';
import {isConnectedRows,connectedRowsPassport} from './connectedFeatureRows';
import {isImageHighlights,imageHighlightsPassport} from './imageHighlights';
import {isFixedImageCards,imageCardsThreePassport} from './imageCardsThree';
import type {PageSection} from '../types';
/** Bounded renderer capabilities. Hidden authored values remain available on layout switches. */
export function renderedColorFields(s:PageSection):readonly string[]|undefined {
 if(isIllustratedCta(s))return illustratedCtaPassport.colors;
 if(isConnectedRows(s))return connectedRowsPassport.colors;
 if(isImageHighlights(s))return imageHighlightsPassport.colors;
 if(isFixedImageCards(s))return imageCardsThreePassport.colors;
 const text=['customBgColor','titleColor','contentColor'];
 if(s.type==='hero_banner'&&s.heroBannerPreset==='corporate-split-composite')return [...text,'eyebrowColor','primaryBtnBg','primaryBtnText','secondaryBtnBg','secondaryBtnText'];
 if(s.type==='media_text_split'&&['corporate-builder-overview','corporate-section-story'].includes(s.layoutPreset??''))return text;
 if(s.type==='media_gallery'&&['corporate-scenarios','corporate-illustrated-steps','corporate-connections'].includes(s.galleryPreset??''))return text;
 if(s.type==='faq_accordion'&&['corporate-starter-faq','pricing-band-faq'].includes(s.faqPreset??''))return text;
 if(s.type==='cta_strip'&&s.ctaPreset==='gradient-panel')return [...text,'primaryBtnBg','primaryBtnText'];
}
export function supportsDesignField(s:PageSection,key:string):boolean {
 if(!renderedColorFields(s))return true;
 if(['columnsCount','mediaPosition','mediaType','mediaContainerMode'].includes(key))return false;
 if(key==='textAlignment')return isConnectedRows(s)||isFixedImageCards(s)||s.type==='media_gallery'&&['corporate-scenarios','corporate-connections'].includes(s.galleryPreset??'');
 return true;
}
