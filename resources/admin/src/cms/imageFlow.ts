import type {PageSection} from '../types';
export const isImageFlow=(s:PageSection|null|undefined)=>s?.type==='media_gallery'&&s.galleryPreset==='image-flow';
/** Three authored pictures own all internal card copy. Motion is presentation only. */
export function imageFlow(prefix:string):PageSection{return {id:prefix+'-flow',type:'media_gallery',galleryPreset:'image-flow',isVisible:true,containerMode:'page',sectionBorder:'none',motionPreset:'subtle-lift',paddingY:'normal',title:'Connect each step.',content:'Show how your process moves from one step to the next.',items:[1,2,3].map(n=>({id:prefix+'-image-'+n,title:'',imageUrl:''}))};}
