import type {PageSection,SectionItem} from '../types';
export const isFocusedSteps=(s:PageSection|null|undefined)=>s?.type==='media_gallery'&&s.galleryPreset==='focused-steps';
export const focusedStepSelection=(items:SectionItem[],selected?:string)=>items.find(i=>i.id===selected)??items[0];
export function focusedSteps(prefix:string):PageSection{return {id:prefix+'-focused',type:'media_gallery',galleryPreset:'focused-steps',isVisible:true,containerMode:'page',sectionBorder:'none',motionPreset:'subtle-lift',paddingY:'normal',subtitle:'A connected journey',title:'Bring each step into focus.',content:'Guide your audience through the important moments.',items:[1,2,3,4].map(n=>({id:prefix+'-step-'+n,title:'Stage '+n,description:'Explain this stage of your process.',imageUrl:''}))};}
