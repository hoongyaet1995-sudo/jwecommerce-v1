import type {PageSection} from '../types';
export const isWideVisualEditorial=(s:PageSection|null|undefined)=>s?.type==='media_text_split'&&['wide-visual-editorial','contained-wide-editorial'].includes(s.layoutPreset??'');
export function wideVisualEditorial(prefix:string):PageSection{return {id:prefix+'-visual',type:'media_text_split',layoutPreset:'wide-visual-editorial',isVisible:true,containerMode:'page',sectionBorder:'none',motionPreset:'subtle-lift',subtitle:'Your starting point',title:'Bring your ideas into view.',content:'Show how your information connects to the next step.',mediaUrl:''};}

export function splitVisualSurface(prefix:string):PageSection{return {...wideVisualEditorial(prefix),id:prefix+'-surface-panel',layoutPreset:'split-visual-surface',subtitle:undefined,title:'Keep your ideas moving.',content:'Introduce your process with a clear visual and a short explanation.'};}

export function centeredWideVisual(prefix:string):PageSection{return {...wideVisualEditorial(prefix),id:prefix+'-centered-visual',layoutPreset:'wide-visual-centered',subtitle:undefined,title:'Show the possibilities.',content:'Bring your story together with one clear visual.'};}

export function insetSurfacePanel(prefix:string):PageSection{return {...splitVisualSurface(prefix),layoutPreset:'inset-surface-panel',surface:'contrast'};}

export function containedWideEditorial(prefix:string):PageSection{return {...wideVisualEditorial(prefix),layoutPreset:'contained-wide-editorial'};}

export function containedWideCentered(prefix:string):PageSection{return {...centeredWideVisual(prefix),layoutPreset:'contained-wide-centered'};}
