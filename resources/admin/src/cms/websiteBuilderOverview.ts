import type {PageSection} from '../types';

/** S02: one illustrative workspace image, independent icon labels, no tabs/actions. */
export function websiteBuilderOverview(prefix:string,artwork=''):PageSection {
 return {id:prefix+'-builder',type:'media_text_split',layoutPreset:'corporate-builder-overview',isVisible:true,
  containerMode:'page',motionPreset:'subtle-lift',sectionBorder:'none',paddingY:'spacious',backgroundColor:'custom',textColor:'dark',
  customColors:{customBgColor:'#ffffff',titleColor:'#0d1234',contentColor:'#626981',eyebrowColor:'#7426ff'},
  title:'Make changes. See them take shape.',content:'Your page canvas, section outline and editing panel. One workspace.',
  mediaUrl:artwork,imageSlots:[{target:'mediaUrl',mediaAlt:'Illustrative visual page builder showing the section outline, editable page canvas and editing panel'}],
  items:[{id:prefix+'-arrange',title:'Arrange sections',icon:'Layers'},{id:prefix+'-edit',title:'Edit content',icon:'Pencil'},{id:prefix+'-preview',title:'Preview each device',icon:'Monitor'}]};
}
