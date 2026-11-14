import {isConnectedFamily} from './connectedFamilies';
import {isUnifiedPricing} from './unifiedPricing';
import {legacyTypographyRoles} from './sectionHeading';
import {hasContainedSurface} from './containedSurface';
import type {PageSection} from '../types';
import {isTypography,type SectionTypography,type TextRole} from './inspectorCapabilities';
export interface BrandPalette {contrast?:string;paper:string;ink:string;muted:string;accent:string;light:string;secondary:string;secondaryInk:string;hover:string}
export interface PageStyle {motionPace?:'smooth'|'relaxed';palette:BrandPalette;colorSource?:'theme'|'custom';typography?:SectionTypography;spacing?:'compact'|'normal'|'spacious';buttonRadius?:0|6|999;buttonSize?:'compact'|'normal'|'large';unifiedButtons?:boolean;buttonHover?:boolean;buttonHoverColor?:string;preserveLayout?:boolean}
export interface NamedPalette {id:string;name:string;primary:string;secondary:string|null;colors:BrandPalette}
export const paletteKeys=['paper','ink','muted','accent','light','secondary','secondaryInk','hover'] as const;
const hex=/^#[a-f\d]{6}$/i;
export const isPalette=(v:unknown):v is BrandPalette=>!!v&&typeof v==='object'&&!Array.isArray(v)&&Object.keys(v).every(k=>(paletteKeys as readonly string[]).includes(k)||k==='contrast')&&((v as BrandPalette).contrast===undefined||hex.test((v as BrandPalette).contrast!))&&paletteKeys.every(k=>hex.test((v as BrandPalette)[k]));
const channels=(h:string)=>h.slice(1).match(/../g)!.map(v=>parseInt(v,16));
const blend=(a:string,b:string,t:number)=>'#'+channels(a).map((v,i)=>Math.round(v*(1-t)+channels(b)[i]*t).toString(16).padStart(2,'0')).join('');
function luminance(h:string){return channels(h).map(v=>v/255).map(v=>v<=.04045?v/12.92:((v+.055)/1.055)**2.4).reduce((s,v,i)=>s+v*[.2126,.7152,.0722][i],0)}
export function colorContrast(a:string,b:string){const [hi,lo]=[luminance(a),luminance(b)].sort((a,b)=>b-a);return (hi+.05)/(lo+.05)}
export const onColor=(bg:string)=>colorContrast('#ffffff',bg)>=colorContrast('#151515',bg)?'#ffffff':'#151515';
export function generatePalette(primary:string,secondary?:string|null):BrandPalette {const paper=blend(primary,'#ffffff',.96),ink=blend(primary,'#000000',.84);let muted=blend(ink,paper,.38);while(colorContrast(muted,paper)<4.5)muted=blend(muted,ink,.12);const second=secondary||blend(primary,'#ffffff',.72);return {paper,ink,muted,accent:primary,light:onColor(primary),secondary:second,secondaryInk:onColor(second),hover:blend(primary,onColor(primary)==='#ffffff'?'#000000':'#ffffff',.12)}}
export const stylePresets=[{id:'warm',name:'Warm editorial',palette:{paper:'#f7f6f2',ink:'#292b24',muted:'#6d7267',accent:'#454e37',light:'#f7f6f2',secondary:'#dad7c7',secondaryInk:'#292b24',hover:'#343c29'}},{id:'clean',name:'Clean modern',palette:{paper:'#ffffff',ink:'#18232b',muted:'#65717b',accent:'#254e5e',light:'#ffffff',secondary:'#dce9ed',secondaryInk:'#18232b',hover:'#183b49'}},{id:'clay',name:'Soft earth',palette:{paper:'#f6eee6',ink:'#44352e',muted:'#806d61',accent:'#885a44',light:'#fff9f2',secondary:'#dec4b3',secondaryInk:'#44352e',hover:'#6e4533'}}];
export function presetStyle(id:string):PageStyle {return {palette:stylePresets.find(p=>p.id===id)!.palette,typography:{eyebrow:{fontFamily:'sans',fontSize:10,fontWeight:600},title:{fontFamily:id==='clean'?'sans':'serif',fontSize:38,fontWeight:400},lead:{fontFamily:'sans',fontSize:18},content:{fontFamily:'sans',fontSize:14},itemTitle:{fontFamily:id==='clean'?'sans':'serif',fontSize:21},itemContent:{fontFamily:'sans',fontSize:12}},spacing:'normal',buttonRadius:0,buttonSize:'normal'}}
/** Native compositions contain light and dark regions inside one semantic section.
 * Their CSS owns default foregrounds; only authored role settings may override them. */
function resolveNativeVisualSection(section:PageSection,style?:PageStyle):PageSection {
 const palette:BrandPalette=(section.surface==='page'?style?.palette:section.brandPalette??style?.palette)??stylePresets[0].palette;
 const typography:SectionTypography={};
 for(const role of new Set([...Object.keys(style?.typography??{}),...Object.keys(section.typography??{})] as TextRole[]))typography[role]={...style?.typography?.[role],...section.typography?.[role]};
 const colors={...section.customColors},customSurface=section.colorOverrides?.includes('customBgColor'),contrast=section.surface==='contrast';
 if(!customSurface&&section.surface!==undefined)colors.customBgColor=contrast?(palette.contrast??palette.ink):palette.paper;
 if(section.surface==='light'||contrast||customSurface){const background=colors.customBgColor??palette.paper;const ink=(contrast||customSurface)&&/^#[\da-f]{6}$/i.test(background)?onColor(background):contrast?onColor(palette.contrast??palette.ink):palette.ink;if(!section.colorOverrides?.includes('titleColor'))colors.titleColor=ink;if(!section.colorOverrides?.includes('contentColor'))colors.contentColor=contrast?ink:palette.muted;}
 return {...section,brandPalette:palette,...(colors.customBgColor?{backgroundColor:'custom',textColor:contrast?'light':'dark'}:{}),...(Object.keys(colors).length?{customColors:colors}:{}),typography:Object.keys(typography).length?typography:undefined,paddingY:section.paddingY??style?.spacing};
}
export function resolveStyledSection(section:PageSection,style?:PageStyle):PageSection {
 if(style?.motionPace&&section.motionPace===undefined)section={...section,motionPace:style.motionPace};
 if(isConnectedFamily(section)||isUnifiedPricing(section))return resolveNativeVisualSection(section,style);
 if(style?.preserveLayout){
  const base=resolveStyledSection(section,section.surface==='page'||isConnectedFamily(section)?{palette:style.palette}:undefined);
  const typography={...base.typography};
  for(const [role,value]of Object.entries(style.typography??{}))typography[role as keyof SectionTypography]={...value,...base.typography?.[role as keyof SectionTypography]};
  return {...base,...(style.spacing?{paddingY:section.paddingY??style.spacing}:{}),...(Object.keys(typography).length?{typography}:{})};
 }
 const palette=section.surface==='page'?style?.palette:section.brandPalette??style?.palette;
 if(!style&&!palette&&!section.surface){
  const reset=Object.entries(section.typography??{}).filter(([,value])=>value.inheritPage);
  if(!reset.length)return section;
  // A role reset must not activate shared colors for unrelated legacy text, buttons or backgrounds.
  const typography={...section.typography};
  for(const [role,value]of reset)typography[role as keyof SectionTypography]={...value,color:value.color??(section.textColor==='light'?'#ffffff':role==='content'||role==='lead'||role==='itemContent'?stylePresets[0].palette.muted:stylePresets[0].palette.ink)};
  return {...section,typography};
 }
 const explicitSurface=section.surface==='page'||section.surface==='light'||section.surface==='contrast';
 const cardContrast=['contained-wide-centered','contained-wide-editorial'].includes(section.layoutPreset??'')||['staggered-focus-cards','image-cards-contrast'].includes(section.galleryPreset??'')||section.type==='faq_accordion'&&['pricing-card-faq','pricing-band-faq'].includes(section.faqPreset??'');
 const contrast=section.surface==='contrast'&&!cardContrast;const c:BrandPalette=palette??stylePresets[0].palette;
 const ink=contrast?(c.contrast?onColor(c.contrast):c.light):c.ink,muted=contrast?(hasContainedSurface(section)?blend(c.light,c.ink,.2):ink):c.muted,bg=contrast?(c.contrast??(hasContainedSurface(section)?c.ink:c.accent)):c.paper;
 const typography:SectionTypography={};
 for(const role of ['eyebrow','title','lead','content','itemTitle','itemContent'] as const){
  const overlayItems=role.startsWith('item')&&section.type==='shop_navigation'&&section.shopNavigationPreset==='collection-banners';
  const lightCards=role.startsWith('item')&&section.type==='promotion_highlights';
  const legacy=section.typography?.[role]?.inheritPage?undefined:role==='eyebrow'?section.customColors?.eyebrowColor:role==='title'||role==='itemTitle'?section.customColors?.titleColor:section.customColors?.contentColor;
  const roleColor=overlayItems?'#ffffff':lightCards?(role==='itemTitle'?c.ink:c.muted):role==='title'||role==='itemTitle'?ink:muted;
  // An explicit surface owns inherited colors; authored Typography overrides still win.
  typography[role]={color:explicitSurface?roleColor:legacy??roleColor,...style?.typography?.[role],...section.typography?.[role],...(!explicitSurface&&legacy&&!section.typography?.[role]?.color?{color:legacy}:{})};
 }
 for(const role of legacyTypographyRoles(section)){
  const local=section.typography?.[role]??section.typography?.eyebrow;
  typography[role]={color:explicitSurface?(role==='secondaryTitle'?ink:muted):local?.inheritPage?muted:section.customColors?.eyebrowColor??muted,...(style?.typography?.[role]??style?.typography?.eyebrow),...local};
 }
 return {...section,...(hasContainedSurface(section)?{brandPalette:c}:{}),...(explicitSurface||palette&&!section.backgroundColor?{backgroundColor:'custom' as const,textColor:contrast?'light' as const:'dark' as const}:{}),paddingY:section.paddingY??style?.spacing,typography,customColors:{eyebrowColor:muted,titleColor:ink,contentColor:muted,primaryBtnBg:contrast?c.paper:c.accent,primaryBtnText:contrast?c.ink:c.light,secondaryBtnBg:c.secondary,secondaryBtnText:c.secondaryInk,customBgColor:bg,...(explicitSurface?Object.fromEntries(Object.entries(section.customColors??{}).filter(([key])=>section.colorOverrides?.includes(key as any)&&!(section.surface==='page'&&key==='customBgColor'))):section.customColors)}};
}

export function isPageStyle(v:unknown):v is PageStyle {if(!v||typeof v!=='object'||Array.isArray(v))return false;const s=v as PageStyle;return Object.keys(s).every(k=>['motionPace','palette','colorSource','typography','spacing','buttonRadius','buttonSize','unifiedButtons','buttonHover','buttonHoverColor','preserveLayout'].includes(k))&&(s.motionPace===undefined||['smooth','relaxed'].includes(s.motionPace))&&isPalette(s.palette)&&(s.colorSource===undefined||['theme','custom'].includes(s.colorSource))&&(s.typography===undefined||isTypography(s.typography))&&(s.spacing===undefined||['compact','normal','spacious'].includes(s.spacing))&&(s.preserveLayout===undefined||typeof s.preserveLayout==='boolean')&&(s.buttonHoverColor===undefined||hex.test(s.buttonHoverColor))&&(s.buttonHover===undefined||typeof s.buttonHover==='boolean')&&(s.unifiedButtons===undefined||typeof s.unifiedButtons==='boolean')&&(s.buttonRadius===undefined||[0,6,999].includes(s.buttonRadius))&&(s.buttonSize===undefined||['compact','normal','large'].includes(s.buttonSize));}

/** Missing colorSource remains a legacy applied palette; follow theme is explicit. */
export function resolvePageStyle(style?:PageStyle,theme?:BrandPalette):PageStyle|undefined {return style?.colorSource==='theme'&&theme?{...style,palette:theme}:style;}
