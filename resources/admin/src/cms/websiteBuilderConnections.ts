import type {PageSection} from '../types';

export function websiteBuilderConnections(prefix:string,images:string[]=[]):PageSection {
 return {id:prefix+'-connections',type:'media_gallery',galleryPreset:'corporate-connections',isVisible:true,
  containerMode:'page',motionPreset:'subtle-lift',sectionBorder:'none',paddingY:'normal',backgroundColor:'custom',textColor:'dark',textAlignment:'center',
  customColors:{customBgColor:'#ffffff',titleColor:'#0d1234',contentColor:'#626981',eyebrowColor:'#7426ff'},
  title:'A website that connects to what comes next.',content:'',
  items:[['ecommerce','Ecommerce','Purchase and enquiry paths.','Explore Ecommerce','/pages/ecommerce'],['ai-support','Website AI Support','Another way for visitors to ask questions.','Explore Website AI Support','/pages/website-ai-chat'],['ai-page','AI Page Generator','AI-assisted drafts. Your final review.','Explore AI Page Generator','/pages/ai-page-generator']].map(([key,title,description,buttonText,linkUrl],i)=>({id:prefix+'-connection-'+key,title,description,buttonText,linkUrl,imageUrl:images[i]??''}))};
}
