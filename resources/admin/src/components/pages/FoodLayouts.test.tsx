import React from 'react';
import {it,expect,vi} from 'vitest';
import {renderToStaticMarkup} from 'react-dom/server';
import {EditorialProductGrid} from './EditorialProductGrid';
import {PantryNavigation} from './PantryNavigation';
import {FlushSplit} from './FlushSplit';
import {InlineNewsletter} from './InlineNewsletter';
vi.mock('./motion/CmsMotionWrapper',()=>({MotionItem:({children,...props}:any)=><div {...props}>{children}</div>,MotionMedia:({children,...props}:any)=><div {...props}>{children}</div>}));
it('renders a real pantry purchase form, disables preview purchase and routes variant choices to PDP',()=>{
 const section={id:'food',type:'product_listing',title:'Bestsellers',isVisible:true,listingPreset:'pantry-grid'} as any;
 const product={id:'coffee',slug:'coffee',title:'Coffee',images:['/coffee.png'],basePrice:42,commerce:{price:42,compareAtPrice:null,canChoose:true,variants:[{id:'sku1',enabled:true,stock:3}]}} as any;
 const render=(mode:string)=>renderToStaticMarkup(<EditorialProductGrid section={section} products={[product]} context={{mode,currency:'MYR'} as any}/>);
 const publicHtml=render('public');expect(publicHtml).toContain('action="/cart/items"');expect(publicHtml).toContain('name="variant_id" value="sku1"');expect(publicHtml).toContain('Add Coffee to cart');
 expect(render('preview')).toMatch(/disabled=""/);
 product.commerce.variants.push({id:'sku2',enabled:true,stock:1});expect(render('public')).toContain('Choose options');expect(render('public')).not.toContain('action="/cart/items"');
});
it('renders every recipe field and fourth producer value with stable image and action ownership',()=>{
 const section={id:'recipes',type:'shop_navigation',title:'Kitchen',isVisible:true,shopNavigationPreset:'recipe-cards',items:[{id:'toast',title:'Toast',description:'Morning ideas',imageUrl:'/toast.png',badge:'Breakfast',sourceType:'custom',linkUrl:'/recipes/toast',buttonText:'Read recipe'}]} as any;
 const html=renderToStaticMarkup(<PantryNavigation section={section} catalog={{categories:[],collections:[]} as any}/>);
 for(const text of ['Breakfast','Morning ideas','Read recipe','/recipes/toast','/toast.png','toast'])expect(html).toContain(text);
 const producer={id:'producer',type:'media_text_split',title:'Our producers',isVisible:true,layoutPreset:'producer-story',items:Array.from({length:4},(_,i)=>({id:'value'+i,title:'Value '+i,description:'Detail '+i,icon:'Leaf'}))} as any;
 expect(renderToStaticMarkup(<FlushSplit section={producer}/>)).toContain('Detail 3');
});

it('consumes vertical spacing in pantry cards, flush stories and inline signup',()=>{
 const section={id:'spacing',type:'shop_navigation',title:'Spacing',isVisible:true,paddingY:'compact',items:[]} as any;
 const cards=(paddingY:string)=>renderToStaticMarkup(<PantryNavigation section={{...section,paddingY}} catalog={{products:[],categories:[],collections:[]} as any}/>);
 expect(cards('compact')).toContain('py-8');expect(cards('normal')).toContain('py-12');expect(cards('spacious')).toContain('py-20');
 const story=(paddingY:string)=>renderToStaticMarkup(<FlushSplit section={{...section,type:'media_text_split',layoutPreset:'producer-story',paddingY}}/>);
 expect(story('compact')).toContain('py-0');expect(story('normal')).toContain('py-8');expect(story('spacious')).toContain('py-16');
 const signup=(paddingY:string)=>renderToStaticMarkup(<InlineNewsletter section={{...section,type:'newsletter_signup',paddingY}} email="" onEmail={()=>{}} onSubmit={()=>{}} success={false} error="" couponCode={null} disabled/>);
 expect(signup('compact')).toContain('py-8');expect(signup('normal')).toContain('py-12');expect(signup('spacious')).toContain('py-20');
});
