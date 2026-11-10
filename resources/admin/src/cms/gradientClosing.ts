import type {PageSection} from '../types';
/** Reusable library preset: no business identity or destination is bound here. */
export function gradientClosing(prefix:string):PageSection {
 return {id:prefix+'-closing',type:'cta_strip',ctaPreset:'gradient-panel',isVisible:true,containerMode:'page',motionPreset:'subtle-lift',sectionBorder:'none',paddingY:'normal',backgroundColor:'custom',textColor:'light',textAlignment:'center',customColors:{customBgColor:'#ffffff',titleColor:'#ffffff',contentColor:'#ffffff',primaryBtnBg:'#ffffff',primaryBtnText:'#171335'},title:'Ready for your next step?',content:'Tell us what you need. Let’s find the right solution together.',buttonText:'Get in touch',buttonLink:''};
}
/** Approved page content stays separate from reusable layout defaults. */
export function websiteBuilderClosing(prefix:string):PageSection {
 return {...gradientClosing(prefix),title:'Let’s build your next chapter online.',content:'Tell us about your business. We’ll walk you through a setup that fits.',buttonText:'Request a demo',buttonLink:'https://www.jwaistudio.online/pages/contact-jw-studio'};
}
