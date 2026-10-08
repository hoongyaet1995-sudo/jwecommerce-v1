import type {CSSProperties} from 'react';
import type {PageSection} from '../types';
export function sectionBorderStyle(section:PageSection):CSSProperties {
 const position=section.sectionBorder;
 if(!position||position==='default') return {};
 return {borderTopWidth:position==='top'||position==='both'?1:0,borderBottomWidth:position==='bottom'||position==='both'?1:0,borderStyle:'solid',borderColor:/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i.test(section.sectionBorderColor??'')?section.sectionBorderColor:'#e5e5e5'};
}
