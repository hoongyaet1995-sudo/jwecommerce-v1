import {describe,it,expect} from 'vitest';
import {createPageTemplateSections,createPageTemplateStyle} from './pageTemplates';
import {isPageStyle} from './pageStyle';
describe('Home & Deco template snapshot',()=>{it('creates independent editable sections and carries the shared page style',()=>{const a=createPageTemplateSections('home-deco',{},'a'),b=createPageTemplateSections('home-deco',{},'b');expect(a).toHaveLength(9);expect(a.map(s=>s.type)).toEqual(['hero_slider','shop_navigation','product_listing','shop_navigation','promotion_highlights','product_listing','hero_slider','media_text_split','feature_cards']);expect(isPageStyle(createPageTemplateStyle('home-deco'))).toBe(true);expect(a[0].items![0].id).not.toBe(b[0].items![0].id);a[0].title='Changed';expect(b[0].title).toBe('The Considered Home');expect(a[2].typography?.itemTitle?.fontFamily).toBe('sans');expect(a[4].surface).toBe('contrast');expect(a[6].sliderHeight).toBe('panoramic');expect(a[6].sliderAutoplay).toBe(false);expect(a[6].textAlignment).toBe('right');expect(a[6].items![0].imageUrl).toBe('/storage/cms/media/7f4313f2-7a4b-443d-8ba9-a42e3a468aaa.png');});});


it('binds new Home drafts only to the current active catalog, never snapshot IDs',()=>{
 const products=[{id:'prod-current',status:'active'},{id:'prod-draft',status:'draft'}] as any;
 const categories=[{id:'cat-current',status:'active',slug:'lighting',name:'Lighting'}] as any;
 const promotionOffers=[{id:'offer-current',status:'active'},{id:'offer-expired',status:'expired'}] as any;
 const sections=createPageTemplateSections('home-deco',{products,categories,promotionOffers},'current');
 expect(sections[1].items![2].sourceId).toBe('cat-current');
 expect(sections[1].items![0].sourceType).toBe('custom');
 expect(sections[3].items!.every(item=>item.sourceType==='custom')).toBe(true);
 expect(sections[2].productIds).toEqual(['prod-current']);
 expect(sections[5].productTabs![1].sourceId).toBe('cat-current');
 expect(sections[5].productTabs![0].productIds).toEqual(['prod-current']);
 expect(sections[4].items!.map(item=>item.sourceId)).toEqual(['offer-current','']);
 const empty=createPageTemplateSections('home-deco',{},'empty');
 expect(empty[2].productIds).toEqual([]);
 expect(empty[4].items!.every(item=>item.sourceId==='')).toBe(true);
 expect(empty[1].items!.every(item=>item.sourceId==='')).toBe(true);
});
