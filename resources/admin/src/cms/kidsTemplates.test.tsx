import {it,expect,vi} from 'vitest';
import {renderToStaticMarkup} from 'react-dom/server';
import {createPageTemplateSections,createPageTemplateStyle} from './pageTemplates';
import {sectionContentFields} from './contentCapabilities';
import {repeatedItemFields} from './repeatedItemContract';
import {imageTargets} from './imageTargets';
import {KidsAgeCards,KidsDiscovery} from '../components/pages/KidsLayouts';
vi.mock('../components/pages/motion/CmsMotionWrapper',()=>({MotionItem:({children}:any)=><div>{children}</div>,MotionButton:({children}:any)=><div>{children}</div>}));
it('creates independent kids templates and filters genuine catalog sources',()=>{
 const context:any={categories:[{id:'toys',name:'Toys'}],products:[{id:'toy',title:'Toy',status:'active',categoryId:'toys',tags:['Play']},{id:'chair',title:'Chair',status:'active',categoryId:'furniture'}]};
 const a=createPageTemplateSections('baby-kids',context,'a'),b=createPageTemplateSections('baby-kids',{},'b');expect(a).toHaveLength(9);expect(a[2].productIds).toEqual(['toy']);expect(a[5].productTabs?.[0].productIds).toEqual(['toy']);expect(b[2].productIds).toEqual([]);expect(a[4].items?.[0].id).not.toBe(b[4].items?.[0].id);expect(createPageTemplateStyle('baby-kids')?.buttonRadius).toBe(999);
});
it('renders all age and milestone items including later added entries with sparse controls',()=>{
 const age:any={id:'age',type:'shop_navigation',title:'Age',shopNavigationPreset:'age-cards',items:Array.from({length:5},(_,i)=>({id:String(i),title:'Age '+i,description:'Stage',linkUrl:'/shop'}))};expect(imageTargets(age)).toHaveLength(5);expect(repeatedItemFields(age)).toContain('description');expect(renderToStaticMarkup(<KidsAgeCards section={age} catalog={{categories:[],collections:[]} as any}/>)).toContain('Age 4');
 const s:any={id:'discovery',type:'media_text_split',title:'Milestones',layoutPreset:'kids-discovery',items:[{id:'care',title:'Care entry',badge:'care',icon:'Leaf'},...Array.from({length:7},(_,i)=>({id:String(i),title:'Milestone '+i,icon:'Sun'}))]};expect(sectionContentFields(s)).toContain('leadText');expect(repeatedItemFields(s)).toContain('badge');expect(imageTargets(s)[0].id).toBe('mediaUrl');const html=renderToStaticMarkup(<KidsDiscovery section={s}/>);expect(html).toContain('Milestone 6');expect(html).toContain('Care entry');
});
