import type {PageSection} from '../types';
import {headingCapability} from './sectionHeading';
import {sectionContentFields} from './contentCapabilities';
import {textRoles} from './inspectorCapabilities';
import {repeatedItemFields} from './repeatedItemContract';
import {imageTargets} from './imageTargets';
import {renderedColorFields,supportsDesignField} from './sectionDesignCapabilities';
/** One composed view of current renderer-backed capabilities; values do not grant permissions. */
export function sectionPassport(section:PageSection){
 const content=sectionContentFields(section),items=repeatedItemFields(section);
 return {heading:headingCapability(section),content,textRoles:textRoles(section),entries:{fields:items},media:imageTargets(section),actions:content.filter(k=>/^(button|secondaryButton)/.test(k)),design:{colors:renderedColorFields(section),supports:(key:string)=>supportsDesignField(section,key)},advanced:{container:true}};
}
