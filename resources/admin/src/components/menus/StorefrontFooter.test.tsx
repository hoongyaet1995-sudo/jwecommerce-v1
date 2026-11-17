import React from 'react';
import {renderToStaticMarkup} from 'react-dom/server';
import {expect,it} from 'vitest';
import {StorefrontFooter} from './StorefrontFooter';
import {shellDesignDefaults} from '../../cms/shellDesign';
import type {FooterMenuL1} from '../../types';
it('uses existing brand/menu fields, filters inactive entries and never forces a CTA',()=>{
 const value={...shellDesignDefaults('service'),brandName:'Merchant',tagline:'Saved description',contactEmail:'hello@example.test',ctaLabel:'Preserved header action',ctaUrl:'/contact'};
 const menus:FooterMenuL1[]=[{id:'one',sectionTitle:'Company',isActive:true,sortOrder:2,links:[{id:'a',title:'Policy',url:'/policy',linkType:'page',isActive:true,sortOrder:2,badge:'NEW',badgeColor:'#4338ca'},{id:'b',title:'Hidden',url:'/hidden',linkType:'custom_url',isActive:false,sortOrder:1}]},{id:'two',sectionTitle:'Disabled group',isActive:false,sortOrder:1,links:[]}];
 const before=JSON.stringify({value,menus});const html=renderToStaticMarkup(<StorefrontFooter value={value} brand="Fallback" menus={menus}/>);
 expect(html).toContain('cms-footer-navigation');expect(html).toContain('Saved description');expect(html).toContain('hello@example.test');expect(html).toContain('Policy');expect(html).toContain('NEW');expect(html).not.toContain('Hidden');expect(html).not.toContain('Disabled group');expect(html).not.toContain('Preserved header action');expect(html).not.toContain('Aurora');expect(JSON.stringify({value,menus})).toBe(before);
});

it.each([0,1,7])('does not cap or invent navigation groups(%i)',count=>{
 const menus:FooterMenuL1[]=Array.from({length:count},(_,i)=>({id:String(i),sectionTitle:'Group '+i,isActive:true,sortOrder:i,links:[]}));
 const html=renderToStaticMarkup(<StorefrontFooter value={shellDesignDefaults()} brand="Merchant" menus={menus}/>);
 expect((html.match(/<nav /g)||[]).length).toBe(count);expect(html).not.toContain('Request a demo');expect(html).not.toContain('mailto:');
});
