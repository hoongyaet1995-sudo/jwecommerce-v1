import React from 'react';
import {describe,it,expect} from 'vitest';
import {renderToStaticMarkup} from 'react-dom/server';
import {corporateMarketplaceSection} from './corporateLanding';
import {CorporateMarketplace} from '../components/pages/CorporateSections';
import {imageTargets} from './imageTargets';
import {repeatedItemFields} from './repeatedItemContract';
import {sectionContentFields} from './contentCapabilities';
import {supportsMediaWidth} from '../components/pages/MediaWidthRegion';
describe('approved Marketplace section',()=>{
 it('keeps independent benefit entries, site width and one feature-page CTA',()=>{
 const a=corporateMarketplaceSection('a','/marketplace.png'),b=corporateMarketplaceSection('b');
 expect(a.type).toBe('media_text_split');expect(a.layoutPreset).toBe('corporate-marketplace');expect(a.containerMode).toBe('page');expect(a.motionPreset).toBe('subtle-lift');
 expect(a.items).toHaveLength(3);expect(repeatedItemFields(a)).toEqual(['title','description','icon']);expect(imageTargets(a)).toHaveLength(1);expect(supportsMediaWidth(a)).toBe(false);expect(sectionContentFields(a)).not.toContain('secondaryButtonLink');
 a.items![0].title='Changed';expect(b.items![0].title).toBe('Central order visibility');
 const html=renderToStaticMarkup(<CorporateMarketplace section={a}/>);expect(html).toContain('corp-marketplace-heading');expect(html).toContain('Explore Marketplace');expect(html).toContain('/pages/marketplace-orders');expect(html.match(/<a /g)).toHaveLength(1);expect(html).not.toContain('data-scroll');
 });
 it('keeps sparse content editable and renders fourth benefits/descriptions',()=>{
 const sparse={id:'s',type:'media_text_split' as const,title:'Marketplace',layoutPreset:'corporate-marketplace' as const,isVisible:true};expect(repeatedItemFields(sparse)).toEqual(['title','description','icon']);expect(imageTargets(sparse).map(x=>x.id)).toEqual(['mediaUrl']);expect(renderToStaticMarkup(<CorporateMarketplace section={sparse} editing/>)).toContain('Add marketplace artwork');
 const s=corporateMarketplaceSection('s');s.items!.push({id:'fourth',title:'Another benefit',description:'Editable detail',icon:'Layers'});expect(renderToStaticMarkup(<CorporateMarketplace section={s}/>)).toContain('Editable detail');
 });
});
