import {isPlanComparison,isFramedFaq} from './planComparison';
import {isFocusedSteps} from './focusedSteps';
import {isScrollStackCards} from './scrollStackCards';
import {isTeamStoryOverview} from './teamStoryOverview';
import {isKnowledgeTriptych} from './knowledgeTriptych';
import {isImageFlow} from './imageFlow';
import {isIllustratedCta,illustratedCtaPassport,isEditorialIllustratedCta,editorialIllustratedCtaPassport} from './illustratedCta';
import {isConnectedRows,connectedRowsPassport} from './connectedFeatureRows';
import {isImageHighlights,imageHighlightsPassport} from './imageHighlights';
import {isFixedImageCards,imageCardsThreePassport} from './imageCardsThree';
import type {PageSection} from '../types';
/** Bounded renderer capabilities. Hidden authored values remain available on layout switches. */
export function renderedColorFields(s:PageSection):readonly string[]|undefined {
 if(isFramedFaq(s))return ['customBgColor','eyebrowColor','titleColor','contentColor','primaryBtnBg','secondaryBtnBg'];
 if(isPlanComparison(s))return ['customBgColor','eyebrowColor','titleColor','contentColor','primaryBtnBg','primaryBtnText','secondaryBtnBg'];
 if(isEditorialIllustratedCta(s))return editorialIllustratedCtaPassport.colors;
 if(isIllustratedCta(s))return illustratedCtaPassport.colors;
 if(isConnectedRows(s))return connectedRowsPassport.colors;
 if(isImageHighlights(s))return imageHighlightsPassport.colors;
 if(isImageFlow(s))return ['customBgColor','titleColor','contentColor'];
 if(isFocusedSteps(s))return ['customBgColor','eyebrowColor','titleColor','contentColor'];
 if(isScrollStackCards(s))return ['customBgColor','eyebrowColor','titleColor','contentColor'];
 if(isTeamStoryOverview(s))return ['customBgColor','eyebrowColor','titleColor','contentColor'];
 if(isKnowledgeTriptych(s))return ['customBgColor','eyebrowColor','titleColor','contentColor'];
 if(isFixedImageCards(s))return imageCardsThreePassport.colors;
 if(s.type==='media_text_split'&&['wide-visual-centered','inset-surface-panel','split-visual-surface'].includes(s.layoutPreset??''))return ['customBgColor','titleColor','contentColor'];
 if(s.type==='media_text_split'&&s.layoutPreset==='wide-visual-editorial')return ['customBgColor','eyebrowColor','titleColor','contentColor'];
 const text=['customBgColor','titleColor','contentColor'];
 if(s.type==='hero_banner'&&s.heroBannerPreset==='corporate-split-composite')return [...text,'eyebrowColor','primaryBtnBg','primaryBtnText','secondaryBtnBg','secondaryBtnText'];
 if(s.type==='media_text_split'&&['corporate-builder-overview','corporate-section-story'].includes(s.layoutPreset??''))return text;
 if(s.type==='media_gallery'&&['corporate-scenarios','corporate-illustrated-steps','corporate-connections'].includes(s.galleryPreset??''))return text;
 if(s.type==='faq_accordion'&&['corporate-starter-faq','pricing-band-faq'].includes(s.faqPreset??''))return text;
 if(s.type==='cta_strip'&&s.ctaPreset==='gradient-panel')return [...text,'primaryBtnBg','primaryBtnText'];
}
export function supportsDesignField(s:PageSection,key:string):boolean {
 if((isPlanComparison(s)||isFramedFaq(s))&&key==='textAlignment')return false;
 if(isFocusedSteps(s)&&key==='textAlignment')return false;
 if(isScrollStackCards(s)&&['motionPreset','textAlignment'].includes(key))return false;
 if(!renderedColorFields(s))return true;
 if(['columnsCount','mediaPosition','mediaType','mediaContainerMode'].includes(key))return false;
 if(key==='textAlignment'&&(isImageFlow(s)||isKnowledgeTriptych(s)||isTeamStoryOverview(s)))return false;
 if(key==='textAlignment')return isConnectedRows(s)||isFixedImageCards(s)||s.type==='media_gallery'&&['corporate-scenarios','corporate-connections'].includes(s.galleryPreset??'');
 return true;
}
