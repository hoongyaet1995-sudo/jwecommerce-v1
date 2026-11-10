import type {PageSection} from '../types';

export function websiteBuilderScenarios(prefix:string,images:string[]=[]):PageSection {
 return {id:prefix+'-scenarios',type:'media_gallery',galleryPreset:'corporate-scenarios',isVisible:true,
  containerMode:'page',motionPreset:'subtle-lift',sectionBorder:'none',paddingY:'normal',backgroundColor:'custom',textColor:'dark',textAlignment:'center',showSectionHeading:true,
  customColors:{customBgColor:'#ffffff',titleColor:'#0d1234',contentColor:'#626981',eyebrowColor:'#7426ff'},
  title:'Made for the way you do business.',content:'',
  items:[['business','Business websites','Monitor'],['services','Service showcases','Layers'],['catalogue','Product catalogues','ShoppingBag']].map(([key,title,icon],i)=>({id:prefix+'-scenario-'+key,title,icon,imageUrl:images[i]??''}))};
}
