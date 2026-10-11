import {it,expect,vi} from 'vitest';
import {renderToStaticMarkup} from 'react-dom/server';
import {createPageTemplateSections,createPageTemplateStyle} from './pageTemplates';
import {repeatedItemFields} from './repeatedItemContract';
import {sectionContentFields} from './contentCapabilities';
import {imageTargets} from './imageTargets';
import {DeviceComparison,TechFeature,TechComparisonSettings} from '../components/pages/TechLayouts';
vi.mock('../components/pages/motion/CmsMotionWrapper',()=>({MotionItem:({children,...p}:any)=><div {...p}>{children}</div>,MotionButton:({children}:any)=><div>{children}</div>}));
it('creates independent tech pages and includes only genuine tech catalog products',()=>{
 const context={products:[{id:'audio',title:'Headphones',status:'active',categoryId:'audio'},{id:'chair',title:'Desk chair',status:'active',categoryId:'furniture'}],categories:[{id:'audio',name:'Audio'}]} as any;
 const a=createPageTemplateSections('tech-electronics',context,'a'),b=createPageTemplateSections('tech-electronics',{},'b');
 expect(a).toHaveLength(9);expect(a[2].productIds).toEqual(['audio']);expect(b[2].productIds).toEqual([]);expect(a[6].productTabs?.[0].productIds).toEqual(['audio']);
 expect(a[5].items?.[0].id).not.toBe(b[5].items?.[0].id);expect(createPageTemplateStyle('tech-electronics')?.palette.accent).toBe('#0066ff');
});
it('provides sparse featured device and comparison Content capabilities',()=>{
 const feature={id:'f',type:'media_text_split',title:'Device',isVisible:true,layoutPreset:'tech-feature'} as any;
 expect(repeatedItemFields(feature)).toEqual(['title','description','icon']);expect(sectionContentFields(feature)).toContain('secondaryButtonLink');expect(imageTargets(feature)[0].id).toBe('mediaUrl');
 const comparison={id:'c',type:'comparison_matrix',title:'Compare',isVisible:true,comparisonPreset:'device-table',items:[]} as any;
 const controls=renderToStaticMarkup(<TechComparisonSettings section={comparison} onChange={()=>{}} mode="content"/>);
 expect(controls).toContain('Add device');expect(controls).toContain('Add specification row');
 const filled={...comparison,items:[{id:'a',title:'Laptop',subtitle:'device',imageUrl:'/laptop.png'},{id:'b',title:'Phone',subtitle:'device',imageUrl:'/phone.png'},{id:'c',title:'Audio',subtitle:'device',imageUrl:'/audio.png'},{id:'row',title:'Weight',badge:'1kg',description:'200g',value:'250g'}]};
 const html=renderToStaticMarkup(<DeviceComparison section={filled}/>);expect(html).toContain('250g');expect(imageTargets(filled)).toHaveLength(3);expect(sectionContentFields(filled)).not.toContain('comparisonCompetitorName');
 expect(renderToStaticMarkup(<TechFeature section={{...feature,paddingY:'spacious',items:[{id:'fourth',title:'Fourth feature',description:'Editable',icon:'Leaf'}]}}/>)).toContain('Fourth feature');
});
