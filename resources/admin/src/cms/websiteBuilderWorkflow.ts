import type {PageSection} from '../types';

export function websiteBuilderWorkflow(prefix:string,images:string[]=[]):PageSection {
 return {id:prefix+'-workflow',type:'media_gallery',galleryPreset:'corporate-illustrated-steps',isVisible:true,
  containerMode:'page',motionPreset:'subtle-lift',sectionBorder:'none',paddingY:'normal',backgroundColor:'custom',textColor:'dark',textAlignment:'center',
  customColors:{customBgColor:'#ffffff',titleColor:'#0d1234',contentColor:'#626981',eyebrowColor:'#7426ff'},
  title:'From first draft to your next update.',content:'Keep refining your pages as your business changes.',
  items:[['shape','Shape your page','Choose a starting point and arrange your sections.'],['edit','Make it yours','Add your content, imagery and links.'],['publish','Review & publish','Check every device. Publish when you are ready.']].map(([key,title,description],i)=>({id:prefix+'-step-'+key,title,description,imageUrl:images[i]??''}))};
}
