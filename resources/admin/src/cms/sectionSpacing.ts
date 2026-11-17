import type {CSSProperties} from 'react';
import type {PageSection} from '../types';
/** Opt-in outer padding only. Undefined preserves the original layout; page inheritance
 * is resolved by resolveStyledSection before this helper reaches the renderer. */
export function sectionSpacingStyle(section:Pick<PageSection,'paddingY'>):CSSProperties|undefined {
 if(!section.paddingY)return undefined;
 return {paddingBlock:{compact:'clamp(32px, 4cqw, 40px)',normal:'clamp(56px, 6cqw, 64px)',spacious:'clamp(80px, 9cqw, 112px)'}[section.paddingY]};
}
