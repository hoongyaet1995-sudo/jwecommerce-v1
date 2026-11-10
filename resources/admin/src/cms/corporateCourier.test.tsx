import React from 'react';
import {describe,it,expect} from 'vitest';
import {renderToStaticMarkup} from 'react-dom/server';
import {corporateCourierSection} from './corporateLanding';
import {CorporateCourier} from '../components/pages/CorporateSections';
import {imageTargets} from './imageTargets';
import {repeatedItemFields} from './repeatedItemContract';
import {sectionContentFields} from './contentCapabilities';
import {supportsMediaWidth} from '../components/pages/MediaWidthRegion';
describe('approved Courier section',()=>{
 it('uses independent editable logo entries, site width and single Shipping CTA',()=>{
 const a=corporateCourierSection('a','/courier.png',Array(4).fill('/logo.png')),b=corporateCourierSection('b');
 expect(a.type).toBe('media_text_split');expect(a.layoutPreset).toBe('corporate-courier');expect(a.containerMode).toBe('page');expect(a.motionPreset).toBe('subtle-lift');expect(a.sectionBorder).toBe('none');
 expect(a.items?.map(x=>x.title)).toEqual(['Ninja Van','J&T Express','Pos Laju','Flash Express']);expect(repeatedItemFields(a)).toEqual(['title','imageUrl','linkUrl']);expect(imageTargets(a)).toHaveLength(5);expect(imageTargets(a)[0].label).toBe('Courier image');
 expect(sectionContentFields(a)).toContain('buttonLink');expect(sectionContentFields(a)).not.toContain('secondaryButtonLink');expect(supportsMediaWidth(a)).toBe(false);
 a.items![0].title='Changed';expect(b.items![0].title).toBe('Ninja Van');
 const html=renderToStaticMarkup(<CorporateCourier section={a}/>);expect(html).toContain('Explore Shipping');expect(html).toContain('/pages/shipping');expect(html).toContain('data-scroll="true"');expect(html).toContain('corp-courier-footer');expect(html.match(/<a /g)).toHaveLength(1);
 });
 it('offers sparse media editing and pauses the shared marquee in Builder',()=>{
 const sparse={id:'s',type:'media_text_split' as const,title:'Shipping',layoutPreset:'corporate-courier' as const,isVisible:true};
 expect(imageTargets(sparse).map(x=>x.id)).toEqual(['mediaUrl']);expect(repeatedItemFields(sparse)).toEqual(['title','imageUrl','linkUrl']);
 expect(renderToStaticMarkup(<CorporateCourier section={sparse} editing/>)).toContain('Add a courier photograph');
 const s=corporateCourierSection('e','',Array(4).fill('/logo.png'));expect(renderToStaticMarkup(<CorporateCourier section={s} editing/>)).toContain('data-scroll="false"');
 });
});
