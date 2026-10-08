import type {PageSection} from '../types';
export function ctaPresetPatch(section:PageSection,preset:NonNullable<PageSection['ctaPreset']>):Partial<PageSection> {
 if(preset==='default') return {ctaPreset:'default'};
 if(preset==='editorial-band')return {ctaPreset:preset,imageSlots:undefined,backgroundColor:'custom',textColor:'light',customColors:{...section.customColors,customBgColor:'#4f46e5',titleColor:'#ffffff',contentColor:'#ffffff',primaryBtnBg:'#ffffff',primaryBtnText:'#4f46e5'}};
 const old=section.imageSlots?.find(slot=>slot.target==='mediaUrl');
 return {ctaPreset:preset,ctaBannerHeight:section.ctaBannerHeight??'normal',imageSlots:old?section.imageSlots:[...(section.imageSlots??[]),{target:'mediaUrl',imageOverlay:true,imageOverlayOpacity:30}],customColors:{...section.customColors,eyebrowColor:'#ffffff',titleColor:'#ffffff',contentColor:'#ffffff'}};
}
