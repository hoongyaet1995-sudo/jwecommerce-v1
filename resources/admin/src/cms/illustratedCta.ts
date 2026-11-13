import type {PageSection} from '../types';
export const illustratedCtaPassport={contentFields:['title','content','mediaUrl','buttonText','buttonLink'],colors:['customBgColor','titleColor','contentColor']} as const;
export const isIllustratedCta=(s:PageSection)=>s.type==='cta_strip'&&['illustrated-panel-right','illustrated-panel','editorial-illustrated'].includes(s.ctaPreset??'');
export function illustratedCta(prefix:string):PageSection {return {id:prefix+'-illustrated-cta',type:'cta_strip',ctaPreset:'illustrated-panel',isVisible:true,containerMode:'page',motionPreset:'subtle-lift',sectionBorder:'none',paddingY:'normal',backgroundColor:'custom',textColor:'dark',customColors:{customBgColor:'#ffffff',titleColor:'#0d1234',contentColor:'#626981'},title:'Take your next step.',content:'Tell us what you need. We will help you find a setup that fits.',mediaUrl:'',buttonText:'Get in touch',buttonLink:''};}

/** Editorial composition, same single image/action contract. Page styles remain authoritative. */
export const isEditorialIllustratedCta=(s:PageSection)=>s.type==='cta_strip'&&s.ctaPreset==='editorial-illustrated';
export const editorialIllustratedCtaPassport={contentFields:['subtitle','title','content','mediaUrl','buttonText','buttonLink'],colors:['customBgColor','eyebrowColor','titleColor','contentColor','primaryBtnBg','primaryBtnText','secondaryBtnBg']} as const;
export function editorialIllustratedCta(prefix:string):PageSection{return {id:prefix+'-editorial-cta',type:'cta_strip',ctaPreset:'editorial-illustrated',isVisible:true,containerMode:'page',motionPreset:'subtle-lift',sectionBorder:'none',paddingY:'normal',subtitle:'Letâ€™s talk about your next step',title:'Make room for what comes next.',content:'Tell us what you need. We will help you find a setup that fits.',mediaUrl:'',buttonText:'Get in touch',buttonLink:''};}

export function illustratedCtaRight(prefix:string):PageSection{return {...illustratedCta(prefix),ctaPreset:'illustrated-panel-right'};}
