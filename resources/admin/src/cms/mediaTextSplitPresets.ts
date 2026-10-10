import type {PageSection} from '../types';
export const mediaFocalPositions = {center:'center center',top:'center top',bottom:'center bottom',left:'left center',right:'right center'};
export function isEditorialMediaTextSplit(section:PageSection) {
  return section.type==='media_text_split' && (section.layoutPreset==='editorial-cover' || section.layoutPreset==='editorial-hero' || section.layoutPreset==='editorial-story' || section.layoutPreset==='story-with-steps');
}
export function mediaTextSplitPresetPatch(section:PageSection,preset:NonNullable<PageSection['layoutPreset']>):Partial<PageSection> {
  if(preset==='editorial-story-cards')return {layoutPreset:preset,items:section.items??[{id:crypto.randomUUID(),title:'Your story',description:'Introduce the article.',imageUrl:section.mediaUrl??'',buttonText:'Read the story',linkUrl:''}],buttonStyle:'text-link'};
  if(preset==='default') return {layoutPreset:'default'};
  let dark=section.backgroundColor!==undefined && !['bg-white','bg-neutral-50'].includes(section.backgroundColor);
  const customBackground=section.backgroundColor==='custom'?section.customColors?.customBgColor?.trim():undefined;
  if(customBackground && /^#(?:[\da-f]{3}|[\da-f]{6})$/i.test(customBackground)) {
    const hex=customBackground.length===4?customBackground.slice(1).split('').map(value=>value+value).join(''):customBackground.slice(1);
    const channels=[0,2,4].map(offset=>parseInt(hex.slice(offset,offset+2),16)/255).map(value=>value<=0.04045?value/12.92:((value+0.055)/1.055)**2.4);
    dark=0.2126*channels[0]+0.7152*channels[1]+0.0722*channels[2]<0.179;
  }
  if(section.textColor) dark=section.textColor==='light';
  return {layoutPreset:preset,subtitle:section.subtitle??'',content:section.content??'',mediaUrl:section.mediaUrl??'',mediaAlt:section.mediaAlt??'',mediaFocalPoint:section.mediaFocalPoint??'center',mediaPosition:section.mediaPosition??((preset==='editorial-story'||preset==='story-with-steps')?'left':'right'),
    ...((preset==='editorial-story'||preset==='story-with-steps')?{mediaAspectRatio:section.mediaAspectRatio??'4:3'}:{}),buttonText:section.buttonText??'',buttonLink:section.buttonLink??'',buttonStyle:section.buttonStyle??'button',backgroundColor:section.backgroundColor??'bg-white',textColor:section.textColor??(dark?'light':'dark'),paddingY:section.paddingY??'normal',
    customColors:{eyebrowColor:dark?'#818cf8':'#4f46e5',titleColor:dark?'#ffffff':'#171717',contentColor:dark?'#d4d4d8':'#737373',primaryBtnBg:'#4f46e5',primaryBtnText:'#ffffff',...section.customColors}};
}
