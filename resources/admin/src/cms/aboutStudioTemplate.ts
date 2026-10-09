import type {PageSection} from '../types';
import {createSectionColorDefaults} from './sectionColorDefaults';
import assets from './aboutStudioMedia.json';
const image=(name:keyof typeof assets)=>`/storage/cms/media/${assets[name]}.png`;
const colors={...createSectionColorDefaults(),titleColor:'#171717',contentColor:'#525252',eyebrowColor:'#4f46e5'};
export function aboutStudioSections(brand:string,prefix:string):PageSection[] {
 const section=(key:string,data:Omit<PageSection,'id'|'isVisible'>):PageSection=>({
  backgroundColor:'bg-white',textColor:'dark',paddingY:'normal',sectionBorder:'none',motionPreset:'subtle-lift',
  customColors:{...colors},isVisible:true,...data,id:`${prefix}-${key}`,
  ...(data.items?{items:data.items.map((item,index)=>({...item,id:`${prefix}-${key}-${index}`}))}:{}),
 });
 return [
  section('world',{type:'media_gallery',galleryPreset:'editorial-montage',title:`The world of ${brand}.`,textAlignment:'center',showSectionHeading:true,columnsCount:3,
   content:`Sample brand story — editable content.\n\nEveryday things, made more considered. ${brand} is a space for objects, clothing and ideas that belong in real life.`,
   items:[{id:'',title:'A sunlit studio desk with plants, books and a black mug',imageUrl:image('hero-desk')},{id:'',title:'Neutral cotton clothing on wooden hangers',imageUrl:image('hero-clothes')},{id:'',title:'A studio cap, camera and books on a metal shelf',imageUrl:image('hero-objects')}]}),
  section('view',{type:'media_text_split',layoutPreset:'editorial-story',title:'A point of view,\nexpressed through\neveryday things.',
   content:'We look for balance — between function and feeling, the familiar and the new. Our edit brings together clothing, objects and details that make daily life a little more intentional.\n\nSimple ideas, well made, for wherever your day takes you.',
   mediaUrl:image('story-chair'),mediaAlt:'An olive jacket draped over a walnut chair beside a sunlit desk',mediaPosition:'right',mediaAspectRatio:'1:1',paddingY:'compact'}),
  section('ideas',{type:'feature_cards',featureCardsPreset:'editorial-columns',title:'Our perspective',showSectionHeading:false,columnsCount:2,backgroundColor:'bg-neutral-100',
   items:[{id:'',title:'What inspires us',description:'We’re inspired by the everyday: the objects we live with, the places we go, the details we notice. Good design, useful things and a slower, more intentional way of living shape what we do.'},
    {id:'',title:'How we choose',description:'We look for pieces with purpose — quality materials, thoughtful design and a sense of longevity. Our collections bring together independent makers and trusted brands we genuinely like, chosen for how they fit into real life.'}]}),
  section('details',{type:'media_gallery',galleryPreset:'editorial-cards',title:'The everyday details',showSectionHeading:false,columnsCount:3,paddingY:'compact',
   items:[{id:'',title:'Objects',description:'Everyday essentials, a little more considered.',imageUrl:image('card-objects')},{id:'',title:'Materials',description:'Textures, finishes and details that last.',imageUrl:image('card-materials')},{id:'',title:'Everyday moments',description:'For the routines, the detours and everything in between.',imageUrl:image('card-moments')}]}),
  section('values',{type:'feature_cards',featureCardsPreset:'editorial-columns',title:'Our values',showSectionHeading:false,columnsCount:3,paddingY:'compact',
   items:[{id:'',title:'Considered',description:'We value thoughtful design, useful details and a more intentional way of living.'},{id:'',title:'Useful',description:'Pieces that fit naturally into your day, and stay with you over time.'},{id:'',title:'Clear',description:'An edited selection, easy to explore, made up of things we truly believe in.'}]}),
  section('explore',{type:'cta_strip',ctaPreset:'image-banner',ctaBannerHeight:'normal',title:'Find something\nthat feels like you.',
   content:'Explore our collection of clothing and objects for everyday life.',buttonText:'Explore the collection',buttonLink:'/shop',mediaUrl:image('banner-chair'),containerMode:'full-bleed',backgroundColor:'bg-neutral-950',paddingY:'compact',textColor:'light',
   customColors:{...colors,titleColor:'#ffffff',contentColor:'#ffffff',primaryBtnBg:'#4f46e5',primaryBtnText:'#ffffff'},imageSlots:[{target:'mediaUrl',imagePosition:'center',imageOverlay:true,imageOverlayOpacity:10}]}),
 ];
}
