import type {PageSection} from '../types';
import {legacyTypographyRoles,preserveLegacyTypography} from './sectionHeading';
import {textRoles,type TextRole,type TextStyle} from './inspectorCapabilities';
import {sectionContentFields} from './contentCapabilities';
import {repeatedItemFields} from './repeatedItemContract';
import {hasButtons} from './actionAppearance';

/** Explicit reset marker suppresses old palette/role fallbacks without migrating saved documents. */
export function resetTextRole(section:PageSection,role:TextRole):PageSection {
 const next=preserveLegacyTypography(section,{...section,typography:{...section.typography,[role]:{inheritPage:true}}});
 return next;
}
export function resetTextProperty(section:PageSection,role:TextRole,key:keyof TextStyle):PageSection {
 const style={...(section.typography?.[role]??(legacyTypographyRoles(section).includes(role as any)?section.typography?.eyebrow:undefined))};
 if(key==='color'){style.inheritPage=true;delete style.colorOverride;}
 delete style[key];
 return preserveLegacyTypography(section,{...section,typography:{...section.typography,[role]:style}});
}
const commonKeys=['surface','brandPalette','backgroundColor','textColor','customColors','paddingY','textAlignment','containerMode','buttonRadius','buttonSize','buttonColors','colorOverrides'] as const;
export function resetSharedSectionStyle(section:PageSection):PageSection {
 const next={...section};for(const key of commonKeys)delete next[key];
 next.typography=Object.fromEntries(textRoles(section).map(role=>[role,{inheritPage:true}]));
 return next;
}
export function textStyleHasOverride(section:PageSection,role:TextRole):boolean {
 const style=section.typography?.[role]??(legacyTypographyRoles(section).includes(role as any)?section.typography?.eyebrow:undefined);
 if(style&&Object.keys(style).some(k=>k!=='inheritPage'))return true;
 if(style?.inheritPage)return false;
 const key=role==='eyebrow'||['label','number','secondaryTitle'].includes(role)?'eyebrowColor':role==='title'||role==='itemTitle'||role==='secondaryTitle'?'titleColor':'contentColor';
 return section.surface===undefined&&!!section.customColors?.[key];
}
export function sharedStyleOverrideCount(section:PageSection):number {
 return commonKeys.filter(k=>{
  if(section[k]===undefined||k==='colorOverrides'||k==='containerMode'&&section.containerMode==='page')return false;
  if(k==='surface'&&section.surface==='page')return false;
  if(section.surface==='page'&&k==='brandPalette')return false;
  if(section.surface&&['backgroundColor','textColor'].includes(k))return false;
  if(section.surface&&k==='customColors')return !!section.colorOverrides?.some(key=>section.surface!=='page'||key!=='customBgColor');
  return true;
 }).length+textRoles(section).filter(role=>textStyleHasOverride(section,role)).length;
}
export function supportsSharedButtons(section:PageSection):boolean {
 return hasButtons(section);
}
