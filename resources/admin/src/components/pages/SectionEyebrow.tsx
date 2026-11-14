import React from 'react';
import type {PageSection,SectionItem} from '../../types';
import {headingCapability,sectionEyebrow,slideEyebrow} from '../../cms/sectionHeading';
import {typographyStyle} from '../../cms/inspectorCapabilities';
import {colorContrast,onColor} from '../../cms/pageStyle';
import './SectionEyebrow.css';
/** One content owner, shared typography, and no DOM/spacing for an empty value. */
export function SectionEyebrow({section,item}:{section:PageSection;item?:SectionItem}){
 const mode=headingCapability(section).mode;
 const value=mode==='slide'?(item?slideEyebrow(section,item):''):sectionEyebrow(section);
 if(!value)return null;
 const outerHeading=['contained-wide-centered','contained-wide-editorial'].includes(section.layoutPreset??'')||['staggered-focus-cards','image-cards-contrast'].includes(section.galleryPreset??'')||section.type==='faq_accordion'&&['pricing-card-faq','pricing-band-faq'].includes(section.faqPreset??'');
 const dark=!outerHeading&&(section.surface==='contrast'||section.surface===undefined&&section.textColor==='light');
 const textStyle={color:section.customColors?.eyebrowColor,...typographyStyle(section,'eyebrow')};
 const ink=typeof textStyle.color==='string'&&/^#[a-f\d]{6}$/i.test(textStyle.color)?textStyle.color:undefined;
 // Preserve the authored ink; adapt only a capsule whose default background makes it unreadable.
 const background=ink&&colorContrast(ink,dark?'#2b2b2b':'#ffffff')<4.5?(onColor(ink)==='#ffffff'?'light':'dark'):undefined;
 return <span className="cms-eyebrow-region" data-cms-eyebrow-region><span className="cms-eyebrow" data-cms-eyebrow="true" data-cms-text="eyebrow" data-cms-item={item?.id} data-eyebrow-background={background} data-eyebrow-tone={dark?'contrast':'light'} style={textStyle}><span className="cms-eyebrow-dot" aria-hidden="true"/><span>{value}</span></span></span>;
}
