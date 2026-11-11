import type {PageSection} from '../types';

/** Generic media Hero. Colours inherit the page; no industry or destination binding. */
export function centeredCompositeHero(prefix:string):PageSection {
 return {id:prefix+'-hero',type:'hero_banner',heroBannerPreset:'corporate-centered-composite',isVisible:true,
  containerMode:'page',motionPreset:'subtle-lift',sectionBorder:'none',paddingY:'normal',
  subtitle:'YOUR BUSINESS, CONNECTED',title:'Bring your next idea into focus.',
  content:'Introduce your business and help visitors discover the next step.',
  mediaUrl:'',imageSlots:[{target:'mediaUrl',mediaAlt:''}],buttonText:'Get in touch',buttonLink:'',secondaryButtonText:'Learn more',secondaryButtonLink:''};
}
