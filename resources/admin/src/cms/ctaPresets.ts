import type {PageSection} from '../types';
export function ctaPresetPatch(section:PageSection,preset:NonNullable<PageSection['ctaPreset']>):Partial<PageSection> {
 if(['illustrated-panel','editorial-illustrated'].includes(preset))return {ctaPreset:preset};
 if(preset==='gradient-panel'&&['illustrated-panel','editorial-illustrated'].includes(section.ctaPreset??''))return {ctaPreset:preset};
 if(preset==='gradient-panel')return {ctaPreset:preset,customColors:{...section.customColors,titleColor:'#ffffff',contentColor:'#ffffff',primaryBtnBg:'#ffffff',primaryBtnText:'#171335'}};
 if(preset==='saas-closing')return {ctaPreset:preset};
 if(preset==='industrial-outline')return {ctaPreset:preset};
 if(preset==='default') return {ctaPreset:'default'};
 if(preset==='help-panel')return {ctaPreset:preset,subtitle:section.subtitle??'',backgroundColor:'bg-white',textColor:'dark',customColors:{...section.customColors,customBgColor:'#f5f3ff',eyebrowColor:'#4f46e5',titleColor:'#171717',contentColor:'#737373'}};
 if(preset==='editorial-band')return {ctaPreset:preset,imageSlots:undefined,backgroundColor:'custom',textColor:'light',customColors:{...section.customColors,customBgColor:'#4f46e5',titleColor:'#ffffff',contentColor:'#ffffff',primaryBtnBg:'#ffffff',primaryBtnText:'#4f46e5'}};
 const old=section.imageSlots?.find(slot=>slot.target==='mediaUrl');
 return {ctaPreset:preset,ctaBannerHeight:section.ctaBannerHeight??'normal',imageSlots:old?section.imageSlots:[...(section.imageSlots??[]),{target:'mediaUrl',imageOverlay:true,imageOverlayOpacity:30}],customColors:{...section.customColors,eyebrowColor:'#ffffff',titleColor:'#ffffff',contentColor:'#ffffff'}};
}
