import type {PageSection} from '../types';

/** Independent starter using the shared Hero semantic type and Inspector. */
export function websiteBuilderHero(prefix:string,artwork=''):PageSection {
 return {id:prefix+'-hero',type:'hero_banner',heroBannerPreset:'corporate-split-composite',isVisible:true,
  containerMode:'page',motionPreset:'subtle-lift',sectionBorder:'none',paddingY:'normal',backgroundColor:'custom',textColor:'dark',
  customColors:{customBgColor:'#ffffff',titleColor:'#0d1234',contentColor:'#626981',eyebrowColor:'#7426ff',primaryBtnBg:'#2860ff',primaryBtnText:'#ffffff',secondaryBtnBg:'#8524ff',secondaryBtnText:'#5928ed'},
  subtitle:'BUSINESS WEBSITES & VISUAL BUILDER',title:'Your business.\nBeautifully\npresented.',
  content:'Bring your business, products and services together. Keep everyday updates in your hands with visual editing and reusable sections.',
  mediaUrl:artwork,imageSlots:[{target:'mediaUrl',mediaAlt:'Illustration of a business owner and designer collaborating, with a visual website builder preview'}],
  buttonText:'Request a demo',buttonLink:'https://www.jwaistudio.online/pages/contact-jw-studio',
  secondaryButtonText:'Explore the builder',secondaryButtonLink:'#section-'+prefix+'-builder'};
}

/** Library defaults are neutral. Approved page-specific copy above is never rewritten. */
export function splitCompositeHero(prefix:string,artwork=''):PageSection {
 const s=websiteBuilderHero(prefix,artwork);
 return {...s,subtitle:'WELCOME',title:'A clearer way\nto tell your story.',content:'Introduce your business, services or products. Make this space your own.',imageSlots:[{target:'mediaUrl',mediaAlt:''}],buttonText:'Get in touch',buttonLink:'',secondaryButtonText:'Learn more',secondaryButtonLink:''};
}
