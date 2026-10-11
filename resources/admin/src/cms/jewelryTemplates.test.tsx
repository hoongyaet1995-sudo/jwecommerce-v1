import {it,expect,vi} from 'vitest';
import {renderToStaticMarkup} from 'react-dom/server';
import {createPageTemplateSections,createPageTemplateStyle} from './pageTemplates';
import {sectionContentFields} from './contentCapabilities';
import {repeatedItemFields} from './repeatedItemContract';
import {imageTargets} from './imageTargets';
import {mediaTextSplitPresetPatch} from './mediaTextSplitPresets';
import {ArtisanStory} from '../components/pages/ArtisanStory';
vi.mock('../components/pages/motion/CmsMotionWrapper',()=>({MotionItem:({children}:any)=><div>{children}</div>,MotionButton:({children}:any)=><div>{children}</div>}));
it('creates independent jewelry drafts with genuine catalog filtering and five independent gifting tabs',()=>{
 const context:any={categories:[{id:'j',name:'Jewelry'}],products:[{id:'ring',status:'active',categoryId:'j',tags:['For Her']},{id:'chair',status:'active',categoryId:'furniture'}]};
 const a=createPageTemplateSections('jewelry-gifts',context,'a'),b=createPageTemplateSections('jewelry-gifts',{},'b');expect(a).toHaveLength(10);expect(a[2].productIds).toEqual(['ring']);expect(a[5].productTabs?.[0].productIds).toEqual(['ring']);expect(a[5].productTabs?.[1].productIds).toEqual([]);expect(b[2].productIds).toEqual([]);expect(a[4].items?.[0].id).not.toBe(b[4].items?.[0].id);expect(createPageTemplateStyle('jewelry-gifts')?.buttonRadius).toBe(0);
});
it('offers sparse artisan controls and renders later photos without dropping copy across layout switches',()=>{
 const s:any={id:'s',type:'media_text_split',title:'Craft',layoutPreset:'artisan-story',content:'Story',buttonText:'Our story',buttonLink:'/shop',items:Array.from({length:4},(_,i)=>({id:String(i),title:'Photo '+i,imageUrl:'/photo-'+i+'.png'}))};
 expect(sectionContentFields(s)).toContain('buttonLink');expect(sectionContentFields(s)).not.toContain('mediaUrl');expect(repeatedItemFields(s)).toEqual(['title','imageUrl']);expect(imageTargets(s)).toHaveLength(4);const html=renderToStaticMarkup(<ArtisanStory section={s} editing/>);expect(html).toContain('Photo 3');expect(html).toContain('Story');
 const back={...s,...mediaTextSplitPresetPatch(s,'default'),...mediaTextSplitPresetPatch(s,'artisan-story')};expect(back.items).toEqual(s.items);expect(back.content).toBe('Story');
 const sparse:any={id:'empty',type:'media_text_split',title:'Empty',layoutPreset:'artisan-story'};expect(repeatedItemFields(sparse)).toContain('imageUrl');expect(sectionContentFields(sparse)).toContain('content');
});
