import type {PageSection} from '../types';
import {pricingFaqSection} from './websiteBuilderStarterFaq';
export function pricingBandFaq(prefix:string):PageSection {return {...pricingFaqSection(prefix),id:prefix+'-pricing-band',faqPreset:'pricing-band-faq',title:'A practical place to start.',content:'Start with the essentials. Build on them as your business grows.',planDescription:'Describe the benefits of your plan.',subtitle:'A few helpful answers.'};}
