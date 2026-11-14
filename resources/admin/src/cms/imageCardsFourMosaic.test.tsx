import React from 'react';
import {it,expect} from 'vitest';
import {renderToStaticMarkup} from 'react-dom/server';
import {fourMosaicImageCards,fixedImageCardItems,normalizeThreeImageCards} from './imageCardsThree';
import {EditorialGallery} from '../components/pages/EditorialCollections';
import {sectionContentFields} from './contentCapabilities';
import {repeatedItemFields} from './repeatedItemContract';
import {imageTargets} from './imageTargets';
import {supportsDesignField} from './sectionDesignCapabilities';
it('creates four independent neutral fixed image/text slots without new fields',()=>{
 const a=fourMosaicImageCards('a'),b=fourMosaicImageCards('b');expect(a.items).toHaveLength(4);expect(imageTargets(a)).toHaveLength(4);expect(repeatedItemFields(a)).toEqual(['title','description','imageUrl']);expect(sectionContentFields(a)).toEqual(['eyebrow','title','content']);expect(a.containerMode).toBe('page');expect(a.motionPreset).toBe('subtle-lift');a.items![0].title='Edited';expect(b.items![0].title).not.toBe('Edited');expect(JSON.stringify(b)).not.toMatch(/JW Studio|\/pages\//);expect(supportsDesignField(a,'columnsCount')).toBe(false);
});
it('normalizes sparse four slots and renders only four without links while retaining hidden legacy data',()=>{
 const a={...fourMosaicImageCards('sparse'),items:[]};expect(fixedImageCardItems(a)).toHaveLength(4);expect(imageTargets(a)).toHaveLength(4);a.items=Array.from({length:5},(_,i)=>({id:'i'+i,title:'Card '+i,description:'Details',imageUrl:'/image.webp',buttonText:'Hidden',linkUrl:'/pages/hidden'}));const before=JSON.stringify(a);const html=renderToStaticMarkup(<EditorialGallery section={a}/>);expect(html).toContain('corp-image-cards-four-mosaic');expect((html.match(/class="corp-connection-card"/g)||[])).toHaveLength(4);expect(html).not.toMatch(/<a\s/);expect(html).not.toContain('Card 4');expect(normalizeThreeImageCards(a).items).toHaveLength(5);expect(JSON.stringify(a)).toBe(before);
});
