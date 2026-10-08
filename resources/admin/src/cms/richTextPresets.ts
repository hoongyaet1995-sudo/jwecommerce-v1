import type {PageSection} from '../types';
export function richTextHasDarkBackground(section:PageSection) {
  if(section.backgroundColor==='custom') {
    const color=section.customColors?.customBgColor?.trim();
    if(color && /^#(?:[\da-f]{3}|[\da-f]{6})$/i.test(color)) {
      const hex=color.length===4?color.slice(1).split('').map(channel=>channel+channel).join(''):color.slice(1);
      const channels=[0,2,4].map(offset=>parseInt(hex.slice(offset,offset+2),16)/255).map(value=>value<=0.04045?value/12.92:((value+0.055)/1.055)**2.4);
      return .2126*channels[0]+.7152*channels[1]+.0722*channels[2]<.179;
    }
    return section.textColor!=='dark';
  }
  return !['bg-white','bg-neutral-50'].includes(section.backgroundColor??'bg-neutral-900');
}
export function isManifesto(section:PageSection) {
  return section.type==='rich_text' && section.richTextPreset==='manifesto';
}
export function richTextPresetPatch(section:PageSection,preset:NonNullable<PageSection['richTextPreset']>):Partial<PageSection> {
  if(preset==='default') return {richTextPreset:'default'};
  return {richTextPreset:preset,subtitle:section.subtitle??'',content:section.content??'',backgroundColor:section.backgroundColor??'bg-white',textColor:section.textColor??(['bg-white','bg-neutral-50'].includes(section.backgroundColor??'bg-white')?'dark':'light'),paddingY:section.paddingY??'normal'};
}
