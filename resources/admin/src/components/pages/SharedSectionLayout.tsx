import React from 'react';
import type {PageSection} from '../../types';
import {isConnectedFamily,connectedLayoutChoices,connectedLayoutPatch} from '../../cms/connectedFamilies';
import {isUnifiedPricing} from '../../cms/unifiedPricing';
import {sharedLayoutLabels} from '../../cms/sharedSectionLibrary';
import {VisualChoiceSelect} from './VisualChoiceSelect';
import {SectionLayoutGroup} from './SectionLayoutGroup';

export function SharedSectionLayout({section,onChange}:{section:PageSection;onChange:(s:PageSection)=>void}){
 if(!isConnectedFamily(section)&&!isUnifiedPricing(section))return null;
 const pricing=isUnifiedPricing(section),choices=pricing?['pricing-cards','pricing-matrix']:connectedLayoutChoices(section);
 const value=pricing?(section.pricingMatrix?.view==='matrix'?'pricing-matrix':'pricing-cards'):section.connectedLayout;
 return <SectionLayoutGroup section={section}><VisualChoiceSelect aria-label="Section layout" value={value} onChange={e=>onChange(pricing?{...section,pricingMatrix:{...section.pricingMatrix!,view:e.target.value==='pricing-matrix'?'matrix':'cards'}}:(isConnectedFamily(section)?connectedLayoutPatch(section,e.target.value):section))}>{choices.map(id=><option key={id} value={id}>{sharedLayoutLabels[id]}</option>)}</VisualChoiceSelect></SectionLayoutGroup>;
}
