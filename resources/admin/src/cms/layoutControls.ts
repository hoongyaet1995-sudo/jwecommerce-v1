import type {PageSection} from '../types';

export type LayoutAlignment='left'|'center'|'right';
export interface LayoutControlCapability {spacing:boolean;alignment:readonly LayoutAlignment[];headingOnly?:boolean;headingOffAlignment?:readonly LayoutAlignment[]}
/** Renderer-backed layout capabilities; never infer support from retained authored fields.
 * Alignment and spacing share the existing Passport across every section.
 * Missing paddingY keeps native layout defaults; explicit values inherit/override through pageStyle.
 */
export function layoutControlCapabilities(_section:PageSection):LayoutControlCapability {
 // One existing Passport field. Missing textAlignment keeps each layout's native composition.
 return {spacing:true,alignment:['left','center','right']};
}
