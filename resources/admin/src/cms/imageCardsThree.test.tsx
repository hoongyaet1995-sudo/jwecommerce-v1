import React from 'react';
import {it,expect} from 'vitest';
import {renderToStaticMarkup} from 'react-dom/server';
import {staggeredImageCards,threeImageCards,fixedImageCardItems,normalizeThreeImageCards} from './imageCardsThree';
import {EditorialGallery} from '../components/pages/EditorialCollections';
import {sectionContentFields} from './contentCapabilities';
import {repeatedItemFields} from './repeatedItemContract';
import {imageTargets} from './imageTargets';
import {supportsDesignField,renderedColorFields} from './sectionDesignCapabilities';
it('has three independent neutral image/text slots and no action capabilities',()=>{
 const s=threeImageCards('neutral');expect(s.type).toBe('media_gallery');expect(s.items).toHaveLength(3);expect(repeatedItemFields(s)).toEqual(['title','description','imageUrl']);expect(sectionContentFields(s)).toEqual(['eyebrow','title','content']);expect(imageTargets(s)).toHaveLength(3);expect(s.containerMode).toBe('page');expect(s.motionPreset).toBe('subtle-lift');expect(JSON.stringify(s)).not.toMatch(/JW Studio|\/pages\/|pricingSource/);s.items![0].title='Changed';expect(threeImageCards('neutral').items![0].title).not.toBe('Changed');
});
it('renders three sparse editable slots and preserves foreign layout entries without showing extra cards or links',()=>{
 const s={...threeImageCards('cards'),items:[]};expect(fixedImageCardItems(s)).toHaveLength(3);expect(imageTargets(s)).toHaveLength(3);
 s.items=Array.from({length:4},(_,i)=>({id:'i'+i,title:'Card '+i,description:'Copy',imageUrl:'/image.webp',buttonText:'Hidden CTA',linkUrl:'/pages/hidden'}));const original=JSON.stringify(s);
 const html=renderToStaticMarkup(<EditorialGallery section={s}/>);expect((html.match(/class="corp-connection-card"/g)||[])).toHaveLength(3);expect(html).not.toMatch(/<a\s/);expect(html).not.toContain('Card 3');expect(JSON.stringify(s)).toBe(original);expect(normalizeThreeImageCards(s).items).toHaveLength(4);
});
it('offers only effective bounded design controls',()=>{const s=threeImageCards('design');expect(supportsDesignField(s,'columnsCount')).toBe(false);expect(supportsDesignField(s,'textAlignment')).toBe(true);expect(renderedColorFields(s)).toEqual(['customBgColor','titleColor','contentColor']);});
it('staggered variant reuses exactly three editable image/text slots without hardcoded brand colors',()=>{
 const s=staggeredImageCards('staggered');expect(s.customColors).toBeUndefined();expect(s.galleryPreset).toBe('image-cards-three-staggered');expect(repeatedItemFields(s)).toEqual(['title','description','imageUrl']);expect(imageTargets({...s,items:[]})).toHaveLength(3);
 const html=renderToStaticMarkup(<EditorialGallery section={{...s,items:[1,2,3,4].map(n=>({id:String(n),title:'Card '+n,description:'Editable description',imageUrl:'/card.webp',buttonText:'No link',linkUrl:'/hidden'}))}}/>);expect(html).toContain('corp-image-cards-staggered');expect(html).not.toContain('Card 4');expect(html).not.toContain('No link');
});
