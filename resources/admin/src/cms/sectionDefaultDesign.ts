import type {PageSection} from '../types';
import type {PageStyle,BrandPalette} from './pageStyle';
import type {SectionTypography} from './inspectorCapabilities';
import {connectedPassport} from './connectedFamilies';
export const demoPalette:BrandPalette={paper:'#ffffff',ink:'#101a3a',muted:'#5c6680',accent:'#6042f5',light:'#ffffff',secondary:'#eae5ff',secondaryInk:'#342373',hover:'#4830c9',contrast:'#101a3a'};
export const demoTypeScale:SectionTypography={
 eyebrow:{fontFamily:'site',fontSize:13,fontWeight:600,lineHeight:1.3,letterSpacing:0},
 title:{fontFamily:'site',fontSize:48,fontWeight:700,lineHeight:1.1,letterSpacing:-1.2},
 content:{fontFamily:'site',fontSize:16,fontWeight:400,lineHeight:1.65,letterSpacing:0},
 lead:{fontFamily:'site',fontSize:20,fontWeight:400,lineHeight:1.5},
 secondaryTitle:{fontFamily:'site',fontSize:28,fontWeight:600,lineHeight:1.2},
 itemTitle:{fontFamily:'site',fontSize:22,fontWeight:600,lineHeight:1.25},
 itemContent:{fontFamily:'site',fontSize:15,fontWeight:400,lineHeight:1.6},
 label:{fontFamily:'site',fontSize:12,fontWeight:500,lineHeight:1.4},
 number:{fontFamily:'site',fontSize:48,fontWeight:700,lineHeight:1.1},
};
export const demoStyle:PageStyle={palette:demoPalette,colorSource:'custom',typography:demoTypeScale,spacing:'normal',buttonSize:'normal',buttonRadius:6,unifiedButtons:true,buttonHover:true};
const image='/section-defaults-sample/media/image.svg',logo='/section-defaults-sample/media/logo.svg';
const title='Lorem ipsum dolor sit amet.',paragraph='Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.';
const text:Record<string,string>={quote:paragraph,authorName:'Lorem Ipsum',beforeLabel:'Before',afterLabel:'After',comparisonOurBrandName:'Lorem ipsum',comparisonCompetitorName:'Lorem ipsum',paymentNote:'Lorem ipsum dolor sit amet.',deliveryNote:'Lorem ipsum dolor sit amet.',policyNote:'Replace sample copy with your approved store policy.',title,content:paragraph,description:'Lorem ipsum dolor sit amet, consectetur adipiscing elit.',eyebrow:'Lorem ipsum',subtitle:'Lorem ipsum',leadText:'Lorem ipsum dolor sit amet, consectetur adipiscing elit.',buttonText:'Explore',secondaryButtonText:'Learn more',caption:'Lorem ipsum dolor sit amet',author:'Lorem Ipsum',authorRole:'Lorem ipsum',badge:'Lorem',highlightLabel:'Lorem',label:'Lorem ipsum',monthlyNote:'Lorem ipsum dolor sit amet',annualNote:'Lorem ipsum dolor sit amet',includesLabel:'Lorem ipsum',pricingLabel:'On request',billingLabel:'/ month',cardBadge:'Lorem',cardTitle:title,cardSubtitle:'Lorem ipsum dolor sit amet',cardPrice:'RM99',cardExtra:'Lorem ipsum',deliveryEstimate:'Lorem ipsum',deliveryFee:'RM0',comparisonDimensionName:'Lorem ipsum',formSuccessMessage:'Sample only — no message is sent',contactAddress:'Lorem ipsum dolor sit amet',contactPhone:'+60 00 000 0000',formEmail:'hello@example.com',categoryPlaceholder:'Choose an option',subjectPlaceholder:'Lorem ipsum',messagePlaceholder:'Lorem ipsum dolor sit amet',contactButtonText:'Contact us',handle:'@example',date:'2026-01-01',question:title,answer:paragraph};
const mediaKeys=new Set(['mediaUrl','imageUrl','beforeImageUrl','afterImageUrl','authorAvatar']);
/** Approved immutable design baseline, shared by the review sample and the new-insert boundary. */
export type DemoSurface='light'|'contrast';
export function normalizeSampleSection(source:PageSection,surface:DemoSurface='light',palette:BrandPalette=demoPalette):PageSection{
 const s=structuredClone(source);
 const passport=connectedPassport(source);
 const compactCopy=(original:string)=>{
  const length=original.trim().length;
  if(/^(VS|[×✓✕+−–—%]|\d+[+%]?)$/i.test(original.trim()))return original;
  return length<=3?'LI':length<=8?'Lorem':length<=16?'Lorem ipsum':length<=24?'Lorem ipsum dolor':'Lorem ipsum dolor sit amet.';
 };
 const visit=(v:unknown,parentKey=''):unknown=>{
  if(Array.isArray(v))return v.map((x,i)=>typeof x==='string'&&['features','tabCategories','inquiryCategories'].includes(parentKey)?`Lorem ipsum ${i+1}`:visit(x,parentKey));
  if(!v||typeof v!=='object')return v;
  return Object.fromEntries(Object.entries(v).map(([k,x])=>{
   if(k==='typography'||k==='customColors'||k==='buttonColors'||k==='titleEmphasis')return [k,undefined];
   if(mediaKeys.has(k))return [k,source.type==='logo_cloud'?logo:image];
   if(k==='subtitle'&&x==='device'&&source.type==='comparison_matrix'&&source.comparisonPreset==='device-table')return [k,x];
   if(k==='mediaAlt')return [k,'Demo image — Lorem ipsum'];
   if(['videoUrl','videoEmbedUrl','mapEmbedUrl'].includes(k))return [k,''];
   if(typeof x==='string'&&['linkUrl','buttonLink','secondaryButtonLink','contactButtonLink'].includes(k))return [k,'#sample'];
   if(k==='price'||k==='annualPrice')return [k,99];
   if(k==='value'&&typeof x==='string')return [k,'24'];
   if(k==='rating')return [k,5];
   if(text[k]!==undefined&&typeof x==='string'){
    const slot=passport?.details.find(d=>d.slot===(v as {layoutSlot?:string}).layoutSlot&&d.field===k);
    if(slot)return [k,compactCopy(slot.label)];
    if(k==='description'||k==='label'||k==='badge'||k==='highlightLabel'||k==='caption'||k==='author'||k==='authorRole'||(k==='title'&&parentKey!=='') )return [k,compactCopy(x)];
    return [k,text[k]];
   }
   return [k,visit(x,k)];
  }));
 };
 const result=visit(s) as PageSection;
 Object.assign(result,{title,surface,brandPalette:{...palette},customColors:{},colorOverrides:[],typography:structuredClone(demoTypeScale),buttonColors:undefined,buttonRadius:6,buttonSize:'normal',containerMode:'page',paddingY:'normal',sectionBorder:'none',backgroundColor:'custom',textColor:'dark',titleEmphasis:undefined});
 if(result.items)result.items=result.items.map(item=>({...item,...(item.authorRole?{authorRole:'Lorem'}:{}),...(!passport&&result.type==='comparison_matrix'?{title:'Lorem ipsum',description:'Lorem'}:{})}));
 if(result.type!=='stats_counter')result.typography!.number={fontFamily:'site',fontWeight:600};
 if(result.mediaType==='video')result.mediaUrl='';
 return result;
}
