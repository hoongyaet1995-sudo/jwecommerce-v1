import type {PageSection,SectionItem} from '../types';
import {isKnowledgeTriptych} from './knowledgeTriptych';
import {isImageFlow} from './imageFlow';

/** Bounded presentation family; other-layout data is retained, never expanded into extra visible cards. */
export const imageCardsThreePassport={preset:'image-cards-three',slots:3,itemFields:['title','description','imageUrl'],contentFields:['title','content'],colors:['customBgColor','titleColor','contentColor']} as const;
export function isThreeImageCards(s:PageSection|null|undefined):boolean{return s?.type==='media_gallery'&&s.galleryPreset===imageCardsThreePassport.preset;}
export const imageCardsFourMosaicPassport={...imageCardsThreePassport,preset:'image-cards-four-mosaic',slots:4} as const;
export function isFourMosaicImageCards(s:PageSection|null|undefined):boolean{return s?.type==='media_gallery'&&s.galleryPreset===imageCardsFourMosaicPassport.preset;}
export function isFixedImageCards(s:PageSection|null|undefined):boolean{return isKnowledgeTriptych(s)||isImageFlow(s)||isThreeImageCards(s)||isFourMosaicImageCards(s);}
export function fixedImageCardCount(s:PageSection):number{return isFourMosaicImageCards(s)?4:3;}
export function normalizeThreeImageCards<T extends PageSection|null|undefined>(s:T):T{
 if(!s||!isFixedImageCards(s))return s;
 const items=[...(s.items??[])];
 for(let i=items.length;i<fixedImageCardCount(s);i++)items.push({id:`${s.id}-slot-${i+1}`,title:'',description:'',imageUrl:''});
 return {...s,items} as T;
}
export function fixedImageCardItems(s:PageSection):SectionItem[]{return normalizeThreeImageCards(s).items!.slice(0,fixedImageCardCount(s));}
export function threeImageCards(prefix:string):PageSection{
 return {id:prefix+'-three-cards',type:'media_gallery',galleryPreset:'image-cards-three',isVisible:true,containerMode:'page',motionPreset:'subtle-lift',sectionBorder:'none',paddingY:'normal',backgroundColor:'custom',textColor:'dark',textAlignment:'center',customColors:{customBgColor:'#ffffff',titleColor:'#0d1234',contentColor:'#626981'},title:'Tell your story in three parts.',content:'Introduce three highlights for your audience.',items:[1,2,3].map(n=>({id:prefix+'-card-'+n,title:'Highlight '+n,description:'Describe this highlight.',imageUrl:''}))};
}
export function fourMosaicImageCards(prefix:string):PageSection{
 return {...threeImageCards(prefix),id:prefix+'-four-mosaic',galleryPreset:'image-cards-four-mosaic',title:'Give every detail its own space.',content:'Present four highlights in a considered collection.',items:[1,2,3,4].map(n=>({id:prefix+'-card-'+n,title:'Highlight '+n,description:'Describe this highlight.',imageUrl:''}))};
}
