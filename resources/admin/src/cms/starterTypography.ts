import type {PageSection} from '../types';
import type {TextRole} from './inspectorCapabilities';
export const starterFonts={
 display:'"CMS Connected Stack Sans Headline", "CMS Connected Inter Display", "CMS Connected Inter", Arial, sans-serif',
 body:'"CMS Connected Switzer", "CMS Connected Inter", Arial, sans-serif',
 ui:'"CMS Connected Inter", Arial, sans-serif',
};
export function inheritedStarterFont(section:PageSection,role:TextRole){
 if(section.starterDesign!=='neutral-v1')return undefined;
 return ['title','itemTitle','secondaryTitle','number'].includes(role)?starterFonts.display:['eyebrow','label'].includes(role)?starterFonts.ui:starterFonts.body;
}
