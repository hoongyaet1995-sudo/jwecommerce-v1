import {createServer} from 'vite';
const server=await createServer({configFile:false,root:process.cwd(),logLevel:'error',optimizeDeps:{noDiscovery:true,entries:[]},server:{middlewareMode:true}});
try {
 const {createPageTemplateSections,createPageTemplateStyle}=await server.ssrLoadModule('/src/cms/pageTemplates.ts');
 const ids=['home-deco','beauty-skincare','food-beverage','tech-electronics','baby-kids','jewelry-gifts','fashion-design','everyday-market','wedding-photography'];
 const context={products:[],categories:[],collections:[],promotionOffers:[],shopName:'Publication acceptance',tagline:'Editable store content',currency:'MYR',shippingRates:[]};
 const fixtures=ids.map(id=>({id,document:{title:id+' publication acceptance',slug:id+'-publication-acceptance',template:'standard',style:createPageTemplateStyle(id),sections:createPageTemplateSections(id,context,'publication-'+id)}}));
 process.stdout.write(JSON.stringify(fixtures));
} finally {await server.close();}
