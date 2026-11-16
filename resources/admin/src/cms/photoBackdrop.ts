import type {PageSection} from '../types';
/** Layout capability shared by the creation defaults and the existing hero renderer. */
export const hasFullHeroBackdrop=(s:PageSection)=>!s.connectedLayout&&(s.type==='hero_banner'&&!['editorial-gradient','industrial-overlay','corporate-centered','corporate-split-composite','corporate-centered-composite'].includes(s.heroBannerPreset??'')||s.type==='hero_slider'&&!['editorial-overlay','fashion-editorial'].includes(s.sliderPreset??''));
export const hasCollectionPhotoBackdrop=(s:PageSection)=>s.type==='shop_navigation'&&['collection-banners','kids-banners','fashion-banners'].includes(s.shopNavigationPreset??'');
