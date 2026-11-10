import type {PageSection} from '../types';

/** S03 reuses CorporateFeature; layered section previews remain a single image. */
export function websiteBuilderStory(prefix:string,artwork=''):PageSection {
 return {id:prefix+'-story',type:'media_text_split',layoutPreset:'corporate-section-story',isVisible:true,
  containerMode:'page',motionPreset:'subtle-lift',sectionBorder:'none',paddingY:'normal',backgroundColor:'custom',textColor:'dark',
  customColors:{customBgColor:'#ffffff',titleColor:'#0d1234',contentColor:'#626981',eyebrowColor:'#7426ff'},
  title:'Build a page that\ntells your story.',content:'Start with reusable sections. Make the message, imagery and customer actions your own.',
  mediaUrl:artwork,imageSlots:[{target:'mediaUrl',mediaAlt:'Illustrative layered website sections showing a story hero, services and image gallery'}],
  items:[{id:prefix+'-hero-label',title:'Hero & introduction',icon:'Layers'},{id:prefix+'-services-label',title:'Services & features',icon:'LayoutGrid'},{id:prefix+'-gallery-label',title:'Galleries, FAQs & contact',icon:'Image'}]};
}
